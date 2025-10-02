<?php
// /site/pages/interface_catalogue_bouquet.php
session_start();

/* === Base URL avec un slash final (ex: "/…/site/pages/") ===
   -> j'utilise `dirname($_SERVER['...'])` pour dériver la base de la page en cours
*/
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* ============================================================
   DATA : tailles de bouquets + variantes par couleur
   ------------------------------------------------------------
   - 1) Je récupère les tailles distinctes (nombre de roses)
   - 2) Pour chaque taille, je charge les variantes (couleurs)
   - 3) Je structure dans $bouquets pour boucler facilement en HTML
   ============================================================ */

// 1) tailles distinctes
$tailles = $pdo->query("
    SELECT DISTINCT b.BOU_NB_ROSES AS nb
    FROM BOUQUET b
    ORDER BY nb ASC
")->fetchAll(PDO::FETCH_COLUMN);

// 2) variantes (par taille)
$bouquets = []; // ex: [ ['nb'=>12, 'prix'=>45.00, 'variants'=>[['pro_id'=>1,'couleur'=>'rouge','stock'=>10,'prix'=>45.00], ...]], ... ]
foreach ($tailles as $nb) {
    $st = $pdo->prepare("
<<<<<<< HEAD
        SELECT p.PRO_ID, p.PRO_PRIX, p.PRO_NOM, b.BOU_COULEUR,b.BOU_DESCRIPTION, b.BOU_QTE_STOCK
=======
        SELECT p.PRO_ID, p.PRO_PRIX, b.BOU_COULEUR, b.BOU_QTE_STOCK
>>>>>>> fa55c79541d63de4a3b2e64f368b59c68daf630d
        FROM BOUQUET b
        JOIN PRODUIT p ON p.PRO_ID = b.PRO_ID
        WHERE b.BOU_NB_ROSES = :nb
        ORDER BY b.BOU_COULEUR
    ");
    $st->execute([':nb' => $nb]);
    $variants = $st->fetchAll(PDO::FETCH_ASSOC);
<<<<<<< HEAD
    if (!$variants) { continue; }
// PAS OUBLIER DE RAJOUTER LES VARIABLES DE BOU_DESCRIPTION
=======
    if (!$variants) continue;

    // je prends le prix depuis la 1ère variante (supposé identique pour toutes les couleurs)
>>>>>>> fa55c79541d63de4a3b2e64f368b59c68daf630d
    $bouquets[] = [
        'nb'       => (int)$nb,
        'prix'     => (float)$variants[0]['PRO_PRIX'],
        'variants' => array_map(function ($v) {
            return [
                'pro_id'  => (int)$v['PRO_ID'],
                'couleur' => (string)$v['BOU_COULEUR'],
                'stock'   => (int)$v['BOU_QTE_STOCK'],
                'prix'    => (float)$v['PRO_PRIX'],
            ];
        }, $variants),
    ];
}

/* === Petite util : image par nombre de roses ===
   -> je mappe rapidement pour éviter les 404 si l’image manque
*/
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
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Catalogue bouquet</title>

    <!-- CSS globaux (déjà existants) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS dédié à cette page (nouveau) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">

    <!-- Expose BASE + API_URL au JS (utilisé par commande.js) -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier (callApi / addToCart / etc.) -->
    <script src="<?= $BASE ?>js/commande.js?v=bouquet-qty3" defer></script>

    <script>
        /* ========================================================
           JS page : gestion couleur/stock/qty + envoi addToCart()
           -> je laisse ce JS ici car il est propre à cette page
           -> tout le CSS a été déplacé dans page-catalogue-bouquet.css
           ======================================================== */

        // Intercepte le submit et applique les bornes qty/stock
        function addBouquetForm(e, form) {
            e.preventDefault();

            const proInput = form.querySelector('input[name="pro_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            const qtyInput = form.querySelector('.qty-input');
            if (!proInput || !btn || !qtyInput) return false;

            const stock = parseInt(qtyInput.getAttribute('max') || '999', 10);
            let qty     = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);

            // si dépassement stock -> j'ajuste + message
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

            // je passe la qty au bouton (addToCart la lit)
            btn.dataset.qty = String(qty);
            btn.setAttribute('data-qty', String(qty));

            // info couleur (facultative côté API car PRO_ID cible déjà la variante)
            const color = form.querySelector('input[type="radio"][name^="couleur_"]:checked')?.value || null;

            const pid   = proInput.value;
            const extra = { qty, color, type: 'bouquet' }; // ENUM('bouquet','fleur','coffret')
            if (typeof addToCart === 'function') addToCart(pid, btn, extra);
            return false;
        }

        // Au chargement : j’installe les comportements sur chaque carte
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.bouquet-card').forEach(card => {
                const hiddenPro = card.querySelector('input[name="pro_id"]');
                const qtyInput  = card.querySelector('.qty-input');
                const stockSpan = card.querySelector('.stock-note .stock-badge');
                const radios    = card.querySelectorAll('input[type="radio"][name^="couleur_"]');
                const addBtn    = card.querySelector('.add-to-cart');
                const msgBox    = card.querySelector('.stock-message');

                const showMsg = (html, type = 'error') => {
                    if (!msgBox) return;
                    msgBox.innerHTML = `<span class="dot" aria-hidden="true"></span><span class="text">${html}</span>`;
                    msgBox.classList.add('show');
                    if (type === 'info') msgBox.classList.add('info'); else msgBox.classList.remove('info');
                };
                const hideMsg = () => { if (msgBox) { msgBox.classList.remove('show', 'info'); msgBox.innerHTML = ''; } };

                // clic sur une pastille désactivée -> petit message rupture
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

                // quand je change de couleur -> je mets à jour pro_id + bornes qty + état bouton
                function refreshFromRadio(r) {
                    const proId = r.getAttribute('data-pro-id');
                    const stock = parseInt(r.getAttribute('data-stock') || '0', 10);
                    const color = r.value || '';

                    hiddenPro.value = proId;

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
                        showMsg(`🌸 <strong>${color || 'Cette couleur'}</strong> est en <strong>rupture de stock</strong>.`, 'error');
                    }
                }

                // écouteurs
                radios.forEach(r => r.addEventListener('change', () => refreshFromRadio(r)));

                // si je tape une quantité à la main -> je recalcule vs stock de la couleur sélectionnée
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

                // init avec la couleur cochée par défaut
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
    // couleurs -> j'associe une couleur CSS simple pour la pastille
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

            // variante par défaut = première en stock sinon première tout court
            $def = null;
            foreach ($item['variants'] as $v) { if ($v['stock'] > 0) { $def = $v; break; } }
            if (!$def) $def = $item['variants'][0];
            ?>
<<<<<<< HEAD
            <form class="card product bouquet-card"
                  data-nb="<?= (int)$nb ?>"
                  onsubmit="return addBouquetForm(event, this)">
                <!-- valeur mise à jour à chaque changement de radio -->
=======
            <form class="card product bouquet-card" onsubmit="return addBouquetForm(event, this)">
                <!-- pro_id mis à jour quand je change la couleur -->
>>>>>>> fa55c79541d63de4a3b2e64f368b59c68daf630d
                <input type="hidden" name="pro_id" value="<?= (int)$def['pro_id'] ?>">
                <img src="<?= htmlspecialchars($img) ?>" alt="Bouquet <?= (int)$nb ?> roses" loading="lazy">
<<<<<<< HEAD
                <h3>  <?= htmlspecialchars($def['pro_nom'], ENT_QUOTES, 'UTF-8')  ?></h3>
=======
                <h3>Bouquet <?= (int)$nb ?></h3>
>>>>>>> fa55c79541d63de4a3b2e64f368b59c68daf630d
                <p class="price"><?= number_format($prix, 2, '.', "'") ?> CHF</p>

                <!-- pastilles de couleurs -->
                <div class="swatches" role="group" aria-label="Couleur">
                    <?php foreach ($item['variants'] as $i => $v):
                        $c = $v['couleur'];
                        $stock = (int)$v['stock'];
                        $checked = ($v['pro_id'] === $def['pro_id']);
                        ?>
                        <label title="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                            <input
                                    type="radio"
                                    name="couleur_<?= (int)$nb ?>"
                                    value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                    data-pro-id="<?= (int)$v['pro_id'] ?>"
                                    data-stock="<?= $stock ?>"
                                <?= $checked ? 'checked' : '' ?>
                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                            <span class="swatch" style="--c:<?= htmlspecialchars($cssColor[$c] ?? '#ccc') ?>"></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- stock affiché -->
                <p id="stock-<?= (int)$nb ?>" class="stock-note">
                    <?php if ($def['stock'] > 0): ?>
                        <span class="stock-badge">Stock : <?= (int)$def['stock'] ?></span>
                    <?php else: ?>
                        <span class="stock-badge oos">Rupture de stock</span>
                    <?php endif; ?>
                </p>

                <!-- quantité + message dynamique -->
                <input
                        type="number"
                        id="qty-<?= (int)$nb ?>"
                        min="1"
                        max="<?= (int)$def['stock'] ?>"
                        value="1"
                        class="qty-input">
                <div id="msg-<?= (int)$nb ?>" class="stock-message" aria-live="polite"></div>

                <button
                        type="submit"
                        class="add-to-cart"
<<<<<<< HEAD
                        data-pro-name="<?= htmlspecialchars($def['pro_nom'] , ENT_QUOTES, 'UTF-8') ?>"
                    <?= $def['stock']<=0 ? 'disabled' : '' ?>>
=======
                        data-pro-name="Bouquet <?= (int)$nb ?>"
                    <?= $def['stock'] <= 0 ? 'disabled' : '' ?>>
>>>>>>> fa55c79541d63de4a3b2e64f368b59c68daf630d
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
