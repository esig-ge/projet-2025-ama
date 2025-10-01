<?php
session_start(); // Démarre la session PHP (obligatoire pour $_SESSION)

/* -----------------------
   1) Gestion cookies
   ----------------------- */
// Vérifie si l'utilisateur a déjà accepté les cookies
// On s'en sert plus tard pour afficher la bannière
$showCookieBanner = empty($_COOKIE['accept_cookies']);

/* -----------------------
   2) Base URL (chemin)
   ----------------------- */
// Permet de générer des liens relatifs corrects, quel que soit le sous-dossier
// Exemple : /site/pages/ => BASE = /site/pages/
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

/* -----------------------
   3) Fonction utilitaire "isAdmin"
   ----------------------- */
// Définie une seule fois (évite redéfinition si déjà incluse ailleurs)
// Vérifie si la session contient un indicateur d'admin
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

    <!-- 4) CSS: global en premier (fond à pois, boutons, Accueil), puis header/footer -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
</head>

<body>

<!-- 5) Raccourci retour admin (affiché seulement si admin) -->
<?php if (isAdmin()): ?>
    <a class="btn-retour-admin" href="<?= $BASE ?>adminAccueil.php" title="Retour au dashboard admin">← Retour admin</a>
<?php endif; ?>

<!-- 6) Header (structure gérée par style_header_footer.css) -->
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- 7) Contenu principal — classes mappées à styles.css -->
<main class="apropos">
    <section class="entete_accueil">
        <div class="image_texte">
            <!-- Colonne texte -->
            <div class="texte">
                <h1>
                    Bienvenu<?php if (!empty($_SESSION['per_prenom'])): ?>e <?= htmlspecialchars($_SESSION['per_prenom']) ?>
                    <?php else: ?>
                        <span class="accent"> à DK Bloom </span>
                    <?php endif; ?>
                </h1>

                <!-- 8) Bannière cookies (réutilisable) -->
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
            <div class="bouquet">
                <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret de roses DK">
            </div>
        </div>
    </section>
</main>

<!-- 9) Footer (structure gérée par style_header_footer.css) -->
<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- 10) Scripts de page (petits utilitaires) -->
<script>
    // Base JS accessible ailleurs si nécessaire
    window.DKBASE = <?= json_encode($BASE) ?>;

    // Dropdown "compte" (si présent dans le header)
    document.addEventListener('DOMContentLoaded', () => {
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

        // Bouton "Accepter" cookies (si la bannière est visible)
        const acceptBtn = document.getElementById('cookieAcceptBtn');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => {
                document.cookie = "accept_cookies=true; path=/; max-age=" + 60*60*24*365; // 1 an
                document.getElementById('cookieBanner')?.remove();
            });
        }
    });
</script>

<!-- 11) JS applicatif (panier, etc.) -->
<script src="<?= $BASE ?>js/commande.js"></script>

<!-- 12) Toasts (si présents en session) -->
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
