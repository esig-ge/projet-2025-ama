<?php
// Démarre/ouvre la session PHP (pour accéder au panier, à l'utilisateur connecté, etc.)
session_start();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <!-- Viewport responsive pour mobiles/tablettes/PC -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- Feuilles de style globales du site (header/footer) -->
    <link rel="stylesheet" href="css/style_header_footer.css">
    <!-- Styles spécifiques à la page "commande" (cart) -->
    <link rel="stylesheet" href="css/commande.css">
</head>

<body>
<?php
// Inclut le header commun à toutes les pages.
// __DIR__ pointe vers le dossier courant du fichier (chemin absolu ⇒ moins d'erreurs de chemin relatifs).
include __DIR__ . '/includes/header.php';
?>

<script>
    // Petite astuce : on calcule dynamiquement la hauteur du header
    // et on la stocke dans une variable CSS --header-h pour gérer les décalages (sticky header, marges, etc.)
    const h = document.querySelector('.site-header');
    if (h) {
        document.documentElement.style.setProperty('--header-h', h.offsetHeight + 'px');
    }
</script>

<!--
 role="main" : indique la zone principale du document pour les technologies d’assistance.
 .wrap : conteneur centré avec largeur max (géré par CSS).
-->
<main class="wrap" role="main">
    <h1 class="page-title">Récapitulatif de mon panier</h1>

    <!--
      .grid : layout en colonnes (liste à gauche, résumé à droite).
    -->
    <div class="grid">
        <!--
          Section liste des articles du panier.
          aria-live="polite" : quand le contenu est mis à jour en JS, les lecteurs d’écran sont informés sans interrompre l’utilisateur.
        -->
        <section class="card" id="cart-list" aria-live="polite"></section>

        <!-- Colonne de droite : résumé de la commande -->
        <aside class="card summary" aria-labelledby="sum-title">
            <!--
              Titre visuellement masqué (sr-only) mais lisible par les lecteurs d’écran
              pour donner un libellé accessible à l’aside.
            -->
            <h2 id="sum-title" class="sr-only">Résumé de commande</h2>

            <!-- Lignes de sous-total / livraison / total. Les valeurs seront mises à jour par JS. -->
            <div class="sum-row">
                <span>Produits</span>
                <span id="sum-subtotal">0.00 CHF</span>
            </div>
            <div class="sum-row">
                <span>Livraison</span>
                <span id="sum-shipping">—</span>
            </div>
            <div class="sum-total">
                <span>Total</span>
                <span id="sum-total">0.00 CHF</span>
            </div>

            <!--
              Bouton de passage au paiement.
              aria-disabled="true" + styles CSS : visuellement actif mais désactivé au clavier/screenreaders tant que panier vide.
            -->
            <a href="checkout.php" class="btn-primary" id="btn-checkout" aria-disabled="true">
                Valider ma commande
            </a>

            <!-- Zone code promo (désactivée tant qu’aucune logique n’est branchée) -->
            <div class="coupon">
                <input type="text" id="coupon-input" placeholder="Mon code de réduction" disabled>
                <button class="btn-ghost" disabled>Ajouter</button>
            </div>

            <!-- Bloc aide/contact -->
            <div class="help">
                <p>Une question ? Contactez-nous au <a href="tel:+41765698541">+41 76 569 85 41</a></p>
                <ul>
                    <li>Expédition sous 7 jours (si disponible)</li>
                    <li>Frais  livraison?</li>
                    <li>Paiement sécurisé</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<!-- Footer simple -->
<footer class="dkb-footer">
    <div class="wrap">© DK Bloom</div>
</footer>

<!--
  JS de la page (fonctions utilitaires : formatage, update du résumé, etc.)
  ⚠️ commande.js doit définir window.updateSummaryUI(items) si on veut que la somme s’actualise.
-->
<script src="js/commande.js"></script>

<script>
    // IIFE async : on isole le scope et on peut utiliser await au chargement.
    (async function(){
        try {
            // Appel à l'API interne pour récupérer la liste du panier.
            // credentials:'same-origin' : envoie les cookies de session (nécessaire si l’API s’appuie sur la session PHP).
            const res = await fetch('../api/cart.php?action=list', {credentials:'same-origin'});

            // On suppose que l’API répond du JSON { ok: true/false, items: [...] }
            const data = await res.json();
            if (!data.ok) return; // si l’API dit "pas ok", on sort silencieusement.

            const wrap = document.getElementById('cart-list');

            // Si aucun article : message vide.
            if (!data.items.length) {
                wrap.innerHTML = '<div class="empty"><p><strong>Votre panier est vide</strong></p></div>';
            } else {
                // Sinon on map chaque item en bref récapitulatif.
                wrap.innerHTML = data.items.map(it => {
                    // Sécurise les calculs : Number(...) renvoie NaN si invalide, on retombe sur 0 via || 0
                    const pu = Number(it.PRO_PRIX) || 0;         // prix unitaire
                    const q  = Number(it.CP_QTE_COMMANDEE) || 0; // quantité
                    const lt = (pu * q).toFixed(2);              // ligne totale formatée

                    // On rend une ligne "cart-brief" (nom, quantité, PU, total)
                    return `
          <div class="cart-brief">
            <div class="name">${it.PRO_NOM}</div>
            <div class="qty">x&nbsp;${q}</div>
            <div class="unit">${pu.toFixed(2)}&nbsp;CHF</div>
            <div class="total">${lt}&nbsp;CHF</div>
          </div>
        `;
                }).join('');
            }

            // Met à jour le bloc résumé (sous-total / total / bouton checkout)
            // si la fonction existe dans js/commande.js
            if (window.updateSummaryUI) window.updateSummaryUI(data.items);
        } catch(e) {
            // En cas d'erreur réseau/JSON : on log pour debug.
            console.error(e);
        }
    })();
</script>

</html>
