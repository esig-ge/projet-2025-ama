<?php
// /site/pages/interface_emballage.php

session_start();

// Base URL avec slash final (ex: "/…/site/pages/")
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/**
 * Déterminer l'origine de navigation (fleur | bouquet) pour la propager.
 * Ordre de priorité:
 *  1) Paramètre explicite ?from=... dans l'URL
 *  2) Paramètre ?from=... présent dans le referer (si l'on vient de Suppléments)
 *  3) Heuristique sur le path du referer (contient "fleur.php" ou "bouquet")
 *  4) Défaut: "bouquet"
 */
$origin = $_GET['from'] ?? '';
if ($origin === '') {
    $refQuery = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
    if ($refQuery) {
        parse_str($refQuery, $qs);
        if (!empty($qs['from'])) $origin = $qs['from'];
    }
}
if ($origin === '') {
    $refPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
    if (stripos($refPath, 'fleur.php') !== false)   $origin = 'fleur';
    elseif (stripos($refPath, 'bouquet') !== false) $origin = 'bouquet';
}
if ($origin === '') $origin = 'bouquet';

// URLs nav
$retourSupp = $BASE . 'interface_supplement.php?from=' . urlencode($origin);
$suivantCmd = $BASE . 'commande.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Emballages</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>

    <script>
        function addEmballageForm(form, evt){
            (evt || window.event)?.preventDefault();
            const embInput = form.querySelector('input[name="emb_id"]');
            const btn      = form.querySelector('button.add-to-cart');
            if(!embInput) return false;
            window.addEmballage?.(embInput.value, btn);
            return false;
        }
    </script>

    <style>
        .catalogue{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:20px;justify-items:center
        }
        .catalogue .card.product{
            background:#fff;
            padding:12px;
            border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,.1);
            text-align:center;
            max-width:240px
        }
        .catalogue .card.product img{
            max-width:180px;
            height:auto;
            display:block;
            margin:0 auto 8px;
            border-radius:8px
        }
        .price{
            font-weight:600;
            color:#2c7a2c
        } /* vert pour “offert” */

        /* ===== Emballages = grille comme Suppléments ===== */
        #emb-page .catalogue{
            display:grid !important;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)) !important;
            gap:24px !important;
            max-width:1200px;
            margin:0 auto;
            align-items:stretch;
        }
        #emb-page .card.product{
            width:100% !important;
            max-width:none !important;
            background:#fff;
            border-radius:14px;
            box-shadow:0 6px 18px rgba(0,0,0,.08);
            padding:14px 14px 16px;
            display:flex;
            flex-direction:column;
            justify-content:flex-start;
        }
        #emb-page .card.product img{
            width:100%;
            height:200px; /* même gabarit visuel que Suppléments */
            object-fit:cover;
            border-radius:10px;
            margin-bottom:10px;
        }
        #emb-page .card.product h3{
            margin:6px 0 2px;
            font-size:1.05rem;
        }
        #emb-page .price{
            font-weight:600;
            color:#2c7a2c;
            margin-bottom:10px;
        }
        #emb-page .add-to-cart{
            align-self:center;
            padding:.5rem 1.1rem;
            border-radius:999px;
            background:var(--accent, #7b0d15);
            color:#fff;
            border:none;
            cursor:pointer;
            transition:transform .06s ease, filter .2s ease;
        }
        #emb-page .add-to-cart:hover{ filter:brightness(1.05); }
        #emb-page .add-to-cart:active{ transform:translateY(1px); }

        /* Petits écrans : 2 colonnes mini */
        @media (max-width: 640px){
            #emb-page .catalogue{
                grid-template-columns: repeat(2, minmax(0,1fr)) !important;
            }
        }

        /* Barre nav */
        .nav-actions{
            text-align:center;
            margin:16px 0 24px;
        }
        .nav-actions .button{
            display:inline-block;
            margin:0 6px;
            padding:.55rem 1.1rem;
            border-radius:999px;
            background:var(--accent, #7b0d15);
            color:#fff;
            text-decoration:none;
        }
        .nav-actions .button:hover{ filter:brightness(1.05); }
    </style>

</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main id="emb-page" class="container catalogue-page" role="main">
    <h1 class="section-title">Emballages</h1>
    <br>
    <p class="muted" style="text-align:center;margin:-6px 0 16px;">
        Choisissez un emballage pour votre/vos fleur(s) ou votre/vos bouquet(s).<br>
        <strong class="price">Emballage offert</strong> — un seul emballage possible par fleur/bouquet.
    </p>

    <div class="catalogue" aria-label="Liste d'emballages">
        <!-- Blanc -->
        <form class="card product" method="POST" onsubmit="return addEmballageForm(this, event)">
            <input type="hidden" name="emb_id" value="1">
            <img src="<?= $BASE ?>img/emballage_blanc.PNG" alt="Emballage blanc" loading="lazy">
            <h3>Emballage blanc</h3>
            <br>
            <p class="price">Offert</p>
            <br>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage blanc">Ajouter</button>
        </form>

        <!-- Gris -->
        <form class="card product" method="POST" onsubmit="return addEmballageForm(this, event)">
            <input type="hidden" name="emb_id" value="2">
            <img src="<?= $BASE ?>img/emballage_gris.PNG" alt="Emballage gris" loading="lazy">
            <h3>Emballage gris</h3>
            <br>
            <p class="price">Offert</p>
            <br>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage gris">Ajouter</button>
        </form>

        <!-- Noir -->
        <form class="card product" method="POST" onsubmit="return addEmballageForm(this, event)">
            <input type="hidden" name="emb_id" value="3">
            <img src="<?= $BASE ?>img/emballage_noir.PNG" alt="Emballage noir" loading="lazy">
            <h3>Emballage noir</h3>
            <br>
            <p class="price">Offert</p>
            <br>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage noir">Ajouter</button>
        </form>

        <!-- Rose -->
        <form class="card product" method="POST" onsubmit="return addEmballageForm(this, event)">
            <input type="hidden" name="emb_id" value="4">
            <img src="<?= $BASE ?>img/emballage_rose.PNG" alt="Emballage rose" loading="lazy">
            <h3>Emballage rose</h3>
            <br>
            <p class="price">Offert</p>
            <br>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage rose">Ajouter</button>
        </form>

        <!-- Violet -->
        <form class="card product" method="POST" onsubmit="return addEmballageForm(this, event)">
            <input type="hidden" name="emb_id" value="5">
            <img src="<?= $BASE ?>img/emballage_violet.PNG" alt="Emballage violet" loading="lazy">
            <h3>Emballage violet</h3>
            <br>
            <p class="price">Offert</p>
            <br>
            <button type="submit" class="add-to-cart" data-emb-name="Emballage violet">Ajouter</button>
        </form>
    </div>

    <div class="nav-actions">
        <!-- Toujours RETOUR → Suppléments, en propageant l'origine -->
        <a href="<?= htmlspecialchars($retourSupp) ?>" class="button">Retour</a>
        <!-- Suivant → Commande (pas besoin d'origine ici) -->
        <a href="<?= htmlspecialchars($suivantCmd) ?>" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
