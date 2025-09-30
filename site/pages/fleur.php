<?php
// /site/pages/fleur.php
session_start();

// Base URL (avec slash final robuste)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Couleurs connues -> métadonnées d’affichage
$imgMap = [
    'rouge'      => ['id' => 'c-rouge',  'file' => $BASE.'img/rouge.png',        'class' => 'rouge',   'swatch' => 'red',      'alt' => 'Rose rouge',       'title' => 'Rouge'],
    'rose clair' => ['id' => 'c-roseC',  'file' => $BASE.'img/rose_claire.png',  'class' => 'roseC',   'swatch' => 'pink',     'alt' => 'Rose rose claire', 'title' => 'Rose claire'],
    'rose'       => ['id' => 'c-rose',   'file' => $BASE.'img/rose.png',         'class' => 'rose',    'swatch' => '#ffa0c4',  'alt' => 'Rose rose',        'title' => 'Rose'],
    'blanc'      => ['id' => 'c-blanc',  'file' => $BASE.'img/rosesBlanche.png', 'class' => 'blanche', 'swatch' => '#e9e9e9',  'alt' => 'Rose blanche',     'title' => 'Blanc'],
    'bleu'       => ['id' => 'c-bleu',   'file' => $BASE.'img/bleu.png',         'class' => 'bleue',   'swatch' => '#0418a5',  'alt' => 'Rose bleue',       'title' => 'Bleu'],
    'noir'       => ['id' => 'c-noir',   'file' => $BASE.'img/noir.png',         'class' => 'noire',   'swatch' => '#111',     'alt' => 'Rose noire',       'title' => 'Noir'],
];

// Récupération BDD
$sql = "SELECT p.PRO_ID, p.PRO_NOM, f.FLE_COULEUR, COALESCE(f.FLE_QTE_STOCK,0) AS STOCK
        FROM FLEUR f
        JOIN PRODUIT p ON p.PRO_ID = f.PRO_ID
        ORDER BY FIELD(f.FLE_COULEUR,'rouge','rose clair','rose','blanc','bleu','noir')";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Index par couleur présente
$roses = [];
foreach ($rows as $r) {
    $c = $r['FLE_COULEUR'];
    if (isset($imgMap[$c])) $roses[$c] = $r;
}

// 1re couleur existante (fallback visuel)
$fallbackClass = null;
foreach ($imgMap as $coul => $meta) {
    if (isset($roses[$coul])) { $fallbackClass = $meta['class']; break; }
}

