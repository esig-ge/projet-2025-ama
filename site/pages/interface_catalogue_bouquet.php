<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/styleCatalogue.css">
    <title>catalogue bouquet</title>
</head>
<?php
include 'includes/header.php';
?>
<!-- Ajouter les bouquets dans le panier -->
<script>
    const CART_KEY = 'cart';

    function getCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
        catch { return []; }
    }
    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }
    function addToCart(item) {
        const cart = getCart();
        const key = item.id || item.sku || item.name;
        const i = cart.findIndex(p => (p.id || p.sku || p.name) === key);
        if (i >= 0) {
            cart[i].qty += item.qty || 1;
        } else {
            cart.push({ ...item, qty: item.qty || 1 });
        }
        saveCart(cart);
        updateCartCount();
    }
    function updateCartCount() {
        const badge = document.getElementById('cart-count');
        if (!badge) return; // no badge in header — skip
        const totalQty = getCart().reduce((s, it) => s + (Number(it.qty) || 0), 0);
        badge.textContent = totalQty > 0 ? totalQty : '';
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.add-to-cart');
        if (!btn) return;

        const price = parseFloat(btn.dataset.price);
        const item = {
            id:   btn.dataset.id   || btn.dataset.sku || btn.dataset.name, // stable key
            sku:  btn.dataset.sku  || '',
            name: btn.dataset.name || 'Produit',
            price: isNaN(price) ? 0 : price,
            image: btn.dataset.img || '',
            qty: 1
        };

        addToCart(item);
        const txt = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Ajouté ✓';
        setTimeout(() => { btn.disabled = false; btn.textContent = txt; }, 900);
    });

    document.addEventListener('DOMContentLoaded', updateCartCount);
</script>

<body>
<h1>Catalogue bouquet</h1>
<div id="produit_bouquet">
<div>
    <img src="img/12Roses.png"/>
    <h3>12 roses</h3>
    <p>30 CHF</p>
    <button class="add-to-cart"
            data-id="7"
            data-nom="12 roses"
            data-prix="30"
            data-img="img/12Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/20Roses.png"/>
    <h3>20 roses</h3>
    <p>40 CHF</p>
    <button class="add-to-cart"
            data-id="8"
            data-nom="20 roses"
            data-prix="40"
            data-img="img/20Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/20Roses.png"/>
    <h3>24 roses</h3>
    <p>45 CHF</p>
    <button class="add-to-cart"
            data-id="9"
            data-nom="24 roses"
            data-prix="45"
            data-img="img/20Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/36Roses.png"/>
    <h3>36 roses</h3>
    <p>60 CHF</p>
    <button class="add-to-cart"
            data-id="10"
            data-nom="36 roses"
            data-prix="60"
            data-img="img/36Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/50Roses.png"/>
    <h3>50 roses</h3>
    <p>70 CHF</p>
    <button class="add-to-cart"
            data-id="11"
            data-nom="50 roses"
            data-prix="70"
            data-img="img/50Roses.png">
        Ajouter
    </button>

</div>

<div>
    <img src="img/66Roses.png"/>
    <h3>66 roses</h3>
    <p>85 CHF</p>
    <button class="add-to-cart"
            data-id="12"
            data-nom="66 roses"
            data-prix="85"
            data-img="img/66Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/100Roses.png"/>
    <h3>99 roses</h3>
    <p>110 CHF</p>
    <button class="add-to-cart"
            data-id="13"
            data-nom="99 roses"
            data-prix="110"
            data-img="img/100Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/100Roses.png"/>
    <h3>100 roses</h3>
    <p>112 CHF</p>
    <button class="add-to-cart"
            data-id="14"
            data-nom="100 roses"
            data-prix="112"
            data-img="img/100Roses.png">
        Ajouter
    </button>

</div>
<div>
    <img src="img/100Roses.png"/>
    <h3>101 roses</h3>
    <p>115 CHF</p>
    <button class="add-to-cart"
            data-id="15"
            data-nom="101 roses"
            data-prix="115"
            data-img="img/100Roses.png">
        Ajouter
    </button>

</div>
</div>

<a href="interface_supplement.php" class="button">suivant</a>
</body>
</html>