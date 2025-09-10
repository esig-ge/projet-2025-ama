<?php
// /site/api/create-intent.php
session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../database/config/env.php';
    loadProjectEnv();
    require_once __DIR__ . '/../database/config/stripe.php'; // setApiKey(STRIPE_SECRET_KEY)

    // Montant reçu (CHF) -> centimes
    $amountChf = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    if ($amountChf <= 0) throw new RuntimeException('Montant invalide');

    $amount = (int) round($amountChf * 100); // en centimes

    // Crée le PaymentIntent
    $pi = \Stripe\PaymentIntent::create([
        'amount' => $amount,
        'currency' => 'chf',
        'automatic_payment_methods' => ['enabled' => true], // carte, twint si activé, etc.
        'metadata' => [
            'per_id' => (string)($_SESSION['per_id'] ?? 0),
            'com_id' => (string)($_SESSION['com_id'] ?? 0),
        ],
    ]);

    $pub = getenv('STRIPE_PUBLISHABLE_KEY');
    if (!$pub) throw new RuntimeException('STRIPE_PUBLISHABLE_KEY manquant.');

    echo json_encode([
        'ok' => true,
        'publishableKey' => $pub,
        'clientSecret'   => $pi->client_secret,
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
