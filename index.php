<?php
session_start();

// Navigation (avant dans le header)
$nav = [
    ["label" => "Accueil", "href" => "#top"],
    ["label" => "Catalogue", "href" => "#catalogue"],
    ["label" => "Personnalisation", "href" => "#custom"],
    ["label" => "Livraison", "href" => "#shipping"],
    ["label" => "Connexion", "href" => "login.php"]
];

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
    ],
    [
        "id" => "shipping",
        "title" => "Livraison",
        "desc"  => "Retrait en main propre à Genève ou livraison soignée à domicile.",
        "btn"   => "Options de livraison",
        "href"  => "livraison.php"
    ]
];

$slides = [
    ["img" => "assets/slide1.jpg", "alt" => "Bouquet rouge profond"],
    ["img" => "assets/slide2.jpg", "alt" => "Roses éternelles en boîte"],
    ["img" => "assets/slide3.jpg", "alt" => "Bouquet personnalisé"]
];

include 'app/includes/header.php';
?>

<!-- Hero -->
<section class="hero container reveal">
    <div class="hero-grid">
        <div class="hero-copy">
            <h1>La fleur, version <span class="accent">élégance</span>.</h1>
            <p>Des créations florales intemporelles, une expérience simple et raffinée.</p>
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
                <img src="<?= htmlspecialchars($s['img']) ?>" alt="<?= htmlspecialchars($s['alt']) ?>" />
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
