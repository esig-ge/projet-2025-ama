<?php
// /site/pages/api/stripe_webhook.php
declare(strict_types=1);

// Stripe veut juste un 2xx + corps minimal.
// Pas de session, pas d'échos verbeux.
header('Content-Type: text/plain; charset=utf-8');

/* ---------- 1) Méthode requise ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

/* ---------- 2) Bootstrap Stripe + DB ---------- */
// Charge la lib + configure les clés + définit STRIPE_WEBHOOK_SECRET (via ton stripe.php)
require_once __DIR__ . '/../../database/config/stripe.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupère le secret du webhook tel que défini dans stripe.php (constante)
$endpointSecret =
    (defined('STRIPE_WEBHOOK_SECRET') && STRIPE_WEBHOOK_SECRET) ? STRIPE_WEBHOOK_SECRET :
        (getenv('STRIPE_WEBHOOK_SECRET') ?: null);

if (!$endpointSecret) {
    http_response_code(500);
    echo 'Webhook secret manquant';
    exit;
}

/* ---------- 3) Lecture payload + vérification signature officielle ---------- */
$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // Utilise la vérification officielle Stripe (horodatage + HMAC)
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    // JSON invalide
    http_response_code(400);
    echo 'Invalid payload';
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Signature invalide
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

/* ---------- 4) Idempotence (anti-doublons Stripe retries) ----------

   Table conseillée :
   CREATE TABLE IF NOT EXISTS STRIPE_WEBHOOK_LOG (
     EVENT_ID     VARCHAR(255) PRIMARY KEY,
     TYPE         VARCHAR(255) NOT NULL,
     RECEIVED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB;
*/
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO STRIPE_WEBHOOK_LOG (EVENT_ID, TYPE) VALUES (:id, :t)");
    $stmt->execute([
        ':id' => (string)($event->id ?? ''),
        ':t'  => (string)($event->type ?? ''),
    ]);
    if ($stmt->rowCount() === 0) {
        // Événement déjà traité — on renvoie 200 pour stopper les retries
        http_response_code(200);
        echo 'Duplicate';
        exit;
    }
} catch (\Throwable $e) {
    // Si la table n'existe pas, on continue (meilleur effort).
}

