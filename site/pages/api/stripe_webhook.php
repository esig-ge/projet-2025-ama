<?php
// /site/pages/api/stripe_webhook.php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

// Pas de session ici
require __DIR__ . '/../../../vendor/autoload.php';

use Stripe\Webhook;

/* =========================
   0) Sécurité : secret
   ========================= */
$endpointSecret = getenv('STRIPE_WEBHOOK_SECRET');
if (!$endpointSecret) {
    http_response_code(500);
    echo 'Webhook secret manquant'; exit;
}

$payload   = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
} catch (Throwable $e) {
    http_response_code(400);
    echo 'Invalid'; // signature invalide
    exit;
}

/* =========================
   1) BDD + helpers
   ========================= */
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* --- Idempotence : ignorer les doublons (à créer une fois)
CREATE TABLE IF NOT EXISTS STRIPE_WEBHOOK_LOG (
  EVENT_ID VARCHAR(255) PRIMARY KEY,
  TYPE     VARCHAR(255) NOT NULL,
  RECEIVED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- (facultatif) indexer TYPE si besoin
*/
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO STRIPE_WEBHOOK_LOG (EVENT_ID, TYPE) VALUES (:id, :t)");
    $stmt->execute([':id' => $event->id, ':t' => $event->type]);
    if ($stmt->rowCount() === 0) { // déjà traité
        http_response_code(200); echo 'Duplicate'; exit;
    }
} catch (Throwable $e) {
    // si la table n'existe pas, on continue (sans idempotence)
}

/* --- Utils : trouver la COM_ID depuis session / intent --- */
function findOrderId(PDO $pdo, ?string $clientRef, ?string $pi): ?int {
    // 1) client_reference_id (source la plus fiable côté Checkout)
    if ($clientRef && ctype_digit($clientRef)) {
        $id = (int)$clientRef;
        $chk = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE COM_ID=:c LIMIT 1");
        $chk->execute([':c'=>$id]);
        if ($chk->fetchColumn()) return $id;
    }
    // 2) Via le PaymentIntent (si on l’a)
    if ($pi) {
        $stmt = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE FK_PAIEMENT_INTENT=:pi OR STRIPE_SESSION_ID=:pi LIMIT 1");
        $stmt->execute([':pi'=>$pi]);
        $got = $stmt->fetchColumn();
        if ($got) return (int)$got;
    }
    return null;
}

/* --- Hooks : à implémenter selon ton MLD si tu veux automatiser --- */
function onOrderPaid(PDO $pdo, int $comId): void {
    // TODO: décrémenter stocks, générer facture/PDF, envoyer email, etc.
}
function onOrderRefunded(PDO $pdo, int $comId): void {
    // TODO: email de confirmation de remboursement, etc.
}
function onOrderFailed(PDO $pdo, int $comId): void {
    // TODO: notifier le client ou remettre la commande en "en préparation"
}
function onOrderExpired(PDO $pdo, int $comId): void {
    // TODO: libérer les réservations de stock, etc.
}

/* =========================
   2) Routing des événements
   ========================= */
