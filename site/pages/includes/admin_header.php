<?php
// /site/includes/admin_layout_start.php
require __DIR__ . '/admin_auth.php';

// 1) Base URL de la page courante (avec slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

// 2) Base du site (remonte si on est sous /pages/)
$SITE_BASE = preg_replace('#/pages/?$#', '/', $BASE);
/** @var string $BASE */
/** @var string $SITE_BASE */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — DK Bloom</title>
    <!-- Assets globaux: utiliser SITE_BASE -->
    <link rel="stylesheet" href="<?= $SITE_BASE ?>css/style_admin.css">
</head>
<body class="adm">
<aside class="adm-sidebar">
    <div class="brand">
        <img src="<?= $SITE_BASE ?>img/logo.jpg" alt="DK Bloom" class="brand-logo">
        <span class="brand-name">DK Bloom</span>
    </div>
    <nav class="adm-nav">
        <!-- Liens de pages: utiliser BASE -->
        <a class="nav-item<?= ($active ?? '')==='dashboard'?' active':'' ?>" href="<?= $BASE ?>adminAccueil.php"><span class="ico">🏠</span> <span>Dashboard</span></a>
        <a class="nav-item<?= ($active ?? '')==='produits'?' active':'' ?>" href="<?= $BASE ?>adminProduits.php"><span class="ico">💐</span> <span>Produits</span></a>
        <a class="nav-item<?= ($active ?? '')==='commandes'?' active':'' ?>" href="<?= $BASE ?>adminCommandes.php"><span class="ico">🧾</span> <span>Commandes</span></a>
        <a class="nav-item<?= ($active ?? '')==='clients'?' active':'' ?>" href="<?= $BASE ?>adminClients.php"><span class="ico">👤</span> <span>Clients</span></a>
        <a class="nav-item<?= ($active ?? '')==='promos'?' active':'' ?>" href="<?= $BASE ?>adminPromos.php"><span class="ico">🏷️</span> <span>Promotions</span></a>
        <a class="nav-item<?= ($active ?? '')==='avis'?' active':'' ?>" href="<?= $BASE ?>adminAvis.php"><span class="ico">⭐</span> <span>Avis</span></a>
        <a class="nav-item<?= ($active ?? '')==='params'?' active':'' ?>" href="<?= $BASE ?>adminParametres.php"><span class="ico">⚙️</span> <span>Paramètres</span></a>
    </nav>
    <div class="adm-footer">© <?= date('Y') ?> DK Bloom</div>
</aside>

<main class="adm-main">
    <header class="adm-topbar">
        <button class="burger" id="burger">☰</button>
        <div class="welcome"><h1><?= htmlspecialchars($topTitle ?? 'Admin') ?></h1><p>Bienvenue, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></p></div>
        <div class="top-actions">
            <a class="btn ghost" href="<?= $SITE_BASE ?>index.php">Voir le site</a>
            <a class="btn" href="<?= $SITE_BASE ?>logout.php">Se déconnecter</a>
        </div>
    </header>
    <section class="page-body">
