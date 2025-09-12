<?php
// /site/pages/interface_catalogue_bouquet.php
session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Catalogue bouquet</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page" role="main">
    <h1 class="section-title">Catalogue bouquet</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div id="produit_bouquet" class="catalogue" aria-label="Liste de bouquets">
        <!-- 12 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="7">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/12Roses.png" alt="Bouquet 12 roses" loading="lazy">
            <h3>12 roses</h3><p class="price">30 CHF</p>
            <label class="sr-only" for="qty-7">Quantité</label>
            <input id="qty-7" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 20 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="8">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/20Roses.png" alt="Bouquet de 20 roses" loading="lazy">
            <h3>20 roses</h3><p class="price">40 CHF</p>
            <label class="sr-only" for="qty-8">Quantité</label>
            <input id="qty-8" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 24 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="9">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/20Roses.png" alt="Bouquet de 24 roses" loading="lazy">
            <h3>24 roses</h3><p class="price">45 CHF</p>
            <label class="sr-only" for="qty-9">Quantité</label>
            <input id="qty-9" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 36 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="10">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/36Roses.png" alt="Bouquet de 36 roses" loading="lazy">
            <h3>36 roses</h3><p class="price">60 CHF</p>
            <label class="sr-only" for="qty-10">Quantité</label>
            <input id="qty-10" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 50 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="11">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/50Roses.png" alt="Bouquet de 50 roses" loading="lazy">
            <h3>50 roses</h3><p class="price">70 CHF</p>
            <label class="sr-only" for="qty-11">Quantité</label>
            <input id="qty-11" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 66 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="12">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/66Roses.png" alt="Bouquet de 66 roses" loading="lazy">
            <h3>66 roses</h3><p class="price">85 CHF</p>
            <label class="sr-only" for="qty-12">Quantité</label>
            <input id="qty-12" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 99 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="13">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 99 roses" loading="lazy">
            <h3>99 roses</h3><p class="price">110 CHF</p>
            <label class="sr-only" for="qty-13">Quantité</label>
            <input id="qty-13" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 100 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="14">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 100 roses" loading="lazy">
            <h3>100 roses</h3><p class="price">112 CHF</p>
            <label class="sr-only" for="qty-14">Quantité</label>
            <input id="qty-14" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>

        <!-- 101 roses -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST">
            <input type="hidden" name="pro_id" value="15">
            <input type="hidden" name="type" value="bouquet">
            <img src="<?= $BASE ?>img/100Roses.png" alt="Bouquet de 101 roses" loading="lazy">
            <h3>101 roses</h3><p class="price">115 CHF</p>
            <label class="sr-only" for="qty-15">Quantité</label>
            <input id="qty-15" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <button type="submit" class="add-to-cart">Ajouter</button>
        </form>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>index.php" class="button">Retour</a>
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
