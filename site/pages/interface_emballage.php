<?php
session_start();
// Base URL avec slash final (pour chemins robustes)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Emballages</title>

    <!-- CSS global (header/footer) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS spécifique catalogue -->
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- JS panier / actions -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title">Emballage</h1>

    <div id="emballage" class="catalogue">
        <div>
            <img src="<?= $BASE ?>img/emballage_blanc.PNG" alt="Emballage blanc">
            <button class="add-to-cart" data-emb-id="1">Ajouter</button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_noir.PNG" alt="Emballage noir">
            <button class="add-to-cart" data-emb-id="2">Ajouter</button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_rose.PNG" alt="Emballage rose">
            <button class="add-to-cart" data-emb-id="3">Ajouter</button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_gris.PNG" alt="Emballage gris">
            <button class="add-to-cart" data-emb-id="4">Ajouter</button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_violet.PNG" alt="Emballage violet">
            <button class="add-to-cart" data-emb-id="5">Ajouter</button>
        </div>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
