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

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de mon panier</h1>

    <div class="grid">
        <!-- Liste du panier -->
        <section class="card" id="cart-list" aria-live="polite"></section>

        <script>
            (async function(){
                try {
                    const res = await fetch('../api/cart.php?action=list', {credentials:'same-origin'});
                    const data = await res.json();
                    if (!data.ok) return;

                    const wrap = document.getElementById('cart-list');
                    if (!data.items.length) { wrap.textContent = 'Votre panier est vide.'; return; }

                    wrap.innerHTML = data.items.map(it => `
      <div class="line">
        <span>${it.PRO_NOM}</span>
        <span>x ${it.CP_QTE_COMMANDEE}</span>
        <span>${Number(it.PRO_PRIX).toFixed(2)} CHF</span>
      </div>
    `).join('');
                } catch(e) { console.error(e); }
            })();
        </script>


        <!-- Résumé -->
        <aside class="card summary" aria-labelledby="sum-title">
            <h2 id="sum-title" class="sr-only">Résumé de commande</h2>
            <div class="sum-row"><span>Produits</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div class="sum-row"><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div class="sum-total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>

            <a href="checkout.php" class="btn-primary" id="btn-checkout" aria-disabled="true">Valider ma commande</a>

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
