<?php
// site/database/config/stripe.php

// Chemins (site/… -> app/libs/stripe/…)
$siteRoot   = dirname(__DIR__, 2);                 // …/site
$stripeRoot = $siteRoot . '/../app/libs/stripe';   // …/app/libs/stripe

// 1) Charger l’autoloader Stripe via init.php
$init = $stripeRoot . '/init.php';
if (!file_exists($init)) {
    http_response_code(500);
    die('Stripe init.php introuvable: ' . htmlspecialchars($init));
}
require_once $init;

// 2) Clé secrète (mets ta vraie clé test)
$STRIPE_SECRET = getenv('STRIPE_SECRET') ?: 'sk_test_xxx_REMPLACE';
\Stripe\Stripe::setApiKey($STRIPE_SECRET);

// (optionnel) figer la version API
// \Stripe\Stripe::setApiVersion('2023-10-16');
