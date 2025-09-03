// site/pages/js/commande.js
(function () {
    // --- Rendu du résumé : rendre global pour les scripts inline ---
    window.updateSummaryUI = function(items) {
        const elSub = document.getElementById('sum-subtotal');
        const elShip = document.getElementById('sum-shipping');
        const elTot = document.getElementById('sum-total');
        const btn = document.getElementById('btn-checkout');
        if (!elSub || !elTot) return;

        const subtotal = (items || []).reduce((acc, it) => {
            const prix = Number(it.PRO_PRIX ?? 0);
            const qte  = Number(it.CP_QTE_COMMANDEE ?? 0);
            return acc + prix * qte;
        }, 0);

        const fmt = (n) => `${n.toFixed(2)} CHF`;
        elSub.textContent = fmt(subtotal);
        if (elShip) elShip.textContent = '—'; // pas de frais pour l'instant
        elTot.textContent = fmt(subtotal);

        if (btn) {
            if (subtotal > 0) {
                btn.removeAttribute('aria-disabled');
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '';
            } else {
                btn.setAttribute('aria-disabled', 'true');
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.6';
            }
        }
    };

    // --- Appel API : ajout d’un produit en BDD ---
    async function callAdd(proId, qty = 1) {
        const form = new FormData();
        form.append('action', 'add');
        form.append('pro_id', String(proId));
        form.append('qty', String(qty));

        const res = await fetch('../api/cart.php?action=add', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        });
        return res.json();
    }

    // --- Binder des boutons ---
    function wire(el, redirectAfter = false) {
        el.addEventListener('click', async (e) => {
            e.preventDefault();
            const proId = Number(el.dataset.id || el.dataset.proId);
            const qty   = Number(el.dataset.qty || 1);
            if (!proId) { alert('Produit invalide: data-id manquant ou non numérique'); return; }

            try {
                const data = await callAdd(proId, qty);
                if (!data.ok) {
                    if (data.error === 'auth_required') { window.location.href = 'interface_connexion.php'; return; }
                    throw new Error(data.error || 'Erreur panier');
                }

                // <<< MÀJ du résumé tout de suite si la page le contient
                if (window.updateSummaryUI) window.updateSummaryUI(data.items);

                if (redirectAfter) {
                    window.location.href = 'commande.php';
                } else {
                    el.textContent = 'Ajouté ✓';
                    setTimeout(() => (el.textContent = 'Ajouter'), 1200);
                }
            } catch (err) {
                console.error(err);
                alert("Impossible d'ajouter au panier.");
            }
        });
    }

    // --- Au chargement : binder tous les boutons ---
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('button.add-to-cart').forEach(btn => wire(btn, false));
        //const next = document.querySelector('a.add-to-cart[href*="commande.php"]');
        //if (next) wire(next, true);
    });
})();
