<?php
// /site/pages/interface_selection_produit.php

/* === Base URL robuste (toujours un slash final) =========================
   -> j'utilise PHP_SELF quand dispo, sinon SCRIPT_NAME
   -> si on est à la racine ('.' ou ''), je force '/'
   ==================================================================== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Nos produits</title>

    <!-- CSS globaux puis CSS catalogue (évite les doublons) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">

    <!-- (facultatif) précharge les aperçus pour éviter le "flash" -->
    <link rel="preload" as="image" href="<?= $BASE ?>img/rouge.png">
    <link rel="preload" as="image" href="<?= $BASE ?>img/BouquetRosePale.png">
    <link rel="preload" as="image" href="<?= $BASE ?>img/ExempleNoel.png">
</head>
<body id="body_prod">
<?php
// header global (menu, logo…)
include __DIR__ . '/includes/header.php';
?>

<!-- Bandeau rouge (même style que "Notre histoire") -->
<section class="page-hero" aria-label="En-tête catalogue">
    <h1 class="page-hero__title">Nos produits</h1>
    <p class="page-hero__sub">Trouve la création qui raconte ton histoire.</p>
</section>

<!--
  .apropos = fond à pois (défini dans style_catalogue.css)
  .catalogue.luxe = centrage / respirations (sans background pour ne pas masquer les points)
-->
<main class="apropos catalogue luxe" role="main">
    <section class="hero-center">
        <!-- Visuel central — change selon la catégorie survolée -->
        <figure class="showcase">
            <img id="heroImage"
                 src="<?= $BASE ?>img/boxe_rouge_DK.png"
                 alt="Aperçu visuel des produits DK Bloom"
                 loading="eager">
        </figure>

        <!-- Navigation catégories (les liens ont une data-img pour l’aperçu) -->
        <nav class="cat-nav" aria-label="Catégories de produits">
            <a class="pill"
               href="<?= $BASE ?>fleur.php"
               data-img="<?= $BASE ?>img/rouge.png">Fleurs</a>

            <a class="pill"
               href="<?= $BASE ?>interface_catalogue_bouquet.php"
               data-img="<?= $BASE ?>img/BouquetRosePale.png">Bouquets</a>

            <a class="pill"
               href="<?= $BASE ?>coffret.php"
               data-img="<?= $BASE ?>img/ExempleNoel.png">Coffret</a>
        </nav>
    </section>
</main>

<?php
// footer global
include __DIR__ . '/includes/footer.php';
?>

<!-- JS global (si tu en as besoin sur le site) -->
<script src="<?= $BASE ?>js/script.js" defer></script>

<script>
    /* =========================================================
       Aperçu dynamique de l'image centrale
       - Au survol / focus d'une catégorie -> change l'image
       - Au mouseleave / blur -> revient à l'image par défaut
       - Je précharge les images pour éviter un "flash" visible
       ========================================================= */
    (() => {
        const hero = document.getElementById('heroImage');
        if (!hero) return;

        const defaultSrc = hero.getAttribute('src'); // je garde l'image d'origine
        const links = document.querySelectorAll('.cat-nav .pill[data-img]');

        // sécurité: si une image est manquante, je ne casse pas l'UI
        const setHero = (url) => {
            if (!url || hero.getAttribute('src') === url) return;
            hero.style.opacity = '.6';
            const tmp = new Image();
            tmp.onload = () => { hero.src = url; hero.style.opacity = '1'; };
            tmp.src = url;
        };

        links.forEach(link => {
            const url = link.dataset.img;

            link.addEventListener('mouseenter', () => setHero(url)); // souris
            link.addEventListener('focus', () => setHero(url));      // clavier

            const back = () => setHero(defaultSrc);
            link.addEventListener('mouseleave', back);
            link.addEventListener('blur', back);
        });
    })();
</script>
</body>
</html>
