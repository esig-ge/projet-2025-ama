// js/commande.js
(() => {
    const API_URL = '/site/api/cart.php';

    function on(selector, event, handler, root = document) {
        const el = root.querySelector(selector);
        if (el) el.addEventListener(event, handler);
        return !!el;
    }

    async function callApi(action, params = {}) {
        const url  = `${API_URL}?action=${encodeURIComponent(action)}`;
        const body = new URLSearchParams({ action, ...params });
        const res  = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch { throw new Error(`Réponse non JSON (${res.status})`); }
        if (!res.ok || data.ok === false) throw new Error(data.error || data.msg || `HTTP ${res.status}`);
        return data;
    }

    // Binder "Ajouter" (délégation sûre)
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-to-cart');
        if (!btn) return;
        const proId = btn.dataset.proId;
        if (!proId) return;

        btn.disabled = true;
        try {
            await callApi('add', { pro_id: proId, qty: 1 });
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; btn.disabled = false; }, 900);
        } catch (err) {
            alert("Impossible d'ajouter au panier.\n" + (err?.message || ''));
            console.error(err);
            btn.disabled = false;
        }
    });

    const chf = n => `${Number(n).toFixed(2)} CHF`;

    async function renderCart() {
        const wrap = document.getElementById('cart-list');
        if (!wrap) return;

        wrap.innerHTML = 'Chargement…';
        try {
            const { items = [] } = await callApi('list');
            // debug: vois ce que renvoie l’API
            console.log('API list →', items);

            if (!items.length) {
                wrap.innerHTML = '<p>Votre panier est vide.</p>';
                updateSummary(0);
                return;
            }

            wrap.innerHTML = items.map(it => {
                const nom   = it.nom ?? it.PRO_NOM ?? it.name ?? `#${it.id ?? it.PRO_ID ?? ''}`;
                const qte   = Math.max(1, Number(it.qte ?? it.CP_QTE_COMMANDEE ?? it.qty ?? 1));
                const prix  = Number(it.prix_unitaire ?? it.PRO_PRIX ?? it.price ?? 0);
                const total = prix * qte;
                const img   = it.image || it.PRO_IMG || 'img/placeholder.png';
                return `
          <div class="cart-row">
            <img src="${img}" alt="${nom}" class="cart-img">
            <div class="cart-name">${nom}</div>
            <div class="cart-qty">x&nbsp;${qte}</div>
            <div class="cart-unit">${chf(prix)}</div>
            <div class="cart-total">${chf(total)}</div>
          </div>`;
            }).join('');

            const subtotal = items.reduce((s, it) => {
                const p = Number(it.prix_unitaire ?? it.PRO_PRIX ?? it.price ?? 0);
                const q = Math.max(1, Number(it.qte ?? it.CP_QTE_COMMANDEE ?? it.qty ?? 1));
                return s + p * q;
            }, 0);

            updateSummary(subtotal);
        } catch (e) {
            console.error(e);
            wrap.innerHTML = '<p>Erreur lors du chargement du panier.</p>';
        }
    }

    function updateSummary(subtotal) {
        const el  = document.getElementById('sum-subtotal');
        const tot = document.getElementById('sum-total');
        if (el)  el.textContent  = chf(subtotal);
        if (tot) tot.textContent = chf(subtotal);

        const btn = document.getElementById('btn-checkout');
        if (btn) {
            const enabled = subtotal > 0;
            btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            btn.style.pointerEvents = enabled ? 'auto' : 'none';
            btn.style.opacity = enabled ? '' : '0.6';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('cart-list')) renderCart();

        // Listeners globaux protégés - empêchent l’erreur addEventListener
        on('.menu-toggle', 'click', () => {
            document.querySelector('[data-nav]')?.classList.toggle('open');
        });

        on('#btn-checkout', 'click', (e) => {
            const a = e.currentTarget;
            if (a.getAttribute('aria-disabled') === 'true') e.preventDefault();
        });

        // Si un carrousel existe ET goto est exposé par script.js
        const prev = document.querySelector('[data-prev]');
        if (prev && typeof window.goto === 'function') {
            prev.addEventListener('click', () => window.goto((window.index ?? 0) - 1));
        }
        const next = document.querySelector('[data-next]');
        if (next && typeof window.goto === 'function') {
            next.addEventListener('click', () => window.goto((window.index ?? 0) + 1));
        }
    });
})();
