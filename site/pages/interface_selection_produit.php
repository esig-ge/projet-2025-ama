<?php

$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // ex: /2526_grep/t25_6_v21/site/pages/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Nos produits</title>

    <!-- CSS global header/footer + CSS de la page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
    <style>
        :root {
            --dk-bordeaux:#5C0012;
            --dk-bordeaux-2:#8A1B2E;
            --dk-paper:#ffffff;
            --dk-ink:#1b1b1b;
            --ink-soft:#4a4a4a;
            --gold:#B08D57;
            --radius:22px;
            --shadow:0 12px 32px rgba(0,0,0,.12);
        }

        /* Fond élégant */
        .catalogue.luxe {
            position: relative;
            isolation: isolate;
            min-height: calc(100vh - 120px); /* occupe presque tout l'écran (120px = header+footer) */
            display: flex;
            align-items: center;  /* centrage vertical */
            justify-content: center; /* centrage horizontal */
            text-align: center;
            padding: 40px 16px;
            background:
                    radial-gradient(1200px 500px at 20% -180px, rgba(92,0,18,.10), transparent 70%),
                    linear-gradient(180deg, #fff, #fff);
        }
        .catalogue.luxe::before {
            content:"";
            position:absolute; inset:-10% -5% auto -5%; height:60%;
            background:
                    radial-gradient(18px 12px at 10% 40%, rgba(92,0,18,.06) 40%, transparent 41%) 0 0/160px 120px,
                    radial-gradient(14px 10px at 60% 30%, rgba(92,0,18,.05) 40%, transparent 41%) 60px 50px/170px 130px;
            pointer-events:none; z-index:-1;
        }

        /* Bloc principal centré */
        .hero-center {
            max-width: 700px;
            margin: 0 auto;
        }

        /* Titres */
        .hero-center h1 {
            margin: 0 0 8px;
            color: var(--dk-bordeaux);
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 800;
        }
        .hero-center .sub {
            margin: 0 0 20px;
            color: var(--ink-soft);
            font-size: clamp(15px,2vw,18px);
        }

        /* Image showcase */
        .showcase {
            background: rgba(255,255,255,.85);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: clamp(6px,2vw,14px);
            margin: 0 auto 22px;
            display: inline-block;
        }
        .showcase img {
            width: min(280px, 70vw); /* image plus petite */
            height: auto;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 10px 22px rgba(0,0,0,.15));
        }

        /* Boutons catégories */
        .cat-nav {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .pill {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 999px;
            background: #fff;
            color: var(--dk-bordeaux);
            font-weight: 700;
            text-decoration: none;
            border: 2px solid rgba(92,0,18,.25);
            box-shadow: 0 6px 16px rgba(92,0,18,.08);
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .pill:hover {
            transform: translateY(-1px);
            border-color: var(--dk-bordeaux);
            box-shadow: 0 12px 24px rgba(92,0,18,.18);
        }

        /* Responsive mobile */
        @media (max-width: 600px) {
            .showcase img {
                width: min(240px, 85vw);
            }
            .cat-nav {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            .pill {
                width: 70%;
                text-align: center;
            }
        }

        /* Effet luxe sur l'image */
        .showcase {
            position: relative;
            display: inline-block;
            overflow: hidden; /* pour que le shine ne déborde pas */
            border-radius: var(--radius);
        }

        .showcase img {
            width: min(280px, 70vw);
            height: auto;
            display: block;
            transition: transform 0.4s ease;
        }

        /* Zoom léger + reflet qui passe */
        .showcase:hover img {
            transform: scale(1.05);
        }

        .showcase::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -60%;
            width: 50%;
            height: 200%;
            background: linear-gradient(
                    120deg,
                    rgba(255,255,255,0) 0%,
                    rgba(255,255,255,0.6) 50%,
                    rgba(255,255,255,0) 100%
            );
            transform: skewX(-20deg);
            opacity: 0;
        }

        .showcase:hover::after {
            animation: shine 1.2s forwards;
        }

        @keyframes shine {
            0% {
                left: -60%;
                opacity: 0;
            }
            20% {
                opacity: 1;
            }
            100% {
                left: 120%;
                opacity: 0;
            }
        }
        .apropos {
            position: relative;
            isolation: isolate;
            background:
                    radial-gradient(3px 3px at 20px 20px, rgba(92,0,18,.06) 98%, transparent 100%) 0 0/120px 120px,
                    radial-gradient(3px 3px at 80px 60px, rgba(92,0,18,.05) 98%, transparent 100%) 0 0/120px 120px,
                    #fff; /* couleur de base */
        }


    </style>
</head>
<body id="body_prod">

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="apropos catalogue luxe">
    <section class="hero-center">
        <h1>Nos produits</h1>
        <p class="sub">Trouve la création qui raconte ton histoire.</p>

        <figure class="showcase">
            <img id="heroImage" src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Visuel DK Bloom">
        </figure>

        <nav class="cat-nav">
            <a class="pill"
               href="<?= $BASE ?>fleur.php"
               data-img="<?= $BASE ?>img/rouge.png">Fleurs</a>

            <a class="pill"
               href="<?= $BASE ?>interface_catalogue_bouquet.php"
               data-img="<?= $BASE ?>img/BouquetRosePale.png">Bouquets</a>

            <a class="pill"
               href="<?= $BASE ?>coffret.php"
               data-img="<?= $BASE ?>img/ExempleNoel.png">Coffret</a>
        </nav>
    </section>
</main>




<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- JS global si besoin -->
<script src="<?= $BASE ?>js/script.js" defer></script>

<script>
    (() => {
        const hero = document.getElementById('heroImage');
        if (!hero) return;

        const defaultSrc = hero.getAttribute('src');
        const links = document.querySelectorAll('.cat-nav .pill[data-img]');

        // Précharger
        links.forEach(a => { const i = new Image(); i.src = a.dataset.img; });

        const setHero = (url) => {
            if (!url || hero.src.endsWith(url)) return;
            hero.style.opacity = .6;
            const tmp = new Image();
            tmp.onload = () => { hero.src = url; hero.style.opacity = 1; };
            tmp.src = url;
        };

        links.forEach(link => {
            const url = link.dataset.img;

            // Survol / focus clavier => montrer
            link.addEventListener('mouseenter', () => setHero(url));
            link.addEventListener('focus',      () => setHero(url));

            // Sortie / blur => revenir
            const back = () => setHero(defaultSrc);
            link.addEventListener('mouseleave', back);
            link.addEventListener('blur',       back);
        });
    })();
</script>

</body>
</html>
