<?php
// /site/pages/interface_emballage.php
session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/**
 * Déterminer l'origine de navigation (fleur | bouquet) pour la propager.
 */
$origin = $_GET['from'] ?? '';
if ($origin === '') {
    $refQuery = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
    if ($refQuery) { parse_str($refQuery, $qs); if (!empty($qs['from'])) $origin = $qs['from']; }
}
if ($origin === '') {
    $refPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
    if (stripos($refPath, 'fleur') !== false)       $origin = 'fleur';
    elseif (stripos($refPath, 'bouquet') !== false) $origin = 'bouquet';
}
if ($origin === '') $origin = 'bouquet';

// URLs nav
$retourSupp = $BASE . 'interface_supplement.php?from=' . urlencode($origin);
$suivantCmd = $BASE . 'commande.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Récupération dynamique depuis la BDD
$sql = "SELECT EMB_ID, EMB_NOM, EMB_COULEUR, COALESCE(EMB_QTE_STOCK,0) AS STOCK
        FROM EMBALLAGE
        ORDER BY EMB_NOM";
$embs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   OPTION B — Résolution d'image « intelligente »
   1) essaie: img/emballages/emb_<ID>.png
   2) anciens fichiers (compat): img/<legacyName>
   3) slug du nom: img/<slug>.(png|PNG|jpg|jpeg|webp)
   -> retourne l’URL publique (BASE/…) ou null si rien trouvé
   ============================================================ */

$legacyMap = array(
    1 => 'emballage_blanc.PNG',
    4 => 'emballage_gris.PNG',
    2 => 'emballage_noir.PNG',
    3 => 'emballage_rose.PNG',  
    5 => 'emballage_violet.PNG'
);

function slugify_name(string $name): string {
    $slug = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
    if ($slug === false || $slug === null) $slug = $name;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i','_', $slug));
    return trim($slug, '_') ?: 'image';
}

