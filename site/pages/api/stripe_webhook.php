<?php
// /site/pages/api/stripe_webhook.php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

/* 1) Méthode */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

/* 2) Stripe + DB */
require_once __DIR__ . '/../../database/config/stripe.php';
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$endpointSecret =
    (defined('STRIPE_WEBHOOK_SECRET') && STRIPE_WEBHOOK_SECRET)
        ? STRIPE_WEBHOOK_SECRET
        : (getenv('STRIPE_WEBHOOK_SECRET') ?: null);

if (!$endpointSecret) {
    http_response_code(500);
    echo 'Webhook secret manquant';
    exit;
}

/* 3) Vérification signature */
$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400); echo 'Invalid payload'; exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); echo 'Invalid signature'; exit;
}

/* 4) Anti-doublons (idempotence) — nécessite un index UNIQUE sur STRIPE_WEBHOOK_LOG.EVENT_ID */
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO STRIPE_WEBHOOK_LOG (EVENT_ID, TYPE) VALUES (:id, :t)");
    $stmt->execute([':id'=>(string)($event->id ?? ''), ':t'=>(string)($event->type ?? '')]);
    if ($stmt->rowCount() === 0) { http_response_code(200); echo 'Duplicate'; exit; }
} catch (\Throwable $e) {
    // best-effort : on continue quand même
}

/* 5) Helpers */
function findOrderId(PDO $pdo, ?string $clientRef, ?string $sessionId): ?int {
    // 1) client_reference_id (tu y mets COM_ID à la création de la session)
    if ($clientRef && ctype_digit($clientRef)) {
        $id  = (int)$clientRef;
        $chk = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
        $chk->execute([':c'=>$id]);
        if ($chk->fetchColumn()) return $id;
    }
    // 2) STRIPE_SESSION_ID (stocké dans COMMANDE lors du create_checkout)
    if ($sessionId) {
        $stmt = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE STRIPE_SESSION_ID=:sid LIMIT 1");
        $stmt->execute([':sid'=>$sessionId]);
        $got = $stmt->fetchColumn();
        if ($got) return (int)$got;
    }
    return null;
}

function getOrderOwner(PDO $pdo, int $comId): ?int {
    $st = $pdo->prepare("SELECT PER_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
    $st->execute([':c'=>$comId]);
    $v = $st->fetchColumn();
    return $v !== false ? (int)$v : null;
}

/** UPSERT paiement par payment_intent (unique) — nécessite un index UNIQUE sur PAI_STRIPE_PAYMENT_INTENT_ID */
function upsertPaiement(PDO $pdo, array $d): int {
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
        $ch = $stripeObj->charges->data[0];
        $chargeId   = (string)($ch->id ?? '');
        $receiptUrl = (string)($ch->receipt_url ?? '');
    } elseif (isset($stripeObj->latest_charge)) {
        $chargeId = (string)$stripeObj->latest_charge;
    } elseif (isset($stripeObj->charge)) {
        $chargeId = (string)$stripeObj->charge;
    }
    return [$chargeId ?: null, $receiptUrl ?: null];
}

/* 6) Routing */
$type = (string)($event->type ?? '');
$obj  = $event->data->object ?? null;
if (!$obj) { http_response_code(400); echo 'bad_object'; exit; }

$STATUS_PAID      = 'payee';
$STATUS_FAILED    = 'paiement_echoue';
$STATUS_EXPIRED   = 'expiree';
$STATUS_REFUND    = 'rembourse';
$STATUS_REFUND_PP = 'partiellement_rembourse';

try {
    switch ($type) {

        /* --- CHECKOUT SESSION --- */
        case 'checkout.session.completed': {
            $status    = (string)($obj->payment_status ?? '');
            $pi        = isset($obj->payment_intent) ? (string)$obj->payment_intent : null;
            $sessionId = (string)($obj->id ?? '');
            $comId     = findOrderId($pdo, (string)($obj->client_reference_id ?? ''), $sessionId);

            if ($comId && $status === 'paid') {
                $perId = getOrderOwner($pdo, $comId) ?? null;
                $amountChf = isset($obj->amount_total) ? ((int)$obj->amount_total)/100 : null;

                // pas de charge ici en général
                [$chargeId, $receiptUrl] = [null, null];

                if ($pi && $perId) {
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
                        ':payload'         => $payload,
                    ]);

                    // Lier à la commande (pas de FK_PAIEMENT_INTENT ici)
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
                    // fallback
                    $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:c LIMIT 1")
                        ->execute([':st'=>$STATUS_PAID, ':c'=>$comId]);
                }
            }
            break;
        }

        /* --- PAYMENT INTENT --- */
        case 'payment_intent.succeeded': {
            $pi    = (string)($obj->id ?? '');
            // ici on n’a pas la session, on ne peut pas retrouver via STRIPE_SESSION_ID
            // on passe par COMMANDE.PAI_ID déjà créée à la création du checkout (recommandé)
            // ou à défaut par client_reference_id dans 'checkout.session.completed'
            // => on ne touche pas COMMANDE ici si on n’a pas la liaison.

            // Si tu veux vraiment lier via PI, conserve-le dans PAIEMENT uniquement
            $amountChf = isset($obj->amount_received) ? ((int)$obj->amount_received)/100 : null;
            [$chargeId, $receiptUrl] = extractChargeInfo($obj);

            // On met à jour la ligne PAIEMENT correspondante
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

        /* --- REFUNDS --- */
        case 'charge.refunded':
        case 'charge.refund.updated':
        case 'charge.refund.created': {
            $pi = null;
            if (isset($obj->payment_intent)) {
                $pi = (string)$obj->payment_intent;
            } elseif (isset($obj->charge->payment_intent)) {
                $pi = (string)$obj->charge->payment_intent;
            }
            if ($pi) {
                // Paiement
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
                // Optionnel côté commande : passé en remboursé
                $pdo->prepare("
                    UPDATE COMMANDE c
                       JOIN PAIEMENT p ON p.PAI_ID = c.PAI_ID
                       SET c.COM_STATUT = :st
                     WHERE p.PAI_STRIPE_PAYMENT_INTENT_ID = :pi
                ")->execute([':st'=>$STATUS_REFUND, ':pi'=>$pi]);
            }
            break;
        }

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
            // on accepte silencieusement
            break;
    }

    http_response_code(200);
    echo 'OK';
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'ERR';
}
