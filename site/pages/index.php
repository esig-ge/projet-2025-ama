<?php
session_start();

/* 1) Cookies banner visible si non accepté */
$showCookieBanner = empty($_COOKIE['accept_cookies']);

/* 2) Base URL (liens relatifs robustes) */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

/* 3) Flag admin réutilisable */
if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        return !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Accueil</title>

    <!-- Global: fond à pois, boutons, utilitaires -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <!-- Header/Footer -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
</head>

<body class="home"><!-- classe 'home' pour cibler des effets spécifiques à l’accueil -->

<?php if (isAdmin()): ?>
    <a class="btn-retour-admin" href="<?= $BASE ?>adminAccueil.php" title="Retour au dashboard admin">← Retour admin</a>
<?php endif; ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="apropos">
    <!-- HERO -->
    <section class="entete_accueil">
        <div class="image_texte container">
            <!-- Colonne texte -->
            <div class="texte">
                <h1>
                    Bienvenu<?php if (!empty($_SESSION['per_prenom'])): ?>e <?= htmlspecialchars($_SESSION['per_prenom']) ?>
                    <?php else: ?>
                        <span class="accent"> Johany </span>
                    <?php endif; ?>
                </h1>

                <?php if ($showCookieBanner): ?>
                    <div class="cookie-banner" id="cookieBanner" role="region" aria-label="Bannière cookies">
                        <span>🍪 Nous utilisons des cookies pour améliorer votre expérience.</span>
                        <button type="button" class="cookie-accept" id="cookieAcceptBtn">Accepter</button>
                    </div>
                <?php endif; ?>

                <p class="paragraphe">
                    L’art floral intemporel, au service d’une expérience unique et raffinée.
                    La beauté qui ne fane jamais.
                </p>

                <div class="btn_accueil">
                    <a class="btn_index" href="<?= $BASE ?>creations.php">Découvrir nos créations</a>
                    <a class="btn_index" href="<?= $BASE ?>interface_selection_produit.php">Créer la vôtre</a>
                </div>
            </div>

            <!-- Colonne image coffret -->
            <div>
                <img class="boxerouge" src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret de roses DK">
            </div>
        </div>
    </section>

    <!-- Séparateur chevron (full-width) -->
    <img id="separateur" src="<?= $BASE ?>img/separateur.png" alt="">

    <!-- 6) Confiance / Praticité -->
    <section class="home-trust container" aria-label="Nos engagements">
        <h2 class="sr-only">Nos engagements</h2>

        <div class="trust-grid">
            <article class="trust-item">
                <img class="trust-ico" src="<?= $BASE ?>img/livraison.png" width="44" height="44" alt="Livraison rapide">
                <h3>Livraison rapide</h3>
                <p>Expédition soignée et délais courts, partout en Suisse.</p>
            </article>

            <article class="trust-item">
                <img class="trust-ico" src="<?= $BASE ?>img/paiement-securise.png" width="44" height="44" alt="Paiement sécurisé">
                <h3>Paiement sécurisé</h3>
                <p>Stripe (cartes, TWINT, Revolut). Données protégées.</p>
            </article>

            <article class="trust-item">
                <img class="trust-ico" src="<?= $BASE ?>img/support.png" width="44" height="44" alt="Support 7j/7">
                <h3>Support 7j/7</h3>
                <p>Nous répondons rapidement par e-mail et téléphone.</p>
            </article>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Scripts de page -->
<script>
    // Base accessible globalement
    window.DKBASE = <?= json_encode($BASE) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // 1) Compte (si présent)
        const accountMenu = document.getElementById('account-menu');
        const trigger = accountMenu?.querySelector('.menu-link');
        if (trigger) {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                accountMenu.classList.toggle('open');
            });
            document.addEventListener('click', (e) => {
                if (!accountMenu.contains(e.target)) accountMenu.classList.remove('open');
            });
        }

        // 2) Cookies: acceptez & mémorisez 1 an
        const acceptBtn = document.getElementById('cookieAcceptBtn');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => {
                document.cookie = "accept_cookies=true; path=/; max-age=" + (60*60*24*365);
                document.getElementById('cookieBanner')?.remove();
            });
        }

        // 3) Effet logo (page d'accueil uniquement)
        //    - survol = léger zoom fluide
        //    - au chargement, on applique un petit drop-shadow
        const logo = document.querySelector('.site-header .logo img');
        if (logo) {
            // look un peu plus "précieux" sans toucher au CSS global
            logo.style.filter = 'drop-shadow(0 2px 6px rgba(0,0,0,.25))';
            logo.style.borderRadius = '6px';
            logo.style.transition = 'transform .18s ease';

            // version “Web Animations API” pour un hover smooth cross-browser
            let animIn = null, animOut = null;
            logo.addEventListener('mouseenter', () => {
                if (animOut) animOut.cancel();
                animIn = logo.animate(
                    [{ transform: 'scale(1)' }, { transform: 'scale(1.06)' }],
                    { duration: 160, fill: 'forwards', easing: 'ease-out' }
                );
            });
            logo.addEventListener('mouseleave', () => {
                if (animIn) animIn.cancel();
                animOut = logo.animate(
                    [{ transform: 'scale(1.06)' }, { transform: 'scale(1)' }],
                    { duration: 140, fill: 'forwards', easing: 'ease-in' }
                );
            });
            // Focus clavier = même effet (a11y)
            logo.addEventListener('focus', () => logo.dispatchEvent(new Event('mouseenter')));
            logo.addEventListener('blur',  () => logo.dispatchEvent(new Event('mouseleave')));
            logo.setAttribute('tabindex', '0'); // focusable si nécessaire
        }
    });
</script>

<!-- JS applicatif (panier, etc.) -->
<script src="<?= $BASE ?>js/commande.js"></script>

<!-- Toasts session -->
<?php if (!empty($_SESSION['toast'])): $t = $_SESSION['toast']; unset($_SESSION['toast']); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const msg  = <?= json_encode($t['text'], JSON_UNESCAPED_UNICODE) ?>;
            const type = <?= json_encode($t['type']) ?>;
            if (typeof window.toast === 'function')           window.toast(msg, type);
            else if (typeof window.showToast === 'function')  window.showToast(msg, type);
            else                                              alert(msg);
        });
    </script>
<?php endif; ?>

</body>
</html>
