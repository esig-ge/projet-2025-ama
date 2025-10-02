<?php
// /site/pages/interface_catalogue_bouquet.php
session_start();

/* === Base URL avec un slash final (ex: "/…/site/pages/") === */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* ============================================================
   DATA : tailles de bouquets + variantes par couleur
   ============================================================ */

// 1) tailles distinctes
$tailles = $pdo->query("
    SELECT DISTINCT b.BOU_NB_ROSES AS nb
    FROM BOUQUET b
    ORDER BY nb ASC
")->fetchAll(PDO::FETCH_COLUMN);

// 2) variantes (par taille)
$bouquets = [];
foreach ($tailles as $nb) {
    $st = $pdo->prepare("
        SELECT p.PRO_ID, p.PRO_PRIX, p.PRO_NOM, b.BOU_COULEUR, b.BOU_DESCRIPTION, b.BOU_QTE_STOCK
        FROM BOUQUET b
        JOIN PRODUIT p ON p.PRO_ID = b.PRO_ID
        WHERE b.BOU_NB_ROSES = :nb
        ORDER BY b.BOU_COULEUR
    ");
    $st->execute([':nb' => $nb]);
    $variants = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$variants) continue;

    $bouquets[] = [
        'nb'       => (int)$nb,
        'prix'     => (float)$variants[0]['PRO_PRIX'],
        'variants' => array_map(function ($v) {
            return [
                'pro_id'  => (int)$v['PRO_ID'],
                'couleur' => (string)$v['BOU_COULEUR'],
                'stock'   => (int)$v['BOU_QTE_STOCK'],
                'prix'    => (float)$v['PRO_PRIX'],
                'nom'     => $v['PRO_NOM'] ?? '',
                'desc'    => $v['BOU_DESCRIPTION'] ?? '',
            ];
        }, $variants),
    ];
}

/* === Petite util : image par nombre de roses === */
function img_for_bouquet_by_nb(int $nb, string $base): string {
    $map = [
        12  => '12Roses.png',
        20  => '20Roses.png',
        24  => '20Roses.png',
        36  => '36Roses.png',
        50  => '50Roses.png',
        66  => '66Roses.png',
        99  => '100Roses.png',
        100 => '100Roses.png',
        101 => '100Roses.png',
    ];
    return $base . 'img/' . ($map[$nb] ?? '100Roses.png');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Catalogue bouquet</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <script src="<?= $BASE ?>js/commande.js?v=bouquet-qty3" defer></script>

    <script>
        function addBouquetForm(e, form) {
            e.preventDefault();
            const proInput = form.querySelector('input[name="pro_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty-input');
            if (!proInput || !btn || !qtyInput) return false;

            const stock = parseInt(qtyInput.getAttribute('max') || '999', 10);
            let qty     = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);

            if (stock >= 0 && qty > stock) {
                qty = stock > 0 ? stock : 1;
                qtyInput.value = String(qty);
                const pname = btn.dataset.proName || 'Ce bouquet';
                const msg = (stock > 0)
                    ? `${pname} — il reste ${stock} en stock, vous ne pouvez pas en commander davantage.`
                    : `${pname} est en rupture de stock.`;
                if (window.toast) window.toast(msg, stock > 0 ? 'info' : 'error'); else alert(msg);
                if (stock <= 0) return false;
            }

            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            const color = form.querySelector('input[type="radio"][name^="couleur_"]:checked')?.value || null;
            const pid   = proInput.value;
            const extra = { qty, color, type: 'bouquet' };
            if (typeof addToCart === 'function') addToCart(pid, btn, extra);
            return false;
        }

        // Safeguards si showMsg/hideMsg ne sont pas déjà définies
        function showMsgFallback(box, html, type='error'){
            if (!box) return;
            box.innerHTML = `<span class="dot" aria-hidden="true"></span><span class="text">${html}</span>`;
            box.classList.add('show');
            if (type === 'info') box.classList.add('info'); else box.classList.remove('info');
        }
        function hideMsgFallback(box){
            if (!box) return;
            box.classList.remove('show','info');
            box.innerHTML = '';
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.bouquet-card').forEach(card => {
                const hiddenPro = card.querySelector('input[name="pro_id"]');
                const qtyInput  = card.querySelector('.qty-input');
                const stockSpan = card.querySelector('.stock-note .stock-badge');
                const radios    = card.querySelectorAll('input[type="radio"][name^="couleur_"]');
                const addBtn    = card.querySelector('.add-to-cart');
                const msgBox    = card.querySelector('.stock-message');
                const titleEl   = card.querySelector('h3');
                const nbRoses   = card.getAttribute('data-nb') || '';
                const priceEl  = card.querySelector('.price');

                const showMsg = (html, type='error') => (window.showMsg ? window.showMsg(html,type) : showMsgFallback(msgBox, html, type));
                const hideMsg = () => (window.hideMsg ? window.hideMsg() : hideMsgFallback(msgBox));

                function capitalize(str){ return (str||'').charAt(0).toUpperCase() + (str||'').slice(1); }

                // Empêche le clic sur une couleur en rupture
                card.querySelectorAll('.swatches label').forEach(lab => {
                    lab.addEventListener('click', (e) => {
                        const input = lab.querySelector('input[type="radio"]');
                        if (input && input.disabled) {
                            const name = lab.getAttribute('title') || 'Cette couleur';
                            showMsg(`🌸 <strong>${name}</strong> est en <strong>rupture de stock</strong>.`, 'error');
                            e.preventDefault();
                        }
                    });
                });

                function refreshFromRadio(r) {
                    const proId  = r.getAttribute('data-pro-id');
                    const stock  = parseInt(r.getAttribute('data-stock') || '0', 10);
                    const color  = r.value || '';
                    const priceFromDom = parseFloat(r.dataset.price || '0');
                    let   newName = (r.dataset.nom || '').trim();

                    if (!newName) newName = `Bouquet ${nbRoses} roses — ${capitalize(color)}`;

                    if (titleEl) titleEl.textContent = newName;
                    if (addBtn)  addBtn.dataset.proName = newName;

                    hiddenPro.value = proId;

                    // 1) Met à jour le prix (indépendant du reste)
                    if (!Number.isNaN(priceFromDom) && priceEl) {
                        priceEl.textContent = priceFromDom.toFixed(2) + ' CHF';
                    }

                    // 2) Puis gère le stock/quantité/bouton
                    if (stock > 0) {
                        qtyInput.disabled = false;
                        qtyInput.max = String(stock);
                        const v = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
                        if (v > stock) {
                            qtyInput.value = String(stock);
                            showMsg(`Il reste <strong>${stock}</strong> en stock. Quantité ajustée à <strong>${stock}</strong>.`, 'error');
                        } else {
                            hideMsg();
                        }
                        if (stockSpan){
                            stockSpan.textContent = 'Stock : ' + stock;
                            stockSpan.classList.remove('oos');
                        }
                        if (addBtn) addBtn.disabled = false;
                    } else {
                        qtyInput.value = '1';
                        qtyInput.max = '1';
                        qtyInput.disabled = true;
                        if (stockSpan){
                            stockSpan.textContent = 'Rupture de stock';
                            stockSpan.classList.add('oos');
                        }
                        if (addBtn) addBtn.disabled = true;
                        showMsg(`🌸 <strong>${color || 'Cette couleur'}</strong> est en <strong>rupture de stock</strong>.`, 'error');
                    }
                }


                ['input', 'change', 'blur'].forEach(ev => {
                    qtyInput && qtyInput.addEventListener(ev, () => {
                        const sel = card.querySelector('input[type="radio"][name^="couleur_"]:checked');
                        if (!sel) return;
                        const stock = Math.max(1, parseInt(sel.dataset.stock || '1', 10));
                        const v = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
                        if (v > stock) {
                            qtyInput.value = String(stock);
                            showMsg(`Il reste <strong>${stock}</strong> en stock. Quantité ajustée à <strong>${stock}</strong>.`, 'error');
                        } else {
                            hideMsg();
                        }
                    });
                });

                radios.forEach(r => r.addEventListener('change', () => refreshFromRadio(r)));

                const initial = card.querySelector('input[type="radio"][name^="couleur_"]:checked');
                if (initial) refreshFromRadio(initial);
            });
        });
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

    <?php
    $cssColor = [
        'rouge'      => 'red',
        'rose clair' => 'pink',
        'rose'       => '#ff4d7a',
        'blanc'      => '#e9e9e9',
        'bleu'       => '#0418a5',
        'noir'       => '#111',
    ];
    ?>

    <div id="produit_bouquet" class="catalogue" aria-label="Liste de bouquets">
        <?php foreach ($bouquets as $item):
            $nb   = $item['nb'];
            $prix = $item['prix'];
            $img  = img_for_bouquet_by_nb($nb, $BASE);

            $def = null;
            foreach ($item['variants'] as $v) { if ($v['stock'] > 0) { $def = $v; break; } }
            if (!$def) $def = $item['variants'][0];
            ?>
            <form class="card product bouquet-card"
                  data-nb="<?= (int)$nb ?>"
                  onsubmit="return addBouquetForm(event, this)">
                <input type="hidden" name="pro_id" value="<?= (int)$def['pro_id'] ?>">
                <img src="<?= htmlspecialchars($img) ?>" alt="Bouquet <?= (int)$nb ?> roses" loading="lazy">
                <h3><?= htmlspecialchars($def['nom'] ?: "Bouquet $nb", ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="price"><?= number_format($def['prix'], 2, '.', "'") ?> CHF</p>

                <div class="swatches" role="group" aria-label="Couleur">
                    <?php foreach ($item['variants'] as $v):
                        $c = $v['couleur'];
                        $stock = (int)$v['stock'];
                        $checked = ($v['pro_id'] === $def['pro_id']);
                        ?>
                        <label title="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="radio"
                                   name="couleur_<?= (int)$nb ?>"
                                   value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                   data-pro-id="<?= (int)$v['pro_id'] ?>"
                                   data-stock="<?= (int)$v['stock'] ?>"
                                   data-nom="<?= htmlspecialchars(($v['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   data-price="<?= number_format($v['prix'], 2, '.', '') ?>"
                                <?= $checked ? 'checked' : '' ?>
                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                            <span class="swatch" style="--c:<?= htmlspecialchars($cssColor[$c] ?? '#ccc') ?>"></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p id="stock-<?= (int)$nb ?>" class="stock-note">
                    <?php if ($def['stock'] > 0): ?>
                        <span class="stock-badge">Stock : <?= (int)$def['stock'] ?></span>
                    <?php else: ?>
                        <span class="stock-badge oos">Rupture de stock</span>
                    <?php endif; ?>
                </p>

                <input type="number"
                       id="qty-<?= (int)$nb ?>"
                       min="1"
                       max="<?= (int)$def['stock'] ?>"
                       value="1"
                       class="qty-input">
                <div id="msg-<?= (int)$nb ?>" class="stock-message" aria-live="polite"></div>

                <button type="submit"
                        class="add-to-cart"
                        data-pro-name="<?= htmlspecialchars($def['nom'] ?: "Bouquet $nb", ENT_QUOTES, 'UTF-8') ?>"
                    <?= $def['stock'] <= 0 ? 'disabled' : '' ?>>
                    Ajouter
                </button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="nav-actions">
        <a href="<?= $BASE ?>interface_selection_produit.php" class="button">Retour</a>
        <a href="<?= $BASE ?>interface_supplement.php?from=bouquet" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
