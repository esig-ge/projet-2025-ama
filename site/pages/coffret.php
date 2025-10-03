<?php
// /site/pages/coffret.php — coffrets + mini-carrousel d’images
session_start();

// Anti-cache
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
 * Slugifier (accents -> ascii, underscores, minuscules)
 */
function slugify(string $txt): string {
    $txt = trim($txt);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$txt);
        if ($t !== false) $txt = $t;
    }
    $txt = preg_replace('~[^A-Za-z0-9]+~', '_', $txt);
    return strtolower(trim($txt, '_'));
}

/**
 * Renvoie un tableau d’URLs d’images pour un évènement.
 * - Si une correspondance explicite existe (map), on l'utilise (string ou array).
 * - Sinon, on cherche tous les fichiers "coffret_<slug>*.png/PNG" dans /img.
 * - Fallback sur "coffret.png" si rien trouvé.
 */
function find_images_for_event(string $event): array {
    $event = trim($event);
    $slug  = slugify($event);

    // 1) Correspondances explicites (tu peux mettre plusieurs images par clé)
    //    -> mets 1 string OU un array de strings
    $map = [
        'Anniversaire'   => ['ExempleAnniversaire.png'],
        'Saint-Valentin' => ['ExempleSaintValentin.png'],
        'Fête des Mères' => ['ExempleFeteMeres3.png', 'ExempleFeteMeres2.png', 'ExempleFeteMeres.png'],
        'Baptême'        => ['PackBleu.png'],
        'Mariage'        => ['ExempleMariage.png'],
        'Pâques'         => ['ExemplePaques.png', 'ExemplePaques2.png'],
        'Noël'           => ['ExempleNoel.png'],
        'Nouvel An'      => ['ExempleNouvelAn.png'],
    ];

    $imgDirFs  = __DIR__ . '/img/';
    $imgDirWeb = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/img/';

    // 1a) Map explicite
    if (isset($map[$event])) {
        $files = is_array($map[$event]) ? $map[$event] : [$map[$event]];
        $out   = [];
        foreach ($files as $f) {
            if (is_file($imgDirFs . $f)) {
                $out[] = $imgDirWeb . $f;
            }
        }
        if ($out) return $out;
    }

    // 2) Recherche auto par motif: coffret_<slug>*.png/PNG
    $cands = array_merge(
        glob($imgDirFs . "coffret_{$slug}*.png") ?: [],
        glob($imgDirFs . "coffret_{$slug}*.PNG") ?: []
    );
    $out = [];
    foreach ($cands as $absPath) {
        $out[] = $imgDirWeb . basename($absPath);
    }

    // 3) Fallback
    if (!$out) $out[] = $imgDirWeb . 'coffret.png';

    return $out;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Coffrets</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">

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

        /* === Bloc carrousel image === */
        .product-images{ position:relative; }
        .product-images .slides{
            position:relative;
            width:100%;
            height:180px;
            border-radius:10px;
            overflow:hidden;
            background:#fdfbf7;       /* blanc cassé */
            display:flex;
            align-items:center;
            justify-content:center;
            padding:12px;
        }
        .product-images .slides img{
            max-width:100%;
            max-height:100%;
            object-fit:contain;
            display:none;
        }
        .product-images .slides img.active{ display:block; }

        .product-images .dots{
            text-align:center;
            margin-top:8px;
        }
        .product-images .dot{
            height:8px; width:8px;
            margin:0 3px;
            background:#cfcfcf;
            border-radius:50%;
            display:inline-block;
            cursor:pointer;
            transition:transform .15s ease;
        }
        .product-images .dot.active{
            background:#7a0000; /* ton rouge DK si défini ailleurs tu peux le remplacer par var(--brand) */
            transform:scale(1.1);
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

        #coffret-grid .card.product > img{
            width:100%;
            height:180px;
            object-fit:contain;
            display:block;
            margin:0 auto 10px;
            border-radius:10px;
            /* si tu avais mis un background ici, enlève-le, le carrousel a son propre fond */
        }
        .product-images .slides img{
            display:none !important;
        }
        .product-images .slides img.active{
            display:block !important;
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

        // Carrousel: points cliquables
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.product-images').forEach(container => {
                const imgs = [...container.querySelectorAll('.slides img')];
                const dots = [...container.querySelectorAll('.dot')];

                const show = (i) => {
                    imgs.forEach((im, idx) => im.classList.toggle('active', idx === i));
                    dots.forEach((d, idx) => d.classList.toggle('active', idx === i));
                };

                dots.forEach(d => d.addEventListener('click', () => {
                    show(parseInt(d.dataset.index, 10) || 0);
                }));

                // Touch swipe (petit bonus)
                let startX = null;
                container.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, {passive:true});
                container.addEventListener('touchend', e => {
                    if (startX == null) return;
                    const dx = e.changedTouches[0].clientX - startX;
                    if (Math.abs(dx) > 40) {
                        const cur = dots.findIndex(d => d.classList.contains('active'));
                        const next = dx < 0 ? (cur+1) % imgs.length : (cur-1+imgs.length) % imgs.length;
                        show(next);
                    }
                    startX = null;
                });

                // Affiche la 1ère image
                show(0);
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
            $imgs   = find_images_for_event($evt);     // <- tableau d’images
            $disabled = ($stock <= 0) ? 'disabled' : '';
            ?>
            <form class="card product" method="POST" onsubmit="return addCoffretForm(event, this)">
                <input type="hidden" name="pro_id" value="<?= $proId ?>">

                <!-- Bloc carrousel -->
                <div class="product-images">
                    <div class="slides">
                        <?php foreach ($imgs as $i => $url): ?>
                            <img src="<?= htmlspecialchars($url) ?>"
                                 alt="Coffret <?= htmlspecialchars($evt) ?> — image <?= $i+1 ?>"
                                 loading="lazy"
                                 class="<?= $i === 0 ? 'active' : '' ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($imgs) > 1): ?>
                        <div class="dots" aria-label="Changer d'image">
                            <?php foreach ($imgs as $i => $_): ?>
                                <span class="dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

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