function resolveEmbImage(int $id, string $name, string $BASE, array $legacyMap): ?string {
    $fsRoot = __DIR__; // /site/pages

    // 1) nouvelle convention
    $fs1 = $fsRoot . "/img/emballages/emb_{$id}.png";
    if (is_file($fs1)) return $BASE . "img/emballages/emb_{$id}.png";

    // 2) fichiers legacy
    if (!empty($legacyMap[$id])) {
        $legacy = $legacyMap[$id];
        $fs2 = $fsRoot . "/img/" . $legacy;
        if (is_file($fs2)) return $BASE . "img/" . $legacy;
    }

    // 3) slug du nom
    $slug = slugify_name($name);
    foreach (['png','PNG','jpg','jpeg','webp'] as $ext) {
        $fs3 = $fsRoot . "/img/{$slug}.{$ext}";
        if (is_file($fs3)) return $BASE . "img/{$slug}.{$ext}";
    }

    return null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Emballages</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">

    <style>
        #emb-grid{
            display:grid !important;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)) !important;
            gap:24px !important;
            max-width:1200px;
            margin:0 auto;
            align-items:stretch;
        }
        .card.product{
            width:100% !important;
            max-width:none !important;
            background:#fff;
            border-radius:14px;
            box-shadow:0 6px 18px rgba(0,0,0,.08);
            padding:14px 14px 16px;
            display:flex;
            flex-direction:column;
            justify-content:flex-start;
            position:relative;
            text-align:center;
        }
        .card.product img{
            width:100%;
            height:200px;
            object-fit:cover;
            border-radius:10px;
            margin-bottom:10px;
            display:block;
        }
        .card.product h3{ margin:6px 0 2px; font-size:1.05rem; }
        .price{ font-weight:600; color:#2c7a2c; margin-bottom:10px; }

        .stock-badge{
            margin:6px 0 8px; font-size:.9rem;
            padding:4px 8px; border-radius:999px; display:inline-block;
            background:#f4f4f5; color:#333;
        }
        .stock-badge.out{ background:#fff1f3; color:#7a0000; border:1px solid #ffd6e0; }

        .stock-msg{
            display:none; align-items:center; gap:8px;
            margin:8px 0 0; padding:8px 12px; border-radius:10px; font-size:.9rem;
            background:#fff5f7; border:1px solid #ffd6e0; color:#7a0000; text-align:left;
        }
        .stock-msg.show{ display:flex; }
        .stock-msg .dot{ width:8px; height:8px; border-radius:50%; background:#d90429; }

        .add-to-cart{
            align-self:center;
            padding:.5rem 1.1rem;
            border-radius:999px;
            background:var(--accent, #7b0d15);
            color:#fff;
            border:none;
            cursor:pointer;
            transition:transform .06s ease, filter .2s ease;
        }
        .add-to-cart:hover{ filter:brightness(1.05); }
        .add-to-cart:active{ transform:translateY(1px); }
        .add-to-cart[disabled]{ opacity:.5; cursor:not-allowed; }

        /* Barre nav */
        .nav-actions{ text-align:center; margin:16px 0 24px; }
        .nav-actions .button{
            display:inline-block; margin:0 6px; padding:.55rem 1.1rem;
            border-radius:999px; background:var(--accent, #7b0d15);
            color:#fff; text-decoration:none;
        }
        .nav-actions .button:hover{ filter:brightness(1.05); }

        @media (max-width: 640px){
            #emb-grid{ grid-template-columns: repeat(2, minmax(0,1fr)) !important; }
        }
    </style>

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main id="emb-page" class="container catalogue-page" role="main">
    <h1 class="section-title">Emballages</h1>
    <p class="muted" style="text-align:center;margin:-6px 0 16px;">
        Choisissez un emballage pour votre/vos fleur(s) ou votre/vos bouquet(s).<br>
        <strong class="price">Emballage offert</strong> — un seul emballage possible par fleur/bouquet.
    </p>

    <div id="emb-grid" aria-label="Liste d'emballages">
        <?php foreach ($embs as $e):
            $id    = (int)$e['EMB_ID'];
            $name  = $e['EMB_NOM'];
            $stock = (int)$e['STOCK'];
            $imgUrl = resolveEmbImage($id, $name, $BASE, $legacyMap);
            $disabled = $stock <= 0;
            ?>
            <div class="card product"
                 data-emb-id="<?= $id ?>"
                 data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                 data-stock="<?= $stock ?>"
                 data-disabled="<?= $disabled ? '1' : '0' ?>">

                <?php if ($imgUrl): ?>
                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
                <?php endif; ?>

                <h3><?= htmlspecialchars($name) ?></h3>
                <span class="stock-badge <?= $disabled ? 'out':'' ?>" data-stock-badge>
          <?= $disabled ? 'Rupture de stock' : ('En stock : '.$stock) ?>
        </span>
                <!--
                                <div class="stock-msg" data-stock-msg>
                                    <span class="dot" aria-hidden="true"></span>
                                    <span class="text" data-stock-text></span>
                                </div>-->

                <br>
                <button type="button" class="add-to-cart" data-add <?= $disabled ? 'disabled' : '' ?>>
                    Ajouter
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="nav-actions">
        <a href="<?= htmlspecialchars($retourSupp) ?>" class="button">Retour</a>
        <a href="<?= htmlspecialchars($suivantCmd) ?>" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    (function(){
        function toast(msg, type){
            if (typeof window.toast === 'function') return window.toast(msg, type);
            if (typeof window.showToast === 'function') return window.showToast(msg, type);
            alert(msg);
        }
        function showMsg(card, html){
            const box = card.querySelector('[data-stock-msg]');
            const txt = card.querySelector('[data-stock-text]');
            if (!box || !txt) return;
            txt.innerHTML = html;
            box.classList.add('show');
        }
        function hideMsg(card){
            const box = card.querySelector('[data-stock-msg]');
            if (box) box.classList.remove('show');
        }
        function updateCardUI(card, stockLeft){
            card.dataset.stock = String(stockLeft);
            const badge = card.querySelector('[data-stock-badge]');
            const btn   = card.querySelector('[data-add]');

            if (badge){
                badge.classList.toggle('out', stockLeft <= 0);
                badge.textContent = (stockLeft <= 0) ? 'Rupture de stock' : ('En stock : ' + stockLeft);
            }
            if (btn){ btn.disabled = (stockLeft <= 0); }

            card.dataset.disabled = (stockLeft <= 0) ? '1' : '0';
            if (stockLeft <= 0) showMsg(card, `🎀 <strong>${card.dataset.name}</strong> est actuellement en <strong>rupture de stock</strong>.`);
            else hideMsg(card);
        }

        // Message si on clique une carte en rupture
        document.querySelectorAll('#emb-grid .card.product').forEach(card => {
            card.addEventListener('click', function(e){
                if (this.dataset.disabled === '1' && !e.target.closest('[data-add]')) {
                    showMsg(this, `🎀 <strong>${this.dataset.name}</strong> est actuellement en <strong>rupture de stock</strong>.`);
                }
            });

            // Ajouter (décrément stock côté API)
            const btn = card.querySelector('[data-add]');
            if (!btn) return;
            btn.addEventListener('click', async function(){
                if (card.dataset.disabled === '1') {
                    showMsg(card, `🎀 <strong>${card.dataset.name}</strong> est en <strong>rupture de stock</strong>.`);
                    return;
                }
                const embId = parseInt(card.dataset.embId || '0', 10);
                try {
                    const res = await fetch(window.API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'add_emballage', emb_id: String(embId), qty: '1' })
                    });
                    const data = await res.json();

                    if (!res.ok || !data.ok) {
                        if (data?.error === 'insufficient_stock') {
                            showMsg(card, `ℹ️ Le stock disponible pour <strong>${card.dataset.name}</strong> ne permet pas d'ajouter davantage.`);
                            return;
                        }
                        throw new Error(data?.error || 'Erreur serveur');
                    }

                    updateCardUI(card, parseInt(data.stockLeft ?? '0', 10));
                    toast(`${data.name} a été ajouté à votre commande.`, 'success');

                } catch (err) {
                    console.error(err);
                    toast(`Impossible d'ajouter ${card.dataset.name}.`, 'error');
                }
            });
        });
    })();
</script>
</body>
</html>