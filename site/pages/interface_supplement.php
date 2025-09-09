<?php
// Base URL avec slash final
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Suppléments</title>

    <!-- CSS global -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS spécifique -->
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title">Suppléments</h1>

    <div class="catalogue">
        <div>
            <img src="<?= $BASE ?>img/ours_blanc.PNG" alt="Mini ourson">
            <h3>Mini ourson</h3>
            <p>2 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="1"
                    data-sup-name="Mini ourson"
                    data-sup-img="<?= $BASE ?>img/ours_blanc.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/happybirthday.PNG" alt="Décoration anniversaire">
            <h3>Décoration anniversaire</h3>
            <p>2 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="2"
                    data-sup-name="Décoration anniversaire"
                    data-sup-img="<?= $BASE ?>img/happybirthday.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/papillon_doree.PNG" alt="Papillons">
            <h3>Papillons</h3>
            <p>2 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="3"
                    data-sup-name="Papillons"
                    data-sup-img="<?= $BASE ?>img/papillon_doree.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/baton_coeur.PNG" alt="Bâton cœur">
            <h3>Bâton cœur</h3>
            <p>2 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="4"
                    data-sup-name="Bâton cœur"
                    data-sup-img="<?= $BASE ?>img/baton_coeur.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/diamant.PNG" alt="Diamant">
            <h3>Diamant</h3>
            <p>5 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="5"
                    data-sup-name="Diamant"
                    data-sup-img="<?= $BASE ?>img/diamant.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/couronne.PNG" alt="Couronne">
            <h3>Couronne</h3>
            <p>5 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="6"
                    data-sup-name="Couronne"
                    data-sup-img="<?= $BASE ?>img/couronne.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/paillette_argent.PNG" alt="Paillettes">
            <h3>Paillettes</h3>
            <p>9 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="7"
                    data-sup-name="Paillettes"
                    data-sup-img="<?= $BASE ?>img/paillette_argent.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/lettre.JPG" alt="Lettre">
            <h3>Lettre</h3>
            <p>10 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="8"
                    data-sup-name="Lettre"
                    data-sup-img="<?= $BASE ?>img/lettre.JPG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/carte.PNG" alt="Carte pour mot">
            <h3>Carte pour mot</h3>
            <p>3 CHF</p>
            <button class="add-to-cart"
                    data-sup-id="9"
                    data-sup-name="Carte pour mot"
                    data-sup-img="<?= $BASE ?>img/carte.PNG"
                    onclick="addSupplement(this.dataset.supId, this, this.dataset.supName, this.dataset.supImg)">
                Ajouter
            </button>
        </div>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_catalogue_bouquet.php" class="button">Retour</a>
        <a href="<?= $BASE ?>interface_emballage.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
