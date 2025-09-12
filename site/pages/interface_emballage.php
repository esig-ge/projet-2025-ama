<?php
// Base URL (slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>DK Bloom — Emballages</title>

    <!-- CSS globaux -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <style>
        /* --- Mise en page locale de la grille d'emballages --- */
        main.emballages {
            max-width: 1200px;
            margin: 120px auto 80px; /* marge sup pour header fixé */
            padding: 0 24px;
        }
        .grid-emballages {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 28px;
            justify-items: center;
        }
        @media (max-width: 1100px){
            .grid-emballages { grid-template-columns: repeat(3, minmax(160px, 1fr)); }
        }
        @media (max-width: 780px){
            .grid-emballages { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
            main.emballages { margin-top: 100px; }
        }
        @media (max-width: 480px){
            .grid-emballages { grid-template-columns: 1fr; }
        }

        .emb-card{
            width: 100%;
            max-width: 240px;     /* plus petit */
            text-align: center;
        }
        .emb-card img{
            width: 100%;
            height: 180px;        /* taille uniforme */
            object-fit: contain;  /* pas de rognage */
            background: #fff;     /* fond neutre sous PNG */
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
        }
        .emb-title{
            margin: 10px 0 2px;
            font-weight: 700;
        }
        .emb-offert{
            font-size: .92rem;
            color: #1f7a1f;       /* vert doux */
        }
        .add-to-cart{
            margin-top: 10px;
        }

        /* Boutons Retour / Suivant centrés */
        .nav-actions{
            margin-top: 26px;
            display: flex;
            gap: 16px;
            justify-content: center;
        }
    </style>

    <!-- Expose BASE + API_URL au JS si tu utilises commande.js -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>
    <script src="<?= $BASE ?>js/commande.js" defer></script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="emballages">
    <h1 style="text-align:center; margin-bottom:22px;">Choisissez votre emballage</h1>

    <section class="grid-emballages">

        <article class="emb-card">
            <img src="<?= $BASE ?>img/emballage_blanc.PNG" alt="Emballage blanc">
            <div class="emb-title">Blanc</div>
            <div class="emb-offert">Emballage offert</div>
            <button class="add-to-cart"
                    data-pro-id="emb_blanc"
                    data-pro-name="Emballage blanc"
                    data-pro-price="0"
                    data-pro-img="<?= $BASE ?>img/emballage_blanc.PNG">
                Ajouter
            </button>
        </article>

        <article class="emb-card">
            <img src="<?= $BASE ?>img/emballage_noir.PNG" alt="Emballage noir">
            <div class="emb-title">Noir</div>
            <div class="emb-offert">Emballage offert</div>
            <button class="add-to-cart"
                    data-pro-id="emb_noir"
                    data-pro-name="Emballage noir"
                    data-pro-price="0"
                    data-pro-img="<?= $BASE ?>img/emballage_noir.PNG">
                Ajouter
            </button>
        </article>

        <article class="emb-card">
            <img src="<?= $BASE ?>img/emballage_rose.PNG" alt="Emballage rose">
            <div class="emb-title">Rose</div>
            <div class="emb-offert">Emballage offert</div>
            <button class="add-to-cart"
                    data-pro-id="emb_rose"
                    data-pro-name="Emballage rose"
                    data-pro-price="0"
                    data-pro-img="<?= $BASE ?>img/emballage_rose.PNG">
                Ajouter
            </button>
        </article>

        <article class="emb-card">
            <img src="<?= $BASE ?>img/emballage_gris.PNG" alt="Emballage gris">
            <div class="emb-title">Gris</div>
            <div class="emb-offert">Emballage offert</div>
            <button class="add-to-cart"
                    data-pro-id="emb_gris"
                    data-pro-name="Emballage gris"
                    data-pro-price="0"
                    data-pro-img="<?= $BASE ?>img/emballage_gris.PNG">
                Ajouter
            </button>
        </article>

        <article class="emb-card">
            <img src="<?= $BASE ?>img/emballage_violet.PNG" alt="Emballage violet">
            <div class="emb-title">Violet</div>
            <div class="emb-offert">Emballage offert</div>
            <button class="add-to-cart"
                    data-pro-id="emb_violet"
                    data-pro-name="Emballage violet"
                    data-pro-price="0"
                    data-pro-img="<?= $BASE ?>img/emballage_violet.PNG">
                Ajouter
            </button>
        </article>

    </section>

    <div class="nav-actions">
        <a href="<?= $BASE ?>interface_supplement.php" class="button">Retour</a>
        <a href="<?= $BASE ?>commande.php" class="button">Suivant</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
