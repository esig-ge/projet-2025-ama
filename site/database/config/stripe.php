<?php
// site/database/config/stripe.php
require_once __DIR__ . '/../../app/libs/stripe/init.php'; // adapte si besoin

$secret = getenv('STRIPE_SECRET_KEY') ?: '';
$secret = trim($secret);

// Log temporaire pour vérifier ce qui est lu
error_log('Stripe sk prefix='.substr($secret,0,8).' len='.strlen($secret));

if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret)) {
    throw new RuntimeException('Stripe secret key invalid or missing.');
}

\Stripe\Stripe::setApiKey($secret);
// \Stripe\Stripe::setApiVersion('2024-06-20'); // optionnel
