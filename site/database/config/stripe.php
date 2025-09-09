<?php
require_once __DIR__ . '/env.php';
loadProjectEnv();

// Si tu n’as pas Composer :
require_once dirname(__DIR__, 3) . '/app/libs/stripe/init.php';

$secret = trim(getenv('STRIPE_SECRET_KEY') ?: '');
if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret)) {
    throw new RuntimeException('Stripe secret key invalid or missing.');
}
\Stripe\Stripe::setApiKey($secret);
// Optionnel : \Stripe\Stripe::setApiVersion('2024-06-20');
