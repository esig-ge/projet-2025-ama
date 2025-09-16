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
    if (!p) return SITE_BASE + 'img/placeholder.png';
    if (/^(https?:)?\/\//.test(p) || p.startsWith('/') || p.startsWith('data:')) return p;
    if (p.startsWith('img/'))   return PAGE_BASE + p;   // "/site/pages/img/..."
    if (p.startsWith('pages/')) return SITE_BASE + p;   // "/site/pages/..."
    return SITE_BASE + p;
}

/** Récupère une quantité valide (>=1 entier) depuis toutes les sources possibles. */
function resolveQty(btn, optsOrQty) {
    // 0) si 3e arg est un nombre
    if (typeof optsOrQty === 'number') {
        const n = Math.max(1, optsOrQty | 0);
        if (n) return n;
    }
    // 1) si 3e arg est un objet avec qty
    if (optsOrQty && typeof optsOrQty === 'object') {
        const q = parseInt(optsOrQty.qty, 10);
        if (Number.isFinite(q) && q > 0) return q;
    }
    // 2) data-qty (attribut) sur le bouton
    const fromAttr = parseInt(btn?.getAttribute?.('data-qty'), 10);
    if (Number.isFinite(fromAttr) && fromAttr > 0) return fromAttr;

    // 3) dataset.qty
    const fromDataset = parseInt(btn?.dataset?.qty, 10);
    if (Number.isFinite(fromDataset) && fromDataset > 0) return fromDataset;

    // 4) input .qty dans le même form / carte
    const form = btn?.closest?.('form, .product, .card, .produit-info');
    const input = form?.querySelector?.('.qty');
    const fromInput = parseInt(input?.value, 10);
    if (Number.isFinite(fromInput) && fromInput > 0) return fromInput;

    // 5) défaut
    return 1;
}

