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
<script>
    const h = document.querySelector('.site-header');
    if (h) {
        document.documentElement.style.setProperty('--header-h', h.offsetHeight + 'px');
    }
</script>


<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de mon panier</h1>

    <div class="grid">
        <!-- Liste du panier -->
        <section class="card" id="cart-list" aria-live="polite"></section>

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
<script>
    (async function(){
        try {
            const res = await fetch('../api/cart.php?action=list', {credentials:'same-origin'});
            const data = await res.json();
            if (!data.ok) return;

            const wrap = document.getElementById('cart-list');
            if (!data.items.length) {
                wrap.innerHTML = '<div class="empty"><p><strong>Votre panier est vide</strong></p></div>';
            } else {
                wrap.innerHTML = data.items.map(it => {
                    const pu = Number(it.PRO_PRIX) || 0;
                    const q  = Number(it.CP_QTE_COMMANDEE) || 0;
                    const lt = (pu * q).toFixed(2);
                    return `
          <div class="cart-brief">
            <div class="name">${it.PRO_NOM}</div>
            <div class="qty">x&nbsp;${q}</div>
            <div class="unit">${pu.toFixed(2)}&nbsp;CHF</div>
            <div class="total">${lt}&nbsp;CHF</div>
          </div>
        `;
                }).join('');
            }

            if (window.updateSummaryUI) window.updateSummaryUI(data.items);
        } catch(e) {
            console.error(e);
        }
    })();
</script>


</html>
