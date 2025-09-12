<?php
// /site/pages/interface_emballage.php
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
    <title>DK Bloom — Emballages</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier (contient callApi, addEmballage, animations, toast, etc.) -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>

    <!-- Hook léger pour empêcher la soumission et appeler addEmballage() -->
    <script>
        function addEmballageForm(form){
            event?.preventDefault();
            const embInput = form.querySelector('input[name="emb_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            if(!embInput) return false;
            // Appelle la fonction globale (commande.js) : remplace l’ancien emballage si besoin
            window.addEmballage?.(embInput.value, btn);
            return false;
        }
    </script>

    <!-- Garde-fou visuel (au cas où) pour garder les cartes compactes -->
    <style>
        .catalogue{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;justify-items:center}
        .catalogue .card.product{background:#fff;padding:12px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.1);text-align:center;max-width:240px}
        .catalogue .card.product img{max-width:180px;height:auto;display:block;margin:0 auto 8px;border-radius:8px}
        .price{font-weight:600}
        .sr-only{position:absolute;left:-9999px}
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page" role="main">
    <h1 class="section-title">Emballages</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <p class="muted" style="text-align:center;margin:-6px 0 16px;">
        Un seul emballage peut être sélectionné par commande. Ajouter un nouvel emballage remplace l’actuel.
    </p>

    <div class="catalogue" aria-label="Liste d'emballages">
        <!-- Emballage Classique -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addEmballageForm(this)">
            <input type="hidden" name="emb_id" value="1">
            <img src="<?= $BASE ?>img/emb_classique.png" alt="Emballage Classique" loading="lazy">
            <h3>Emballage Classique</h3>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage Classique">Ajouter</button>
        </form>

        <!-- Emballage Luxe -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addEmballageForm(this)">
            <input type="hidden" name="emb_id" value="2">
            <img src="<?= $BASE ?>img/emb_luxe.png" alt="Emballage Luxe" loading="lazy">
            <h3>Emballage Luxe</h3>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage Luxe">Ajouter</button>
        </form>

        <!-- Boîte ronde -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addEmballageForm(this)">
            <input type="hidden" name="emb_id" value="3">
            <img src="<?= $BASE ?>img/emb_boite_ronde.png" alt="Boîte ronde" loading="lazy">
            <h3>Boîte ronde</h3><p class="price">20 CHF</p>
            <button type="submit" class="add-to-cart" data-emb-name="Boîte ronde">Ajouter</button>
        </form>

        <!-- Boîte cœur -->
        <form class="card product" action="<?= $BASE ?>traitement_commande_add.php" method="POST" onsubmit="return addEmballageForm(this)">
            <input type="hidden" name="emb_id" value="4">
            <img src="<?= $BASE ?>img/emb_boite_coeur.png" alt="Boîte cœur" loading="lazy">
            <h3>Boîte cœur</h3><p class="price">22 CHF</p>
            <button type="submit" class="add-to-cart" data-emb-name="Boîte cœur">Ajouter</button>
        </form>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
