/*
// site/pages/js/checkout.js
let stripe, elements, paymentElement;

async function fetchJSON(url, opts = {}) {
    const res = await fetch(url, opts);
    const txt = await res.text();
    let data;
    try { data = JSON.parse(txt); } catch { throw new Error(`Réponse non JSON (${res.status})`); }
    if (!res.ok || data.ok === false) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
}

async function getCartTotal() {
    // Si tu as un endpoint serveur:
    // const data = await fetchJSON(`${window.API_URL}?action=list`, { credentials: 'same-origin' });
    // return Number(data.total_chf || 0);

    // Sinon: lis le localStorage (temporaire, côté client)
    const cart = JSON.parse(localStorage.getItem('dkb_cart_v1') || '[]');
    const total = cart.reduce((sum, it) => sum + (Number(it.price) * Number(it.qty)), 0);
    return Number(total.toFixed(2));
}

export async function initCheckout() {
    const msg = (t) => { const el = document.getElementById('form-message'); if (el) el.textContent = t || ''; };
    msg('Initialisation du paiement…');

    try {
        // 1) Total panier
        const total = await getCartTotal();
        document.getElementById('sum-total')?.innerText = `${total.toFixed(2)} CHF`;
        // TODO: mets à jour le récap (lignes, TVA, etc.)

        if (total <= 0) {
            msg('Votre panier est vide.');
            return;
        }

        // 2) Crée le PaymentIntent côté serveur
        const data = await fetchJSON('/site/api/create-intent.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ amount: String(total) }),
            credentials: 'same-origin',
        });

        // 3) Stripe.js
        stripe = Stripe(data.publishableKey);
        elements = stripe.elements({ clientSecret: data.clientSecret });

        // 4) Monte le Payment Element
        paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        // 5) Activer le bouton
        document.getElementById('pay-btn')?.removeAttribute('disabled');
        msg('');

    } catch (err) {
        console.error(err);
        msg('Erreur d’initialisation: ' + err.message);
    }
}

export async function onPay(form) {
    form.querySelector('#pay-btn')?.setAttribute('disabled', 'disabled');

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            // URLs absolues en prod:
            return_url: window.location.origin + '/site/pages/success.php',
        },
    });

    if (error) {
        document.getElementById('pay-btn')?.removeAttribute('disabled');
        const msg = document.getElementById('form-message');
        if (msg) msg.textContent = error.message || 'Le paiement a échoué.';
    }
    return false; // empêcher submit normal
}

// expose pour l’inline onload/onsubmit
window.initCheckout = initCheckout;
window.onPay = onPay;
*/
