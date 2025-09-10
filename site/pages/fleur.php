<?php
// Base URL (slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Fleurs</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleFleurUnique.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <section class="catalogue">
        <div class="produit">
            <div class="produit-info">
                <h3 class="product-title">La rose</h3>
                <p class="product-desc">
                    Elle est le symbole d’un amour né au premier regard. Et incarne l’unicité.
                </p>

                <!-- Radios (portent l'ID produit + nom + image) -->
                <input type="radio" id="c-rouge"  name="rose-color" class="color-radio"
                       data-pro-id="1" data-name="Rose rouge"  data-img="<?= $BASE ?>img/rouge.png" checked>
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

                <!-- Zone image (tes 6 images superposées comme avant) -->
                <div class="rose">
                    <img src="<?= $BASE ?>img/rouge.png"        class="img-rose rouge"   alt="Rose rouge"   width="500">
                    <img src="<?= $BASE ?>img/rose.png"         class="img-rose rose"    alt="Rose"         width="500">
                    <img src="<?= $BASE ?>img/rose_claire.png"  class="img-rose roseC"   alt="Rose claire"  width="500">
                    <img src="<?= $BASE ?>img/rosesBlanche.png" class="img-rose blanche" alt="Rose blanche" width="500">
                    <img src="<?= $BASE ?>img/noir.png"         class="img-rose noire"   alt="Rose noire"   width="500">
                    <img src="<?= $BASE ?>img/bleu.png"         class="img-rose bleue"   alt="Rose bleue"   width="500">
                </div>

                <!-- Pastilles (labels) -->
                <fieldset class="swatches" aria-label="Couleur de la rose">
                    <label class="swatch" for="c-rouge"  title="Rouge"><span style="--swatch:red"></span></label>
                    <label class="swatch" for="c-rose"   title="Rose"><span style="--swatch:#ffa0c4"></span></label>
                    <label class="swatch" for="c-roseC"  title="Rose claire"><span style="--swatch:pink"></span></label>
                    <label class="swatch" for="c-blanc"  title="Blanc"><span style="--swatch:#e9e9e9"></span></label>
                    <label class="swatch" for="c-noir"   title="Noir"><span style="--swatch:#111"></span></label>
                    <label class="swatch" for="c-bleu"   title="Bleu"><span style="--swatch:#0418a5"></span></label>
                </fieldset>
                <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">

                <!-- Sélectionner = ajoute au panier la radio cochée -->
                <button class="btn" onclick="selectRose(this)">Sélectionner</button>
            </div>
        </div>

        <div class="btn_accueil">
            <a href="<?= $BASE ?>catalogue.php" class="button">Retour</a>
            <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
