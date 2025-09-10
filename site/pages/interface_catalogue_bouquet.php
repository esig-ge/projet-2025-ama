<?php
// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Parent de /pages/ → "/…/site/"
$SITE_BASE = preg_replace('#/pages/$#', '/', $BASE);

// Détection de l'API (on vérifie sur le système de fichiers)
$api_in_pages = is_file(__DIR__ . '/api/cart.php');           // /site/pages/api/cart.php
$api_in_site  = is_file(dirname(__DIR__) . '/api/cart.php');  // /site/api/cart.php

$API_URL = $api_in_pages ? ($BASE . 'api/cart.php')
    : ($api_in_site  ? ($SITE_BASE . 'api/cart.php')
        : ($BASE . 'api/cart.php')); // fallback
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Catalogue bouquet</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;               // "/…/site/pages/"
        window.API_URL = <?= json_encode($API_URL) ?>;             // auto: "/…/site/pages/api/cart.php" ou "/…/site/api/cart.php"
    </script>
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page" role="main">
    <h1 class="section-title">Catalogue bouquet</h1>

    <div id="produit_bouquet" class="catalogue" aria-label="Liste de bouquets">
        <div class="card product">
            <img src="<?= $BASE ?>img/12Roses.png" alt="Bouquet 12 roses" loading="lazy">
            <h3>12 roses</h3><p class="price">30 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="7"
                    data-pro-name="Bouquet 12 roses"
                    data-pro-img="<?= $BASE ?>img/12Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/20Roses.png" alt="Bouquet de 20 roses" loading="lazy">
            <h3>20 roses</h3>
            <p class="price">40 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="8"
                    data-pro-name="Bouquet 20 roses"
                    data-pro-img="<?= $BASE ?>img/20Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/20Roses.png" alt="Bouquet de 24 roses" loading="lazy">
            <h3>24 roses</h3>
            <p class="price">45 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">

            <button class="add-to-cart"
                    data-pro-id="9"
                    data-pro-name="Bouquet 24 roses"
                    data-pro-img="<?= $BASE ?>img/20Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/36Roses.png" alt="Bouquet de 36 roses" loading="lazy">
            <h3>36 roses</h3>
            <p class="price">60 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="10"
                    data-pro-name="Bouquet 36 roses"
                    data-pro-img="<?= $BASE ?>img/36Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/50Roses.png" alt="Bouquet de 50 roses" loading="lazy">
            <h3>50 roses</h3>
            <p class="price">70 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="11"
                    data-pro-name="Bouquet 50 roses"
                    data-pro-img="<?= $BASE ?>img/50Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/66Roses.png" alt="Bouquet de 66 roses" loading="lazy">
            <h3>66 roses</h3>
            <p class="price">85 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="12"
                    data-pro-name="Bouquet 66 roses"
                    data-pro-img="<?= $BASE ?>img/66Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 99 roses" loading="lazy">
            <h3>99 roses</h3>
            <p class="price">110 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="13"
                    data-pro-name="Bouquet 99 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 100 roses" loading="lazy">
            <h3>100 roses</h3>
            <p class="price">112 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="14"
                    data-pro-name="Bouquet 100 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>

        <div class="card product">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 101 roses" loading="lazy">
            <h3>101 roses</h3>
            <p class="price">115 CHF</p>
            <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">
            <button class="add-to-cart"
                    data-pro-id="15"
                    data-pro-name="Bouquet 101 roses"
                    data-pro-img="<?= $BASE ?>img/100Roses.png"
                    onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                Ajouter
            </button>
        </div>
        <div>
            <!-- Radios (portent l'ID produit + nom + image) -->
            <h3>Selectionner la couleur du bouquet</h3>
            <input type="radio" id="c-rouge"  name="rose-color" class="color-radio"
                   data-pro-id="1" data-name="Rose rouge"  data-img="<?= $BASE ?>img/rouge.png" >
            <input type="radio" id="c-rose"   name="rose-color" class="color-radio"
                   data-pro-id="2" data-name="Rose rose"   data-img="<?= $BASE ?>img/rose.png">
            <input type="radio" id="c-roseC"  name="rose-color" class="color-radio"
                   data-pro-id="3" data-name="Rose claire" data-img="<?= $BASE ?>img/rose_claire.png">
            <input type="radio" id="c-blanc"  name="rose-color" class="color-radio"
                   data-pro-id="4" data-name="Rose blanche" data-img="<?= $BASE ?>img/rosesBlanche.png">
            <input type="radio" id="c-noir"   name="rose-color" class="color-radio"
                   data-pro-id="5" data-name="Rose noire"   data-img="<?= $BASE ?>img/noir.png">
            <input type="radio" id="c-bleu"   name="rose-color" class="color-radio"
                   data-pro-id="6" data-name="Rose bleue"   data-img="<?= $BASE ?>img/bleu.png">

            <fieldset class="swatches" aria-label="Couleur de la rose">
                <label class="swatch" for="c-rouge"  title="Rouge"><span style="--swatch:red"></span></label>
                <label class="swatch" for="c-rose"   title="Rose"><span style="--swatch:#ffa0c4"></span></label>
                <label class="swatch" for="c-roseC"  title="Rose claire"><span style="--swatch:pink"></span></label>
                <label class="swatch" for="c-blanc"  title="Blanc"><span style="--swatch:#e9e9e9"></span></label>
                <label class="swatch" for="c-noir"   title="Noir"><span style="--swatch:#111"></span></label>
                <label class="swatch" for="c-bleu"   title="Bleu"><span style="--swatch:#0418a5"></span></label>
            </fieldset>

        </div>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
