<?php session_start();?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LashesBeauty</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<?php include 'app/includes/header.php';
?>
<script>
    const btn = document.querySelector('.hamburger');
    const nav = document.getElementById('nav-menu');

    btn.addEventListener('click', () => {
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        nav.hidden = open; // si c'était ouvert, on cache; sinon on montre
    });
</script>
<body class="corps">

<!-- Hero -->
<section class="entete_accueil">
    <div class="boutons">
        <div class="cta-row">
            <h1>Bienvenu <span class="accent">élégance</span>.</h1>
            <p>L’art floral intemporel, au service d’une expérience unique et raffinée. La beauté qui ne fane jamais.</p>
            <a class="btn btn-primary" href="catalogue.php">Découvrir nos créations</a>
            <a class="btn btn-ghost" href="personnalisation.php">Créer la vôtre</a>
            <a href="index.php">Espace administrateur</a>
        </div>
        <div>
            <img class="" src="assets/img/bouquet-removebg-preview.png" alt="" width="200px" height="auto">
        </div>
</section>



<!-- Features -->
<section class="features container">
    <?php foreach ($features as $f): ?>
        <article id="<?= htmlspecialchars($f['id']) ?>" class="card reveal">
            <h3><?= htmlspecialchars($f['title']) ?></h3>
            <p><?= htmlspecialchars($f['desc']) ?></p>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($f['href']) ?>"><?= htmlspecialchars($f['btn']) ?></a>
        </article>
    <?php endforeach; ?>
</section>

<!-- Carrousel -->
<section class="carousel container reveal" aria-label="Sélection DK Bloom">
    <div class="carousel-track" data-track>
        <?php foreach ($slides as $s): ?>
            <figure class="slide">
                <img src="assets/img/singlefleur-removebg-preview.png" alt="1" />
                <img src="assets/img/bouquet-removebg-preview.png" alt="2"/>
                <img src="assets/img/boxefleur.jpeg " alt="3"/>

                <img src="assets/img/singlefleur-removebg-preview.png" alt="1" />
                <img src="assets/img/bouquet-removebg-preview.png" alt="2"/>
                <img src="assets/img/boxefleur.jpeg" alt="3"/>
                <figcaption><?= htmlspecialchars($s['alt']) ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <div class="carousel-controls">
        <button class="btn btn-ghost" data-prev aria-label="Image précédente">‹</button>
        <button class="btn btn-ghost" data-next aria-label="Image suivante">›</button>
    </div>
</section>
</body>

<?php
include 'app/includes/footer.php';
?>
