<?php
// Base URL (toujours slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Coffrets</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

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
    <h1 class="section-title">Nos coffrets</h1>

    <div class="catalogue">
        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt=" coffret Anniversaire" loading="lazy">
                <h3>Anniversaire</h3>
                <p>90 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="16"
                        data-pro-name="Coffret Anniversaire"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>
            </form>
        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Saint-Valentin">
                <h3>Saint-Valentin</h3>
                <p>90 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="17"
                        data-pro-name="Coffret Saint-Valentin"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>
            </form>
        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Fête des mères">
                <h3>Fête des mères</h3>
                <p>100 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="18"
                        data-pro-name="Coffret Fête des mères"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>
            </form>

        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">

                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Baptême">
                <h3>Baptême</h3>
                <p>100 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="19"
                        data-pro-name="Coffret Baptême"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>
            </form>

        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Mariage">
                <h3>Mariage</h3>
                <p>100 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="20"
                        data-pro-name="Coffret Mariage"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>

            </form>

        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Pâques">
                <h3>Pâques</h3>
                <p>100 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="21"
                        data-pro-name="Coffret Pâques"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>

            </form>

        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">
                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Noël">
                <h3>Noël</h3>
                <p>100 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="22"
                        data-pro-name="Coffret Noël"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>

            </form>

        </div>

        <div>
            <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addBouquetForm(this)">

                <img src="<?= $BASE ?>img/coffret.png" alt="Coffret Nouvel an">
                <h3>Nouvel an</h3>
                <p>150 CHF</p>
                <label class="sr-only" for="qty-15">Quantité</label>
                <button class="add-to-cart"
                        data-pro-id="23"
                        data-pro-name="Coffret Nouvel an"
                        data-pro-img="<?= $BASE ?>img/coffret.png"
                        onclick="addToCart(this.dataset.proId, this, this.dataset.proName, this.dataset.proImg)">
                    Ajouter
                </button>
            </form>

        </div>

    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>catalogue.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
