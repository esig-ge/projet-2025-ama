<?php
// /site/pages/api/stripe_webhook.php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

/* ======= Config ======= */
$endpointSecret = getenv('STRIPE_WEBHOOK_SECRET'); // whsec_...
if (!$endpointSecret) { http_response_code(500); echo 'Webhook secret manquant'; exit; }

/* ======= Lecture payload + signature ======= */
$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

/* ======= Vérification signature (HMAC, tolérance 5 min) ======= */
function verify_stripe_signature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool {
    // Header ex: t=169..., v1=abc, v0=def
    $parts = [];
    foreach (explode(',', $sigHeader) as $kv) {
        [$k,$v] = array_map('trim', explode('=', $kv, 2) + ['', '']);
        if ($k && $v) $parts[$k] = $v;
    }
    if (empty($parts['t']) || empty($parts['v1'])) return false;
    if (abs(time() - (int)$parts['t']) > $tolerance) return false;

    $signed = $parts['t'].'.'.$payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    // comparaison temps-constant
    if (function_exists('hash_equals')) return hash_equals($expected, $parts['v1']);
    return $expected === $parts['v1'];
}
if (!verify_stripe_signature($payload, $sigHeader, $endpointSecret)) {
    http_response_code(400); echo 'Invalid'; exit;
}

/* ======= BDD ======= */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ======= Idempotence =======
   CREATE TABLE IF NOT EXISTS STRIPE_WEBHOOK_LOG (
     EVENT_ID VARCHAR(255) PRIMARY KEY,
     TYPE     VARCHAR(255) NOT NULL,
     RECEIVED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB;
*/
$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type'])) { http_response_code(400); echo 'bad_event'; exit; }

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO STRIPE_WEBHOOK_LOG (EVENT_ID, TYPE) VALUES (:id, :t)");
    $stmt->execute([':id'=>$event['id'] ?? '', ':t'=>$event['type'] ?? '']);
    if ($stmt->rowCount() === 0) { http_response_code(200); echo 'Duplicate'; exit; }
} catch (Throwable $e) {
    // si la table n'existe pas, on continue sans idempotence
}

/* ======= Helpers ======= */
function findOrderId(PDO $pdo, ?string $clientRef, ?string $pi): ?int {
    if ($clientRef && ctype_digit($clientRef)) {
        $id = (int)$clientRef;
        $chk = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
        $chk->execute([':c'=>$id]);
        if ($chk->fetchColumn()) return $id;
    }
    if ($pi) {
        $stmt = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE FK_PAIEMENT_INTENT=:pi OR STRIPE_SESSION_ID=:pi LIMIT 1");
        $stmt->execute([':pi'=>$pi]);
        $got = $stmt->fetchColumn();
        if ($got) return (int)$got;
    }
    return null;
}
function onOrderPaid(PDO $pdo, int $comId): void { /* TODO: stock, email, etc. */ }
function onOrderRefunded(PDO $pdo, int $comId): void { /* TODO */ }
function onOrderFailed(PDO $pdo, int $comId): void { /* TODO */ }
function onOrderExpired(PDO $pdo, int $comId): void { /* TODO */ }

/* ======= Routing ======= */
$type = $event['type'];
$obj  = $event['data']['object'] ?? [];

/* Examples fields:
   checkout.session.*  -> $obj['payment_status'], $obj['payment_intent'], $obj['client_reference_id'], $obj['amount_total']
   payment_intent.*    -> $obj['id'], $obj['status'], $obj['amount_received']
   charge.* / refund.* -> $obj['payment_intent'] ou $event['data']['object']['charge']['payment_intent']
*/

