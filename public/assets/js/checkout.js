const LS_KEY = 'dkb_cart_v1';
const API_CREATE_PI = '/api/create_payment_intent.php';
const RETURN_URL_SUCCESS = '/success.php';

let stripe, elements, clientSecret;

function loadCart(){
    try { return JSON.parse(localStorage.getItem(LS_KEY)) || []; }
    catch { return []; }
}

function renderSummary(lines, b){
    const box = document.getElementById('cart-lines');
    box.innerHTML = lines.map(l => `
    <div class="cart-line">
      <div class="name">${l.name} <span class="qty">x ${l.qty}</span></div>
      <div>${l.price.toFixed(2)} CHF</div>
    </div>`).join('');
    document.getElementById('sum-subtotal').textContent = b.subtotal.toFixed(2)+' CHF';
    document.getElementById('sum-shipping').textContent = b.shipping ? b.shipping.toFixed(2)+' CHF' : 'Offert';
    document.getElementById('sum-tva').textContent = b.tva.toFixed(2)+' CHF';
    document.getElementById('sum-total').textContent = b.total.toFixed(2)+' CHF';
}

async function init(){
    const cart = loadCart();
    if (!cart.length){
        document.getElementById('form-message').textContent = "Votre panier est vide.";
        document.getElementById('pay-btn').disabled = true;
        return;
    }

    const res = await fetch(API_CREATE_PI, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ items: cart.map(p => ({id:p.id, qty:p.qty})) })
    });
    const data = await res.json();
    if (data.error){ document.getElementById('form-message').textContent = data.error; return; }

    clientSecret = data.clientSecret;
    renderSummary(cart, data.breakdown);

    stripe = Stripe(data.publishableKey || window.__STRIPE_PK__);
    elements = stripe.elements({ clientSecret });
    elements.create("payment").mount("#payment-element");

    document.getElementById('checkout-form').addEventListener('submit', onPay);
}

async function onPay(e){
    e.preventDefault();
    document.getElementById('pay-btn').disabled = true;

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: RETURN_URL_SUCCESS }
    });

    if (error){
        document.getElementById('form-message').textContent = error.message || "Paiement refusé.";
        document.getElementById('pay-btn').disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', init);
