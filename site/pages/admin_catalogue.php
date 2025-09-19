<?php

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // ex: /2526_grep/t25_6_v21/site/pages/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Nos produits</title>

    <!-- CSS global header/footer + CSS de la page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
</head>
<body id="body_prod">

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<main>


    <div id="select_produit">
<div></div>
            <a href="<?= $BASE ?>admin_catalogue_fleur.php">Fleurs</a>
            <a href="<?= $BASE ?>interface_catalogue_bouquet.php">Bouquets</a>
            <a href="<?= $BASE ?>coffret.php">Coffret</a>
            <a href="<?= $BASE ?>supplément.php">Supplément</a>
            <a href="<?= $BASE ?>emballage.php">Emballage</a>


    </div>
    </div>
</main>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<!-- JS global si besoin -->
<script src="<?= $BASE ?>js/script.js" defer></script>
</body>
</html>
