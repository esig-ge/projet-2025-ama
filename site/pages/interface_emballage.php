<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/styleCatalogue.css">
    <title>emballages</title>
</head>

<?php
include 'includes/header.php';
?>
<body>

<h1>Emballage</h1>
<div id="emballage">
    <div>
        <img src="img/emballage_blanc.PNG" alt="Emballage blanc">
        <button class="add-to-cart"
                data-id="8"
        data-name="20 roses"
        data-price="40"
        data-img="assets/img/20Roses.png">
        Ajouter
        </button>
    </div>

    <div>
        <img src="img/emballage_noir.PNG" alt="Emballage noir">
        <button class="add-to-cart"
                data-id="BQ-20"
                data-sku="BQ-20"
                data-name="20 roses"
                data-price="40"
                data-img="assets/img/20Roses.png">
            Ajouter
        </button>
    </div>

    <div>
        <img src="img/emballage_rose.PNG" alt="Emballage rose">
        <button class="add-to-cart"
                data-id="BQ-20"
                data-sku="BQ-20"
                data-name="20 roses"
                data-price="40"
                data-img="assets/img/20Roses.png">
            Ajouter
        </button>
    </div>

    <div>
        <img src="img/emballage_gris.PNG" alt="Emballage gris">
        <button class="add-to-cart"
                data-id="BQ-20"
                data-sku="BQ-20"
                data-name="20 roses"
                data-price="40"
                data-img="assets/img/20Roses.png">
            Ajouter
        </button>
    </div>

    <div>
        <img src="img/emballage_violet.PNG" alt="Emballage violet">
        <button class="add-to-cart"
                data-id="BQ-20"
                data-sku="BQ-20"
                data-name="20 roses"
                data-price="40"
                data-img="assets/img/20Roses.png">
            Ajouter
        </button>
    </div>
</div>

<a href="interface_supplement.php" class="button">Retour</a>
<a href="commande.php"
   class="button"
   data-id="8"
data-name="20 roses"
data-price="40"
data-img="assets/img/20Roses.png">
Suivant
</a>

<script src="js/commande.js"></script>
</body>
</html>

