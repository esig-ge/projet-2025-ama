<?php
// /site/pages/interface_supplement.php
session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Récupération dynamique depuis la BDD
$sql = "SELECT SUP_ID, SUP_NOM, SUP_PRIX_UNITAIRE AS PRICE, COALESCE(SUP_QTE_STOCK,0) AS STOCK
        FROM SUPPLEMENT
        ORDER BY SUP_NOM";
$supps = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   Résolution d'image « intelligente »
   ============================================================ */

// anciens noms de fichiers que tu utilises déjà (compat)
$legacyMap = [
    1=>'ours_blanc.PNG',
    2=>'happybirthday.PNG',
    3=>'papillon_doree.PNG',
    4=>'baton_coeur.PNG',
    5=>'diamant.PNG',
    6=>'couronne.PNG',
    7=>'paillette_argent.PNG',
    8=>'lettre.png',
    9=>'carte.PNG',
];

function slugify_name(string $name): string {
    $slug = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
    if ($slug === false || $slug === null) $slug = $name;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i','_', $slug));
    $slug = trim($slug, '_');
    return $slug ?: 'image';
}

function resolveSuppImage(int $id, string $name, string $BASE, array $legacyMap): ?string {
    $fsRoot = __DIR__; // /site/pages

    // 1) nouvelle convention
    $fs1 = $fsRoot . "/img/supplements/supp_{$id}.png";
    if (is_file($fs1)) return $BASE . "img/supplements/supp_{$id}.png";

    // 2) anciens fichiers (compat)
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

    return null; // rien trouvé
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Suppléments</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <style>
        #supp-grid{
            display:grid; gap:20px; justify-items:center;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        }
        @media (min-width:1500px){ #supp-grid{ grid-template-columns: repeat(5, minmax(0, 1fr)); } }

        .card.product{
            background:#fff; padding:12px; border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,.1);
            text-align:center; width:100%; max-width:260px; position:relative;
        }
        .card.product img{ display:block; margin:0 auto 8px; border-radius:8px; max-width:180px; height:auto; }

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

        .qty{ width:90px; }
        .add-to-cart[disabled]{ opacity:.5; cursor:not-allowed; }
    </style>

    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container catalogue-page" role="main">
    <h1 class="section-title">Suppléments</h1>

    <div id="supp-grid" class="catalogue" aria-label="Liste de suppléments">
        <?php foreach ($supps as $s):
            $id    = (int)$s['SUP_ID'];
            $name  = $s['SUP_NOM'];
            $price = (float)$s['PRICE'];
            $stock = (int)$s['STOCK'];
            $imgUrl = resolveSuppImage($id, $name, $BASE, $legacyMap);
            $disabled = $stock <= 0;
            ?>
            <div class="card product"
                 data-sup-id="<?= (int)$id ?>"
                 data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                 data-price="<?= htmlspecialchars(number_format($price, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                 data-stock="<?= (int)$stock ?>"
                 data-disabled="<?= $disabled ? '1' : '0' ?>">

            <?php if ($imgUrl): ?>
                    <img src="<?= htmlspecialchars($imgUrl) ?>"
                         alt="<?= htmlspecialchars($name) ?>"
                         loading="lazy">
                <?php endif; ?>

                <h3><?= htmlspecialchars($name) ?></h3>
                <p class="price"><?= number_format($price, 2, '.', '\'') ?> CHF</p>

                <span class="stock-badge <?= $disabled ? 'out':'' ?>" data-stock-badge>
                    <?= $disabled ? 'Rupture de stock' : ('En stock : '.$stock) ?>
                </span>

                <div class="qty-wrap">
                    <label class="sr-only" for="qty-<?= $id ?>">Quantité</label>
                    <input id="qty-<?= $id ?>"
                           type="number"
                           class="qty"
                           name="qty"
                           min="1"
                           max="<?= max(1, $stock) ?>"
                           step="1"
                           value="1"
                           inputmode="numeric"
                        <?= $disabled ? 'disabled' : '' ?>>
                </div>

                <div class="stock-msg" data-stock-msg>
                    <span class="dot" aria-hidden="true"></span>
                    <span class="text" data-stock-text></span>
                </div>

                <br>
                <button type="button" class="add-to-cart" data-add <?= $disabled ? 'disabled' : '' ?>>
                    Ajouter
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Navigation (identique à tes autres pages)
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
    $retour  = ($origin === 'fleur') ? $BASE . 'fleur.php' : $BASE . 'interface_catalogue_bouquet.php';
    $suivant = $BASE . 'interface_emballage.php?from=' . urlencode($origin);
    ?>
    <div class="nav-actions" style="text-align:center; margin:16px 0 24px;">
        <a href="<?= htmlspecialchars($retour) ?>" class="button">Retour</a>
        <a href="<?= htmlspecialchars($suivant) ?>" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Optionnel : si ton layout n'a pas déjà le conteneur de toast -->
<div id="toast-root" aria-live="polite" aria-atomic="true" style="position:fixed;right:1rem;top:1rem;z-index:9999"></div>

<script>
    (function(){
        // Utilise le toast global si présent (comme sur la page bouquet).
        // Sinon fallback minimal sur alert().
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

        function clampQty(card){
            const qtyInput = card.querySelector('.qty');
            const stock    = parseInt(card.dataset.stock || '0', 10);
            if (!qtyInput) return;

            const min = 1;
            const max = Math.max(1, stock);
            let val = parseInt(qtyInput.value || '1', 10);
            if (isNaN(val) || val < min) val = min;
            if (val > max) {
                qtyInput.value = max;
                showMsg(card, `ℹ️ Il reste <strong>${stock}</strong> en stock pour <strong>${card.dataset.name}</strong>. La quantité a été ajustée à <strong>${max}</strong>.`);
            } else {
                hideMsg(card);
            }
        }

        function updateCardUI(card, stockLeft){
            card.dataset.stock = String(stockLeft);
            const badge = card.querySelector('[data-stock-badge]');
            const qty   = card.querySelector('.qty');
            const btn   = card.querySelector('[data-add]');

            if (badge){
                badge.classList.toggle('out', stockLeft <= 0);
                badge.textContent = (stockLeft <= 0) ? 'Rupture de stock' : ('En stock : ' + stockLeft);
            }
            if (qty){
                qty.max = String(Math.max(1, stockLeft));
                qty.disabled = (stockLeft <= 0);
                if (parseInt(qty.value,10) > stockLeft) qty.value = String(Math.max(1, stockLeft));
            }
            if (btn){ btn.disabled = (stockLeft <= 0); }

            card.dataset.disabled = (stockLeft <= 0) ? '1' : '0';
            if (stockLeft <= 0) showMsg(card, `🌸 <strong>${card.dataset.name}</strong> est actuellement en <strong>rupture de stock</strong>.`);
            else hideMsg(card);
        }

        document.querySelectorAll('#supp-grid .card.product').forEach(card => {
            // Message si on clique une carte en rupture (partout sauf sur qty/bouton)
            card.addEventListener('click', function(e){
                if (this.dataset.disabled === '1' &&
                    !e.target.closest('[data-add]') &&
                    !e.target.closest('.qty')) {
                    showMsg(this, `🌸 <strong>${this.dataset.name}</strong> est actuellement en <strong>rupture de stock</strong>.`);
                }
            });

            const qty = card.querySelector('.qty');
            if (qty){
                ['input','change','blur'].forEach(ev => qty.addEventListener(ev, () => clampQty(card)));
            }

            // Ajouter (décrément stock côté API)
            const btn = card.querySelector('[data-add]');
            if (btn){
                btn.addEventListener('click', async function(){
                    if (card.dataset.disabled === '1') {
                        showMsg(card, `🌸 <strong>${card.dataset.name}</strong> est en <strong>rupture de stock</strong>.`);
                        return;
                    }
                    const qtyInput = card.querySelector('.qty');
                    const supId    = parseInt(card.dataset.supId || '0', 10);
                    const q        = Math.max(1, parseInt(qtyInput?.value || '1', 10));

                    try {
                        const res  = await fetch(window.API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'add_supplement', sup_id: String(supId), qty: String(q) })
                        });

                        const raw  = await res.text();
                        let data; try { data = JSON.parse(raw); } catch { data = null; }

                        if (!res.ok || !data || data.ok === false) {
                            const code = data?.error || `HTTP ${res.status}`;
                            const msg  = data?.msg || (raw && raw.length < 300 ? raw : 'Erreur serveur');
                            if (code === 'insufficient_stock') {
                                clampQty(card);
                                showMsg(card, `ℹ️ Le stock disponible pour <strong>${card.dataset.name}</strong> ne permet pas cette quantité.`);
                                return;
                            }
                            console.error('add_supplement failed:', {code, msg, raw});
                            toast(`Échec: ${msg}`, 'error');
                            throw new Error(`${code}: ${msg}`);
                        }

                        // OK
                        const left = parseInt(data.stockLeft ?? '0', 10);
                        updateCardUI(card, isFinite(left) ? left : 0);

                        // >>> MESSAGE STYLE "BOUQUET" (sans "(e)")
                        toast(`${data.name} a bien été ajouté au panier !`, 'success');

                        // si tu as un résumé panier sur la page, on le rafraîchit (optionnel)
                        if (typeof window.renderCart === 'function') {
                            try { await window.renderCart(); } catch {}
                        }
                    } catch (err) {
                        console.error(err);
                        toast(`Impossible d'ajouter ${card.dataset.name}.`, 'error');
                    }
                });
            }

        });
    })();

        document.addEventListener('DOMContentLoaded', () => {
        const fmt = n => (Number(n) || 0).toFixed(2) + ' CHF';

        document.querySelectorAll('#supp-grid .card.product').forEach(card => {
        const nameEl  = card.querySelector('.supp-name');
        const priceEl = card.querySelector('.price');
        const supId   = parseInt(card.dataset.supId || '0', 10);

        // 1) Met en cohérence nom/prix avec data-* (ne change pas la mise en page)
        if (nameEl && card.dataset.name)  nameEl.textContent  = card.dataset.name;
        if (priceEl && card.dataset.price) priceEl.textContent = fmt(card.dataset.price);


    });
    });



</script>
</body>
</html>
