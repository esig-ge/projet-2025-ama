// js/commande.js

const API_URL = '/Projet_sur_Mandat/site/api/cart.php';

// helper API commun
async function callApi(action, params = {}) {
    const form = new URLSearchParams({ action, ...params });
    const url  = `${API_URL}?action=${encodeURIComponent(action)}`;

    const res  = await fetch(url, {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
    });

    const text = await res.text();
    console.log(`[API ${action}] ${res.status} ${url}\n↳`, text); // <-- LOG

    let data;
    try { data = JSON.parse(text); }
    catch { throw new Error(`Réponse non JSON (HTTP ${res.status})`); }

    if (!res.ok || data.ok === false) {
        // remonte le message côté PHP (product_not_found, auth_required, server_error, etc.)
        throw new Error(data.error || data.msg || `HTTP ${res.status}`);
    }
    return data;
}

// binder (seulement le catch changé)
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.add-to-cart');
    if (!btn) return;

    const proId = btn.dataset.proId;
    btn.disabled = true;
    try {
        await callApi('add', { pro_id: proId, qty: 1 });
        btn.textContent = 'Ajouté ✓';
        setTimeout(() => { btn.textContent = 'Ajouter'; btn.disabled = false; }, 900);
    } catch (err) {
        alert("Impossible d'ajouter au panier.\n" + (err?.message || ''));
        console.error(err);
        btn.disabled = false;
    }
});

// ——— Rendu du panier sur la page commande ———
function chf(n){ return `${Number(n).toFixed(2)} CHF`; }

async function renderCart() {
    const wrap = document.getElementById('cart-list');
    if (!wrap) return;
    wrap.innerHTML = 'Chargement…';

    try {
        const data  = await callApi('list');
        console.log('LIST items →', data.items);
        const items = Array.isArray(data.items) ? data.items : [];

        if (items.length === 0) {
            wrap.innerHTML = `<p>Votre panier est vide.</p>`;
            updateSummary(0);
            return;
        }

        wrap.innerHTML = items.map(it => {
            console.log('row →', it);
            const nom   = it.nom ?? it.PRO_NOM ?? it.name ?? `#${it.id ?? it.PRO_ID ?? ''}`;
            const qte   = Math.max(1, Number(it.qte ?? it.CP_QTE_COMMANDEE ?? it.qty ?? 1));
            const prix  = Number(it.prix_unitaire ?? it.PRO_PRIX ?? it.price ?? 0);
            const total = prix * qte;
            const img   = it.image || it.PRO_IMG || 'img/placeholder.png';

            return `
        <div class="cart-row">
          <img src="${img}" alt="${nom}" class="cart-img">
          <div class="cart-name">${nom}</div>
          <div class="cart-qty">x&nbsp;${qte}</div>
          <div class="cart-unit">${chf(prix)}</div>
          <div class="cart-total">${chf(total)}</div>
        </div>
      `;
        }).join('');

        const subtotal = items.reduce((s, it) => {
            const p = Number(it.prix_unitaire ?? it.PRO_PRIX ?? it.price ?? 0);
            const q = Math.max(1, Number(it.qte ?? it.CP_QTE_COMMANDEE ?? it.qty ?? 1));
            return s + p*q;
        }, 0);
        updateSummary(subtotal);

    } catch (err) {
        console.error(err);
        wrap.innerHTML = `<p>Erreur lors du chargement du panier.</p>`;
    }
}



function updateSummary(subtotal) {
    const el = document.getElementById('sum-subtotal');
    if (el) el.textContent = `${subtotal.toFixed(2)} CHF`;
}

// Auto-rendu si on est sur la page commande
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('cart-list')) renderCart();
});
