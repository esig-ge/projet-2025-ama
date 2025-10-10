<?php
// /site/pages/includes/header.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Base URL robuste */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* Connexion PDO disponible ? sinon on charge (évite les "undefined $pdo") */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = @require __DIR__ . '/../../database/config/connexionBDD.php';
    if ($pdo instanceof PDO) { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
}

$isLogged = !empty($_SESSION['per_id']);

/* Compteur de notifications non lues (si connecté + PDO dispo) */
$notifCount = 0;
if ($isLogged && ($pdo instanceof PDO)) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATION WHERE PER_ID = :id AND READ_AT IS NULL");
        $st->execute([':id' => (int)$_SESSION['per_id']]);
        $notifCount = (int)$st->fetchColumn();
    } catch (Throwable $e) { /* silencieux : on cache le badge si souci */ }
}
?>
<header class="site-header">
    <div class="header">
        <a href="<?= $BASE ?>index.php" class="logo">
            <img src="<?= $BASE ?>img/logo.jpg" alt="DK Bloom" width="120">
        </a>

        <nav class="menu">
            <a href="<?= $BASE ?>index.php">Accueil</a>
            <a href="<?= $BASE ?>apropos.php">À propos</a>
            <a href="<?= $BASE ?>interface_selection_produit.php">Catalogue</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>

            <?php if ($isLogged): ?>
                <a href="<?= $BASE ?>info_perso.php" class="myspace">
                    Mon espace
                    <?php if ($notifCount > 0): ?>
                        <span class="notif-dot" aria-label="<?= $notifCount ?> nouvelle(s) notification(s)"></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $BASE ?>commande.php">Mon panier</a>
                <a href="<?= $BASE ?>interface_deconnexion.php">Me déconnecter</a>
            <?php else: ?>
                <a href="<?= $BASE ?>interface_inscription.php">M'inscrire</a>
                <a href="<?= $BASE ?>interface_connexion.php">Me connecter</a>
            <?php endif; ?>
        </nav>
    </div>

    <style>
        .menu { display:flex; gap:18px; align-items:center; }
        .menu a { position:relative; text-decoration:none; }
        .menu a.myspace { padding-right:14px; } /* espace pour le point */
        .menu a.myspace .notif-dot{
            position:absolute; top:-4px; right:-6px;
            width:10px; height:10px; border-radius:50%;
            background:#e0112b; box-shadow:0 0 0 2px rgba(255,255,255,.95);
            display:inline-block;
        }
    </style>
</header>
