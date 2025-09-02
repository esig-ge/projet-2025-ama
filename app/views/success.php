<?php
session_start();
require_once __DIR__ . '/../../config/connexionBDD.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Merci</title>
    <link rel="stylesheet" href="/public/assets/css/checkout.css?v=1">
</head>
<body>
<main class="wrap">
    <h1>Merci pour votre commande 🌹</h1>
    <p>Vous allez recevoir un e-mail de confirmation. La livraison est estimée sous ~7 jours ouvrables.</p>
    <a href="catalogue.php" class="link">← Retour au catalogue</a>
</main>
</body>
</html>
