<?php
// /app/actions/stripe_webhook.php
$ROOT = dirname(__DIR__, 2); // …/Projet_sur_Mandat
require_once $ROOT . '/config/stripe.php';
require_once $ROOT . '/config/connexionBDD.php';


$payload = @file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpointSecret = $endpointSecret = STRIPE_WEBHOOK_SECRET; // secret du webhook (dashboard)

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig, $endpointSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400); exit; // payload invalide
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); exit; // signature invalide
}

if ($event->type === 'payment_intent.succeeded') {
    $pi = $event->data->object; // \Stripe\PaymentIntent
    $stripeId = $pi->id;
    $amount = $pi->amount_received / 100.0;

    // TODO: retrouver la commande liée au $stripeId et la passer en "PAYE"
    // $stmt = $pdo->prepare("UPDATE COMMANDE SET statut='PAYE', total=? WHERE stripe_pi_id=?");
    // $stmt->execute([$amount, $stripeId]);
}

http_response_code(200);
