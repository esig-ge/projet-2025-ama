<?php
// /site/pages/api/stripe_webhook.php
declare(strict_types=1); // Typage strict : limite les conversions implicites → moins d'erreurs subtiles.

header('Content-Type: text/plain; charset=utf-8'); // Le webhook répond en texte brut (utile pour Stripe, pas besoin de JSON).

/* =========================================================
   1) GARDE MÉTHODE HTTP
   =========================================================
   Stripe envoie ses webhooks en POST. Toute autre méthode est refusée.
*/
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

/* =========================================================
   2) INITIALISATION STRIPE + CONNEXION BDD
   =========================================================
   - On charge la config Stripe (clé secrète, autoload SDK, etc.).
   - On ouvre une connexion PDO à la base et on active les exceptions.
*/
require_once __DIR__ . '/../../database/config/stripe.php';
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupération de la "signing secret" du webhook (priorité à la constante, puis à la variable d'env).
$endpointSecret =
    (defined('STRIPE_WEBHOOK_SECRET') && STRIPE_WEBHOOK_SECRET)
        ? STRIPE_WEBHOOK_SECRET
        : (getenv('STRIPE_WEBHOOK_SECRET') ?: null);

if (!$endpointSecret) {
    // Sans secret → impossible de vérifier l'authenticité des notifications.
    http_response_code(500);
    echo 'Webhook secret manquant';
    exit;
}

/* =========================================================
   3) VÉRIFICATION DE LA SIGNATURE (SÉCURITÉ)
   =========================================================
   - On lit le corps brut de la requête (payload).
   - On récupère l’en-tête Stripe-Signature.
   - On demande au SDK Stripe de valider la signature.
   - Si payload invalide/sig invalide → 400.
*/
$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // Valide la signature, et reconstruit l’objet Event typé Stripe.
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    // Corps invalide (non JSON Stripe) → 400
    http_response_code(400); echo 'Invalid payload'; exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Signature non valide (secret incorrect, horodatage expiré, etc.) → 400
    http_response_code(400); echo 'Invalid signature'; exit;
}

/* =========================================================
   4) ANTI-DOUBLONS (IDEMPOTENCE WEBHOOK)
   =========================================================
   - Insère event_id dans STRIPE_WEBHOOK_LOG (avec un index UNIQUE sur EVENT_ID).
   - Si déjà présent → on ne traite pas à nouveau (réponses 'Duplicate').
   - Si l’insert échoue (erreur DB), on continue "best-effort".
*/
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO STRIPE_WEBHOOK_LOG (EVENT_ID, TYPE) VALUES (:id, :t)");
    $stmt->execute([':id'=>(string)($event->id ?? ''), ':t'=>(string)($event->type ?? '')]);
    if ($stmt->rowCount() === 0) { http_response_code(200); echo 'Duplicate'; exit; }
} catch (\Throwable $e) {
    // best-effort : on n'interrompt pas le traitement du webhook si le log échoue.
}

/* =========================================================
   5) HELPERS MÉTIER (COMMANDE / PAIEMENT)
   =========================================================
   - findOrderId(...) : retrouve COM_ID via client_reference_id (id passé à Stripe au checkout)
     ou via STRIPE_SESSION_ID (stocké côté COMMANDE lors de la création de la session Stripe).
   - getOrderOwner(...) : renvoie PER_ID (propriétaire de la commande).
   - upsertPaiement(...) : insère ou met à jour un paiement via l'identifiant unique PI (Payment Intent).
     NÉCESSITE un index UNIQUE sur PAI_STRIPE_PAYMENT_INTENT_ID.
   - extractChargeInfo(...) : récupère le charge_id et le receipt_url depuis des objets PI/Charge Stripe.
*/
function findOrderId(PDO $pdo, ?string $clientRef, ?string $sessionId): ?int {
    // 1) Essai par client_reference_id (conseillé : y placer COM_ID lors du create checkout)
    if ($clientRef && ctype_digit($clientRef)) {
        $id  = (int)$clientRef;
        $chk = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
        $chk->execute([':c'=>$id]);
        if ($chk->fetchColumn()) return $id;
    }
    // 2) Essai par STRIPE_SESSION_ID (stocké dans COMMANDE au moment de la création de session)
    if ($sessionId) {
        $stmt = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE STRIPE_SESSION_ID=:sid LIMIT 1");
        $stmt->execute([':sid'=>$sessionId]);
        $got = $stmt->fetchColumn();
        if ($got) return (int)$got;
    }
    return null; // Pas trouvé
}