/** Récupère une couleur (si utilisée) ; supporte name="couleur_ID" ou "couleur". */
function resolveColor(btn, optsOrQty) {
    if (optsOrQty && typeof optsOrQty === 'object' && optsOrQty.color) {
        return String(optsOrQty.color);
    }
    const form = btn?.closest?.('form, .product, .card, .produit-info');
    return (
        form?.querySelector?.('input[name^="couleur_"]:checked')?.value ||
        form?.querySelector?.('input[name="couleur"]:checked')?.value ||
        null
    );
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
        btn.toggleAttribute('tabindex', !enabled);
        btn.style.pointerEvents = enabled ? '' : 'none';
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
/**
 * Signature tolérante :
 *   addToCart(proId, btn, 3eArg)
 * - 3eArg peut être un nombre (qty) OU un objet { qty, color, ... }
 * - si absent, la quantité est résolue via data-qty / input .qty proches.
 */
async function addToCart(proId, btn, thirdArg) {
    const pid = Number(proId);
    if (!pid) { console.debug('[DKBloom:addToCart] proId invalide:', proId); return; }

    const qty   = resolveQty(btn, thirdArg);
    const color = resolveColor(btn, thirdArg); // utilisé seulement si ton API gère la couleur

    // Prépare payload pour l’API
    const payload = { pro_id: pid, qty };
    if (color) payload.color = color; // garde ou retire selon ton PHP

    if (btn) btn.disabled = true;
    try {
        await callApi('add', payload);
        if (document.getElementById('cart-list')) await renderCart();
        toastAdded(btn, `Produit #${pid}`);
        if (btn){
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e) {
        console.error('API add error', e);
        toastError(btn, `Produit #${pid}`, e);
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

    // pour compat avec d’autres scripts/toasts
    const qty = resolveQty(btn);
    btn.dataset.qty = String(qty);

    return addToCart(proId, btn, qty);
}

/* === Emballage === */
async function addEmballage(embId, btn){
    const id = Number(embId);
    if (!id) return;

    if (btn) btn.disabled = true;
    try {
        await callApi('add', { emb_id: id, qty: 1 });
        if (document.getElementById('cart-list')) await renderCart();

        const name = btn?.dataset?.embName || `Emballage #${id}`;
        showToast(`${name} a bien été ajouté au panier !`, 'success');

        if (btn){
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e){
        toastError(btn, `Emballage #${id}`, e);
        console.error(e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* === Supplément === */
async function addSupplement(supId, btn){
    const id = Number(supId);
    if (!id) return;

    // ← NOUVEAU : on lit la quantité comme sur fleurs/bouquets
    const qty = resolveQty(btn) || 1;

    if (btn) btn.disabled = true;
    try {
        await callApi('add', { sup_id: id, qty }); // ← qty dynamique
        if (document.getElementById('cart-list')) await renderCart();

        const name = btn?.dataset?.supName || `Supplément #${id}`;
        showToast(`${name} a bien été ajouté au panier !`, 'success');

        if (btn){
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e){
        toastError(btn, `Supplément #${id}`, e);
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
        if (itemType === 'produit')       params.pro_id = id;
        else if (itemType === 'emballage') params.emb_id = id;
        else if (itemType === 'supplement') params.sup_id = id;

        await callApi('remove', params);
        await renderCart();
    } catch (e) {
        console.error(e);
        alert("Impossible de supprimer l'article.");
    }
}

/* =============== 8) Toast Helper =============== */
function getToastRoot() {
    let root = document.getElementById('dkb-toasts');
    if (!root) {
        root = document.createElement('div');
        root.id = 'dkb-toasts';
        root.className = 'dkb-toasts';
        root.setAttribute('aria-live', 'polite');
        root.setAttribute('aria-atomic', 'true');
        document.body.appendChild(root);
    }
    return root;
}

function toastAdded(btn, fallback){
    const label = btn?.dataset?.proName
        || btn?.dataset?.embName
        || btn?.dataset?.suppName
        || fallback;
    showToast(`${label} a bien été ajouté au panier !`, 'success');
}
function toastError(btn, fallback, err){
    const label = btn?.dataset?.proName
        || btn?.dataset?.embName
        || btn?.dataset?.suppName
        || fallback;
    showToast(`Échec de l’ajout : ${label}`, 'error', 3600, 'Erreur');
    console.error(err);
}

/**
 * Affiche un toast.
 * @param {string} message
 * @param {'success'|'info'|'error'} [type='success']
 * @param {number} [timeout=2600]
 * @param {string} [title]
 */
function showToast(message, type = 'success', timeout = 2600, title){
    if(!document.getElementById('dkb-toast-css')){
        const s = document.createElement('style');
        s.id = 'dkb-toast-css';
        s.textContent = `
  .dkb-toasts{
    position:fixed; top:12px; left:50%; transform:translateX(-50%);
    display:flex; flex-direction:column; gap:10px; z-index:2147483647; pointer-events:none;
  }
  .dkb-toast{
    --t-border:#CFEAD8;
    display:flex; align-items:center; gap:10px;
    min-width:260px; max-width:min(92vw,520px);
    background:#fff !important; color:#111 !important;
    padding:12px 14px; border-radius:12px;
    border:2px solid var(--t-border);
    box-shadow:0 10px 25px rgba(0,0,0,.12);
    font:500 14px/1.3 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    opacity:0; transform:translateY(-10px);
    transition:opacity .2s ease, transform .2s ease; pointer-events:auto;
  }
  .dkb-toast.show{ opacity:1; transform:translateY(0); }
  .dkb-toast.success{ --t-border:#B9E4C8; }
  .dkb-toast.info   { --t-border:#C9DAFF; }
  .dkb-toast.error  { --t-border:#F6C6C6; }
  .dkb-toast::before{
    content:""; width:6px; align-self:stretch;
    background:var(--t-border); border-radius:8px 0 0 8px;
  }
  .dkb-toast .title{ font-weight:700; margin-right:4px; }
  .dkb-toast .dkb-close{
    all:unset; margin-left:auto; cursor:pointer; opacity:.6;
    font-size:18px; line-height:1; padding:2px 4px; color:#111;
  }
  .dkb-toast .dkb-close:hover{ opacity:1; }
`;
        document.head.appendChild(s);
    }

    const root = getToastRoot();
    const toast = document.createElement('div');
    toast.className = `dkb-toast ${type}`;
    toast.role = 'status';

    const content = document.createElement('div');
    if (title){
        const strong = document.createElement('span');
        strong.className = 'title';
        strong.textContent = title;
        content.appendChild(strong);
    }
    content.append(document.createTextNode(message));

    const closeBtn = document.createElement('button');
    closeBtn.className = 'dkb-close';
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label','Fermer');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => removeToast(toast));

    toast.append(content, closeBtn);
    root.prepend(toast);

    void toast.offsetHeight;
    toast.classList.add('show');
    setTimeout(()=>toast.classList.add('show'), 50);

    if (timeout > 0){
        toast._timer = setTimeout(()=>removeToast(toast), timeout);
        toast.addEventListener('mouseenter', ()=>clearTimeout(toast._timer));
        toast.addEventListener('mouseleave', ()=>{
            if (timeout > 0) toast._timer = setTimeout(()=>removeToast(toast), 900);
        });
    }
}
function removeToast(toast){
    if (!toast) return;
    toast.classList.remove('show');
    setTimeout(()=>toast.remove(), 200);
}

/* =============== 9) Expose global =============== */
window.renderCart     = renderCart;
window.addToCart      = addToCart;
window.selectRose     = selectRose;
window.addSupplement  = addSupplement;
window.addEmballage   = addEmballage;
window.removeFromCart = removeFromCart;
