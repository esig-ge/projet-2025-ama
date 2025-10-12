/* ============================================================
   1) Bases de chemins
   ------------------------------------------------------------
   Objectif : déterminer des "bases" d’URL robustes pour
   - la page courante (PAGE_BASE),
   - le site (SITE_BASE),
   - l’API du panier (API_URL),
   - la base des assets (ASSET_BASE).
   Ces valeurs servent à construire des liens absolus/relatifs
   stables, quel que soit l’endroit où la page est rendue.
   ============================================================ */

/* PAGE_BASE :
   - Priorité à window.DKBASE si défini côté HTML (ex: dans <script>).
   - Sinon, on dérive depuis location.pathname en supprimant la
     dernière partie non-slash (nom de fichier ou segment final).
   - Exemple : "/site/pages/produit.php" -> "/site/pages/" */
const PAGE_BASE = (typeof window.DKBASE === 'string' && window.DKBASE.length)
    ? window.DKBASE
    : location.pathname.replace(/[^/]+$/, '');

/* SITE_BASE :
   - Si la page est sous "/pages/", on remonte d’un cran pour
     obtenir la racine du site (répertoire parent avant "pages/").
   - Sinon, on retient PAGE_BASE tel quel.
   - Exemple : "/site/pages/" -> "/site/" */
const SITE_BASE = (() => {
    const m = PAGE_BASE.match(/^(.*\/)pages\/$/);
    return m ? m[1] : PAGE_BASE;
})();

/* API_URL :
   - Priorité à window.API_URL si défini.
   - Sinon, fallback vers un endpoint par défaut : "api/cart.php"
     à la racine du site (SITE_BASE).
   - Centralise le point d’entrée des opérations panier. */
const API_URL = (typeof window.API_URL === 'string' && window.API_URL.length)
    ? window.API_URL
    : SITE_BASE + 'api/cart.php';

/* Traces de debug : permettent de contrôler les bases calculées.
   À désactiver en prod si nécessaire. */
console.debug('[DKBloom] PAGE_BASE =', PAGE_BASE);
console.debug('[DKBloom] SITE_BASE =', SITE_BASE);
console.debug('[DKBloom] API_URL   =', API_URL);

/* ASSET_BASE :
   - Base à utiliser pour les médias statiques (images, etc.).
   - Ici égale à PAGE_BASE par cohérence avec l’arborescence
     des pages. */
const ASSET_BASE = PAGE_BASE;

/* ============================================================
   2) Helpers (fonctions utilitaires, sans effet de bord)
   ============================================================ */

/* chf(n) :
   - Formate un nombre en "12.34 CHF" avec 2 décimales.
   - Force Number(n) pour éviter les NaN stringifiés. */
const chf = n => `${Number(n).toFixed(2)} CHF`;

/* normImgPath(p) :
   - Normalise un chemin d’image en URL exploitable par <img>.
   - Cas gérés :
     1) p falsy -> placeholder par défaut.
     2) p déjà absolu (http(s)://), racine ("/...") ou data: URI -> inchangé.
     3) p commençant par "img/" -> relatif à PAGE_BASE (ex: "/site/pages/img/...").
     4) p commençant par "pages/" -> relatif à SITE_BASE (ex: "/site/pages/...").
     5) sinon -> relatif à SITE_BASE (fallback).
   - Évite les 404 quand le code est déplacé/changé de dossier. */
