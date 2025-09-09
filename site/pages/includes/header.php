<?php if (!isset($BASE)) { $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; } ?>
<header class="site-header">
    <div class="header">
        <a href="<?= $BASE ?>index.php" class="logo">
            <img src="<?= $BASE ?>img/logo.png" alt="DK Bloom">
        </a>

        <button class="menu-toggle" aria-expanded="false" aria-label="Menu">☰</button>

        <nav data-nav class="menu">
            <a href="<?= $BASE ?>index.php">Accueil</a>
            <a href="<?= $BASE ?>apropos.php">À propos</a>
            <a href="<?= $BASE ?>interface_catalogue_bouquet.php">Catalogue</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>
            <a href="<?= $BASE ?>inscription.php">S'inscrire</a>
            <a href="<?= $BASE ?>interface_connexion.php">Se connecter</a>
        </nav>
    </div>
</header>
