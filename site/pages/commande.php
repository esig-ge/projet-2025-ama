<?php
session_start();

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';   // ex: "/…/site/pages/"

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/commande.css">

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;               // "/…/site/pages/"
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>; // "/…/site/pages/api/cart.php"
    </script>
    <script src="<?= $BASE ?>js/commande.js" defer></script>

</head>

<body onload="renderCart()">
<?php include __DIR__ . '/includes/header.php'; ?>

<script>
    // Ajuster la variable CSS --header-h selon la hauteur du header
    const h = document.querySelector('.site-header');
    if (h) document.documentElement.style.setProperty('--header-h', h.offsetHeight + 'px');
</script>

<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de ma commande</h1>

    <div class="grid">
        <!-- Liste des articles -->
        <section class="card" id="cart-list" aria-live="polite" data-state="loading">
            <!-- Contenu injecté par commande.js -->
        </section>

        <!-- Résumé -->
        <aside class="card summary" aria-labelledby="sum-title">
            <h2 id="sum-title" class="sr-only">Résumé de commande</h2>

            <div class="sum-row">
                <span>Produits</span>
                <span id="sum-subtotal">0.00 CHF</span>
            </div>
            <div class="sum-row">
                <span>Livraison</span>
                <span id="sum-shipping">—</span>
            </div>
            <div class="sum-total">
                <span>Total</span>
                <span id="sum-total">0.00 CHF</span>
            </div>

            <!-- checkout est à /site/checkout.php -->
            <a href="../checkout.php"
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
