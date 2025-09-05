<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/styleCatalogue.css">
    <title>Catalogue bouquet</title>

    <!-- charge le JS panier (API) -->
    <script src="/site/pages/js/helpers.js?v=1" defer></script>
    <script src="/site/pages/js/commande.js" defer></script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <h1>Catalogue bouquet</h1>
    <div id="produit_bouquet">
        <div>
            <img src="img/12Roses.png" alt="12 roses">
            <h3>12 roses</h3>
            <p>30 CHF</p>
            <button class="add-to-cart" data-pro-id="7">Ajouter</button>
        </div>

        <div>
            <img src="img/20Roses.png" alt="20 roses">
            <h3>20 roses</h3>
            <p>40 CHF</p>
            <button class="add-to-cart" data-pro-id="8">Ajouter</button>
        </div>

        <div>
            <img src="img/20Roses.png" alt="24 roses">
            <h3>24 roses</h3>
            <p>45 CHF</p>
            <button class="add-to-cart" data-pro-id="9">Ajouter</button>
        </div>

        <div>
            <img src="img/36Roses.png" alt="36 roses">
            <h3>36 roses</h3>
            <p>60 CHF</p>
            <button class="add-to-cart" data-pro-id="10">Ajouter</button>
        </div>

        <div>
            <img src="img/50Roses.png" alt="50 roses">
            <h3>50 roses</h3>
            <p>70 CHF</p>
            <button class="add-to-cart" data-pro-id="11">Ajouter</button>
        </div>

        <div>
            <img src="img/66Roses.png" alt="66 roses">
            <h3>66 roses</h3>
            <p>85 CHF</p>
            <button class="add-to-cart" data-pro-id="12">Ajouter</button>
        </div>

        <div>
            <img src="img/100Roses.png" alt="99 roses">
            <h3>99 roses</h3>
            <p>110 CHF</p>
            <button class="add-to-cart" data-pro-id="13">Ajouter</button>
        </div>

        <div>
            <img src="img/100Roses.png" alt="100 roses">
            <h3>100 roses</h3>
            <p>112 CHF</p>
            <button class="add-to-cart" data-pro-id="14">Ajouter</button>
        </div>

        <div>
            <img src="img/100Roses.png" alt="101 roses">
            <h3>101 roses</h3>
            <p>115 CHF</p>
            <button class="add-to-cart" data-pro-id="15">Ajouter</button>
        </div>
    </div>

    <a href="interface_supplement.php" class="button">Suivant</a>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