function normImgPath(p) {
    if (!p) return SITE_BASE + 'img/placeholder.png';
    if (/^(https?:)?\/\//.test(p) || p.startsWith('/') || p.startsWith('data:')) return p;
    if (p.startsWith('img/'))   return PAGE_BASE + p;   // "/site/pages/img/..."
    if (p.startsWith('pages/')) return SITE_BASE + p;   // "/site/pages/..."
    return SITE_BASE + p;
}

/* resolveQty(btn, optsOrQty) :
   - Récupère une quantité entière >= 1 à partir de plusieurs sources
     par ordre de priorité :
     a) si optsOrQty est un nombre -> le normalise (>=1).
     b) si optsOrQty est un objet -> lit optsOrQty.qty.
     c) attribut data-qty sur le bouton (getAttribute + dataset).
     d) champ .qty dans un form/containeur parent proche.
     e) fallback = 1.
   - Rend robuste l’ajout au panier quelle que soit l’UI. */
/** Récupère une quantité valide (>=1 entier) depuis toutes les sources possibles. */
function resolveQty(btn, optsOrQty) {
    if (typeof optsOrQty === 'number') {
        const n = Math.max(1, optsOrQty | 0);
        if (n) return n;
    }
    if (optsOrQty && typeof optsOrQty === 'object') {
        const q = parseInt(optsOrQty.qty, 10);
        if (Number.isFinite(q) && q > 0) return q;
    }
    const fromAttr = parseInt(btn?.getAttribute?.('data-qty'), 10);
    if (Number.isFinite(fromAttr) && fromAttr > 0) return fromAttr;

    const fromDataset = parseInt(btn?.dataset?.qty, 10);
    if (Number.isFinite(fromDataset) && fromDataset > 0) return fromDataset;

    const form = btn?.closest?.('form, .product, .card, .produit-info');
    const input = form?.querySelector?.('.qty');
    const fromInput = parseInt(input?.value, 10);
    if (Number.isFinite(fromInput) && fromInput > 0) return fromInput;

    return 1;
}

/* resolveColor(btn, optsOrQty) :
   - Récupère une couleur éventuelle pour les produits variables.
   - Priorité :
     a) optsOrQty.color si présent,
     b) input radio commençant par "couleur_" coché,
     c) input radio "couleur" coché,
     d) null si rien trouvé.
   - Ne force pas l’existence d’une couleur : l’API peut ne pas
     en avoir besoin selon le type de produit. */
/** Récupère une couleur (si utilisée). */
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

/* ============================================================
   3) Appels API (fetch, gestion des erreurs, JSON)
   ------------------------------------------------------------
   Convention :
   - action passée en query string ET dans le body (x-www-form-urlencoded),
     ce qui permet un debug plus simple côté serveur.
   - credentials:same-origin -> cookies/session envoyés.
   - 401 -> redirection vers la page de connexion.
   - Réponse :
     - JSON attendu { ok:true, ... } ou { ok:false, msg:... }.
     - En cas de non-JSON, on logge la réponse brute pour diagnostic.
   ============================================================ */
async function callApi(action, params = {}) {
    const url  = `${API_URL}?action=${encodeURIComponent(action)}`;
    const body = new URLSearchParams({ action, ...params });

    const res = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
    });

    // Si session expirée ou accès refusé, on renvoie vers connexion
    if (res.status === 401) {
        window.location.href = SITE_BASE + 'pages/interface_connexion.php';
        return;
    }

    // Lecture brute du texte puis tentative de parse JSON pour
    // offrir un message d’erreur utile si la réponse n’est pas JSON.
    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch {
        console.error('Réponse brute:', text);
        throw new Error(`Réponse non JSON (HTTP ${res.status}) depuis ${url}`);
    }

    // Normalisation des erreurs : si HTTP !ok ou ok:false, lever une exception
    if (!res.ok || data.ok === false) {
        const msg = data?.msg || data?.error || `HTTP ${res.status}`;
        throw new Error(`API "${action}" a échoué: ${msg} (${res.status})`);
    }
    return data;
}

/* ============================================================
   4) UI : résumé (totaux / état du bouton checkout)
   ------------------------------------------------------------
   - Met à jour le sous-total et le total affichés (même valeur
     ici, TVA non gérée dans ce snippet).
   - Désactive/active visuellement le bouton de checkout selon
     que le panier est vide ou non.
   ============================================================ */
function updateSummary(subtotal) {
    const el  = document.getElementById('sum-subtotal');
    const tot = document.getElementById('sum-total');
    if (el)  el.textContent  = chf(subtotal);
    if (tot) tot.textContent = chf(subtotal);

    // Mise en forme accessibilité et UX du bouton de paiement
    const btn = document.getElementById('btn-checkout');
    if (btn) {
        const enabled = subtotal > 0;
        btn.toggleAttribute('tabindex', !enabled);
        btn.style.pointerEvents = enabled ? '' : 'none';
        btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        btn.style.opacity = enabled ? '' : '0.6';
    }
}

/* ============================================================
   5) Rendu du panier (lecture via API + DOM)
   ------------------------------------------------------------
   - Récupère la liste des items via callApi('list').
   - Gère le cas vide, calcule le sous-total, produit le HTML
     de chaque ligne (nom, quantité, prix unitaire, total, image).
   - Bouton de suppression par item, en s’appuyant sur data-attrs.
   ============================================================ */
