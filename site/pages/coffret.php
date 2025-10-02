<?php
// /site/pages/coffret.php (robuste + détection d'image)
session_start();

// Anti-cache (toujours refléter la BDD au reload)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Base URL (toujours slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// --- Récupération coffrets
$sql = "SELECT p.PRO_ID, p.PRO_NOM, p.PRO_PRIX,
               c.COF_EVENEMENT, c.COF_QTE_STOCK
        FROM COFFRET c
        JOIN PRODUIT p ON p.PRO_ID = c.PRO_ID
        ORDER BY p.PRO_PRIX ASC, c.COF_EVENEMENT ASC";
$coffrets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/**
 * Slugifier un texte (accents -> ascii, tirets bas, minuscules)
 */
function slugify(string $txt): string {
    $txt = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = preg_replace('~[^A-Za-z0-9]+~', '_', $txt);
    return strtolower(trim($txt, '_'));
}

/**
 * Trouver un fichier image disponible dans /site/pages/img
 * Essaie plusieurs candidats (évènement spécifique puis fallback générique),
 * en .png et .PNG pour éviter les soucis de casse sur l’hébergement.
 */
function find_image_for_event(string $event): string {
    $slug = slugify($event);

    $candidates = [
        "coffret_{$slug}.png",
        "coffret_{$slug}.PNG",
        "coffret.png",
        "coffret.PNG",
    ];

    $imgDirFs  = __DIR__ . '/img/';    // FS path
    $imgDirWeb = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/img/'; // équiv. $BASE.'img/'

    foreach ($candidates as $file) {
        if (is_file($imgDirFs . $file)) {
            return $imgDirWeb . $file;
        }
    }
    return $imgDirWeb . 'coffret.png';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Coffrets</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>
    <script src="<?= $BASE ?>js/commande.js?v=coffret-qty2" defer></script>

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
            width:100%; max-width:260px;
        }
        .catalogue .card.product img{
            max-width:200px; height:auto; display:block; margin:0 auto 10px; border-radius:10px;
        }
        .price{ font-weight:600; }
        .stock-note{ font-size:.9rem; color:#666; margin:6px 0 0; }
        .stock-badge{
            display:inline-block; margin-top:6px; padding:3px 8px; border-radius:999px;
            font-size:.78rem; background:#eee; color:#333;
        }
        .oos{ color:#a30000; }
        .add-to-cart[disabled]{ opacity:.55; cursor:not-allowed; }
        .sr-only{ position:absolute; left:-9999px; }
        #coffret-grid{
            display:grid !important;
            grid-template-columns:repeat(5, minmax(0, 1fr));
            gap:24px;
            max-width:1400px;
            margin:0 auto;
            align-items:stretch;
        }
        #coffret-grid .card.product{ width:100% !important; max-width:none !important; }
        #coffret-grid .card.product img{
            width:100%; height:180px; object-fit:contain; display:block; margin:0 auto 10px; border-radius:10px;
        }
        @media (max-width: 1280px){ #coffret-grid{ grid-template-columns:repeat(4,1fr); } }
        @media (max-width: 1024px){ #coffret-grid{ grid-template-columns:repeat(3,1fr); } }
        @media (max-width: 768px) { #coffret-grid{ grid-template-columns:repeat(2,1fr); } }
        @media (max-width: 480px) { #coffret-grid{ grid-template-columns:1fr; } }
    </style>

    <script>
        // Interception submit -> borne qty au stock + envoi à l’API (type=coffret)
        function addCoffretForm(e, form){
            e.preventDefault();

            const proInput = form.querySelector('input[name="pro_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty');

            if(!proInput || !btn || !qtyInput) return false;

            const stock = parseInt(qtyInput.getAttribute('max') || '999', 10);
            let   qty   = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);

            const pname = btn.dataset.proName || 'Ce coffret';

            if (stock >= 0 && qty > stock){
                qty = stock > 0 ? stock : 1;
                qtyInput.value = String(qty);
                const msg = (stock > 0)
                    ? `${pname} — il reste ${stock} en stock, vous ne pouvez pas en commander davantage.`
                    : `${pname} est momentanément en rupture de stock.`;
                if (window.toast) window.toast(msg, stock>0 ? 'info' : 'error');
                else alert(msg);
                if (stock <= 0) return false;
            }

            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            const extra = { qty, type: 'coffret' };
            if (typeof addToCart === 'function') addToCart(proInput.value, btn, extra);
            return false;
        }

        // (Optionnel) petite aide pour rafraîchir prix/stock "live" via /api/product.php?id=PRO_ID
        async function fetchLive(productId){
            try{
                const res = await fetch(`${window.DKBASE || '/'}api/product.php?id=${encodeURIComponent(productId)}`, {cache:'no-store'});
                if(!res.ok) return null;
                const json = await res.json();
                return json && json.ok ? json : null;
            }catch(e){ return null; }
        }
        function formatCHF(n){ return (Number(n)||0).toFixed(2) + ' CHF'; }

        document.addEventListener('DOMContentLoaded', () => {
            // Parcourt toutes les cartes pour “peaufiner” prix/stock au chargement
            document.querySelectorAll('#coffret-grid .card.product').forEach(card => {
                const proId   = parseInt(card.querySelector('input[name="pro_id"]')?.value || '0', 10);
                const priceEl = card.querySelector('.price');
                const qtyEl   = card.querySelector('.qty');
                const addBtn  = card.querySelector('.add-to-cart');
                const badge   = card.querySelector('.stock-badge');

                // Mémorise le pro_id sur le prix (utile si tu fais d’autres hooks)
                if (priceEl) priceEl.dataset.proId = String(proId);

                // 👉 Dé-commente pour activer la vérification “live” côté serveur :
                // if (proId > 0) {
                //     fetchLive(proId).then(data => {
                //         if (!data) return;
                //         if (priceEl && typeof data.price === 'number') {
                //             priceEl.textContent = formatCHF(data.price);
                //         }
                //         if (typeof data.stock === 'number' && qtyEl && badge) {
                //             const s = Math.max(0, data.stock|0);
                //             qtyEl.max = String(Math.max(1, s));
                //             qtyEl.disabled = (s <= 0);
                //             if (s > 0) {
                //                 badge.textContent = 'Stock : ' + s;
                //                 badge.classList.remove('oos');
                //                 if (addBtn) addBtn.disabled = false;
                //             } else {
                //                 badge.textContent = 'Rupture de stock';
                //                 badge.classList.add('oos');
                //                 if (addBtn) addBtn.disabled = true;
                //                 qtyEl.value = '1'; qtyEl.max = '1';
                //             }
                //         }
                //     });
                // }
            });
        });
    </script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title">Nos coffrets</h1>

    <div id="coffret-grid" class="catalogue" aria-label="Liste de coffrets">
        <?php foreach ($coffrets as $c):
            $proId  = (int)$c['PRO_ID'];
            $evt    = $c['COF_EVENEMENT']; // Anniversaire, Noël…
            $prix   = (float)$c['PRO_PRIX'];
            $stock  = max(0, (int)$c['COF_QTE_STOCK']);
            $img    = find_image_for_event($evt);
            $disabled = ($stock <= 0) ? 'disabled' : '';
            ?>
            <form class="card product" method="POST" onsubmit="return addCoffretForm(event, this)">
                <input type="hidden" name="pro_id" value="<?= $proId ?>">

                <img src="<?= htmlspecialchars($img) ?>" alt="Coffret <?= htmlspecialchars($evt) ?>" loading="lazy">
                <h3><?= htmlspecialchars($evt) ?></h3>

                <!-- Prix initial (BDD) ; data-pro-id pour éventuel rafraîchissement live -->
                <p class="price" data-pro-id="<?= $proId ?>"><?= number_format($prix, 2, '.', "'") ?> CHF</p>

                <label class="sr-only" for="qty-<?= $proId ?>">Quantité</label>
                <input
                        id="qty-<?= $proId ?>" type="number" class="qty" name="qty"
                        min="1" step="1" value="1" inputmode="numeric" required
                    <?= $disabled ?>
                    <?= $stock > 0 ? 'max="'.(int)$stock.'"' : 'max="1"' ?>
                        aria-describedby="stock-<?= $proId ?>"
                >

                <p id="stock-<?= $proId ?>" class="stock-note">
                    <?php if ($stock > 0): ?>
                        <span class="stock-badge">Stock : <?= (int)$stock ?></span>
                    <?php else: ?>
                        <span class="stock-badge oos">Rupture de stock</span>
                    <?php endif; ?>
                </p>

                <br>
                <button
                        type="submit"
                        class="add-to-cart"
                        data-pro-name="Coffret <?= htmlspecialchars($evt) ?>"
                    <?= $disabled ?>
                >Ajouter</button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= $BASE ?>interface_selection_produit.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
