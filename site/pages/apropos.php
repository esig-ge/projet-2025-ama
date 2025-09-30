<?php
session_start();

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$isLogged = !empty($_SESSION['per_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — À propos</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_apropos.css">
    <style>
        :root{
            --dk-bordeaux:#5C0012;
            --dk-bordeaux-2:#8A1B2E;
            --dk-ink:#1b1b1b;
            --dk-ink-soft:#444;
            --dk-paper:#ffffff;
            --dk-gold:#B08D57;
            --dk-bg:#faf7f7;
            --radius:20px;
            --shadow:0 10px 30px rgba(0,0,0,.08);
        }

        body {
            margin: 0;
            padding: 0;
        }


        /* Reset local */
        .apropos *{box-sizing:border-box}
        .apropos img{max-width:100%;display:block}

        /* Containers */
        .container{
            width:min(1100px, 92%);
            margin-inline:auto;
        }

        /* HERO */
        .hero{
            background: linear-gradient(180deg, var(--dk-bordeaux) 0%, var(--dk-bordeaux-2) 100%);
            color:#fff;
            padding: clamp(64px, 12vw, 140px) 0 64px;
            text-align:center;
            position:relative;
        }
        .hero::after{
            content:"";
            position:absolute; left:0; right:0; bottom:-24px; height:24px;
            background: linear-gradient(to bottom, rgba(0,0,0,.15), transparent);
            opacity:.06; pointer-events:none;
        }
        .hero h1{
            font-size: clamp(34px, 4.5vw, 56px);
            letter-spacing:.5px;
            margin:0 0 8px 0;
        }
        .hero p{
            margin:0;
            opacity:.9;
            font-size: clamp(16px, 2.1vw, 20px);
        }

        /* STORY */
        .story{
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            gap: clamp(24px, 4vw, 48px);
            align-items:center;
            padding: clamp(40px, 6vw, 72px) 0;
        }
        .story__text h2{
            font-size: clamp(26px, 3vw, 34px);
            color:var(--dk-bordeaux);
            margin:0 0 12px;
        }
        .story__text p{
            line-height:1.75;
            color:var(--dk-ink-soft);
            font-size: clamp(16px, 2vw, 18px);
        }
        .story__image{
            background: var(--dk-paper);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        /* VALEURS */
        .values{padding: 16px 0 8px}
        .values h2{
            text-align:center; margin: 0 0 18px; color:var(--dk-bordeaux);
            font-size: clamp(24px, 3vw, 30px);
        }
        .values__grid{
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }
        .card{
            background: var(--dk-paper);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            border: 1px solid #eee;
            text-align:center;
        }
        .card h3{
            margin:0 0 6px; font-size: 20px; color: var(--dk-ink);
        }
        .card p{ margin:0; color:var(--dk-ink-soft); line-height:1.6 }

        /* MARQUEE / SLIDER */
        .marquee{
            --fade: 80px;
            margin: clamp(30px, 6vw, 60px) 0;
            position:relative;
            overflow:hidden;
            padding: 18px 0;
            background: var(--dk-bg);
            border-top:1px solid #eee;
            border-bottom:1px solid #eee;
        }
        .marquee::before,
        .marquee::after{
            content:""; position:absolute; top:0; bottom:0; width:var(--fade); z-index:2;
            pointer-events:none;
            mask-image: linear-gradient(to right, black, transparent);
            -webkit-mask-image: linear-gradient(to right, black, transparent);
            background: linear-gradient(90deg, var(--dk-bg), transparent);
        }
        .marquee::after{
            right:0; left:auto;
            transform: scaleX(-1);
        }
        .marquee__track{
            display:flex; gap: 40px; align-items:center;
            animation: scroll 28s linear infinite;
            will-change: transform;
        }
        .marquee img{
            height: 120px; width:auto; filter: drop-shadow(0 6px 12px rgba(0,0,0,.08));
            opacity:.98;
        }
        @keyframes scroll{
            from{ transform: translateX(0) }
            to{ transform: translateX(-50%) }
        }

        /* CITATION */
        .quote{
            padding: clamp(28px, 5vw, 48px) 0;
        }
        .quote blockquote{
            margin:0;
            text-align:center;
            font-size: clamp(18px, 2.2vw, 22px);
            color: var(--dk-ink);
            background: #fff;
            border-radius: var(--radius);
            padding: clamp(18px, 3vw, 28px);
            border-left: 6px solid var(--dk-gold);
            box-shadow: var(--shadow);
        }

        /* STATS */
        .stats{
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding: 8px 0 40px;
        }
        .stats__item{
            background:#fff; border-radius:var(--radius); padding:18px; text-align:center;
            border:1px solid #eee; box-shadow: var(--shadow);
        }
        .stats__number{
            font-size: clamp(26px, 3.2vw, 34px);
            font-weight:700; color: var(--dk-bordeaux);
            line-height:1;
        }
        .stats__label{
            color: var(--dk-ink-soft); margin-top:6px;
        }

        /* CTA RIBBON */
        .cta{
            text-align:center;
            background: linear-gradient(90deg, var(--dk-bordeaux), var(--dk-bordeaux-2));
            color:#fff; padding: 28px 16px;
            margin-top: 0px;
        }
        .cta p{ margin:0 0 10px; font-size: clamp(16px, 2vw, 18px) }
        .btn{
            display:inline-block;
            background:#fff; color: var(--dk-bordeaux);
            padding: 10px 18px; border-radius: 999px;
            text-decoration:none; font-weight:600; border:2px solid transparent;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s;
        }
        .btn:hover{ transform: translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.15); border-color:#fff }

        /* RESPONSIVE */
        @media (max-width: 900px){
            .story{ grid-template-columns: 1fr; }
            .values__grid, .stats{ grid-template-columns: 1fr; }
            .marquee img{ height: 96px }
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
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="apropos">
    <!-- HERO -->
    <section class="hero">
        <div class="hero__inner">
            <h1>Notre histoire</h1>
            <p>Des fleurs qui durent, des émotions qui restent.</p>
        </div>
    </section>

    <!-- HISTOIRE 2 COLONNES -->
    <section class="story container">
        <div class="story__text">
            <h2>Dans chaque pétale, un souvenir</h2>
            <p>
                Recevoir des fleurs fait toujours plaisir. Elles apportent de la joie, de la couleur et un instant d’émotion…
                C’est de ce constat qu’est née <strong>DK Bloom</strong> : l’envie de prolonger ces instants précieux en créant
                des fleurs qui durent pour l’éternité. Nos créations allient l’élégance de la nature et la durabilité, pour que
                chaque bouquet devienne un souvenir intemporel.
            </p>
        </div>
        <div class="story__image">
            <img src="<?= $BASE ?>img/ExempleNouvelAn.png" alt="Coffret DK Bloom Nouvel An">
        </div>
    </section>

    <!-- VALEURS -->
    <section class="values container">
        <h2>Nos valeurs</h2>
        <div class="values__grid">
            <article class="card">
                <h3>Élégance</h3>
                <p>Des lignes sobres, des finitions soignées, un raffinement assumé.</p>
            </article>
            <article class="card">
                <h3>Durabilité</h3>
                <p>Des fleurs qui traversent le temps, pour des souvenirs qui durent.</p>
            </article>
            <article class="card">
                <h3>Personnalisation</h3>
                <p>Chaque création est pensée pour raconter votre histoire.</p>
            </article>
        </div>
    </section>

    <!-- SLIDER / MARQUEE -->
    <section class="marquee">
        <div class="marquee__track">
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet rond de roses rouges">
            <img src="<?= $BASE ?>img/RosesNoir.png" alt="Bouquet rond de roses rouges">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret rond rouge">
            <img src="<?= $BASE ?>img/RosesPale.png" alt="Coffret rond rouge">
            <img src="<?= $BASE ?>img/BouquetNoir.png" alt="Bouquet noir">
            <img src="<?= $BASE ?>img/BouquetRosePale.png" alt="Bouquet rose pale">
            <img src="<?= $BASE ?>img/ExempleSaintValentin.png" alt="Coffret SV">
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet rond de roses rouges">
            <img src="<?= $BASE ?>img/RosesPale.png" alt="Coffret rond rouge">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret rond rouge">
            <img src="<?= $BASE ?>img/BouquetNoir.png" alt="Bouquet noir">
            <img src="<?= $BASE ?>img/BouquetRosePale.png" alt="Bouquet rose pale">
            <img src="<?= $BASE ?>img/ExempleSaintValentin.png" alt="Coffret SV">
            <img src="<?= $BASE ?>img/RosesNoir.png" alt="Bouquet rond de roses rouges">
            <img src="<?= $BASE ?>img/ExempleFeteMeres.png" alt="Coffret SV">
            <!-- Dupliqué pour boucle fluide -->
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="" aria-hidden="true">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="" aria-hidden="true">
        </div>
    </section>

    <!-- CITATION -->
    <section class="quote container">
        <blockquote>
            « Offrir des fleurs, c’est offrir un instant. Chez DK Bloom, nous le rendons <em>inoubliable</em>. »
        </blockquote>
    </section>

    <!-- STATS -->
    <section class="stats container">
        <div class="stats__item">
            <div class="stats__number">+500</div>
            <div class="stats__label">bouquets livrés</div>
        </div>
        <div class="stats__item">
            <div class="stats__number">98%</div>
            <div class="stats__label">clients satisfaits</div>
        </div>
        <div class="stats__item">
            <div class="stats__number">7j/7</div>
            <div class="stats__label">service client</div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <p>Envie d’offrir une émotion qui dure&nbsp;?</p>
        <a class="btn" href="<?= $BASE ?>interface_selection_produit.php">Voir le catalogue</a>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
