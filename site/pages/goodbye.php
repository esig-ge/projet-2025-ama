<?php
// /site/pages/goodbye.php
session_start();

// Base URL (même logique que tes autres pages)
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// --- Logout propre ---
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Démarre une session vide (utile si ton header a besoin de $_SESSION)
session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Suppression de compte</title>
    <style>
        :root{
            /* Palette bordeaux validée */
            --dk-bg-1:#5C0012;   /* primaire, très bordeaux */
            --dk-bg-2:#8A1B2E;   /* secondaire, bordeaux rosé pour le dégradé */
            --card-bg:rgba(255,255,255,.10);
            --card-bd:rgba(255,255,255,.28);
            --glass-blur:16px;
            --rose-light:#ffd7de; /* rose doux (reflets/pétales) */
            --txt:#ffffff;
        }

        body.logout{
            min-height:100vh; margin:0; color:var(--txt); display:flex; flex-direction:column;
            background:
                    radial-gradient(1200px 600px at 10% -10%, #ffb3c9 0%, transparent 60%),
                    radial-gradient(900px 500px at 110% 110%, #ffcfe0 0%, transparent 60%),
                    linear-gradient(120deg, var(--dk-bg-1), var(--dk-bg-2));
            overflow-x:hidden;
        }

        .logout-wrap{
            flex:1;
            display:grid;
            place-items:center;
            padding: clamp(24px, 6vw, 72px);
            position:relative;
            overflow:hidden;
            isolation:isolate; /* pour bien empiler pétales */
        }

        /* Pétales décoratives qui “tombent” */
        .petal{
            position:absolute;
            width: 140px; height: 95px;
            background:
                    radial-gradient(120% 100% at 50% 40%, rgba(255,255,255,.28), transparent 60%),
                    radial-gradient(closest-side, rgba(255,221,235,.75), rgba(255,221,235,.15));
            border-radius: 60% 60% 65% 65% / 55% 55% 45% 45%;
            opacity:.55;
            filter: drop-shadow(0 6px 18px rgba(0,0,0,.18));
            transform-origin: 50% 20%;
            animation: petalFall 16s linear infinite;
        }
        .petal.p1{ top:-10vh; left:10vw; transform: rotate(12deg); animation-delay: .0s; }
        .petal.p2{ top:-14vh; left:26vw; transform: rotate(-8deg); animation-delay: .8s;  width:120px; height:82px; opacity:.50; }
        .petal.p3{ top:-12vh; left:48vw; transform: rotate(18deg); animation-delay: 1.6s; width:150px; height:102px; }
        .petal.p4{ top:-16vh; left:68vw; transform: rotate(-16deg); animation-delay: 2.4s; width:110px; height:78px;  opacity:.45; }
        .petal.p5{ top:-18vh; left:84vw; transform: rotate(10deg); animation-delay: 3.2s;  width:130px; height:90px;  opacity:.50; }

        @keyframes petalFall{
            0%   { transform: translateY(-10vh) rotate(0deg);   }
            50%  { transform: translateY(45vh)  rotate(35deg);  }
            100% { transform: translateY(100vh) rotate(90deg);  }
        }

        /* bulles en fond (subtiles) */
        .bubble{
            position:absolute; border-radius:50%; opacity:.22; filter: blur(2px);
            background: radial-gradient(closest-side, #fff, rgba(255,255,255,.12));
            animation: float 12s ease-in-out infinite;
            z-index:0;
        }
        .b1{ width:260px; height:260px; top:6%; left:6%; animation-delay:0s; }
        .b2{ width:180px; height:180px; bottom:10%; right:14%; animation-delay:1.2s; }
        .b3{ width:130px; height:130px; top:18%; right:26%; animation-delay:.6s; }
        @keyframes float{
            0%,100%{ transform: translateY(0) }
            50%{ transform: translateY(-12px) }
        }

        .logout-card{
            z-index:1;
            width:min(600px, 92vw);
            background: var(--card-bg);
            border:1px solid var(--card-bd);
            border-radius:22px;
            backdrop-filter: blur(var(--glass-blur));
            -webkit-backdrop-filter: blur(var(--glass-blur));
            box-shadow: 0 18px 50px rgba(0,0,0,.25);
            padding: clamp(24px, 3.8vw, 38px);
            text-align:center;
            color:var(--txt);
        }

        .rose-icon{ width:64px; height:64px; margin: 4px auto 12px; display:block; color: var(--rose-light); }

        .logout-card h1{
            margin:.2rem 0 .6rem; font-size: clamp(22px, 2.6vw, 30px);
            font-weight:800; letter-spacing:.2px;
        }
        .logout-card p{
            margin:0 auto 18px; max-width:42ch; line-height:1.6; opacity:.95;
            font-size: clamp(14px, 1.4vw, 16px);
        }

        .btn-home{
            display:inline-flex; align-items:center; gap:8px;
            border:none; cursor:pointer; text-decoration:none;
            padding:12px 20px; border-radius:999px; font-weight:700;
            background:#fff; color:var(--dk-bg-1);
            box-shadow: 0 10px 24px rgba(255,255,255,.18);
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease;
        }
        .btn-home:hover{ transform: translateY(-1px); filter: brightness(1.03); }
        .btn-home:active{ transform: translateY(0); box-shadow: 0 6px 18px rgba(255,255,255,.15); }

        @media (prefers-reduced-motion: no-preference){
            .logout-card[data-autoback="true"]::after{
                content:"Retour automatique dans quelques secondes…";
                display:block; margin-top:10px; font-size:12px; opacity:.85;
            }
        }

    </style>
</head>
<body class="logout">

<main class="logout-wrap" role="main">
    <!-- pétales -->
    <span class="petal p1" aria-hidden="true"></span>
    <span class="petal p2" aria-hidden="true"></span>
    <span class="petal p3" aria-hidden="true"></span>
    <span class="petal p4" aria-hidden="true"></span>
    <span class="petal p5" aria-hidden="true"></span>

    <!-- bulles -->
    <span class="bubble b1" aria-hidden="true"></span>
    <span class="bubble b2" aria-hidden="true"></span>
    <span class="bubble b3" aria-hidden="true"></span>

    <section class="logout-card" role="status" aria-live="polite" data-autoback="false">
        <!-- Icône “rose” en SVG inline -->
        <svg class="rose-icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <path d="M32 38c7.18 0 13-5.82 13-13 0-7.18-5.82-13-13-13-3.47 0-7.1 1.45-9.53 3.88C20.22 18 18 21.5 18 25c0 7.18 6.82 13 14 13Z" stroke="currentColor" stroke-width="2" />
            <path d="M25 22c2-3 7-4 10-1m-7 9c3 2 8 1 10-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M32 38c0 8-4 16-12 20m12-20c0 8 4 16 12 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M20 52c4-1 8-3 12-6 4 3 8 5 12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>

        <h1>Suppression de compte réussie</h1>
        <p>Nous sommes désolées de vous voir partir 😔</p>

        <a class="btn-home" href="<?= $BASE ?>index.php" aria-label="Retour à l'accueil">
            Aller à l'accueil
            <span aria-hidden="true">↗</span>
        </a>
    </section>
</main>
<script>
    setTimeout(function(){ window.location.href = "<?= $BASE ?>index.php"; }, 7000);
</script>
</body>
</html>