/* ---------- 5) Helpers ---------- */
function findOrderId(PDO $pdo, ?string $clientRef, ?string $pi): ?int {
    if ($clientRef && ctype_digit($clientRef)) {
        $id = (int)$clientRef;
        $chk = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
        $chk->execute([':c' => $id]);
        if ($chk->fetchColumn()) return $id;
    }
    if ($pi) {
        // Cherche par intent / session si stockés en BDD
        $stmt = $pdo->prepare("
            SELECT COM_ID
            FROM COMMANDE
            WHERE FK_PAIEMENT_INTENT = :pi
               OR STRIPE_SESSION_ID  = :pi
            LIMIT 1
        ");
        $stmt->execute([':pi' => $pi]);
        $got = $stmt->fetchColumn();
        if ($got) return (int)$got;
    }
    return null;
}

function upsertPaiement(PDO $pdo, array $d): void {
    // Mets un index unique sur PAI_STRIPE_PAYMENT_INTENT_ID pour fiabiliser l’UPSERT.
    // ALTER TABLE PAIEMENT ADD UNIQUE KEY uniq_pi (PAI_STRIPE_PAYMENT_INTENT_ID);

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
               PER_ID = VALUES(PER_ID),
               PAI_MODE = VALUES(PAI_MODE),
               PAI_MONTANT = VALUES(PAI_MONTANT),
               PAI_MONNAIE = VALUES(PAI_MONNAIE),
               PAI_STRIPE_LATEST_CHARGE_ID = VALUES(PAI_STRIPE_LATEST_CHARGE_ID),
               PAI_RECEIPT_URL = VALUES(PAI_RECEIPT_URL),
               PAI_STATUT = VALUES(PAI_STATUT),
               PAI_DATE_ = VALUES(PAI_DATE_),
               PAI_DATE_CONFIRMATION = VALUES(PAI_DATE_CONFIRMATION),
               PAI_LAST_EVENT_ID = VALUES(PAI_LAST_EVENT_ID),
               PAI_LAST_EVENT_TYPE = VALUES(PAI_LAST_EVENT_TYPE),
               PAI_LAST_EVENT_PAYLOAD = VALUES(PAI_LAST_EVENT_PAYLOAD)";
    $st = $pdo->prepare($sql);
    $st->execute($d);
}


// Hooks métier (à implémenter si besoin)
function onOrderPaid(PDO $pdo, int $comId): void { /* TODO: décrément stock, email, etc. */ }
function onOrderRefunded(PDO $pdo, int $comId): void { /* TODO */ }
function onOrderFailed(PDO $pdo, int $comId): void { /* TODO */ }
function onOrderExpired(PDO $pdo, int $comId): void { /* TODO */ }

/* ---------- 6) Routing des événements ---------- */
$type = (string)($event->type ?? '');
$obj  = $event->data->object ?? null; // \Stripe\StripeObject
if (!$obj) {
    http_response_code(400);
    echo 'bad_object';
    exit;
}

/* Notes utiles :
   checkout.session.*  -> $obj->payment_status, $obj->payment_intent, $obj->client_reference_id, $obj->amount_total
   payment_intent.*    -> $obj->id, $obj->status, $obj->amount_received
   charge.* / refund.* -> $obj->payment_intent ou $event->data->object->charge->payment_intent
*/

// Choix de statut : on utilise 'payee' pour rester cohérent avec ton code existant.
$STATUS_PAID      = 'payee';
$STATUS_FAILED    = 'paiement_echoue';
$STATUS_EXPIRED   = 'expiree';
$STATUS_REFUND    = 'rembourse';
$STATUS_REFUND_PP = 'partiellement_rembourse';

try {
    switch ($type) {

        /* ----- CHECKOUT SESSION ----- */
        case 'checkout.session.completed': {
            $status = (string)($obj->payment_status ?? '');
            $pi     = isset($obj->payment_intent) ? (string)$obj->payment_intent : null;
            $comId  = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $pi);

            if ($comId) {
                // Renseigne les identifiants si absents
                try {
                    $pdo->prepare("
                        UPDATE COMMANDE
                           SET STRIPE_SESSION_ID = COALESCE(STRIPE_SESSION_ID, :sid),
                               FK_PAIEMENT_INTENT = COALESCE(FK_PAIEMENT_INTENT, :pi)
                         WHERE COM_ID = :cid
                         LIMIT 1
                    ")->execute([
                        ':sid' => (string)($obj->id ?? ''),
                        ':pi'  => (string)($pi ?? ''),
                        ':cid' => $comId
                    ]);
                } catch (\PDOException $e) {
                    // Si colonnes manquantes, on ignore proprement
                }

                if ($status === 'paid') {
                    $amtChf = isset($obj->amount_total) ? ((int)$obj->amount_total) / 100 : null;

                    try {
                        $pdo->prepare("
                            UPDATE COMMANDE
                               SET COM_STATUT = :st,
                                   TOTAL_PAYER_CHF = COALESCE(:amt, TOTAL_PAYER_CHF)
                             WHERE COM_ID = :cid
                             LIMIT 1
                        ")->execute([
                            ':st'  => $STATUS_PAID,
                            ':amt' => $amtChf,
                            ':cid' => $comId
                        ]);
                    } catch (\PDOException $e) {
                        // Fallback minimal
                        $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                            ->execute([':st'=>$STATUS_PAID, ':cid'=>$comId]);
                    }

                    onOrderPaid($pdo, $comId);

                } elseif ($status === 'unpaid') {
                    $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                        ->execute([':st'=>$STATUS_FAILED, ':cid'=>$comId]);
                    onOrderFailed($pdo, $comId);
                }
            }
            break;
        }

        case 'checkout.session.async_payment_failed': {
            $pi    = isset($obj->payment_intent) ? (string)$obj->payment_intent : null;
            $comId = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $pi);
            if ($comId) {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$STATUS_FAILED, ':cid'=>$comId]);
                onOrderFailed($pdo, $comId);
            }
            break;
        }

        case 'checkout.session.expired': {
            $pi    = isset($obj->payment_intent) ? (string)$obj->payment_intent : null;
            $comId = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $pi);
            if ($comId) {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$STATUS_EXPIRED, ':cid'=>$comId]);
                onOrderExpired($pdo, $comId);
            }
            break;
        }

        /* ----- PAYMENT INTENT ----- */
        case 'payment_intent.succeeded': {
            $pi    = (string)($obj->id ?? '');
            $comId = findOrderId($pdo, null, $pi);
            if ($comId) {
                $amtChf = isset($obj->amount_received) ? ((int)$obj->amount_received) / 100 : null;

                try {
                    $pdo->prepare("
                        UPDATE COMMANDE
                           SET COM_STATUT = :st,
                               FK_PAIEMENT_INTENT = :pi,
                               TOTAL_PAYER_CHF = COALESCE(:amt, TOTAL_PAYER_CHF)
                         WHERE COM_ID = :cid
                         LIMIT 1
                    ")->execute([
                        ':st'  => $STATUS_PAID,
                        ':pi'  => $pi,
                        ':amt' => $amtChf,
                        ':cid' => $comId
                    ]);
                } catch (\PDOException $e) {
                    $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                        ->execute([':st'=>$STATUS_PAID, ':cid'=>$comId]);
                }

                onOrderPaid($pdo, $comId);
            }
            break;
        }

        case 'payment_intent.payment_failed':
        case 'payment_intent.canceled': {
            $pi    = (string)($obj->id ?? '');
            $comId = findOrderId($pdo, null, $pi);
            if ($comId) {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$STATUS_FAILED, ':cid'=>$comId]);
                onOrderFailed($pdo, $comId);
            }
            break;
        }

        /* ----- CHARGE / REFUND ----- */
        case 'charge.refunded':
        case 'charge.refund.updated':
        case 'charge.refund.created': {
            // Déterminer le PaymentIntent
            $pi = null;
            if (isset($obj->payment_intent)) {
                $pi = (string)$obj->payment_intent;
            } elseif (isset($obj->charge->payment_intent)) {
                $pi = (string)$obj->charge->payment_intent;
            }
            $comId = $pi ? findOrderId($pdo, null, $pi) : null;

            if ($comId) {
                // amount_refunded existe sur Charge ; amount sur Refund
                $amountCaptured = $obj->amount_captured ?? null;                   // charge
                $amountRefunded = $obj->amount_refunded ?? ($obj->amount ?? null); // charge || refund

                $newStatus = $STATUS_REFUND_PP;
                if ($amountCaptured !== null && $amountRefunded !== null && (int)$amountRefunded >= (int)$amountCaptured) {
                    $newStatus = $STATUS_REFUND;
                }

                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':st'=>$newStatus, ':cid'=>$comId]);

                onOrderRefunded($pdo, $comId);
            }
            break;
        }

        default:
            // On ignore le reste, mais on renvoie 200 pour éviter les retries inutiles
            break;
    }

    http_response_code(200);
    echo 'OK';

} catch (\Throwable $e) {
    // En cas d’erreur serveur, on peut retourner 500 pour que Stripe retente
    // (utile si DB down). Ici, on loguerait idéalement $e->getMessage().
    http_response_code(500);
    echo 'ERR';
}
