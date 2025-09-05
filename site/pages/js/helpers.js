/**
 * ==============================
 * helpers.js
 * Fonctions utilitaires globales
 * Projet DK Bloom (site e-commerce)
 * ==============================
 *
 * ⚠️ Inclure ce fichier AVANT commande.js, script.js, etc.
 * Exemple dans commande.php :
 *   <script src="/Projet_sur_Mandat/site/js/helpers.js?v=1" defer></script>
 *   <script src="/Projet_sur_Mandat/site/js/commande.js?v=3" defer></script>
 */

/**
 * Sélecteur sécurisé : retourne le premier élément qui correspond,
 * ou null si rien trouvé.
 *
 * Exemple :
 *   const btn = qs('#btn-checkout');
 */
function qs(selector, root = document) {
    return root.querySelector(selector);
}

/**
 * Ajoute un listener à un élément si celui-ci existe.
 * Retourne true si l’élément a été trouvé, sinon false.
 *
 * Exemple :
 *   on('.menu-toggle', 'click', () => { ... });
 */
function on(selector, event, handler, root = document) {
    const el = root.querySelector(selector);
    if (el) el.addEventListener(event, handler);
    return !!el;
}

/**
 * Ajoute un listener en délégation (écoute sur un parent,
 * réagit si un enfant correspond au sélecteur).
 *
 * Utile pour les boutons ajoutés dynamiquement (panier).
 *
 * Exemple :
 *   delegate(document, 'click', 'button.add-to-cart', (e, btn) => { ... });
 */
function delegate(root, event, selector, handler) {
    root.addEventListener(event, (e) => {
        const el = e.target.closest(selector);
        if (el) handler(e, el);
    });
}

/**
 * Formatte un nombre en CHF (2 décimales).
 *
 * Exemple :
 *   chf(45) → "45.00 CHF"
 */
function chf(n) {
    return `${Number(n).toFixed(2)} CHF`;
}

/**
 * Active ou désactive un bouton de manière accessible.
 *
 * Exemple :
 *   toggleBtn('#btn-checkout', true); // active
 *   toggleBtn('#btn-checkout', false); // désactive
 */
function toggleBtn(selector, enable = true) {
    const btn = qs(selector);
    if (!btn) return;
    btn.setAttribute('aria-disabled', enable ? 'false' : 'true');
    btn.style.pointerEvents = enable ? 'auto' : 'none';
    btn.style.opacity = enable ? '' : '0.6';
}

/**
 * Appel générique à l’API panier (cart.php).
 * Actions possibles : add, list, remove, clear, etc.
 *
 * Exemple :
 *   const data = await callApi('list');
 *   console.log(data.items);
 */
async function callApi(action, params = {}) {
    const API_URL = '/Projet_sur_Mandat/site/api/cart.php';
    const url = `${API_URL}?action=${encodeURIComponent(action)}`;
    const body = new URLSearchParams({ action, ...params });

    const res = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin'
    });

    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch {
        throw new Error(`Réponse non JSON (${res.status}): ${text}`);
    }

    if (!res.ok || data.ok === false) {
        throw new Error(data.error || data.msg || `HTTP ${res.status}`);
    }
    return data;
}
