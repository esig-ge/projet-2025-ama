<?php
session_start();

$features = [
    [
        "id" => "catalogue",
        "title" => "Catalogue",
        "desc"  => "Roses éternelles, bouquets saisonniers et créations signature.",
        "btn"   => "Voir le catalogue",
        "href"  => "catalogue.php"
    ],
    [
        "id" => "custom",
        "title" => "Personnalisation",
        "desc"  => "Couleurs, lettres, paillettes et message — créez la pièce parfaite.",
        "btn"   => "Personnaliser",
        "href"  => "personnalisation.php"
    ]
];

$slides = [
    ["img" => "assets/img/12Roses.png", "alt" => "Demande au mariage ou engagement"],
    ["img" => "assets/img/20Roses.png", "alt" => "Engagement sincère"],
    ["img" => "assets/img/36Roses.png", "alt" => "Amour romantique et passionnel"],
    ["img" => "assets/img/50Roses.png", "alt" => "Amour incontionnel et sans limite"],
    ["img" => "assets/img/66Roses.png", "alt" => "Mon amour pour toi ne changera pas"],
    ["img" => "assets/img/100Roses.png", "alt" => "Dévouement absolu"]
];

include 'app/includes/header.php';
?>

<!-- Hero -->
<section class="hero container reveal">
    <div class="hero-grid">
        <div class="hero-copy">
            <h1>Bienvenu <span class="accent">élégance</span>.</h1>
            <p>L’art floral intemporel, au service d’une expérience unique et raffinée. La beauté qui ne fane jamais.</p>
            <div class="cta-row">
                <a class="btn btn-primary" href="catalogue.php">Découvrir nos créations</a>
                <a class="btn btn-ghost" href="personnalisation.php">Créer la vôtre</a>
            </div>
        </div>
        <div class="hero-media">
            <div class="logo-ring">
                <img src="assets/img/logo.jpg" alt="DK Bloom" />
            </div>
        </div>
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
                <img src="assets/img/bouquet-removebg-preview.png" alt="1" />
                <img src="assets/img/boxefleur-removebg-preview.png" alt="2"/>
                <img src="assets/img/boxefleur-removebg-preview.png" alt="3"/>

                <img src="assets/img/bouquet-removebg-preview.png" alt="1" />
                <img src="assets/img/boxefleur-removebg-preview.png" alt="2"/>
                <img src="assets/img/boxefleur-removebg-preview.png" alt="3"/>
                <figcaption><?= htmlspecialchars($s['alt']) ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <div class="carousel-controls">
        <button class="btn btn-ghost" data-prev aria-label="Image précédente">‹</button>
        <button class="btn btn-ghost" data-next aria-label="Image suivante">›</button>
    </div>
</section>

<?php
include 'app/includes/footer.php';
?>