async function renderCart() {
    const wrap = document.getElementById('cart-list');
    if (!wrap) return;

    wrap.innerHTML = 'Chargement…';
    try {
        const resp  = await callApi('list');
        const items = resp.items || [];

        // Panier vide : message + désactivation du checkout
        if (!items.length) {
            wrap.innerHTML = '<p>Votre panier est vide.</p>';
            updateSummary(0);
            return;
        }

        // Construction du rendu de chaque ligne du panier.
        // Les champs sont multi-sources pour tolérer des schémas d’API/BDD variés.
        wrap.innerHTML = items.map(it => {
            const nom   = it.PRO_NOM ?? it.nom ?? it.name ?? `#${it.PRO_ID ?? it.id ?? ''}`;
            const qte   = Math.max(1, Number(it.CP_QTE_COMMANDEE ?? it.qte ?? it.qty ?? 1));
            const prix  = Number(it.PRO_PRIX ?? it.prix_unitaire ?? it.price ?? 0);
            const total = prix * qte;
            const img   = normImgPath(it.PRO_IMG || it.image || 'img/placeholder.png');
            const type  = it.item_type || 'produit'; // "produit" | "emballage" | "supplement"
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

        // Sous-total : priorité à resp.subtotal si fourni par l’API, sinon recalcul local.
        const subtotal = typeof resp.subtotal === 'number'
            ? resp.subtotal
            : items.reduce((s, it) => {
                const p = Number(it.PRO_PRIX ?? it.prix_unitaire ?? it.price ?? 0);
                const q = Math.max(1, Number(it.CP_QTE_COMMANDEE ?? it.qte ?? it.qty ?? 1));
                return s + p * q;
            }, 0);

        updateSummary(subtotal);
    } catch (e) {
        // Gestion d’erreur générique avec message visible et réinitialisation du résumé
        console.error(e);
        const msg = (e && e.message) ? e.message : 'Erreur inconnue';
        wrap.innerHTML = `<p>Erreur lors du chargement du panier.<br><small>${msg}</small></p>`;
        updateSummary(0);
    }
}

/* ============================================================
   6) Ajouter au panier (produits, emballages, suppléments)
   ------------------------------------------------------------
   - addToCart(proId, btn, thirdArg)
     * thirdArg : nombre (qty) OU objet { qty, color, ... }.
   - Sélection rose : helpers dédiés pour un UX spécifique.
   - addEmballage / addSupplement : variantes d’ajout par type.
   - Chaque ajout tente un re-render du panier si présent dans le DOM.
   - Toasts de feedback + petit changement de label du bouton.
   ============================================================ */

/**
 * addToCart(proId, btn, thirdArg)
 * thirdArg peut être un nombre (qty) ou un objet { qty, color, ... }.
 */
async function addToCart(proId, btn, thirdArg) {
    const pid = Number(proId);
    if (!pid) {
        console.debug('[DKBloom:addToCart] proId invalide:', proId);
        return;
    }

    // Détermination quantité/couleur via les resolvers
    const qty   = resolveQty(btn, thirdArg);
    const color = resolveColor(btn, thirdArg); // transmis si l’API le supporte

    // Payload minimal ; champ optionnel "color" si présent
    const payload = { pro_id: pid, qty };
    if (color) payload.color = color;

    // Désactivation optimiste du bouton pour éviter les doublons
    if (btn) btn.disabled = true;
    try {
        await callApi('add', payload);

        // Si la zone du panier existe sur la page, re-render pour feedback immédiat
        if (document.getElementById('cart-list')) await renderCart();

        // Toast + micro feedback textuel du bouton
        toastAdded(btn, `Produit #${pid}`);
        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e) {
        // Gestion d’erreur avec toast explicite
        console.error('API add error', e);
        toastError(btn, `Produit #${pid}`, e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* === Sélection de rose (fleurs) : workflow spécifique
   - selectedRoseRadio() : radio cochée pour une palette de couleurs.
   - selectRose(btn) : ajoute le produit lié à la radio sélectionnée
     avec la quantité détectée. */
function selectedRoseRadio() {
    return document.querySelector('input[name="rose-color"]:checked');
}

async function selectRose(btn) {
    const r = selectedRoseRadio();
    if (!r) {
        alert('Choisis une couleur de rose.');
        return;
    }
    const proId = r.dataset.proId;

    // On fixe la quantité choisie dans data-qty du bouton (traçabilité/UI)
    const qty = resolveQty(btn);
    btn.dataset.qty = String(qty);

    return addToCart(proId, btn, qty);
}

/* === Emballage : appel dédié à l’API "add_emballage"
   - Quantité fixée à 1 par défaut (ajout unitaire). */
async function addEmballage(embId, btn) {
    const id = Number(embId);
    if (!id) return;

    if (btn) btn.disabled = true;
    try {
        await callApi('add_emballage', { emb_id: id, qty: 1 });
        if (document.getElementById('cart-list')) await renderCart();

        const name = btn?.dataset?.embName || `Emballage #${id}`;
        showToast(`${name} a bien été ajouté au panier !`, 'success');

        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e) {
        toastError(btn, `Emballage #${id}`, e);
        console.error(e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* === Supplément : similaire à "emballage" mais avec qty variable
   - resolveQty pour autoriser l’utilisateur à choisir la quantité. */
async function addSupplement(supId, btn) {
    const id = Number(supId);
    if (!id) return;

    const qty = resolveQty(btn) || 1;

    if (btn) btn.disabled = true;
    try {
        await callApi('add_supplement', { sup_id: id, qty });
        if (document.getElementById('cart-list')) await renderCart();

        const name = btn?.dataset?.supName || `Supplément #${id}`;
        showToast(`${name} a bien été ajouté au panier !`, 'success');

        if (btn) {
            const old = btn.textContent;
            btn.textContent = 'Ajouté ✓';
            setTimeout(() => { btn.textContent = old || 'Ajouter'; }, 900);
        }
    } catch (e) {
        toastError(btn, `Supplément #${id}`, e);
        console.error(e);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* ============================================================
   7) Supprimer du panier
   ------------------------------------------------------------
   - Route générique "remove" avec un paramètre variant selon
     le type : pro_id / emb_id / sup_id.
   - Re-rendu systématique du panier après suppression.
   ============================================================ */
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

/* ============================================================
   8) Toast Helper (notifications non bloquantes)
   ------------------------------------------------------------
   - Système de toasts minimaliste, injecté dynamiquement :
     * insertion du conteneur #dkb-toasts si absent,
     * insertion du <style> dédié au premier appel à showToast,
     * support des types : success | info | error,
     * fermeture manuelle (×) et auto-hide avec pause au survol,
     * ARIA live region pour lecteurs d’écran.
   - toastAdded/toastError : helpers de message contextuels.
   ============================================================ */

// Garantit l’existence d’un root pour les toasts
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

// Message standard d’ajout réussi (label déduit du bouton si possible)
function toastAdded(btn, fallback) {
    const label = btn?.dataset?.proName
        || btn?.dataset?.embName
        || btn?.dataset?.suppName
        || fallback;
    showToast(`${label} a bien été ajouté au panier !`, 'success');
}

// Message standard d’erreur (texte d’exception inclus si dispo)
function toastError(btn, fallback, err) {
    const label = btn?.dataset?.proName
        || btn?.dataset?.embName
        || btn?.dataset?.suppName
        || fallback;
    const msg = (err && err.message) ? String(err.message) : 'Erreur inconnue';
    showToast(`Échec de l’ajout : ${label} — ${msg}`, 'error', 3600, 'Erreur');
    console.error(err);
}

/**
 * Affiche un toast.
 * @param {string} message  - contenu textuel du toast
 * @param {'success'|'info'|'error'} [type='success'] - style visuel
 * @param {number} [timeout=2600] - durée avant auto-hide (ms); 0 = persistant
 * @param {string} [title] - titre optionnel en gras
 */
function showToast(message, type = 'success', timeout = 2600, title) {
    // Injection du CSS une seule fois, identifiée par #dkb-toast-css
    if (!document.getElementById('dkb-toast-css')) {
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

    // Création du toast et de son contenu
    const root  = getToastRoot();
    const toast = document.createElement('div');
    toast.className = `dkb-toast ${type}`;
    toast.role = 'status'; // annonce non bloquante aux AT

    const content = document.createElement('div');
    if (title) {
        const strong = document.createElement('span');
        strong.className = 'title';
        strong.textContent = title;
        content.appendChild(strong);
    }
    content.append(document.createTextNode(message));

    // Bouton de fermeture (×)
    const closeBtn = document.createElement('button');
    closeBtn.className = 'dkb-close';
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Fermer');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => removeToast(toast));

    toast.append(content, closeBtn);
    root.prepend(toast);

    // Forcer un reflow pour déclencher la transition CSS
    void toast.offsetHeight;
    toast.classList.add('show');

    // Auto-hide avec gestion du survol (pause/reprise)
    if (timeout > 0) {
        toast._timer = setTimeout(() => removeToast(toast), timeout);
        toast.addEventListener('mouseenter', () => clearTimeout(toast._timer));
        toast.addEventListener('mouseleave', () => {
            if (timeout > 0) toast._timer = setTimeout(() => removeToast(toast), 900);
        });
    }
}

// Retire un toast avec petite animation (opacity/translate gérée via .show)
function removeToast(toast) {
    if (!toast) return;
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 200);
}

/* ============================================================
   9) Expose global (API JS pour lier HTML ↔ JS)
   ------------------------------------------------------------
   - Attache les fonctions au scope global (window) pour permettre
     l’appel depuis des attributs inline (onclick, etc.) ou depuis
     d’autres scripts sans import/bundler.
   - Permet l’intégration progressive dans des pages existantes.
   ============================================================ */
window.renderCart     = renderCart;
window.addToCart      = addToCart;
window.selectRose     = selectRose;
window.addSupplement  = addSupplement;
window.addEmballage   = addEmballage;
window.removeFromCart = removeFromCart;
