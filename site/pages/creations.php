<?php
session_start();
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // ex: /.../site/pages/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Coffrets</title>

    <!-- CSS global + page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <h1>Nos prestations déjà réalisées</h1>

    <section class="main_slide">
        <div class="slider">
            <div class="slides">

                <!-- Slide 1 -->

<!--                <video controls>
                    <source src="img/videofleur2.mp4" type="video/mp4" >
                </video>

                 Slide 2
                <video controls>
                    <source src="img/videofleur3.mp4" type="video/mp4" >
-->
                <video controls playsinline>
                    <!-- .mov = QuickTime. Si tu convertis en .mp4, change type en video/mp4 -->
                    <source src="<?= $BASE ?>img/videofleur2.mp4" type="video/mp4">
                    <source src="img/videofleur2.mp4" type="video/mp4">
                    Votre navigateur ne supporte pas la vidéo.
                </video>

                <!-- Slide 2 -->
                <video controls playsinline>
                    <source src="<?= $BASE ?>img/videofleur3.mov" type="video/mp4">
                    Votre navigateur ne supporte pas la vidéo.
                </video>
            </div>
            <button class="btn prev" aria-label="Précédent">&#10094;</button>
            <button class="btn next" aria-label="Suivant">&#10095;</button>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- JS éventuel du slider -->
<script src="<?= $BASE ?>js/script.js" defer></script>
<!-- <script src="<?= $BASE ?>js/slider.js" defer></script> -->
</body>
</html>
