<?php
// /site/pages/interface_catalogue_bouquet.php
session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* =========================
   DATA: 9 tailles + variantes
   ========================= */
// 1) On récupère les tailles distinctes (BOU_NB_ROSES)
$tailles = $pdo->query("
    SELECT DISTINCT b.BOU_NB_ROSES AS nb
    FROM BOUQUET b
    ORDER BY nb ASC
")->fetchAll(PDO::FETCH_COLUMN);

// 2) Pour chaque taille, on charge ses 6 variantes (pro_id, couleur, stock, prix)
$bouquets = []; // tableau [{nb, prix, variants: [{pro_id,couleur,stock,prix}]}]
foreach ($tailles as $nb) {
    $st = $pdo->prepare("
        SELECT p.PRO_ID, p.PRO_PRIX, p.PRO_NOM, b.BOU_COULEUR, b.BOU_QTE_STOCK
        FROM BOUQUET b
        JOIN PRODUIT p ON p.PRO_ID = b.PRO_ID
        WHERE b.BOU_NB_ROSES = :nb
        ORDER BY b.BOU_COULEUR
    ");
    $st->execute([':nb' => $nb]);
    $variants = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$variants) { continue; }

    $bouquets[] = [
        'nb'       => (int)$nb,
        'prix'     => (float)$variants[0]['PRO_PRIX'], // même prix pour toutes les couleurs
        'variants' => array_map(function($v){
            return [
                'pro_id'  => (int)$v['PRO_ID'],
                'pro_nom' => $v['PRO_NOM'],
                'couleur' => $v['BOU_COULEUR'],
                'stock'   => (int)$v['BOU_QTE_STOCK'],
                'prix'    => (float)$v['PRO_PRIX'],
            ];
        }, $variants)
    ];
}

/* =========================
   Utils: image par nombre de roses
   ========================= */
function img_for_bouquet_by_nb(int $nb, string $base): string {
    $map = [
        12  => '12Roses.png',
        20  => '20Roses.png',
        24  => '20Roses.png', // fallback visuel correct
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
    <script src="<?= $BASE ?>js/commande.js?v=bouquet-qty3" defer></script>

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

        /* ===== message custom façon “2e image” ===== */
        .stock-message{
            display:none;            /* caché par défaut */
            align-items:flex-start;  /* icône + texte alignés en haut */
            gap:10px;
            margin:8px 0 10px;       /* espace sous l’input et au-dessus du bouton */
            padding:10px 12px;
            border-radius:12px;
            background:#fff5f7;
            border:1px solid #ffd6e0;
            color:#7a0000;
            line-height:1.35;
            text-align:left;
            max-width:100%;
            word-break:break-word;
        }
        .stock-message.show{ display:flex; }
        .stock-message.info{background:#eaf4ff;border-color:#b6d4fe;color:#084298}
        .stock-message::before{
            content:"";width:8px;height:8px;border-radius:50%;background:#d90429;display:inline-block
        }
        .stock-message.info{
            background:#eaf4ff; border-color:#b6d4fe; color:#084298;
        }
        .stock-message .dot{
            width:10px; height:10px; border-radius:50%;
            background:#d90429; flex:0 0 10px; margin-top:4px;
        }
        .stock-message.info .dot{ background:#0d6efd; }
        .stock-message .text{ flex:1; font-size:.92rem; }
        .stock-message strong{ font-weight:700; }
        .qty-input{margin:8px 0 4px;width:90px}
    </style>

    <script>
        // Intercepte le submit : borne la qty / vérifie stock / envoie type='bouquet'
        function addBouquetForm(e, form){
            e.preventDefault();

            const proInput = form.querySelector('input[name="pro_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty-input');

            if(!proInput || !btn || !qtyInput) return false;

            const stock = parseInt(qtyInput.getAttribute('max') || '999', 10);
            let   qty   = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
            if (stock >= 0 && qty > stock){
                qty = stock > 0 ? stock : 1;
                qtyInput.value = String(qty);
                const pname = btn.dataset.proName || 'Ce bouquet';
                const msg = (stock > 0)
                    ? `${pname} — il reste ${stock} en stock, vous ne pouvez pas en commander davantage.`
                    : `${pname} est en rupture de stock.`;
                if (window.toast) window.toast(msg, stock>0 ? 'info' : 'error'); else alert(msg);
                if (stock <= 0) return false;
            }

            // rendre la qty dispo (comme sur fleur/supplément)
            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            // couleur sélectionnée (facultatif côté API car PRO_ID représente la variante)
            const color = form.querySelector('input[type="radio"][name^="couleur_"]:checked')?.value || null;

            const pid   = proInput.value;
            const extra = { qty, color, type: 'bouquet' }; // ENUM('bouquet','fleur','coffret')
            if (typeof addToCart === 'function') addToCart(pid, btn, extra);
            return false;
        }

        // Init par carte : gestion couleur -> stock/max, messages, etc.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.bouquet-card').forEach(card => {
                const hiddenPro = card.querySelector('input[name="pro_id"]');
                const qtyInput  = card.querySelector('.qty-input');
                const stockSpan = card.querySelector('.stock-note .stock-badge');
                const radios    = card.querySelectorAll('input[type="radio"][name^="couleur_"]');
                const addBtn    = card.querySelector('.add-to-cart');
                const msgBox    = card.querySelector('.stock-message');

                const showMsg = (html, type='error') => {
                    if(!msgBox) return;
                    msgBox.innerHTML =
                        `<span class="dot" aria-hidden="true"></span><span class="text">${html}</span>`;
                    msgBox.classList.add('show');
                    if (type === 'info') msgBox.classList.add('info'); else msgBox.classList.remove('info');
                };

                const hideMsg = () => { if(msgBox){ msgBox.classList.remove('show','info'); msgBox.innerHTML=''; } };

                // Click sur pastille: si disabled -> message rupture
                card.querySelectorAll('.swatches label').forEach(lab=>{
                    lab.addEventListener('click', e=>{
                        const input = lab.querySelector('input[type="radio"]');
                        if (input && input.disabled){
                            const name = (lab.getAttribute('title') || 'Cette couleur');
                            showMsg(`🌸 <strong>${name}</strong> est en <strong>rupture de stock</strong>.`, 'error');
                            e.preventDefault();
                        }
                    });
                });

                function refreshFromRadio(r){
                    const proId = r.getAttribute('data-pro-id');
                    const proNom  = r.getAttribute('data-pro-nom') || '';
                    const stock = parseInt(r.getAttribute('data-stock') || '0', 10);
                    const color = r.value || '';

                    hiddenPro.value = proId;
                    // MAJ titre
                    const titleEl = card.querySelector('.pname');
                    if (titleEl && proNom) titleEl.textContent = proNom;

                    // MAJ nom utilisé par le bouton / toasts / addToCart
                    if (addBtn && proNom) {
                        addBtn.dataset.proName = proNom;
                        addBtn.setAttribute('data-pro-name', proNom);
                    }

                    if (stock > 0) {
                        qtyInput.disabled = false;
                        qtyInput.max = String(stock);
                        // ajuste si dépassement
                        const v = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
                        if (v > stock) {
                            qtyInput.value = String(stock);
                            showMsg(`Il reste <strong>${stock}</strong> en stock. Quantité ajustée à <strong>${stock}</strong>.`, 'error');
                        } else {
                            hideMsg();
                        }
                        stockSpan.textContent = 'Stock : ' + stock;
                        stockSpan.classList.remove('oos');
                        addBtn.disabled = false;
                    } else {
                        qtyInput.value = '1';
                        qtyInput.max = '1';
                        qtyInput.disabled = true;
                        stockSpan.textContent = 'Rupture de stock';
                        stockSpan.classList.add('oos');
                        addBtn.disabled = true;
                        showMsg(`🌸 <strong>${color||'Cette couleur'}</strong> est en <strong>rupture de stock</strong>.`, 'error');
                    }
                }

                // Changement de couleur
                radios.forEach(r => {
                    r.addEventListener('change', () => refreshFromRadio(r));
                });

                // Saisie quantité : borne vs stock de la COULEUR sélectionnée
                ['input','change','blur'].forEach(ev=>{
                    qtyInput && qtyInput.addEventListener(ev, () => {
                        const sel = card.querySelector('input[type="radio"][name^="couleur_"]:checked');
                        if(!sel) return;
                        const stock = Math.max(1, parseInt(sel.dataset.stock || '1', 10));
                        const v = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
                        if (v > stock){
                            qtyInput.value = String(stock);
                            showMsg(`Il reste <strong>${stock}</strong> en stock. Quantité ajustée à <strong>${stock}</strong>.`, 'error');
                        } else {
                            hideMsg();
                        }
                    });
                });

                // init (prend la couleur cochée par défaut)
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

    <div id="produit_bouquet" class="catalogue" aria-label="Liste de bouquets">
        <?php
        // Couleurs -> couleur CSS pour la pastille
        $cssColor = [
            'rouge' => 'red',
            'rose clair' => 'pink',
            'rose' => '#ff4d7a',
            'blanc' => '#e9e9e9',
            'bleu' => '#0418a5',
            'noir' => '#111'
        ];
        ?>

        <?php foreach ($bouquets as $item):
            $nb   = $item['nb'];
            $prix = $item['prix'];
            $img  = img_for_bouquet_by_nb($nb, $BASE);

            // Variante par défaut = première avec stock > 0 sinon la 1ère
            $def = null;
            foreach ($item['variants'] as $v) { if ($v['stock'] > 0) { $def = $v; break; } }
            if (!$def) $def = $item['variants'][0];
            ?>
            <form class="card product bouquet-card"
                  onsubmit="return addBouquetForm(event, this)">
                <!-- valeur mise à jour à chaque changement de radio -->
                <input type="hidden" name="pro_id" value="<?= (int)$def['pro_id'] ?>">

                <img src="<?= htmlspecialchars($img) ?>" alt="Bouquet <?= (int)$nb ?> roses" loading="lazy">
                <h3>Bouquet <?= (int)$nb); htmlspecialchars($def['pro_n']) ?></h3>
                <p class="price"><?= number_format($prix, 2, '.', "'") ?> CHF</p>

                <div class="swatches" role="group" aria-label="Couleur">
                    <?php foreach ($item['variants'] as $i => $v):
                        $c = $v['couleur']; $checked = ($v['pro_id'] === $def['pro_id']);
                        $stock = (int)$v['stock'];
                        ?>
                        <label title="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="radio"
                                   name="couleur_<?= (int)$nb ?>"
                                   value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                   data-pro-id="<?= (int)$v['pro_id'] ?>"
                                   data-pro-nom="<?= htmlspecialchars($v['pro_nom'], ENT_QUOTES, 'UTF-8') ?>"
                                   data-stock="<?= $stock ?>"
                                <?= $checked ? 'checked' : '' ?>
                                <?= $stock<=0 ? 'disabled' : '' ?>>
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

                <!-- Quantité + message dynamique (comme la 2e image) -->
                <input
                        type="number"
                        id="qty-<?= (int)$nb ?>"
                        min="1"
                        max="<?= (int)$def['stock'] ?>"
                        value="1"
                        class="qty-input">
                <div id="msg-<?= (int)$nb ?>" class="stock-message" aria-live="polite"></div>

                <br>
                <button type="submit"
                        class="add-to-cart"
                        data-pro-name="Bouquet <?= (int)$nb ?>"
                    <?= $def['stock']<=0 ? 'disabled' : '' ?>>
                    Ajouter
                </button>
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