switch ($event->type) {

    /* ---------- CHECKOUT ---------- */
    case 'checkout.session.completed': {
        /** @var \Stripe\Checkout\Session $s */
        $s = $event->data->object;
        $status = (string)($s->payment_status ?? '');
        $pi     = $s->payment_intent ? (string)$s->payment_intent : null;
        $comId  = findOrderId($pdo, (string)($s->client_reference_id ?? ''), $pi);

        if ($comId) {
            // Mettre à jour les références Stripe connues
            $upd = $pdo->prepare("
                UPDATE COMMANDE
                   SET STRIPE_SESSION_ID = COALESCE(STRIPE_SESSION_ID, :sid),
                       FK_PAIEMENT_INTENT = COALESCE(FK_PAIEMENT_INTENT, :pi)
                 WHERE COM_ID = :cid
                 LIMIT 1
            ");
            $upd->execute([
                ':sid' => (string)($s->id ?? ''),
                ':pi'  => (string)($pi ?? ''),
                ':cid' => $comId,
            ]);

            if ($status === 'paid') {
                $amt = isset($s->amount_total) ? ((int)$s->amount_total)/100 : null;
                $stmt = $pdo->prepare("
                    UPDATE COMMANDE
                       SET COM_STATUT='payee',
                           TOTAL_PAYER_CHF = COALESCE(:amt, TOTAL_PAYER_CHF)
                     WHERE COM_ID=:cid
                     LIMIT 1
                ");
                $stmt->execute([':amt'=>$amt, ':cid'=>$comId]);
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
        $s    = $event->data->object;
        $pi   = $s->payment_intent ? (string)$s->payment_intent : null;
        $comId= findOrderId($pdo, (string)($s->client_reference_id ?? ''), $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='paiement_echoue' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderFailed($pdo, $comId);
        }
        break;
    }

    case 'checkout.session.expired': {
        $s    = $event->data->object;
        $pi   = $s->payment_intent ? (string)$s->payment_intent : null;
        $comId= findOrderId($pdo, (string)($s->client_reference_id ?? ''), $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='expiree' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderExpired($pdo, $comId);
        }
        break;
    }

    /* ---------- PAYMENT INTENT ---------- */
    case 'payment_intent.succeeded': {
        /** @var \Stripe\PaymentIntent $piObj */
        $piObj = $event->data->object;
        $pi    = (string)$piObj->id;
        $comId = findOrderId($pdo, null, $pi);
        if ($comId) {
            $amt = isset($piObj->amount_received) ? ((int)$piObj->amount_received)/100 : null;
            $stmt = $pdo->prepare("
                UPDATE COMMANDE
                   SET COM_STATUT='payee',
                       FK_PAIEMENT_INTENT=:pi,
                       TOTAL_PAYER_CHF = COALESCE(:amt, TOTAL_PAYER_CHF)
                 WHERE COM_ID=:cid
                 LIMIT 1
            ");
            $stmt->execute([':pi'=>$pi, ':amt'=>$amt, ':cid'=>$comId]);
            onOrderPaid($pdo, $comId);
        }
        break;
    }

    case 'payment_intent.payment_failed':
    case 'payment_intent.canceled': {
        $piObj = $event->data->object;
        $pi    = (string)$piObj->id;
        $comId = findOrderId($pdo, null, $pi);
        if ($comId) {
            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT='paiement_echoue' WHERE COM_ID=:cid LIMIT 1")
                ->execute([':cid'=>$comId]);
            onOrderFailed($pdo, $comId);
        }
        break;
    }

    /* ---------- CHARGE / REFUND ---------- */
    case 'charge.refunded':
    case 'charge.refund.updated':
    case 'charge.refund.created': {
        /** @var \Stripe\Charge $charge */
        $charge = $event->data->object->charge ?? $event->data->object; // selon le type exact
        // Récupérer le PaymentIntent depuis la charge si possible
        $pi = null;
        if (is_object($event->data->object) && isset($event->data->object->payment_intent)) {
            $pi = (string)$event->data->object->payment_intent;
        } elseif (is_object($charge) && isset($charge->payment_intent)) {
            $pi = (string)$charge->payment_intent;
        }

        $comId = $pi ? findOrderId($pdo, null, $pi) : null;
        if ($comId) {
            // Montants (si dispo) pour déterminer le statut
            $amountCaptured = null;
            $amountRefunded = null;

            if (isset($event->data->object->amount)) {
                // refund.created / updated: montant de CE remboursement
                $amountRefunded = (int)$event->data->object->amount;
            }
            if (isset($event->data->object->amount_refunded)) {
                // charge.refunded: montant total remboursé à date
                $amountRefunded = (int)$event->data->object->amount_refunded;
            }
            if (isset($event->data->object->amount_captured)) {
                $amountCaptured = (int)$event->data->object->amount_captured;
            }

            // Déterminer le statut global
            $newStatus = 'partiellement_rembourse';
            if ($amountCaptured !== null && $amountRefunded !== null && $amountRefunded >= $amountCaptured) {
                $newStatus = 'rembourse';
            }

            $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=:st WHERE COM_ID=:cid LIMIT 1")
                ->execute([':st'=>$newStatus, ':cid'=>$comId]);

            onOrderRefunded($pdo, $comId);
        }
        break;
    }

    /* ---------- FALLBACK : logger sans casser ---------- */
    default:
        // Rien à faire pour les autres types, mais on répond 200.
        break;
}

/* =========================
   3) Fin
   ========================= */
http_response_code(200);
echo 'OK';
