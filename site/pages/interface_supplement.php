<?php
// /site/pages/interface_supplement.php
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
    <title>DK Bloom — Suppléments</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier -->
    <script src="<?= $BASE ?>js/commande.js?v=supp-qty1" defer></script>

    <!-- Hook: empêche le submit, lit qty, passe à addSupplement -->
    <script>
        function addSupplementForm(form, evt){
            (evt || window.event)?.preventDefault();

            const supInput = form.querySelector('input[name="sup_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty');

            if(!supInput || !btn) return false;

            const qty = Math.max(1, parseInt(qtyInput?.value ?? '1', 10) || 1);

            // Rendre la qty dispo à resolveQty(btn)
            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            // Appel JS global (commande.js)
            window.addSupplement?.(supInput.value, btn);
            return false;
        }
    </script>

    <style>
        /* Grille compacte et responsive */
        #supp-grid{
            display: grid !important;
            gap: 20px;
            justify-items: center;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        }
        @media (min-width: 1500px){
            #supp-grid{ grid-template-columns: repeat(5, minmax(0, 1fr)); }
        }
        #supp-grid .card.product{
            background:#fff; padding:12px; border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,.1);
            text-align:center; width:100%; max-width:260px;
        }
        #supp-grid .card.product img {
            display: block;
            margin: 0 auto 8px;
            border-radius: 8px;
            max-width: 180px;
            height: auto;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page" role="main">
    <h1 class="section-title">Suppléments</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div id="supp-grid" class="catalogue" aria-label="Liste de suppléments">

        <!-- Mini ourson -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="1">
            <img src="<?= $BASE ?>img/ours_blanc.PNG" alt="Mini ourson" loading="lazy">
            <h3>Mini ourson</h3><p class="price">2 CHF</p>
            <label class="sr-only" for="qty-sup-1">Quantité</label>
            <input id="qty-sup-1" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Mini ourson">Ajouter</button>
        </form>

        <!-- Décoration anniversaire -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="2">
            <img src="<?= $BASE ?>img/happybirthday.PNG" alt="Décoration anniversaire" loading="lazy">
            <h3>Déco anniv</h3><p class="price">2 CHF</p>
            <label class="sr-only" for="qty-sup-2">Quantité</label>
            <input id="qty-sup-2" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Décoration anniversaire">Ajouter</button>
        </form>

        <!-- Papillons -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="3">
            <img src="<?= $BASE ?>img/papillon_doree.PNG" alt="Papillons dorés" loading="lazy">
            <h3>Papillons</h3><p class="price">2 CHF</p>
            <label class="sr-only" for="qty-sup-3">Quantité</label>
            <input id="qty-sup-3" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Papillons">Ajouter</button>
        </form>

        <!-- Bâton cœur -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="4">
            <img src="<?= $BASE ?>img/baton_coeur.PNG" alt="Bâton cœur" loading="lazy">
            <h3>Bâton cœur</h3><p class="price">2 CHF</p>
            <label class="sr-only" for="qty-sup-4">Quantité</label>
            <input id="qty-sup-4" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Bâton cœur">Ajouter</button>
        </form>

        <!-- Diamant -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="5">
            <img src="<?= $BASE ?>img/diamant.PNG" alt="Diamant décoratif" loading="lazy">
            <h3>Diamant</h3><p class="price">5 CHF</p>
            <label class="sr-only" for="qty-sup-5">Quantité</label>
            <input id="qty-sup-5" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Diamant">Ajouter</button>
        </form>

        <!-- Couronne -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="6">
            <img src="<?= $BASE ?>img/couronne.PNG" alt="Couronne décorative" loading="lazy">
            <h3>Couronne</h3><p class="price">5 CHF</p>
            <label class="sr-only" for="qty-sup-6">Quantité</label>
            <input id="qty-sup-6" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Couronne">Ajouter</button>
        </form>

        <!-- Paillettes -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="7">
            <img src="<?= $BASE ?>img/paillette_argent.PNG" alt="Paillettes argentées" loading="lazy">
            <h3>Paillettes</h3><p class="price">9 CHF</p>
            <label class="sr-only" for="qty-sup-7">Quantité</label>
            <input id="qty-sup-7" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Paillettes">Ajouter</button>
        </form>

        <!-- Lettre -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="8">
            <img src="<?= $BASE ?>img/lettre.png" alt="Carte lettre" loading="lazy">
            <h3>Lettre</h3> <p class="price">10 CHF</p>
            <label class="sr-only" for="qty-sup-8">Quantité</label>
            <input id="qty-sup-8" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Lettre">Ajouter</button>
        </form>

        <!-- Carte pour mot -->
        <form class="card product" method="POST" onsubmit="return addSupplementForm(this, event)">
            <input type="hidden" name="sup_id" value="9">
            <img src="<?= $BASE ?>img/carte.PNG" alt="Carte pour mot" loading="lazy">
            <h3>Carte pour mot</h3><p class="price">3 CHF</p>
            <label class="sr-only" for="qty-sup-9">Quantité</label>
            <input id="qty-sup-9" type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric" required>
            <br><br>
            <button type="submit" class="add-to-cart" data-sup-name="Carte pour mot">Ajouter</button>
        </form>
    </div>

    <?php
    // ===== Navigation : origine (fleur | bouquet) + liens =====
    $origin = $_GET['from'] ?? '';
    if ($origin === '') {
        $refQuery = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
        if ($refQuery) { parse_str($refQuery, $qs); if (!empty($qs['from'])) $origin = $qs['from']; }
    }
    if ($origin === '') {
        $refPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
        if (stripos($refPath, 'fleur') !== false)      $origin = 'fleur';
        elseif (stripos($refPath, 'bouquet') !== false) $origin = 'bouquet';
    }
    if ($origin === '') $origin = 'bouquet';

    $retour  = ($origin === 'fleur') ? $BASE . 'fleur.php' : $BASE . 'interface_catalogue_bouquet.php';
    $suivant = $BASE . 'interface_emballage.php?from=' . urlencode($origin);
    ?>
    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= htmlspecialchars($retour) ?>" class="button">Retour</a>
        <a href="<?= htmlspecialchars($suivant) ?>" class="button">Suivant</a>
    </div>

</main>


<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
