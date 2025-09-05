<?php
// Démarre/ouvre la session PHP (pour accéder au panier, à l'utilisateur connecté, etc.)
session_start();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- Styles globaux + page -->
    <link rel="stylesheet" href="css/style_header_footer.css">
    <link rel="stylesheet" href="css/commande.css">

    <!-- JS panier (version sans addEventListener) -->
    <script src="/site/pages/js/commande.js" defer></script>
</head>

<!-- 👉 onload lance directement renderCart() -->
<body onload="renderCart()">
<?php
// Header commun
include __DIR__ . '/includes/header.php';
?>

<script>
    // Ajuste une variable CSS selon la hauteur du header (utile si sticky)
    const h = document.querySelector('.site-header');
    if (h) document.documentElement.style.setProperty('--header-h', h.offsetHeight + 'px');
</script>

<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de ma commande</h1>

    <div class="grid">
        <!-- Liste des articles -->
        <section class="card" id="cart-list" aria-live="polite" data-state="loading">
            <!-- Le contenu est injecté par js/commande.js → renderCart() -->
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

            <!-- 👉 ici, onclick direct pour contrôler si désactivé -->
            <a href="checkout.php" class="btn-primary" id="btn-checkout"
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
                    <li>Frais livraison?</li>
                    <li>Paiement sécurisé</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
