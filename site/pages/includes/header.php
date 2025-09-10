<?php
session_start();

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Utilisateur connecté ?
$isLogged = !empty($_SESSION['per_id']); // on teste la clé correcte
?>
<header class="site-header">
    <div class="header">
        <a href="<?= $BASE ?>index.php" class="logo">
            <img src="<?= $BASE ?>img/logo.jpg" alt="DK Bloom" width="120">
        </a>

        <nav class="menu">
            <a href="<?= $BASE ?>index.php">Accueil</a>
            <a href="<?= $BASE ?>apropos.php">À propos</a>
            <a href="<?= $BASE ?>interface_catalogue_bouquet.php">Catalogue</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>

            <?php if ($isLogged): ?>
                <a href="<?= $BASE ?>deconnexion.php">Se déconnecter</a>
            <?php else: ?>
                <a href="<?= $BASE ?>inscription.php">S'inscrire</a>
                <a href="<?= $BASE ?>interface_connexion.php">Se connecter</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
