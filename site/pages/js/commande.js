// site/pages/js/commande.js
(function () {
    // ================================
    // 1) RENDU DU RÉSUMÉ (GLOBAL)
    // ================================
    // On expose la fonction sur window pour qu'elle soit réutilisable
    // par les scripts inline de la page (ex: après un fetch qui rafraîchit le panier).
    window.updateSummaryUI = function(items) {
        // Récupère les éléments du résumé (sous-total, livraison, total, bouton checkout)
        const elSub = document.getElementById('sum-subtotal');
        const elShip = document.getElementById('sum-shipping');
        const elTot = document.getElementById('sum-total');
        const btn = document.getElementById('btn-checkout');

        // Si le sous-total ou le total manquent, on sort (rien à mettre à jour)
        if (!elSub || !elTot) return;

        // Calcule le sous-total à partir des items renvoyés par l'API
        // items = [{ PRO_PRIX: "12.50", CP_QTE_COMMANDEE: "2", ... }, ...]
        const subtotal = (items || []).reduce((acc, it) => {
            const prix = Number(it.PRO_PRIX ?? 0);           // prix unitaire
            const qte  = Number(it.CP_QTE_COMMANDEE ?? 0);   // quantité
            return acc + prix * qte;
        }, 0);

        // Formatage simple en CHF (2 décimales)
        const fmt = (n) => `${n.toFixed(2)} CHF`;

        // Injection du rendu dans le DOM
        elSub.textContent = fmt(subtotal);
        if (elShip) elShip.textContent = '—'; // (Placeholder) pas de frais de port pour l’instant
        elTot.textContent = fmt(subtotal);

        // Active/désactive le bouton "Valider ma commande"
        if (btn) {
            if (subtotal > 0) {
                // panier non vide → bouton actif
                btn.removeAttribute('aria-disabled');
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '';
            } else {
                // panier vide → bouton inactif (UX + accessibilité)
                btn.setAttribute('aria-disabled', 'true');
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.6';
            }
        }
    };

    // ========================================
    // 2) APPEL API : AJOUTER UN PRODUIT (POST)
    // ========================================
    // Envoie une requête POST à l'API panier pour ajouter un produit.
    // Retourne la réponse JSON { ok: bool, items: [...], error?: string }
    async function callAdd(proId, qty = 1) {
        const form = new FormData();
        form.append('action', 'add');
        form.append('pro_id', String(proId));
        form.append('qty', String(qty));

        const res = await fetch('../api/cart.php?action=add', {
            method: 'POST',
            body: form,
            credentials: 'same-origin', // important pour les cookies de session (PHP)
        });
        return res.json();
    }

    // =========================================
    // 3) BINDER LES BOUTONS "AJOUTER AU PANIER"
    // =========================================
    // wire(el, redirectAfter) : attache le comportement au clic d’un bouton
    // - Lit les dataset data-id / data-pro-id / data-qty
    // - Appelle l'API et gère la réponse (update UI, redirection, feedback)
    function wire(el, redirectAfter = false) {
        el.addEventListener('click', async (e) => {
            e.preventDefault();

            // On récupère l'id produit et la quantité à partir des attributs data-*
            const proId = Number(el.dataset.id || el.dataset.proId);
            const qty   = Number(el.dataset.qty || 1);

            if (!proId) {
                alert('Produit invalide: data-id manquant ou non numérique');
                return;
            }

            try {
                const data = await callAdd(proId, qty);

                // Gestion d’erreur côté API
                if (!data.ok) {
                    // Cas typique : l'API exige une authentification
                    if (data.error === 'auth_required') {
                        window.location.href = 'interface_connexion.php';
                        return;
                    }
                    throw new Error(data.error || 'Erreur panier');
                }

                // Met à jour immédiatement le résumé si la fonction globale existe
                if (window.updateSummaryUI) window.updateSummaryUI(data.items);

                // Comportement après ajout :
                // - soit on redirige vers la page commande
                // - soit on affiche un feedback "Ajouté ✓" temporaire
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

    // =================================================
    // 4) AU CHARGEMENT DE LA PAGE : BIND AUTOMATIQUE
    // =================================================
    // Sélectionne tous les boutons "Ajouter au panier"
    // et leur attache le comportement défini dans wire(...)
    document.addEventListener('DOMContentLoaded', () => {
        document
            .querySelectorAll('button.add-to-cart')
            .forEach(btn => wire(btn, false));

        // Exemple si tu veux binder un lien qui doit rediriger vers le panier :
        // const next = document.querySelector('a.add-to-cart[href*="commande.php"]');
        // if (next) wire(next, true);
    });
})();
