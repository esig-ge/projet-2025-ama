<?php
// Base URL avec slash final (relatif à /site/pages/)
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Catalogue bouquet</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose $BASE + API_URL au JS -->
    <script>
        // ex: "/site/pages/"
        window.DKBASE  = <?= json_encode($BASE) ?>;
        // on pointe sur le PROXY sous /site/pages/api/
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page">
    <h1 class="section-title">Catalogue bouquet</h1>

    <div id="produit_bouquet">
        <div>
            <img src="<?= $BASE ?>img/12Roses.png" alt="12 roses">
            <h3>12 roses</h3>
            <p>30 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="7"
                    data-pro-name="Bouquet 12 roses"
                    data-pro-img="<?= $BASE ?>img/12Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/20Roses.png" alt="20 roses">
            <h3>20 roses</h3>
            <p>40 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="8"
                    data-pro-name="Bouquet 20 roses"
                    data-pro-img="<?= $BASE ?>img/20Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/20Roses.png" alt="24 roses">
            <h3>24 roses</h3>
            <p>45 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="9"
                    data-pro-name="Bouquet 24 roses"
                    data-pro-img="<?= $BASE ?>img/20Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/36Roses.png" alt="36 roses">
            <h3>36 roses</h3>
            <p>60 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="10"
                    data-pro-name="Bouquet 36 roses"
                    data-pro-img="<?= $BASE ?>img/36Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/50Roses.png" alt="50 roses">
            <h3>50 roses</h3>
            <p>70 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="11"
                    data-pro-name="Bouquet 50 roses"
                    data-pro-img="<?= $BASE ?>img/50Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/66Roses.png" alt="66 roses">
            <h3>66 roses</h3>
            <p>85 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="12"
                    data-pro-name="Bouquet 66 roses"
                    data-pro-img="<?= $BASE ?>img/66Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/100Roses.png" alt="99 roses">
            <h3>99 roses</h3>
            <p>110 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="13"
                    data-pro-name="Bouquet 99 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/100Roses.png" alt="100 roses">
            <h3>100 roses</h3>
            <p>112 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="14"
                    data-pro-name="Bouquet 100 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/100Roses.png" alt="101 roses">
            <h3>101 roses</h3>
            <p>115 CHF</p>
            <button class="add-to-cart"
                    data-pro-id="15"
                    data-pro-name="Bouquet 101 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>
    </div>

    <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
