<?php
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Emballages</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title">Emballage</h1>

    <div id="emballage" class="catalogue">
        <div>
            <img src="<?= $BASE ?>img/emballage_blanc.PNG" alt="Emballage blanc">
            <button class="add-to-cart"
                    data-emb-id="1"
                    data-emb-name="Emballage blanc"
                    data-emb-img="<?= $BASE ?>img/emballage_blanc.PNG"
                    onclick="addEmballage(this.dataset.embId, this, this.dataset.embName, this.dataset.embImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_noir.PNG" alt="Emballage noir">
            <button class="add-to-cart"
                    data-emb-id="2"
                    data-emb-name="Emballage noir"
                    data-emb-img="<?= $BASE ?>img/emballage_noir.PNG"
                    onclick="addEmballage(this.dataset.embId, this, this.dataset.embName, this.dataset.embImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_rose.PNG" alt="Emballage rose">
            <button class="add-to-cart"
                    data-emb-id="3"
                    data-emb-name="Emballage rose"
                    data-emb-img="<?= $BASE ?>img/emballage_rose.PNG"
                    onclick="addEmballage(this.dataset.embId, this, this.dataset.embName, this.dataset.embImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_gris.PNG" alt="Emballage gris">
            <button class="add-to-cart"
                    data-emb-id="4"
                    data-emb-name="Emballage gris"
                    data-emb-img="<?= $BASE ?>img/emballage_gris.PNG"
                    onclick="addEmballage(this.dataset.embId, this, this.dataset.embName, this.dataset.embImg)">
                Ajouter
            </button>
        </div>

        <div>
            <img src="<?= $BASE ?>img/emballage_violet.PNG" alt="Emballage violet">
            <button class="add-to-cart"
                    data-emb-id="5"
                    data-emb-name="Emballage violet"
                    data-emb-img="<?= $BASE ?>img/emballage_violet.PNG"
                    onclick="addEmballage(this.dataset.embId, this, this.dataset.embName, this.dataset.embImg)">
                Ajouter
            </button>
        </div>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
