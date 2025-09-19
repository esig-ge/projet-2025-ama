<?php
// /site/pages/interface_catalogue_bouquet.php
session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Récupération dynamique des bouquets
$sql = "SELECT p.PRO_ID, p.PRO_NOM, p.PRO_PRIX, b.BOU_QTE_STOCK
        FROM BOUQUET b
        JOIN PRODUIT p ON p.PRO_ID = b.PRO_ID
        ORDER BY p.PRO_PRIX ASC, p.PRO_NOM ASC";
$bouquets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Mapping “intelligent” d’images selon le nombre de roses dans le nom du produit
function img_for_bouquet(string $nom, string $base): string {
    // Ex: “Bouquet 12”, “Bouquet 66”, “Bouquet 100”
    if (preg_match('~(\d{2,3})~', $nom, $m)) {
        $n = (int)$m[1];
        $candidates = [
            12 => '12Roses.png',
            20 => '20Roses.png',
            24 => '20Roses.png',  // fallback connu
            36 => '36Roses.png',
            50 => '50Roses.png',
            66 => '66Roses.png',
            99 => '100Roses.png', // fallback visuel correct
            100 => '100Roses.png',
            101 => '100Roses.png',
        ];
        if (isset($candidates[$n])) return $base . 'img/' . $candidates[$n];
    }
    return $base . 'img/100Roses.png';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Catalogue bouquet</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier (callApi/addToCart/resolveQty/toasts, etc.) -->
    <script src="<?= $BASE ?>js/commande.js?v=bouquet-qty2" defer></script>

    <style>
        .catalogue{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
            gap:22px;justify-items:center
        }
        .catalogue .card.product{
            background:#fff;
            padding:12px;
            border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,.1);
            text-align:center;
            width:100%;
            max-width:260px;
        }
        .catalogue .card.product img{
            max-width:200px;height:auto;display:block;margin:0 auto 10px;border-radius:10px
        }
        .price{ font-weight:600; }
        .stock-note{ font-size:.9rem; color:#666; margin:6px 0 0; }
        .stock-badge{
            display:inline-block; margin-top:6px; padding:3px 8px; border-radius:999px;
            font-size:.78rem; background:#eee; color:#333;
        }
        .oos{ color:#a30000; }
        .btn[disabled], .add-to-cart[disabled]{ opacity:.55; cursor:not-allowed; }
        .swatches{ display:flex; gap:8px; justify-content:center; margin:8px 0 10px; }
        .swatches .swatch{ width:16px; height:16px; border-radius:50%; display:inline-block; background:var(--c); border:1px solid #ccc; }
        .sr-only{ position:absolute; left:-9999px; }
    </style>

    <script>
        // Interception submit -> vérifie stock, borne la quantité, envoie type='bouquet'
        function addBouquetForm(e, form){
            e.preventDefault();

            const proInput = form.querySelector('input[name="pro_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty');

            if(!proInput || !btn || !qtyInput) return false;

            const stock = parseInt(qtyInput.getAttribute('max') || '999', 10);
            let   qty   = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);

            const pname = btn.dataset.proName || 'Ce bouquet';

            if (stock >= 0 && qty > stock){
                qty = stock > 0 ? stock : 1;
                qtyInput.value = String(qty);
                // message sympa (même style que rupture de stock)
                const msg = (stock > 0)
                    ? `${pname} — il reste ${stock} en stock, vous ne pouvez pas en commander davantage.`
                    : `${pname} est momentanément en rupture de stock.`;
                if (window.toast) window.toast(msg, stock>0 ? 'info' : 'error');
                else alert(msg);
                if (stock <= 0) return false; // on bloque carrément si 0
            }

            // Rendre la qty dispo (comme sur fleur/supplément)
            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            // Couleur (optionnelle). On suit le pattern couleur_<PRO_ID>
            const pid   = proInput.value;
            const color =
                form.querySelector(`input[name="couleur_${pid}"]:checked`)?.value
                || form.querySelector('input[name^="couleur_"]:checked')?.value
                || form.querySelector('input[name="couleur"]:checked')?.value
                || null;

            // On force le type au backend pour respecter ENUM('bouquet','fleur','coffret')
            const extra = { qty, color, type: 'bouquet' };
            if (typeof addToCart === 'function') addToCart(pid, btn, extra);
            return false;
        }
    </script>
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
        <?php foreach ($bouquets as $b):
            $proId   = (int)$b['PRO_ID'];
            $nom     = $b['PRO_NOM'];
            $prix    = (float)$b['PRO_PRIX'];
            $stock   = max(0, (int)$b['BOU_QTE_STOCK']);
            $img     = img_for_bouquet($nom, $BASE);

            // Accessibilité + swatches génériques (couleur visuelle seulement)
            $colors = [
                ['value'=>'rouge','css'=>'red'],
                ['value'=>'rose','css'=>'pink'],
                ['value'=>'blanc','css'=>'#e9e9e9'],
                ['value'=>'bleu','css'=>'#0418a5'],
                ['value'=>'noir','css'=>'#111'],
            ];
            $disabled = ($stock <= 0) ? 'disabled' : '';
            ?>
            <form class="card product" method="POST" onsubmit="return addBouquetForm(event, this)">
                <input type="hidden" name="pro_id" value="<?= $proId ?>">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($nom) ?>" loading="lazy">
                <h3><?= htmlspecialchars($nom) ?></h3>
                <p class="price"><?= number_format($prix, 2, '.', "'") ?> CHF</p>

                <label class="sr-only" for="qty-<?= $proId ?>">Quantité</label>
                <input
                        id="qty-<?= $proId ?>" type="number" class="qty" name="qty"
                        min="1" step="1" value="1" inputmode="numeric" required
                    <?= $disabled ?>
                    <?= $stock > 0 ? 'max="'.(int)$stock.'"' : 'max="1"' ?>
                        aria-describedby="stock-<?= $proId ?>"
                >

                <div class="swatches" role="group" aria-label="Couleur">
                    <?php foreach ($colors as $i => $c): ?>
                        <label title="<?= htmlspecialchars($c['value']) ?>">
                            <input type="radio" name="couleur_<?= $proId ?>" value="<?= htmlspecialchars($c['value']) ?>" <?= $i===0?'required':'' ?> <?= $disabled ?>>
                            <span class="swatch" style="--c:<?= htmlspecialchars($c['css']) ?>"></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p id="stock-<?= $proId ?>" class="stock-note">
                    <?php if ($stock > 0): ?>
                        <span class="stock-badge">Stock : <?= (int)$stock ?></span>
                    <?php else: ?>
                        <span class="stock-badge oos">Rupture de stock</span>
                    <?php endif; ?>
                </p>

                <button
                        type="submit"
                        class="add-to-cart"
                        data-pro-name="<?= htmlspecialchars($nom) ?>"
                    <?= $disabled ?>
                >Ajouter</button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_selection_produit.php" class="button">Retour</a>
        <a href="<?= $BASE ?>interface_supplement.php?from=bouquet" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
