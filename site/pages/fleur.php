<?php
// Base URL (avec slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Couleurs connues -> métadonnées d'affichage
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

// Index par couleur (seulement celles présentes en BDD)
$roses = [];
foreach ($rows as $r) {
    $c = $r['FLE_COULEUR'];
    if (isset($imgMap[$c])) $roses[$c] = $r;
}

// 1re couleur existante (pour l’aperçu si tout est à 0)
$fallbackClass = null;
foreach ($imgMap as $coul => $meta) {
    if (isset($roses[$coul])) { $fallbackClass = $meta['class']; break; }
}

// 1re couleur EN STOCK (pour cocher par défaut)
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

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleFleurUnique.css">

    <!-- Styles pour messages stock -->
    <style>
        .stock-msg{
            display:none; align-items:center; gap:8px;
            margin:12px 0 0; padding:10px 14px; border-radius:12px;
            background:#fff5f7; border:1px solid #ffd6e0; color:#7a0000; font-size:.95rem;
        }
        .stock-msg.show{ display:inline-flex; }
        .stock-msg .dot{ width:8px; height:8px; border-radius:50%; background:#d90429; display:inline-block; }
        .img-rose{ display:none; }
        .img-rose.show{ display:block; }
    </style>

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- (On laisse ton JS global) -->
    <script src="<?= $BASE ?>js/commande.js?v=qty4" defer></script>
</head>

<body data-fallback="<?= htmlspecialchars($fallbackClass ?? '') ?>">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <section class="catalogue">
        <div class="produit">
            <div class="produit-info">
                <h3 class="product-title">La rose</h3>
                <p class="product-desc">
                    Elle est le symbole d’un amour né au premier regard et incarne l’unicité.
                </p>

                <!-- Radios dynamiques (disabled si rupture ; 1re en stock cochée) -->
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

                <!-- Pastilles (labels) + message stock -->
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

                <!-- Quantité : max = stock ; disabled si 0 -->
                <input
                        type="number"
                        class="qty"
                        name="qty"
                        min="1"
                        max="<?= max(1, $initialMax) ?>"
                        step="1"
                        value="1"
                        inputmode="numeric"
                    <?= $initialCheckedId ? '' : 'disabled' ?>
                >

                <!-- Bouton -->
                <?php
                $initialStock = 0;
                if ($initialCheckedId) {
                    foreach ($imgMap as $coul => $meta) {
                        if ($meta['id'] === $initialCheckedId && isset($roses[$coul])) {
                            $initialStock = (int)$roses[$coul]['STOCK']; break;
                        }
                    }
                }
                ?>
                <button class="btn" type="button" onclick="selectRose(this)" <?= ($initialStock > 0) ? '' : 'disabled' ?>>
                    Sélectionner
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

<!-- UX : image fallback, messages rupture & “max stock”, et SELECTROSE qui affiche le nom -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radios = document.querySelectorAll('.color-radio');
        const qty    = document.querySelector('.qty');
        const btn    = document.querySelector('.btn');
        const msgBox = document.getElementById('stockMsg');
        const msgTxt = msgBox ? msgBox.querySelector('.text') : null;
        const images = document.querySelectorAll('.img-rose');
        const fallback = document.body.dataset.fallback || '';

        function showImageByClass(cls){
            images.forEach(img => {
                img.classList.toggle('show', img.classList.contains(cls));
            });
        }

        function currentSelection(){
            const sel = document.querySelector('.color-radio:checked');
            if (!sel) return null;
            return {
                proId: parseInt(sel.dataset.proId || '0',10),
                name:  sel.dataset.name || ('Produit #' + sel.dataset.proId),
                stock: parseInt(sel.dataset.stock || '0',10),
                imgCls: sel.dataset.imgClass || ''
            };
        }

        function showMessage(html){
            if (!msgBox || !msgTxt) return;
            msgTxt.innerHTML = html;
            msgBox.classList.add('show');
        }
        function hideMessage(){ if (msgBox) msgBox.classList.remove('show'); }

        function refreshUI(){
            const sel = currentSelection();
            if (sel){
                if (sel.imgCls) showImageByClass(sel.imgCls);
                if (qty){
                    const max = Math.max(1, sel.stock);
                    qty.max = max;
                    qty.disabled = (sel.stock <= 0);
                    if (parseInt(qty.value,10) > max) qty.value = max;
                }
                if (btn) btn.disabled = (sel.stock <= 0);
                hideMessage();
            } else {
                if (fallback) showImageByClass(fallback);
                if (qty){ qty.disabled = true; qty.max = 1; qty.value = 1; }
                if (btn) btn.disabled = true;
                hideMessage();
            }
        }

        // Pastille cliquée alors que la radio est disabled -> message rupture
        document.querySelectorAll('.swatch').forEach(label => {
            label.addEventListener('click', function(e){
                if (this.dataset.disabled === '1') {
                    const name = this.dataset.label || 'Cette rose';
                    showMessage(`🌸 <strong>${name}</strong> est actuellement en <strong>rupture de stock</strong>.`);
                    e.preventDefault();
                }
            });
        });

        // Saisie quantité > stock -> message et clamp
        ['input','change','blur'].forEach(ev => {
            if (!qty) return;
            qty.addEventListener(ev, function(){
                const sel = currentSelection(); if (!sel) return;
                const max = Math.max(1, sel.stock);
                const val = parseInt(qty.value || '1', 10);
                if (val > max){
                    qty.value = max;
                    showMessage(`ℹ️ Il reste <strong>${sel.stock}</strong> en stock pour <strong>${sel.name}</strong>. La quantité a été ajustée à <strong>${max}</strong>.`);
                } else {
                    hideMessage();
                }
            });
        });

        radios.forEach(r => r.addEventListener('change', refreshUI));

        // Initialisation
        const checked = document.querySelector('.color-radio:checked');
        if (checked) showImageByClass(checked.dataset.imgClass || fallback);
        else if (fallback) showImageByClass(fallback);
        refreshUI();

        // === Remplace la fonction globale selectRose pour afficher le NOM dans le toast ===
        window.selectRose = async function(btnEl){
            const sel = currentSelection();
            if (!sel) return;

            const q = parseInt(qty?.value || '1', 10);
            if (q > sel.stock){
                // sécurité si l’utilisateur a forcé la valeur
                qty.value = sel.stock;
                showMessage(`ℹ️ Il reste <strong>${sel.stock}</strong> en stock pour <strong>${sel.name}</strong>. La quantité a été ajustée.`);
                return;
            }
            if (sel.stock <= 0){
                showMessage(`🌸 <strong>${sel.name}</strong> est en <strong>rupture de stock</strong>.`);
                return;
            }

            try {
                // Appel simple vers l’API panier (POST urlencoded).
                // Adapte les clés si ton API diffère (action/type/pro_id/qty).
                const res = await fetch(window.API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'add',
                        type:   'fleur',
                        pro_id: String(sel.proId),
                        qty:    String(q)
                    })
                });
                const data = await res.json().catch(()=> ({}));
                if (!res.ok || (data && data.ok === false)) throw new Error(data.error || 'Erreur panier');

                // Toast lisible avec le NOM
                if (typeof window.toast === 'function') window.toast(`${sel.name} a bien été ajoutée au panier !`, 'success');
                else if (typeof window.showToast === 'function') window.showToast(`${sel.name} a bien été ajoutée au panier !`, 'success');
                else alert(`${sel.name} a bien été ajoutée au panier !`);
            } catch (e){
                if (typeof window.toast === 'function') window.toast(`Impossible d'ajouter ${sel.name}.`, 'error');
                else if (typeof window.showToast === 'function') window.showToast(`Impossible d'ajouter ${sel.name}.`, 'error');
                else alert(`Impossible d'ajouter ${sel.name}.`);
            }
        };
    });
</script>
</body>
</html>
