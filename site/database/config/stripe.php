<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../../../app/libs/stripe/lib/Stripe.php';

define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET'));

if (!defined('STRIPE_SECRET_KEY') || !STRIPE_SECRET_KEY) {
    throw new RuntimeException('Stripe secret key manquante.');
}

// 3) Initialiser Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
