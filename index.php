<?php
session_start();

// Données "démo" (vous pourrez les remplacer par une BD plus tard)
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

?>
<!doctype html>
<html lang="fr" id="top">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Fleurs & Créations</title>
    <meta name="description" content="DK Bloom : bouquets, roses éternelles, personnalisation et livraison soignée." />
    <link rel="preload" href="assets/img/logo.jpg" as="image">
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="theme-dark">
<!-- Pétales décoratifs -->
<div class="petals" aria-hidden="true"></div>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <a class="brand" href="#top">
            <img src="assets/img/logo.jpg" alt="Logo DK Bloom" height="48" />
            <span class="sr-only">DK Bloom</span>
        </a>
        <button class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">☰</button>
        <nav class="site-nav" data-nav>
            <?php foreach ($nav as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

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

<!-- Footer -->
<footer class="site-footer">
    <div class="container footer-grid">
        <p>© <?= date('Y') ?> DK Bloom. Tous droits réservés.</p>
        <nav class="footer-nav">
            <a href="mentions.php">Mentions légales</a>
            <a href="contact.php">Contact</a>
            <a href="login.php">Espace client</a>
        </nav>
    </div>
</footer>

<!-- JS (sans lib) -->
<script>
    // Menu mobile
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('[data-nav]');
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        nav.classList.toggle('open');
    });

    // Révélations au scroll (fade & translate)
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) e.target.classList.add('reveal--visible');
        });
    }, { threshold: 0.15 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));

    // Carrousel minimal
    const track = document.querySelector('[data-track]');
    const slides = Array.from(track.children);
    let index = 0;

    function goto(i) {
        index = (i + slides.length) % slides.length;
        track.style.transform = `translateX(${index * -100}%)`;
    }
    document.querySelector('[data-prev]').addEventListener('click', () => goto(index - 1));
    document.querySelector('[data-next]').addEventListener('click', () => goto(index + 1));
    setInterval(() => goto(index + 1), 5000); // auto-slide
</script>
</body>
</html>