switch ($type) {
    /* ---- CHECKOUT ---- */
    case 'checkout.session.completed': {
        $status = (string)($obj['payment_status'] ?? '');
        $pi     = isset($obj['payment_intent']) ? (string)$obj['payment_intent'] : null;
        $comId  = findOrderId($pdo, (string)($obj['client_reference_id'] ?? ''), $pi);

        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE
                              SET STRIPE_SESSION_ID = COALESCE(STRIPE_SESSION_ID, :sid),
                                  FK_PAIEMENT_INTENT = COALESCE(FK_PAIEMENT_INTENT, :pi)
                            WHERE COM_ID=:cid LIMIT 1")
                ->execute([':sid'=>(string)($obj['id'] ?? ''), ':pi'=>(string)($pi ?? ''), ':cid'=>$comId]);

            if ($status === 'paid') {
                $amt = isset($obj['amount_total']) ? ((int)$obj['amount_total'])/100 : null;
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='payee', TOTAL_PAYER_CHF=COALESCE(:amt, TOTAL_PAYER_CHF) WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':amt'=>$amt, ':cid'=>$comId]);
                onOrderPaid($pdo, $comId);
            } elseif ($status === 'unpaid') {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='paiement_echoue' WHERE COM_ID=:cid LIMIT 1")
                    ->execute([':cid'=>$comId]);
                onOrderFailed($pdo, $comId);
            }
        }
        break;
    }

    case 'checkout.session.async_payment_failed': {
        $pi    = isset($obj['payment_intent']) ? (string)$obj['payment_intent'] : null;
        $comId = findOrderId($pdo, (string)($obj['client_reference_id'] ?? ''), $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='paiement_echoue' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderFailed($pdo, $comId);
        }
        break;
    }

    case 'checkout.session.expired': {
        $pi    = isset($obj['payment_intent']) ? (string)$obj['payment_intent'] : null;
        $comId = findOrderId($pdo, (string)($obj['client_reference_id'] ?? ''), $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='expiree' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderExpired($pdo, $comId);
        }
        break;
    }

    /* ---- PAYMENT INTENT ---- */
    case 'payment_intent.succeeded': {
        $pi    = (string)($obj['id'] ?? '');
        $comId = findOrderId($pdo, null, $pi);
        if ($comId) {
            $amt = isset($obj['amount_received']) ? ((int)$obj['amount_received'])/100 : null;
            $pdo->prepare("UPDATE COMMANDE
                              SET COM_STATUT='payee',
                                  FK_PAIEMENT_INTENT=:pi,
                                  TOTAL_PAYER_CHF=COALESCE(:amt, TOTAL_PAYER_CHF)
                            WHERE COM_ID=:cid LIMIT 1")
                ->execute([':pi'=>$pi, ':amt'=>$amt, ':cid'=>$comId]);
            onOrderPaid($pdo, $comId);
        }
        break;
    }

    case 'payment_intent.payment_failed':
    case 'payment_intent.canceled': {
        $pi    = (string)($obj['id'] ?? '');
        $comId = findOrderId($pdo, null, $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='paiement_echoue' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderFailed($pdo, $comId);
        }
        break;
    }

    /* ---- CHARGE / REFUND ---- */
    case 'charge.refunded':
    case 'charge.refund.updated':
    case 'charge.refund.created': {
        // Trouve le PI
        $pi = null;
        if (isset($obj['payment_intent'])) {
            $pi = (string)$obj['payment_intent'];
        } elseif (isset($obj['charge']['payment_intent'])) {
            $pi = (string)$obj['charge']['payment_intent'];
        }
        $comId = $pi ? findOrderId($pdo, null, $pi) : null;
        if ($comId) {
            $amountCaptured = $obj['amount_captured'] ?? null;
            $amountRefunded = $obj['amount_refunded'] ?? ($obj['amount'] ?? null);
            $newStatus = 'partiellement_rembourse';
            if ($amountCaptured !== null && $amountRefunded !== null && (int)$amountRefunded >= (int)$amountCaptured) {
                $newStatus = 'rembourse';
            }
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                ->execute([':st'=>$newStatus, ':cid'=>$comId]);
            onOrderRefunded($pdo, $comId);
        }
        break;
    }

    default:
        // on ignore le reste
        break;
}

http_response_code(200);
echo 'OK';
