/* assets/js/cart.js */

const LS_KEY = 'dkb_cart_v1';

function loadCart(){
    try { return JSON.parse(localStorage.getItem(LS_KEY)) || []; }
    catch { return []; }
}
function saveCart(cart){ localStorage.setItem(LS_KEY, JSON.stringify(cart)); }
function money(n){ return `${n.toFixed(2)} CHF`; }

function renderCart(){
    const $list = document.getElementById('cart-list');
    const cart = loadCart();

    if (!cart.length){
        $list.innerHTML = `
      <div class="cart-item" style="grid-template-columns:1fr;">
        Votre panier est vide. <a href="catalogue.php" class="link" style="margin-left:8px">Voir le catalogue</a>
      </div>`;
        document.getElementById('sum-subtotal').textContent = money(0);
        document.getElementById('sum-shipping').textContent = '—';
        document.getElementById('sum-total').textContent = money(0);
        document.getElementById('btn-checkout').classList.add('disabled');
        return;
    }

    // Construit les lignes
    $list.innerHTML = cart.map(item => `
    <div class="cart-item" data-id="${item.id}">
      <img class="item-img" src="${item.img}" alt="${item.name}">
      <div>
        <div class="item-title">${item.name}</div>
        <div class="item-sub">Ref. ${item.sku || item.id}</div>
      </div>
      <div class="price">${money(item.price)}</div>
      <div class="qty">
        <button class="btn-dec" aria-label="Diminuer">−</button>
        <input class="qty-input" type="text" value="${item.qty}" inputmode="numeric">
        <button class="btn-inc" aria-label="Augmenter">+</button>
      </div>
      <div class="line-total">${money(item.price * item.qty)}</div>
      <button class="remove" title="Supprimer">✕</button>
    </div>
  `).join('');

    attachRowEvents();
    updateTotals();
}

function attachRowEvents(){
    const $rows = document.querySelectorAll('.cart-item');
    $rows.forEach(row => {
        const id = row.dataset.id;
        row.querySelector('.btn-inc').addEventListener('click', () => changeQty(id, +1));
        row.querySelector('.btn-dec').addEventListener('click', () => changeQty(id, -1));
        row.querySelector('.qty-input').addEventListener('change', e => setQty(id, +e.target.value || 1));
        row.querySelector('.remove').addEventListener('click', () => removeItem(id));
    });
}

function changeQty(id, delta){
    const cart = loadCart();
    const it = cart.find(p => p.id === id);
    if (!it) return;
    it.qty = Math.max(1, (it.qty || 1) + delta);
    saveCart(cart);
    renderCart();
}
function setQty(id, value){
    const cart = loadCart();
    const it = cart.find(p => p.id === id);
    if (!it) return;
    it.qty = Math.max(1, parseInt(value,10) || 1);
    saveCart(cart);
    renderCart();
}
function removeItem(id){
    let cart = loadCart();
    cart = cart.filter(p => p.id !== id);
    saveCart(cart);
    renderCart();
}

function updateTotals(){
    const cart = loadCart();
    const subtotal = cart.reduce((s,p)=> s + p.price * p.qty, 0);
    const shipping = subtotal >= 80 ? 0 : (cart.length ? 7 : 0);
    const total = subtotal + shipping;

    document.getElementById('sum-subtotal').textContent = money(subtotal);
    document.getElementById('sum-shipping').textContent = shipping ? money(shipping) : 'Offert';
    document.getElementById('sum-total').textContent = money(total);
}

// ----- Hook pour la page catalogue (boutons "ajouter") -----
// Utilise des boutons avec .add-to-cart et data- attributs
function addToCart(product){
    const cart = loadCart();
    const found = cart.find(p => p.id === product.id);
    if (found){ found.qty += product.qty || 1; }
    else { cart.push({...product, qty: product.qty || 1}); }
    saveCart(cart);
}

// Si on est sur une page avec des boutons .add-to-cart :
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.add-to-cart');
    if (!btn) return;
    e.preventDefault();
    addToCart({
        id: btn.dataset.id,
        sku: btn.dataset.sku || btn.dataset.id,
        name: btn.dataset.name,
        price: parseFloat(btn.dataset.price),
        img: btn.dataset.img
    });
});

// Rendu initial si on est sur le panier
document.addEventListener('DOMContentLoaded', renderCart);
