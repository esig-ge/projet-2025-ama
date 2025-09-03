<?php
session_start();
?>
<!doctype html>
<html lang="fr">
<head>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- CSS du site -->
    <link rel="stylesheet" href="css/style_header_footer.css">
    <!-- CSS du panier -->
    <link rel="stylesheet" href="css/commande.css">
</head>
<?php include __DIR__ . '/includes/header.php'; ?>

<body>

<main class="wrap">
    <h1 class="page-title">Récapitulatif de mon panier</h1>

    <div class="grid">
        <section class="card" id="cart-list" aria-live="polite"></section>

        <aside class="card summary">
            <div class="sum-row"><span>Produits</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div class="sum-row"><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div class="sum-total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>
            <a href="checkout.php" class="btn-primary" id="btn-checkout">Valider ma commande</a>

            <div class="coupon">
                <input type="text" id="coupon-input" placeholder="Mon code de réduction" disabled>
                <button class="btn-ghost" disabled>Ajouter</button>
            </div>

            <div class="help">
                <p>Une question ? Contactez-nous au <a href="tel:+41765698541">+41 76 569 85 41</a></p>
                <ul>
                    <li>Expédition sous 7 jours (si disponible)</li>
                    <li>Frais  livraison?</li>
                    <li>Paiement sécurisé</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<footer class="dkb-footer">
    <div class="wrap">© DK Bloom</div>
</footer>

<!-- JS du panier -->
<script src="js/commande.js"></script>
</body>
</html>
