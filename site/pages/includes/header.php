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

            <?php if (!empty($_SESSION['per_id'])): ?>
                <div class="dd-account" id="dd-account">
                    <a href="#" class="dd-trigger" aria-haspopup="true" aria-expanded="false">
                        Mon compte <span class="caret">▾</span>
                    </a>
                    <div class="dd-panel" role="menu" aria-label="Menu Mon compte">
                        <a role="menuitem" href="<?= $BASE ?>mon_compte.php">Infos personnelles</a>
                        <hr>
                        <a role="menuitem" href="<?= $BASE ?>deconnexion.php">Me déconnecter</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= $BASE ?>inscription.php">S'inscrire</a>
                <a href="<?= $BASE ?>interface_connexion.php">Se connecter</a>
            <?php endif; ?>

        </nav>
    </div>
</header>
