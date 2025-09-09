/* =============== 1) Bases de chemins =============== */
const PAGE_BASE = (typeof window.DKBASE === 'string' && window.DKBASE.length)
    ? window.DKBASE
    : location.pathname.replace(/[^/]+$/, '');

const SITE_BASE = (() => {
    const m = PAGE_BASE.match(/^(.*\/)pages\/$/);
    return m ? m[1] : PAGE_BASE;
})();

const API_URL = (typeof window.API_URL === 'string' && window.API_URL.length)
    ? window.API_URL
    : SITE_BASE + 'api/cart.php';

console.debug('[DKBloom] PAGE_BASE =', PAGE_BASE);
console.debug('[DKBloom] SITE_BASE =', SITE_BASE);
console.debug('[DKBloom] API_URL   =', API_URL);

const ASSET_BASE = PAGE_BASE;

/* =============== 2) Helpers =============== */
const chf = n => `${Number(n).toFixed(2)} CHF`;

function normImgPath(p) {
    if (!p) return PAGE_BASE + 'img/placeholder.png';
    if (/^(https?:)?\/\//.test(p) || p.startsWith('/') || p.startsWith('data:')) return p;
    if (p.startsWith('img/'))   return PAGE_BASE + p;   // "/site/pages/img/..."
    if (p.startsWith('pages/')) return SITE_BASE + p;   // "/site/pages/..."
    return SITE_BASE + p;
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
        btn.toggleAttribute('disabled', !enabled);
        btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        btn.style.opacity = enabled ? '' : '0.6';
    }
}

/* =============== 5) Rendu panier =============== */
async function renderCart() {
    const wrap = document.getElementById('cart-list');
    if (!wrap) return;

    wrap.innerHTML = 'Chargement…';
    try {
        const resp = await callApi('list');
        const items = resp.items || [];

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
            const type  = it.item_type || 'produit'; // produit | emballage | supplement
            const id    = it.id ?? it.PRO_ID ?? 0;

            return `
        <div class="cart-row">
          <img src="${img}" alt="${nom}" class="cart-img">
          <div class="cart-name">${nom}</div>
          <div class="cart-qty">x&nbsp;${qte}</div>
          <div class="cart-unit">${chf(prix)}</div>
          <div class="cart-total">${chf(total)}</div>
          <button class="cart-remove" title="Supprimer"
                  data-type="${type}" data-id="${id}"
                  onclick="removeFromCart(this.dataset.type, this.dataset.id)">🗑️</button>
        </div>`;
        }).join('');

        // Utilise le subtotal serveur si présent, sinon recalcule
        const subtotal = typeof resp.subtotal === 'number'
            ? resp.subtotal
            : items.reduce((s, it) => {
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

/* === Sélection de rose (fleurs) === */
function selectedRoseRadio(){
    return document.querySelector('input[name="rose-color"]:checked');
}

async function selectRose(btn){
    const r = selectedRoseRadio();
    if (!r) { alert('Choisis une couleur de rose.'); return; }
    const proId = r.dataset.proId;
    await addToCart(proId, btn);
}

/* === Emballage === */
async function addEmballage(embId, btn){
    const id = Number(embId);
    if (!id) return;

    if (btn) btn.disabled = true;
    try {
        // API accepte 'add' avec emb_id (ou alias add_emballage)
        await callApi('add', { emb_id: id, qty: 1 });

        if (document.getElementById('cart-list')) {
            await renderCart();
        }

        if (btn){
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e){
        alert('Impossible d’ajouter cet emballage.\n' + (e?.message || ''));
        console.error(e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* === Supplément === */
async function addSupplement(supId, btn){
    const id = Number(supId);
    if (!id) return;

    if (btn) btn.disabled = true;
    try {
        // IMPORTANT: on utilise l'action 'add' avec sup_id (compatible avec ton API PHP)
        await callApi('add', { sup_id: id, qty: 1 });

        if (document.getElementById('cart-list')) {
            await renderCart();
        }

        if (btn){
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e){
        alert('Impossible d’ajouter ce supplément.\n' + (e?.message || ''));
        console.error(e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* =============== 7) Supprimer du panier =============== */
async function removeFromCart(itemType, id) {
    try {
        id = Number(id);
        if (!id) return;

        const params = {};
        if (itemType === 'produit')    params.pro_id = id;
        else if (itemType === 'emballage') params.emb_id = id;
        else if (itemType === 'supplement') params.sup_id = id;

        await callApi('remove', params);
        await renderCart();
    } catch (e) {
        console.error(e);
        alert("Impossible de supprimer l'article.");
    }
}

/* Expose au global pour onload/onclick inline */
window.renderCart     = renderCart;
window.addToCart      = addToCart;
window.selectRose     = selectRose;
window.addSupplement  = addSupplement;
window.addEmballage   = addEmballage;
window.removeFromCart = removeFromCart;
