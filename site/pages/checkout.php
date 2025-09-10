<?php
session_start();

/* 1) ENV & STRIPE (hors /pages/) */
require_once __DIR__ . '/../database/config/env.php';
loadProjectEnv();
require_once __DIR__ . '/../database/config/stripe.php';
require_once __DIR__ . '/../database/config/connexionBDD.php'; // si besoin DB

/* 2) Bases de chemins (assets dans /pages, API détectée) */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);

/* Détection API cart.php pour récap sur checkout si tu l’utilises */
$api_fs_main  = __DIR__ . '/../api/cart.php';
$api_fs_pages = __DIR__ . '/api/cart.php';
if (is_file($api_fs_main)) {
    $API_URL = $SITE_BASE . 'api/cart.php';
} elseif (is_file($api_fs_pages)) {
    $API_URL = $PAGE_BASE . 'api/cart.php';
} else {
    $API_URL = $SITE_BASE . 'api/cart.php';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Paiement</title>

    <!-- ASSETS : DANS /site/pages/ -->
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/checkout.css">

    <script>
        window.DKBASE  = <?= json_encode($PAGE_BASE) ?>; // pour images
        window.API_URL = <?= json_encode($API_URL) ?>;    // cart.php détecté
        console.debug('[checkout] PAGE_BASE=', DKBASE, 'API_URL=', API_URL);
    </script>
    <script src="<?= $PAGE_BASE ?>js/checkout.js" defer></script>
    <!-- Stripe.js (si tu l’utilises en front) -->
    <script src="https://js.stripe.com/v3/"></script>
</head>

<body onload="initCheckout()">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap" role="main">
    <h1 class="page-title">Paiement sécurisé</h1>

    <section class="card">
        <div id="cart-lines" class="cart-lines">Chargement…</div>

        <div class="sum">
            <div><span>Produits</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div><span>TVA</span><span id="sum-tva">0.00 CHF</span></div>
            <div class="total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>
        </div>

        <form id="pay-form" onsubmit="return onPay(this)">
            <div id="payment-element"></div>
            <p id="form-message" class="form-msg" aria-live="polite"></p>
            <button id="pay-btn" class="btn-primary" type="submit" disabled>Payer</button>
        </form>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
