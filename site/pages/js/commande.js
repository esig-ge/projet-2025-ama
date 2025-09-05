// js/commande.js (sans addEventListener)

const API_URL = '/site/api/cart.php';

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

// bouton "Ajouter"
async function addToCart(proId, btn) {
    if (!proId) return;
    if (btn) btn.disabled = true;
    try {
        await callApi('add', { pro_id: proId, qty: 1 });

        // refresh si panier présent
        if (document.getElementById('cart-list')) {
            await renderCart();
        }

        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; btn.disabled = false; }, 900);
        }
    } catch (err) {
        alert("Impossible d'ajouter au panier.\n" + (err?.message || ''));
        console.error(err);
        if (btn) btn.disabled = false;
    }
}

const chf = n => `${Number(n).toFixed(2)} CHF`;

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

async function renderCart() {
    const wrap = document.getElementById('cart-list');
    if (!wrap) return;

    wrap.innerHTML = 'Chargement…';
    try {
        const { items = [] } = await callApi('list');
        // debug rapide pour voir les clés de l’API
        if (items[0]) console.log('Item[0] keys →', Object.keys(items[0]), items[0]);

        if (!items.length) {
            wrap.innerHTML = '<p>Votre panier est vide.</p>';
            updateSummary(0);
            return;
        }

        wrap.innerHTML = items.map(it => {
            const nom  = it.PRO_NOM ?? it.nom ?? it.name ?? `#${it.PRO_ID ?? it.id ?? ''}`;
            const qte  = Number(it.CP_QTE_COMMANDEE ?? it.qte ?? it.qty ?? 1);
            const prix = Number(it.PRO_PRIX ?? it.prix_unitaire ?? it.price ?? 0);
            const total = prix * qte;
            const img   = it.PRO_IMG || it.image || 'img/placeholder.png';
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
            const p = Number(it.PRO_PRIX ?? it.prix_unitaire ?? it.price ?? 0);
            const q = Math.max(1, Number(it.CP_QTE_COMMANDEE ?? it.qte ?? it.qty ?? 1));
            return s + p * q;
        }, 0);

        updateSummary(subtotal);
    } catch (e) {
        console.error(e);
        wrap.innerHTML = '<p>Erreur lors du chargement du panier.</p>';
        updateSummary(0);
    }
}

/* --- IMPORTANT : expose au global pour <body onload="renderCart()">, onclick="addToCart(...)" --- */
window.renderCart = renderCart;
window.addToCart  = addToCart;
