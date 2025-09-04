<?php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>fleurs</title>
    <link rel="stylesheet" href="css/styleFleurUnique.css">
</head>
<?php
include 'includes/header.php';
?>

<body>
<h1 class="section-title">Nos fleurs</h1>
<section class="catalogue">


    <div class="produit">
        <div class="rose">
            <img src="img/rosesBlanche.png" alt="Rose" class="product-img" width="500px"/>
        </div>

        <div class="produit-info">
               <h3 class="product-title">Fleurs</h3>
               <p class="product-desc">
                   Une rose incarne l’unicité. Elle est le symbole d’un amour né au premier regard.
               </p>

            <div class="rose">
                <img src="img/rouge.png"   class="img-rose rouge"   alt=" ">
                <img src="img/rose.png"    class="img-rose claire"  alt="">
                <img src="img/rose_claire.png" class="img-rose foncee" alt="">
                <img src="img/rosesBlanche.png" class="img-rose blanche" alt="">
                <img src="img/noir.png"   class="img-rose noire"   alt="">
                <img src="img/bleu.png"   class="img-rose bleue"   alt="">
            </div>
            <!-- Variantes couleur -->
            <fieldset class="swatches" aria-label="Couleur de la rose">
                <!-- Chaque pastille transporte l'image correspondante en data-img -->
                <label class="swatch" title="Rouge">
                    <input type="radio" name="rose-color-1" checked
                           data-img="assets/img/rose-rouge.png" />
                    <span style="--swatch:#d1121b"></span>
                </label>

                <label class="swatch" title="Rose">
                    <input type="radio" name="rose-color-1"
                           data-img="assets/img/rose-bordeaux.png" />
                    <span style="--swatch:#8b0005"></span>
                </label>

                <label class="swatch" title="Rose_claire">
                    <input type="radio" name="rose-color-1"
                           data-img="assets/img/rose-rose.png" />
                    <span style="--swatch:#ffa0c4"></span>
                </label>

                <label class="swatch" title="Blanc">
                    <input type="radio" name="rose-color-1"
                           data-img="assets/img/rose-blanche.png" />
                    <span style="--swatch:#e9e9e9"></span>
                </label>

                <label class="swatch" title="Noir">
                    <input type="radio" name="rose-color-1"
                           data-img="assets/img/rose-noire.png" />
                    <span style="--swatch:#111"></span>
                </label>

                <label class="swatch" title="Bleu">
                    <input type="radio" name="rose-color-1"
                           data-img="assets/img/rose-bleue.png" />
                    <span style="--swatch:#0418a5"></span>
                </label>

            </fieldset>
            <button class="btn">Sélectionner</button>
        </div>
    </div>
    <div class="btn_accueil">
        <!--<a class="btn_index" href="">Découvrir nos créations</a>-->
        <a href="interface_supplement.php" class="button">suivant</a>
    </div>
</section>
<?php
include 'includes/footer.php';
?>

</body>