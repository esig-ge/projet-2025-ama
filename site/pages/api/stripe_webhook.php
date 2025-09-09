<?php
// site/api/stripe_webhook.php

// — chemins adaptés à ton dépôt —
require_once __DIR__ . '/../database/config/stripe.php';      // initialise \Stripe\Stripe::setApiKey(...)
require_once __DIR__ . '/../database/config/connexionBDD.php'; // fournit $pdo

// Récupère le secret du webhook (depuis .env ou env.php)
$endpointSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : null);
if (!$endpointSecret) {
    http_response_code(500);
    echo 'Missing webhook secret';
    exit;
}

// Payload brut + signature
$payload   = @file_get_contents('php://input');
$sig       = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400); echo 'Invalid payload'; exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); echo 'Invalid signature'; exit;
}

// --- TRAITEMENT ---
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object; // \Stripe\Checkout\Session
        $comId   = $session->metadata->com_id ?? null;
        $piId    = $session->payment_intent ?? null;
        $amount  = isset($session->amount_total) ? $session->amount_total / 100 : null;

        if ($comId) {
            // Exemple de mise à jour — adapte noms de colonnes / statut
            $stmt = $pdo->prepare("UPDATE COMMANDE SET COM_STATUT = 'PAYEE', STRIPE_PI_ID = :pi, COM_MONTANT = COALESCE(:amount, COM_MONTANT)
                                   WHERE COM_ID = :id");
            $stmt->execute([
                'pi'     => $piId,
                'amount' => $amount,
                'id'     => $comId
            ]);
        }
        break;

    case 'payment_intent.succeeded':
        // Optionnel si tu préfères travailler à ce niveau
        $pi = $event->data->object; // \Stripe\PaymentIntent
        // $pi->id, $pi->amount_received, etc.
        break;

    default:
        // autres événements ignorés
        break;
}

// Toujours répondre 2xx rapidement
http_response_code(200);
echo 'ok';
