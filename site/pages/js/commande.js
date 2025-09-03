// js/commande.js
(function () {
    // ---------- Core panier
    const KEY = 'dkb_cart_v1';

    function money(n) {
        return new Intl.NumberFormat('fr-CH', { style: 'currency', currency: 'CHF' }).format(n);
    }
    function getCart() {
        try { return JSON.parse(localStorage.getItem(KEY)) || { lines: [] }; }
        catch { return { lines: [] }; }
    }
    function saveCart(cart) {
        localStorage.setItem(KEY, JSON.stringify(cart));
        window.dispatchEvent(new Event('cart:updated'));
    }
    function findIndex(lines, sku) { return lines.findIndex(l => l.sku === sku); }

    function addItem({ id, sku, name, price, img, qty = 1 }) {
        const cart = getCart();
        const i = findIndex(cart.lines, sku);
        if (i >= 0) cart.lines[i].qty += qty;
        else cart.lines.push({ id, sku, name, price: Number(price), img, qty });
        saveCart(cart);
    }
    function updateQty(sku, qty) {
        const cart = getCart();
        const i = findIndex(cart.lines, sku);
        if (i >= 0) { cart.lines[i].qty = Math.max(1, Number(qty)); saveCart(cart); }
    }
    function removeItem(sku) {
        const cart = getCart();
        cart.lines = cart.lines.filter(l => l.sku !== sku);
        saveCart(cart);
    }
    function clear() { saveCart({ lines: [] }); }

    function totals({ shippingThreshold = 80, shippingFee = 9.9 } = {}) {
        const cart = getCart();
        const subtotal = cart.lines.reduce((s, l) => s + l.price * l.qty, 0);
        const shipping = subtotal > 0 && subtotal < shippingThreshold ? shippingFee : 0;
        const total = subtotal + shipping;
        return {
            subtotal, shipping, total,
            fmt: {
                subtotal: money(subtotal),
                shipping: shipping ? money(shipping) : '—',
                total: money(total)
            }
        };
    }

    // Expose global
    window.Cart = { getCart, addItem, updateQty, removeItem, clear, totals, money };

    // ---------- Rendu UI (page commande)
    const list = document.getElementById('cart-list');
    const sumSubtotal = document.getElementById('sum-subtotal');
    const sumShipping = document.getElementById('sum-shipping');
    const sumTotal = document.getElementById('sum-total');
    const btnCheckout = document.getElementById('btn-checkout');

    if (list && sumSubtotal && sumShipping && sumTotal && btnCheckout) {
        const SHIPPING_THRESHOLD = 80;
        const SHIPPING_FEE = 9.9;

        function lineTpl(l) {
            const lineTotal = l.price * l.qty;
            return `
        <div class="cart-line" data-sku="${l.sku}">
          <img src="${l.img}" alt="${l.name}" class="thumb">
          <div class="meta">
            <div class="name">${l.name}</div>
            <div class="price">${Cart.money(l.price)}</div>
          </div>
          <div class="qty">
            <button class="btn-ghost" data-action="dec">−</button>
            <input class="qty-input" type="number" min="1" value="${l.qty}">
            <button class="btn-ghost" data-action="inc">+</button>
          </div>
          <div class="line-total">${Cart.money(lineTotal)}</div>
          <button class="btn-remove" title="Supprimer" data-action="remove">×</button>
        </div>`;
        }

        function render() {
            const cart = Cart.getCart();
            list.innerHTML = cart.lines.length
                ? cart.lines.map(lineTpl).join('')
                : `<div class="empty"><p><strong>Panier vide</strong><br>Ajoutez des produits depuis le catalogue.</p></div>`;

            const t = Cart.totals({ shippingThreshold: SHIPPING_THRESHOLD, shippingFee: SHIPPING_FEE });
            sumSubtotal.textContent = t.fmt.subtotal;
            sumShipping.textContent = t.fmt.shipping;
            sumTotal.textContent = t.fmt.total;

            // Bouton checkout activé seulement si total > 0
            const isEmpty = cart.lines.length === 0;
            btnCheckout.setAttribute('aria-disabled', isEmpty ? 'true' : 'false');
        }

        list.addEventListener('click', (e) => {
            const wrap = e.target.closest('.cart-line');
            if (!wrap) return;
            const sku = wrap.dataset.sku;
            const input = wrap.querySelector('.qty-input');
            const current = Number(input.value) || 1;

            if (e.target.matches('[data-action="inc"]')) Cart.updateQty(sku, current + 1);
            if (e.target.matches('[data-action="dec"]')) Cart.updateQty(sku, Math.max(1, current - 1));
            if (e.target.matches('[data-action="remove"]')) Cart.removeItem(sku);
        });

        list.addEventListener('change', (e) => {
            if (!e.target.matches('.qty-input')) return;
            const wrap = e.target.closest('.cart-line');
            Cart.updateQty(wrap.dataset.sku, Number(e.target.value) || 1);
        });

        // Sécurité : empêcher le clic si désactivé
        btnCheckout.addEventListener('click', (e) => {
            if (btnCheckout.getAttribute('aria-disabled') === 'true') e.preventDefault();
        });

        window.addEventListener('cart:updated', render);
        window.addEventListener('storage', (ev) => { if (ev.key === KEY) render(); });
        render();
    }

    // site/pages/js/commande.js
    document.addEventListener('DOMContentLoaded', () => {
        const callAdd = async (proId, qty = 1) => {
            const form = new FormData();
            form.append('action', 'add');
            form.append('pro_id', String(proId));
            form.append('qty', String(qty));

            const res = await fetch('../api/cart.php?action=add', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            });
            return res.json();
        };

        const wire = (el, redirectAfter = false) => {
            el.addEventListener('click', async (e) => {
                e.preventDefault();
                const proId = Number(el.dataset.id || el.dataset.proId);
                const qty   = Number(el.dataset.qty || 1);
                if (!proId) { alert('Produit invalide: data-id manquant'); return; }

                try {
                    const data = await callAdd(proId, qty);
                    if (!data.ok) {
                        if (data.error === 'auth_required') { window.location.href = 'interface_connexion.php'; return; }
                        throw new Error(data.error || 'Erreur panier');
                    }
                    if (redirectAfter) {
                        window.location.href = 'commande.php';
                    } else {
                        el.textContent = 'Ajouté ✓';
                        setTimeout(() => (el.textContent = 'Ajouter'), 1200);
                    }
                } catch (err) {
                    console.error(err);
                    alert("Impossible d'ajouter au panier.");
                }
            });
        };

        document.querySelectorAll('button.add-to-cart').forEach(btn => wire(btn, false));

        const next = document.querySelector('a.add-to-cart[href*="commande.php"]');
        if (next) wire(next, true);
    });

})();
