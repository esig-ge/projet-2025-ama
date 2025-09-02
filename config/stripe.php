<?php
// /config/stripe.php
require_once __DIR__ . '/../app/libs/stripe/init.php';

// Remplacer par vraies clés (ou via variables d'environnement).
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51RxmN6JbWse4WkaF0I989MVCMxSUnskpcgWvAcHiSKXL56sITnPJpHcUANKAREVuGBb9D0uvc5gWXGMcBpRfUCQs00yieDsbqo');
define('STRIPE_SECRET_KEY', 'sk_test_51RxmN6JbWse4WkaF3wGmKs06V1xFEg20hjHMRlrGZQ5CEOLTasSXF7kGhmkuDjCh8dVMYfWfHvohCRg3GSek9lF900YHjex6KR');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