// 1re couleur EN STOCK (cochée par défaut)
$initialCheckedId = null;
$initialMax       = 1;
foreach ($imgMap as $coul => $meta) {
    if (!isset($roses[$coul])) continue;
    if ((int)$roses[$coul]['STOCK'] > 0) {
        $initialCheckedId = $meta['id'];
        $initialMax       = (int)$roses[$coul]['STOCK'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Fleurs</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleFleurUnique.css">

    <style>
        .stock-msg{display:none;align-items:center;gap:8px;margin:12px 0 0;padding:10px 14px;border-radius:12px;background:#fff5f7;border:1px solid #ffd6e0;color:#7a0000;font-size:.95rem}
        .stock-msg.show{display:inline-flex}
        .stock-msg .dot{width:8px;height:8px;border-radius:50%;background:#d90429;display:inline-block}
        .img-rose{display:none}
        .img-rose.show{display:block}
        .swatches{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
        .swatch{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;border:2px solid #ddd;cursor:pointer}
        .swatch>span{display:block;width:18px;height:18px;border-radius:50%;background:var(--swatch)}
        .qty{margin:14px 0 18px;width:100px}
        .btn-add{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;border:1px solid #ccc;background: #9f1313;color:#fff;cursor:pointer}
        .btn-add[disabled]{opacity:.6;cursor:not-allowed}
        .btn_accueil{display:flex;gap:10px;margin-top:18px}
        .button{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ddd;background:#fff;color: #610202}
    </style>

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS global panier -->
    <script src="<?= $BASE ?>js/commande.js?v=final" defer></script>
</head>

<body data-fallback="<?= htmlspecialchars($fallbackClass ?? '') ?>">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <section class="catalogue">
        <div class="produit">
            <div class="produit-info">
                <h3 class="product-title">La rose</h3>
                <p class="product-desc">Elle symbolise l’amour au premier regard et l’unicité.</p>

                <!-- Radios couleurs -->
                <?php foreach ($imgMap as $coul => $meta): if (!isset($roses[$coul])) continue;
                    $proId   = (int)$roses[$coul]['PRO_ID'];
                    $proNom  = $roses[$coul]['PRO_NOM'];
                    $stock   = (int)$roses[$coul]['STOCK'];
                    $checked = ($meta['id'] === $initialCheckedId);
                    $disabled = ($stock <= 0);
                    ?>
                    <input
                            type="radio"
                            id="<?= htmlspecialchars($meta['id']) ?>"
                            name="rose-color"
                            class="color-radio"
                        <?= $checked ? 'checked' : '' ?>
                        <?= $disabled ? 'disabled' : '' ?>
                            data-pro-id="<?= $proId ?>"
                            data-name="<?= htmlspecialchars($proNom) ?>"
                            data-img="<?= htmlspecialchars($meta['file']) ?>"
                            data-img-class="<?= htmlspecialchars($meta['class']) ?>"
                            data-stock="<?= $stock ?>"
                    >
                <?php endforeach; ?>

                <!-- Zone image -->
                <div class="rose" id="rose-visual">
                    <?php foreach ($imgMap as $coul => $meta): if (!isset($roses[$coul])) continue; ?>
                        <img src="<?= $meta['file'] ?>"
                             class="img-rose <?= $meta['class'] ?>"
                             alt="<?= htmlspecialchars($meta['alt']) ?>"
                             width="500">
                    <?php endforeach; ?>
                </div>

                <!-- Pastilles + message stock -->
                <fieldset class="swatches" aria-label="Couleur de la rose">
                    <?php foreach ($imgMap as $coul => $meta): if (!isset($roses[$coul])) continue;
                        $stock   = (int)$roses[$coul]['STOCK'];
                        $disabled = ($stock <= 0);
                        $dim = $disabled ? 'opacity:.45;cursor:not-allowed;' : '';
                        ?>
                        <label class="swatch"
                               for="<?= htmlspecialchars($meta['id']) ?>"
                               title="<?= htmlspecialchars($meta['title'] . ($disabled ? ' — Rupture' : '')) ?>"
                               style="<?= $dim ?>"
                               data-label="<?= htmlspecialchars($meta['alt']) ?>"
                               data-disabled="<?= $disabled ? '1':'0' ?>">
                            <span style="--swatch:<?= $meta['swatch'] ?>"></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="stock-msg" id="stockMsg" role="status" aria-live="polite">
                    <span class="dot" aria-hidden="true"></span>
                    <span class="text"></span>
                </div>

                <!-- Quantité -->
                <input
                        type="number"
                        class="qty"
                        name="qty"
                        min="1"
                        max="<?= max(1, (int)$initialMax) ?>"
                        step="1"
                        value="1"
                        inputmode="numeric"
                    <?= $initialCheckedId ? '' : 'disabled' ?>
                >

                <!-- Bouton -->
                <button id="btn-add-rose" class="btn-add" type="button">
                    Ajouter au panier
                </button>
            </div>
        </div>

        <div class="btn_accueil">
            <a href="<?= $BASE ?>interface_selection_produit.php" class="button">Retour</a>
            <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radios  = document.querySelectorAll('.color-radio');
        const qty     = document.querySelector('.qty');
        const msgBox  = document.getElementById('stockMsg');
        const msgTxt  = msgBox ? msgBox.querySelector('.text') : null;
        const images  = document.querySelectorAll('.img-rose');
        const fallback= document.body.dataset.fallback || '';
        const btn     = document.getElementById('btn-add-rose');

        function showImageByClass(cls){
            images.forEach(img => img.classList.toggle('show', img.classList.contains(cls)));
        }
        function selected(){
            const sel = document.querySelector('.color-radio:checked');
            if (!sel) return null;
            return {
                id:   parseInt(sel.dataset.proId || '0', 10),
                name: sel.dataset.name || ('Produit #' + sel.dataset.proId),
                cls:  sel.dataset.imgClass || '',
                stk:  parseInt(sel.dataset.stock || '0', 10)
            };
        }
        function showMsg(html){ if (msgBox && msgTxt){ msgTxt.innerHTML = html; msgBox.classList.add('show'); } }
        function hideMsg(){ if (msgBox) msgBox.classList.remove('show'); }

        function refreshUI(){
            const s = selected();
            if (s){
                if (s.cls) showImageByClass(s.cls);
                if (qty){
                    const m = Math.max(1, s.stk);
                    qty.max = m;
                    qty.disabled = (s.stk <= 0);
                    if ((parseInt(qty.value,10) || 1) > m) qty.value = m;
                }
                if (btn) btn.disabled = (s.stk <= 0);
                hideMsg();
            } else {
                if (fallback) showImageByClass(fallback);
                if (qty){ qty.disabled = true; qty.max = 1; qty.value = 1; }
                if (btn) btn.disabled = true;
                hideMsg();
            }
        }

        document.querySelectorAll('.swatch').forEach(label => {
            label.addEventListener('click', function(e){
                if (this.dataset.disabled === '1') {
                    const name = this.dataset.label || 'Cette rose';
                    showMsg(`🌸 <strong>${name}</strong> est en <strong>rupture de stock</strong>.`);
                    e.preventDefault();
                }
            });
        });

        ['input','change','blur'].forEach(ev => {
            if (!qty) return;
            qty.addEventListener(ev, function(){
                const s = selected(); if (!s) return;
                const m = Math.max(1, s.stk);
                const v = parseInt(qty.value || '1', 10);
                if (v > m){
                    qty.value = m;
                    showMsg(`ℹ️ Il reste <strong>${s.stk}</strong> en stock pour <strong>${s.name}</strong>. Quantité ajustée à <strong>${m}</strong>.`);
                } else hideMsg();
            });
        });

        radios.forEach(r => r.addEventListener('change', refreshUI));

        const checked = document.querySelector('.color-radio:checked');
        if (checked) showImageByClass(checked.dataset.imgClass || fallback);
        else if (fallback) showImageByClass(fallback);
        refreshUI();

        // ✅ Appel vers commande.js global
        if (btn) {
            btn.addEventListener('click', () => {
                const q = parseInt(qty?.value || '1', 10);
                if (Number.isFinite(q) && q > 0) btn.dataset.qty = String(q);
                if (typeof window.selectRose === "function") {
                    window.selectRose(btn);
                } else {
                    showMsg("⚠️ Erreur : fonction panier manquante.");
                }
            });
        }
    });
</script>
</body>
</html>
