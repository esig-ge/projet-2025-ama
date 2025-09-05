<?php ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>fleurs</title>
    <link rel="stylesheet" href="css/styleFleurUnique.css">
</head>

<body>
<?php include 'includes/header.php'; ?>

<h1 class="section-title">Nos fleurs</h1>

<section class="catalogue">
    <div class="produit">
        <div class="produit-info">
            <h3 class="product-title">La rose</h3>
            <p class="product-desc">
                Elle est le symbole d’un amour né au premier regard. Et incarne l’unicité.
            </p>
        <!-- 1) Radios cachés AVANT .rose et au même niveau -->
        <input type="radio" id="c-rouge"  name="rose-color" class="color-radio" checked>
        <input type="radio" id="c-rose"   name="rose-color" class="color-radio">
        <input type="radio" id="c-roseC"  name="rose-color" class="color-radio">
        <input type="radio" id="c-blanc"  name="rose-color" class="color-radio">
        <input type="radio" id="c-noir"   name="rose-color" class="color-radio">
        <input type="radio" id="c-bleu"   name="rose-color" class="color-radio">

        <!-- 2) Les pastilles (labels) déclenchent chaque radio via l’attribut for -->


        <!-- 3) Toutes les images sont présentes mais cachées par défaut -->
        <div class="rose">
            <img src="img/rouge.png"          class="img-rose rouge"   alt="Rose rouge"   width="500px">
            <img src="img/rose.png"           class="img-rose rose"    alt="Rose"         width="500px">
            <img src="img/rose_claire.png"    class="img-rose roseC"   alt="Rose claire"  width="500px">
            <img src="img/rosesBlanche.png"   class="img-rose blanche" alt="Rose blanche" width="500px">
            <img src="img/noir.png"           class="img-rose noire"   alt="Rose noire"   width="500px">
            <img src="img/bleu.png"           class="img-rose bleue"   alt="Rose bleue"   width="500px">
        </div>

            <fieldset class="swatches" aria-label="Couleur de la rose">
                <label class="swatch" for="c-rouge"  title="Rouge"><span style="--swatch:red"></span></label>
                <label class="swatch" for="c-rose"   title="Rose"><span style="--swatch:#ffa0c4"></span></label>
                <label class="swatch" for="c-roseC"  title="Rose claire"><span style="--swatch:pink"></span></label>
                <label class="swatch" for="c-blanc"  title="Blanc"><span style="--swatch:#e9e9e9"></span></label>
                <label class="swatch" for="c-noir"   title="Noir"><span style="--swatch:#111"></span></label>
                <label class="swatch" for="c-bleu"   title="Bleu"><span style="--swatch:#0418a5"></span></label>
            </fieldset>
            <button class="btn">Sélectionner</button>
        </div>
    </div>

    <div class="btn_accueil">
        <a href="catalogue.php" class="button">Retour</a>
        <a href="interface_supplement.php" class="button">suivant</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>
