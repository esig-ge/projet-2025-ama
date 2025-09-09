// js/commande.js

/* =============== 1) Bases de chemins =============== */
// Base dossier de la page
const PAGE_BASE = (typeof window.DKBASE === 'string' && window.DKBASE.length)
    ? window.DKBASE
    : location.pathname.replace(/[^/]+$/, '');

// Parent de /pages/
const SITE_BASE = (() => {
    const m = PAGE_BASE.match(/^(.*\/)pages\/$/);
    return m ? m[1] : PAGE_BASE;
})();

// URL de l'API (injectée par PHP ou fallback local)
const API_URL = (typeof window.API_URL === 'string' && window.API_URL.length)
    ? window.API_URL
    : PAGE_BASE + 'api/cart.php';

console.debug('[DKBloom] API_URL =', API_URL);

// Base visuelle des assets de page
const ASSET_BASE = PAGE_BASE;

// … le reste (callApi/renderCart/addToCart) inchangé …


/* =============== 2) Helpers =============== */
const chf = n => `${Number(n).toFixed(2)} CHF`;

function normImgPath(p) {
    if (!p) return ASSET_BASE + 'img/placeholder.png';
    if (/^(https?:)?\/\//.test(p) || p.startsWith('/') || p.startsWith('data:')) return p;
    return `${SITE_BASE}${p}`;
}

/* =============== 3) Appels API =============== */
async function callApi(action, params = {}) {
    const url  = `${API_URL}?action=${encodeURIComponent(action)}`;
    const body = new URLSearchParams({ action, ...params });

    const res  = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
    });

    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch {
        console.error('Réponse brute:', text);
        throw new Error(`Réponse non JSON (HTTP ${res.status}) depuis ${url}`);
    }
    if (!res.ok || data.ok === false) {
        const msg = data?.error || data?.msg || `HTTP ${res.status}`;
        throw new Error(`API "${action}" a échoué: ${msg}`);
    }
    return data;
}

/* =============== 4) UI: résumé =============== */
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

/* =============== 5) Rendu panier =============== */
async function renderCart() {
    const wrap = document.getElementById('cart-list');
    if (!wrap) return;

    wrap.innerHTML = 'Chargement…';
    try {
        const { items = [] } = await callApi('list');

        if (!items.length) {
            wrap.innerHTML = '<p>Votre panier est vide.</p>';
            updateSummary(0);
            return;
        }

        wrap.innerHTML = items.map(it => {
            const nom   = it.PRO_NOM ?? it.nom ?? it.name ?? `#${it.PRO_ID ?? it.id ?? ''}`;
            const qte   = Math.max(1, Number(it.CP_QTE_COMMANDEE ?? it.qte ?? it.qty ?? 1));
            const prix  = Number(it.PRO_PRIX ?? it.prix_unitaire ?? it.price ?? 0);
            const total = prix * qte;
            const img   = normImgPath(it.PRO_IMG || it.image || 'img/placeholder.png');

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
        const msg = (e && e.message) ? e.message : 'Erreur inconnue';
        wrap.innerHTML = `<p>Erreur lors du chargement du panier.<br><small>${msg}</small></p>`;
        updateSummary(0);
    }
}

/* =============== 6) Ajouter au panier =============== */
async function addToCart(proId, btn) {
    if (!proId) return;
    const pid = Number(proId);
    if (!pid) return;

    if (btn) btn.disabled = true;
    try {
        await callApi('add', { pro_id: pid, qty: 1 });

        if (document.getElementById('cart-list')) {
            await renderCart();
        }

        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (err) {
        alert("Impossible d'ajouter au panier.\n" + (err?.message || ''));
        console.error(err);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* Expose au global pour onload/onclick inline */
window.renderCart = renderCart;
window.addToCart  = addToCart;
