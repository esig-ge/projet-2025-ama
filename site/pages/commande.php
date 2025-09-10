<?php
session_start();

/* Bases de chemins :
   - $PAGE_BASE = dossier courant (souvent /…/site/pages/)
   - $SITE_BASE = racine du site (souvent /…/site/) */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);

/* Détection API cart.php */
$api_fs_main  = __DIR__ . '/../api/cart.php';  // /site/api/cart.php (si api hors /pages)
$api_fs_pages = __DIR__ . '/api/cart.php';     // /site/pages/api/cart.php
if (is_file($api_fs_main)) {
    $API_URL = $SITE_BASE . 'api/cart.php';
} elseif (is_file($api_fs_pages)) {
    $API_URL = $PAGE_BASE . 'api/cart.php';
} else {
    $API_URL = $SITE_BASE . 'api/cart.php';      // fallback
}

/* Forcer le bouton vers adresse_paiement.php (et fallback checkout.php si jamais) */
$pay_pages = __DIR__ . '/adresse_paiement.php';   // /site/pages/adresse_paiement.php
$pay_main  = __DIR__ . '/../adresse_paiement.php';// /site/adresse_paiement.php

if (is_file($pay_pages)) {
    $CHECKOUT_URL = $PAGE_BASE . 'adresse_paiement.php';
} elseif (is_file($pay_main)) {
    $CHECKOUT_URL = $SITE_BASE . 'adresse_paiement.php';
} else {
    // fallback sur checkout.php si la page adresses n’existe pas
    $co_fs_main  = __DIR__ . '/../checkout.php';
    $co_fs_pages = __DIR__ . '/checkout.php';
    if (is_file($co_fs_main)) {
        $CHECKOUT_URL = $SITE_BASE . 'checkout.php';
    } elseif (is_file($co_fs_pages)) {
        $CHECKOUT_URL = $PAGE_BASE . 'checkout.php';
    } else {
        $CHECKOUT_URL = $SITE_BASE . 'checkout.php';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- ASSETS : comme ils sont DANS /site/pages/, on utilise PAGE_BASE -->
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/commande.css">

    <script>
        // DKBASE = dossier de cette page (pour assets/images)
        // API_URL = endpoint détecté (hors/avec pages)
        window.DKBASE       = <?= json_encode($PAGE_BASE) ?>;
        window.API_URL      = <?= json_encode($API_URL) ?>;
        window.CHECKOUT_URL = <?= json_encode($CHECKOUT_URL) ?>;
        console.debug('[cmd] PAGE_BASE=', DKBASE, 'API_URL=', API_URL, 'CHECKOUT_URL=', CHECKOUT_URL);
    </script>
    <script src="<?= $PAGE_BASE ?>js/commande.js" defer></script>
</head>

<body onload="renderCart()">
<?php include __DIR__ . '/includes/header.php'; ?>

<script>
    const h = document.querySelector('.site-header');
    if (h) document.documentElement.style.setProperty('--header-h', h.offsetHeight + 'px');
</script>

<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de ma commande</h1>

    <div class="grid">
        <!-- Liste des articles -->
        <section class="card" id="cart-list" aria-live="polite" data-state="loading"></section>

        <!-- Résumé -->
        <aside class="card summary" aria-labelledby="sum-title">
            <h2 id="sum-title" class="sr-only">Résumé de commande</h2>

            <div class="sum-row"><span>Produits</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div class="sum-row"><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div class="sum-total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>

            <!-- On utilise l’URL détectée -->
            <a href="<?= $CHECKOUT_URL ?>"
               class="btn-primary"
               id="btn-checkout"
               aria-disabled="true"
               onclick="if(this.getAttribute('aria-disabled')==='true'){return false;}">
                Valider ma commande
            </a>

            <div class="coupon">
                <input type="text" id="coupon-input" placeholder="Mon code de réduction" disabled>
                <button class="btn-ghost" disabled>Ajouter</button>
            </div>

            <div class="help">
                <p>Une question ? Contactez-nous au <a href="tel:+41765698541">+41 76 569 85 41</a></p>
                <ul>
                    <li>Expédition sous 7 jours (si disponible)</li>
                    <li>Frais livraison ?</li>
                    <li>Paiement sécurisé</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
