<?php
// Base URL relative au script courant (toujours avec un slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<header class="site-header" role="banner">
    <div class="header">
        <!-- Logo -->
        <a href="<?= $BASE ?>index.php" class="logo" aria-label="DK Bloom — retour à l'accueil">
            <img src="<?= $BASE ?>img/logo.jpg" alt="DK Bloom" width="120" height="auto" loading="lazy">
        </a>

        <!-- Bouton menu mobile -->
        <button class="menu-toggle" aria-expanded="false" aria-label="Menu principal">☰</button>

        <!-- Navigation principale -->
        <nav class="menu" data-nav role="navigation" aria-label="Navigation principale">
            <a href="../index.php">Accueil</a>
            <a href="../apropos.php">À propos</a>
            <a href="../interface_selection_produit.php">Catalogue</a>
            <a href="../contact.php">Contact</a>
            <a href="../inscription.php">S'inscrire</a>
            <a href="../interface_connexion.php">Se connecter</a>
        </nav>
    </div>
</header>
