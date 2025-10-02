<?php
// Page À propos : présentation DK Bloom (histoire, valeurs, images, chiffres)
session_start();

// Base URL robuste pour référencer /css /img /js depuis /site/pages/
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$isLogged = !empty($_SESSION['per_id']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — À propos</title>

    <!-- Feuilles globales -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <!-- Spécifique à cette page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_apropos.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="apropos">
    <!-- HERO : bandeau d’intro -->
    <section class="hero">
        <div class="container">
            <h1>Notre histoire</h1>
            <p>Des fleurs qui durent, des émotions qui restent.</p>
        </div>
    </section>

    <!-- STORY : petit texte + image (2 colonnes desktop, 1 colonne mobile) -->
    <section class="story container">
        <div class="story__text">
            <h2>Dans chaque pétale, un souvenir</h2>
            <p>
                Recevoir des fleurs fait toujours plaisir. Elles apportent de la joie, de la couleur et un instant d’émotion.
                C’est de ce constat qu’est née <strong>DK Bloom</strong> : prolonger ces instants précieux en créant
                des fleurs qui durent pour l’éternité. Nos créations allient élégance et durabilité
                pour que chaque bouquet devienne un souvenir intemporel.
            </p>
        </div>

        <div class="story__image">
            <img src="<?= $BASE ?>img/ExempleNouvelAn.png" alt="Coffret DK Bloom — Nouvel An">
        </div>
    </section>

    <!-- VALEURS : 3 cartes simples -->
    <section class="values container">
        <h2>Nos valeurs</h2>
        <div class="values__grid">
            <article class="card">
                <h3>Élégance</h3>
                <p>Des lignes sobres et des finitions soignées.</p>
            </article>
            <article class="card">
                <h3>Durabilité</h3>
                <p>Des fleurs qui traversent le temps.</p>
            </article>
            <article class="card">
                <h3>Personnalisation</h3>
                <p>Chaque création raconte votre histoire.</p>
            </article>
        </div>
    </section>

    <!-- MARQUEE : bande d’images défilantes (loop) -->
    <section class="marquee">
        <div class="marquee__track">
            <!-- NB : on duplique une partie de la liste pour créer une boucle fluide -->
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet de roses rouges">
            <img src="<?= $BASE ?>img/RosesNoir.png" alt="Bouquet noir">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret rond rouge">
            <img src="<?= $BASE ?>img/RosesPale.png" alt="Bouquet rose pâle">
            <img src="<?= $BASE ?>img/BouquetNoir.png" alt="Bouquet noir, version 2">
            <img src="<?= $BASE ?>img/BouquetRosePale.png" alt="Bouquet rose pâle, version 2">
            <img src="<?= $BASE ?>img/ExempleSaintValentin.png" alt="Coffret Saint-Valentin">
            <img src="<?= $BASE ?>img/ExempleFeteMeres.png" alt="Coffret Fête des mères">

            <!-- duplicata pour continuité -->
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="" aria-hidden="true">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="" aria-hidden="true">
        </div>
    </section>

    <!-- CITATION : encadré simple -->
    <section class="quote container">
        <blockquote>
            « Offrir des fleurs, c’est offrir un instant. Chez DK Bloom, nous le rendons <em>inoubliable</em>. »
        </blockquote>
    </section>

    <!-- STATS : petites métriques (mock) -->
    <section class="stats container">
        <div class="stats__item">
            <div class="stats__number">+50</div>
            <div class="stats__label">bouquets livrés</div>
        </div>
        <div class="stats__item">
            <div class="stats__number">100%</div>
            <div class="stats__label">clients satisfaits</div>
        </div>
        <div class="stats__item">
            <div class="stats__number">7j/7</div>
            <div class="stats__label">service client</div>
        </div>
    </section>

    <!-- CTA : lien vers le catalogue -->
    <section class="cta">
        <div class="container">
            <p>Envie d’offrir une émotion qui dure&nbsp;?</p>
            <a class="btn" href="<?= $BASE ?>interface_selection_produit.php">Voir le catalogue</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
