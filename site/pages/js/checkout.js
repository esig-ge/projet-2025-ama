// js/checkout.js — version sans addEventListener, avec rendu "renderCart-like"

/* =======================
   0) Constantes / URLs
   ======================= */
const API_CREATE_PI     = '/api/create_payment_intent.php'; // adapte si besoin
const RETURN_URL_SUCCESS = '/success.php';

// Si tu exposes DKBASE & API_URL dans la page (comme ailleurs), on les réutilise :
const PAGE_BASE = (typeof window.DKBASE === 'string' && window.DKBASE) ? window.DKBASE : location.pathname.replace(/[^/]+$/, '');
const API_URL   = (typeof window.API_URL  === 'string' && window.API_URL)  ? window.API_URL  : (PAGE_BASE + 'api/cart.php');

/* =======================
   1) Helpers généraux
   ======================= */
const chf = n => `${Number(n || 0).toFixed(2)} CHF`;

function normImgPath(p) {
    if (!p) return PAGE_BASE + 'img/placeholder.png';
    if (/^(https?:)?\/\//.test(p) || p.startsWith('/') || p.startsWith('data:')) return p;
    if (p.startsWith('img/')) return PAGE_BASE + p;
    if (p.startsWith('pages/')) return PAGE_BASE.replace(/pages\/$/, '') + p;
    return PAGE_BASE.replace(/pages\/$/, '') + p;
}

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
    catch { throw new Error(`Réponse non JSON (HTTP ${res.status})`); }

    if (!res.ok || data.ok === false) {
        throw new Error(data?.error || data?.msg || `HTTP ${res.status}`);
    }
    return data;
}

/* =======================
   2) Rendu du récap panier
   ======================= */
async function renderCheckout() {
    const box = document.getElementById('cart-lines');
    if (!box) return;

    box.innerHTML = 'Chargement…';
    try {
        const { items = [], subtotal = 0 } = await callApi('list');

        if (!items.length) {
            box.innerHTML = '<p>Votre panier est vide.</p>';
            updateSummary(0, 0, 0, 0);
            // Désactive le bouton payer s’il existe
            const pay = document.getElementById('pay-btn');
            if (pay) pay.disabled = true;
            return [];
        }

        box.innerHTML = items.map(it => {
            const nom   = it.PRO_NOM ?? it.nom ?? `#${it.id}`;
            const qte   = Number(it.CP_QTE_COMMANDEE ?? it.qte ?? 1);
            const prix  = Number(it.PRO_PRIX ?? it.prix_unitaire ?? 0);
            const total = prix * qte;
            const img   = normImgPath(it.PRO_IMG || it.image || 'img/placeholder.png');

            // data-* pour la suppression sans JSON.stringify dans l’HTML
            const type  = it.item_type || 'produit'; // 'produit' | 'emballage' | 'supplement'

            return `
        <div class="cart-row">
          <img src="${img}" alt="${nom}" class="cart-img">
          <div class="cart-name">${nom}</div>
          <div class="cart-qty">x&nbsp;${qte}</div>
          <div class="cart-unit">${chf(prix)}</div>
          <div class="cart-total">${chf(total)}</div>
          <button class="cart-remove"
                  title="Supprimer"
                  data-type="${type}" data-id="${it.id}"
                  onclick="removeFromCart(this.dataset.type, this.dataset.id)">🗑️</button>
        </div>`;
        }).join('');

        // Ici, tu peux calculer TVA / livraison si tu veux.
        // Exemple simple: livraison Offert, TVA 0.
        const shipping = 0;
        const tva      = 0;
        const total    = subtotal + shipping + tva;
        updateSummary(subtotal, shipping, tva, total);

        return items;
    } catch (e) {
        console.error(e);
        box.innerHTML = `<p>Erreur lors du chargement du panier.<br><small>${e.message || e}</small></p>`;
        updateSummary(0,0,0,0);
        return [];
    }
}

function updateSummary(subtotal, shipping, tva, total){
    const elSubtotal = document.getElementById('sum-subtotal');
    const elShip     = document.getElementById('sum-shipping');
    const elTva      = document.getElementById('sum-tva');
    const elTotal    = document.getElementById('sum-total');

    if (elSubtotal) elSubtotal.textContent = chf(subtotal);
    if (elShip)     elShip.textContent     = shipping ? chf(shipping) : 'Offert';
    if (elTva)      elTva.textContent      = chf(tva);
    if (elTotal)    elTotal.textContent    = chf(total);

    const btn = document.getElementById('pay-btn');
    if (btn) btn.disabled = (total <= 0);
}

/* =======================
   3) Suppression d’un article
   ======================= */
async function removeFromCart(itemType, id){
    try {
        id = Number(id);
        if (!id) return;

        const params = {};
        if (itemType === 'produit')    params.pro_id = id;
        else if (itemType === 'emballage') params.emb_id = id;
        else if (itemType === 'supplement') params.sup_id = id;

        await callApi('remove', params);
        await renderCheckout();
    } catch (e) {
        console.error(e);
        alert("Impossible de supprimer l'article.");
    }
}
window.removeFromCart = removeFromCart;

/* =======================
   4) Stripe Checkout
   ======================= */
let stripe, elements, clientSecret;

async function initCheckout(){
    // 1) Rendre le récap depuis l’API (source de vérité)
    const items = await renderCheckout();
    if (!items.length) {
        const msg = document.getElementById('form-message');
        if (msg) msg.textContent = "Votre panier est vide.";
        const pay = document.getElementById('pay-btn');
        if (pay) pay.disabled = true;
        return;
    }

    // 2) Construire un payload minimal pour créer le Payment Intent (id + qty)
    const payload = {
        items: items.map(it => ({
            id: Number(it.id),
            qty: Number(it.CP_QTE_COMMANDEE ?? it.qte ?? 1)
        }))
    };

    // 3) Appeler ton endpoint serveur
    const res  = await fetch(API_CREATE_PI, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.error){
        const msg = document.getElementById('form-message');
        if (msg) msg.textContent = data.error;
        const pay = document.getElementById('pay-btn');
        if (pay) pay.disabled = true;
        return;
    }

    clientSecret = data.clientSecret;

    // 4) Init Stripe Elements
    stripe   = Stripe(data.publishableKey || window.__STRIPE_PK__);
    elements = stripe.elements({ clientSecret });

    const mountPoint = document.getElementById('payment-element');
    if (mountPoint) {
        elements.create("payment").mount("#payment-element");
    }
}

async function onPay(form) {
    // appelée depuis onsubmit="return onPay(this)"
    const payBtn = document.getElementById('pay-btn');
    if (payBtn) payBtn.disabled = true;

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: RETURN_URL_SUCCESS }
    });

    if (error) {
        const msg = document.getElementById('form-message');
        if (msg) msg.textContent = error.message || "Paiement refusé.";
        if (payBtn) payBtn.disabled = false;
        return false; // empêche le submit natif
    }
    return true; // laisse Stripe rediriger
}

// Expose pour l’appel inline onsubmit
window.initCheckout = initCheckout;
window.onPay        = onPay;
