<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/styleCatalogue.css">
    <title>Emballages</title>
    <script src="js/commande.js" defer></script>
</head>
<body>
<?php include 'includes/header.php'; ?>

<h1>Emballage</h1>

<div id="emballage" class="catalogue">
    <div>
        <img src="img/emballage_blanc.PNG" alt="Emballage blanc">
        <button class="add-to-cart" data-id="1">Ajouter</button>
    </div>

    <div>
        <img src="img/emballage_noir.PNG" alt="Emballage noir">
        <button class="add-to-cart" data-id="2">Ajouter</button>
    </div>

    <div>
        <img src="img/emballage_rose.PNG" alt="Emballage rose">
        <button class="add-to-cart" data-id="3">Ajouter</button>
    </div>

    <div>
        <img src="img/emballage_gris.PNG" alt="Emballage gris">
        <button class="add-to-cart" data-id="4">Ajouter</button>
    </div>

    <div>
        <img src="img/emballage_violet.PNG" alt="Emballage violet">
        <button class="add-to-cart" data-id="5">Ajouter</button>
    </div>
</div>

<div class="nav-actions">
    <a href="interface_supplement.php" class="button">Retour</a>
    <a href="commande.php" class="button">Suivant</a>
</div>

</body>
</html>