function getOrderOwner(PDO $pdo, int $comId): ?int {
    $st = $pdo->prepare("SELECT PER_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
    $st->execute([':c'=>$comId]);
    $v = $st->fetchColumn();
    return $v !== false ? (int)$v : null;
}

/** UPSERT paiement par payment_intent (unique) — nécessite un index UNIQUE sur PAI_STRIPE_PAYMENT_INTENT_ID */
function upsertPaiement(PDO $pdo, array $d): int {
    // INSERT ... ON DUPLICATE KEY UPDATE :
    // - insère une ligne paiement avec toutes les infos
    // - si le PI existe déjà (contrainte unique), met à jour la ligne existante
    $sql = "INSERT INTO PAIEMENT
              (PER_ID, PAI_MODE, PAI_MONTANT, PAI_MONNAIE,
               PAI_STRIPE_PAYMENT_INTENT_ID, PAI_STRIPE_LATEST_CHARGE_ID,
               PAI_RECEIPT_URL, PAI_STATUT, PAI_DATE_, PAI_DATE_CONFIRMATION,
               PAI_LAST_EVENT_ID, PAI_LAST_EVENT_TYPE, PAI_LAST_EVENT_PAYLOAD)
            VALUES
              (:per_id, :mode, :montant, :currency,
               :pi_id, :charge_id, :receipt_url, :statut, :date_, :date_conf,
               :last_event_id, :last_event_type, :payload)
            ON DUPLICATE KEY UPDATE
               PER_ID                     = VALUES(PER_ID),
               PAI_MODE                   = VALUES(PAI_MODE),
               PAI_MONTANT                = VALUES(PAI_MONTANT),
               PAI_MONNAIE                = VALUES(PAI_MONNAIE),
               PAI_STRIPE_LATEST_CHARGE_ID= VALUES(PAI_STRIPE_LATEST_CHARGE_ID),
               PAI_RECEIPT_URL            = VALUES(PAI_RECEIPT_URL),
               PAI_STATUT                 = VALUES(PAI_STATUT),
               PAI_DATE_                  = VALUES(PAI_DATE_),
               PAI_DATE_CONFIRMATION      = VALUES(PAI_DATE_CONFIRMATION),
               PAI_LAST_EVENT_ID          = VALUES(PAI_LAST_EVENT_ID),
               PAI_LAST_EVENT_TYPE        = VALUES(PAI_LAST_EVENT_TYPE),
               PAI_LAST_EVENT_PAYLOAD     = VALUES(PAI_LAST_EVENT_PAYLOAD)";
    $st = $pdo->prepare($sql);
    $st->execute($d);

    // Si insert, lastInsertId > 0. Sinon, on va chercher l'ID existant via le PI.
    $id = (int)$pdo->lastInsertId();
    if ($id > 0) return $id;

    $g = $pdo->prepare("SELECT PAI_ID FROM PAIEMENT WHERE PAI_STRIPE_PAYMENT_INTENT_ID=:pi LIMIT 1");
    $g->execute([':pi'=>$d[':pi_id']]);
    return (int)$g->fetchColumn();
}

/** Extrait charge/receipt d’un PaymentIntent ou d’une Charge */
function extractChargeInfo($stripeObj): array {
    $chargeId = null; $receiptUrl = null;
    if (isset($stripeObj->charges) && isset($stripeObj->charges->data[0])) {
        // Cas PaymentIntent avec charges expandues
        $ch = $stripeObj->charges->data[0];
        $chargeId   = (string)($ch->id ?? '');
        $receiptUrl = (string)($ch->receipt_url ?? '');
    } elseif (isset($stripeObj->latest_charge)) {
        // Certains objets exposent latest_charge directement
        $chargeId = (string)$stripeObj->latest_charge;
    } elseif (isset($stripeObj->charge)) {
        // Les objets Charge (ou Refund) exposent charge
        $chargeId = (string)$stripeObj->charge;
    }
    return [$chargeId ?: null, $receiptUrl ?: null];
}

/* =========================================================
   6) ROUTAGE DES ÉVÉNEMENTS
   =========================================================
   - On récupère le type d'event Stripe, l'objet principal, et on traite au switch.
   - Si l’objet est manquant/inattendu → 400.
*/
$type = (string)($event->type ?? '');
$obj  = $event->data->object ?? null;
if (!$obj) { http_response_code(400); echo 'bad_object'; exit; }

// Libellés internes de statut COMMANDE (à faire matcher avec la BDD).
$STATUS_PAID      = 'payee';
$STATUS_FAILED    = 'paiement_echoue';
$STATUS_EXPIRED   = 'expiree';
$STATUS_REFUND    = 'rembourse';
$STATUS_REFUND_PP = 'partiellement_rembourse'; // (non utilisé ci-dessous, mais prêt si besoin)

try {
    switch ($type) {

        /* -----------------------------------------------
           A) CHECKOUT SESSION (retour global de Checkout)
           -----------------------------------------------
           - 'checkout.session.completed' : paiement capturé/confirmé.
           - On marque la commande payée, upsert un paiement si on a PI.
        */
        case 'checkout.session.completed': {
            $status    = (string)($obj->payment_status ?? ''); // attendu: 'paid'
            $pi        = isset($obj->payment_intent) ? (string)$obj->payment_intent : null; // PaymentIntent id
            $sessionId = (string)($obj->id ?? '');             // id de session checkout
            $comId     = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $sessionId);

            if ($comId && $status === 'paid') {
                $perId = getOrderOwner($pdo, $comId) ?? null; // proprio de la commande
                $amountChf = isset($obj->amount_total) ? ((int)$obj->amount_total)/100 : null; // montant total (centimes → CHF)

                // À ce stade, on n'a généralement pas de charge détaillée sur l'event session.completed
                [$chargeId, $receiptUrl] = [null, null];

                if ($pi && $perId) {
                    // Crée/Met à jour la ligne PAIEMENT liée au PaymentIntent
                    $paiId = upsertPaiement($pdo, [
                        ':per_id'          => $perId,
                        ':mode'            => 'stripe',
                        ':montant'         => $amountChf,
                        ':currency'        => 'CHF',
                        ':pi_id'           => $pi,
                        ':charge_id'       => $chargeId,
                        ':receipt_url'     => $receiptUrl,
                        ':statut'          => 'succeeded',
                        ':date_'           => date('Y-m-d H:i:s'),
                        ':date_conf'       => date('Y-m-d H:i:s'),
                        ':last_event_id'   => (string)$event->id,
                        ':last_event_type' => $type,
                        ':payload'         => $payload, // trace brute utile pour debug
                    ]);

                    // Marque la commande payée + enregistre (si fourni) le montant total et la FK vers PAIEMENT
                    $pdo->prepare("
                        UPDATE COMMANDE
                           SET COM_STATUT        = :st,
                               COM_MONTANT_TOTAL = COALESCE(:amt, COM_MONTANT_TOTAL),
                               PAI_ID            = :pai
                         WHERE COM_ID = :cid
                         LIMIT 1
                    ")->execute([
                        ':st'=>$STATUS_PAID, ':amt'=>$amountChf, ':pai'=>$paiId, ':cid'=>$comId
                    ]);
                } else {
                    // Fallback : on marque au moins la commande comme payée (si pas de PI/per_id)
                    $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:c LIMIT 1")
                        ->execute([':st'=>$STATUS_PAID, ':c'=>$comId]);
                }
            }
            break;
        }

        /* -----------------------------------------------
           B) PAYMENT INTENT (états fins du paiement)
           -----------------------------------------------
           - succeeded : mise à jour de la ligne PAIEMENT (charge, receipt, dates…)
           - failed / canceled : on passe le paiement en failed
           NOTE : on ne met pas à jour COMMANDE ici, sauf si on as
           une stratégie claire de liaison PI→COMMANDE active partout.
        */
        case 'payment_intent.succeeded': {
            $pi    = (string)($obj->id ?? '');
            // Ici, on n’a pas la session Checkout. On met à jour PAIEMENT seulement.
            $amountChf = isset($obj->amount_received) ? ((int)$obj->amount_received)/100 : null;
            [$chargeId, $receiptUrl] = extractChargeInfo($obj);

            $pdo->prepare("
                UPDATE PAIEMENT
                   SET PAI_STRIPE_LATEST_CHARGE_ID = :ch,
                       PAI_RECEIPT_URL             = :ru,
                       PAI_MONTANT                 = COALESCE(:amt, PAI_MONTANT),
                       PAI_STATUT                  = 'succeeded',
                       PAI_DATE_CONFIRMATION       = NOW(),
                       PAI_LAST_EVENT_ID           = :eid,
                       PAI_LAST_EVENT_TYPE         = :etype,
                       PAI_LAST_EVENT_PAYLOAD      = :payload
                 WHERE PAI_STRIPE_PAYMENT_INTENT_ID = :pi
            ")->execute([
                ':ch'=>$chargeId, ':ru'=>$receiptUrl, ':amt'=>$amountChf,
                ':eid'=>(string)$event->id, ':etype'=>$type, ':payload'=>$payload,
                ':pi'=>$pi
            ]);

            break;
        }

        case 'payment_intent.payment_failed':
        case 'payment_intent.canceled': {
            // Échec/annulation du payment intent → on marque le paiement en failed.
            $pi = (string)($obj->id ?? '');
            $pdo->prepare("
                UPDATE PAIEMENT
                   SET PAI_STATUT             = 'failed',
                       PAI_DATE_ANNULATION    = NOW(),
                       PAI_LAST_EVENT_ID      = :eid,
                       PAI_LAST_EVENT_TYPE    = :etype,
                       PAI_LAST_EVENT_PAYLOAD = :payload
                 WHERE PAI_STRIPE_PAYMENT_INTENT_ID = :pi
            ")->execute([
                ':eid'=>(string)$event->id, ':etype'=>$type, ':payload'=>$payload, ':pi'=>$pi
            ]);
            break;
        }

        /* -----------------------------------------------
           C) REFUNDS (remboursements)
           -----------------------------------------------
           - Plusieurs events possibles : charge.refunded, charge.refund.updated,
             charge.refund.created. On marque PAIEMENT en refunded et on
             propage (optionnel) sur COMMANDE.
        */
        case 'charge.refunded':
        case 'charge.refund.updated':
        case 'charge.refund.created': {
            // Retrouver le PaymentIntent lié au remboursement.
            $pi = null;
            if (isset($obj->payment_intent)) {
                $pi = (string)$obj->payment_intent;
            } elseif (isset($obj->charge->payment_intent)) {
                $pi = (string)$obj->charge->payment_intent;
            }
            if ($pi) {
                // 1) Marquer le paiement comme remboursé
                $pdo->prepare("
                    UPDATE PAIEMENT
                       SET PAI_STATUT             = 'refunded',
                           PAI_LAST_EVENT_ID      = :eid,
                           PAI_LAST_EVENT_TYPE    = :etype,
                           PAI_LAST_EVENT_PAYLOAD = :payload
                     WHERE PAI_STRIPE_PAYMENT_INTENT_ID = :pi
                ")->execute([
                    ':eid'=>(string)$event->id, ':etype'=>$type, ':payload'=>$payload, ':pi'=>$pi
                ]);
                // 2) (Optionnel) Marquer la commande associée comme remboursée
                $pdo->prepare("
                    UPDATE COMMANDE c
                       JOIN PAIEMENT p ON p.PAI_ID = c.PAI_ID
                       SET c.COM_STATUT = :st
                     WHERE p.PAI_STRIPE_PAYMENT_INTENT_ID = :pi
                ")->execute([':st'=>$STATUS_REFUND, ':pi'=>$pi]);
            }
            break;
        }

        /* -----------------------------------------------
           D) ASYNC FAILURES / EXPIRED SESSION
           -----------------------------------------------
           - checkout.session.async_payment_failed : échec async (ex : iDEAL, etc.)
           - checkout.session.expired : session expirée sans paiement
           - On met à jour le statut COMMANDE si on retrouve com_id.
        */
        case 'checkout.session.async_payment_failed': {
            $sessionId = (string)($obj->id ?? '');
            $comId = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $sessionId);
            if ($comId) {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$STATUS_FAILED, ':cid'=>$comId]);
            }
            break;
        }

        case 'checkout.session.expired': {
            $sessionId = (string)($obj->id ?? '');
            $comId = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $sessionId);
            if ($comId) {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$STATUS_EXPIRED, ':cid'=>$comId]);
            }
            break;
        }

        default:
            // Tout autre event : on accepte silencieusement (pas d'action spécifique).
            break;
    }

    // Réponse standard OK pour Stripe (important : 2xx pour éviter les retries).
    http_response_code(200);
    echo 'OK';
} catch (\Throwable $e) {
    // Erreur inattendue côté serveur → 500 (Stripe retentera le webhook).
    http_response_code(500);
    echo 'ERR';
}
