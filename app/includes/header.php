<?php
// Données nav disponibles globalement
if (!isset($nav)) {
    $nav = [
        ["label" => "Accueil", "href" => "#top"],
        ["label" => "Catalogue", "href" => "#catalogue"],
        ["label" => "Personnalisation", "href" => "#custom"],
        ["label" => "Livraison", "href" => "#shipping"],
        ["label" => "Connexion", "href" => "login.php"]
    ];
}
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
<!-- décor pétales -->
<div class="petals" aria-hidden="true"></div>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <a class="brand" href="#top">
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
