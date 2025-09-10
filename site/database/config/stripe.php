<?php
// Charge les variables d'environnement
require_once __DIR__ . '/env.php';
loadProjectEnv();

// Lib Stripe (sans Composer)
require_once dirname(__DIR__, 3) . '/app/libs/stripe/init.php';

// Clé secrète Stripe
$secret = trim(getenv('STRIPE_SECRET_KEY') ?: '');
if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret)) {
    error_log("⚠️ Stripe secret mal lu : '".substr($secret,0,10)."' (len=".strlen($secret).")");
    throw new RuntimeException('Stripe secret key invalid or missing.');
}

\Stripe\Stripe::setApiKey($secret);
// Optionnel : forcer version API
// \Stripe\Stripe::setApiVersion('2024-06-20');
