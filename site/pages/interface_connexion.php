<?php
// /site/pages/interface_connexion.php
session_start();

// Base URL
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Connexion</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <style>
        :root{
            --dk-bg-1:#5C0012;
            --dk-bg-2:#8A1B2E;
        }

        /* Page centrée, fond bordeaux lisse */
        body.logout{
            min-height:100vh; margin:0;
            display:flex; justify-content:center; align-items:center;
            background:linear-gradient(120deg, var(--dk-bg-1), var(--dk-bg-2));
            font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            color:#3d0020;
        }

        /* Carte split */
        .card{
            position:relative;
            width:min(940px,96vw);
            border-radius:24px;
            overflow:hidden;
            background:#fff;
            box-shadow:0 20px 60px rgba(0,0,0,.25);
        }
        .split{ display:grid; grid-template-columns:1.05fr 0.95fr; min-height:520px; }

        /* Colonne gauche = formulaire */
        .left{ padding:32px clamp(20px,4vw,40px); background:#fff; position:relative; z-index:2; }
        .left h2{ margin:0 0 6px; font-size:clamp(22px,2.6vw,28px); color:#5C0012; font-weight:900; }
        .left p.desc{ margin:0 0 18px; color:#7a2350; }

        form.form-login{ display:grid; gap:14px; }
        label{ font-size:14px; font-weight:600; color:#5C0012; }

        .input{
            display:flex; align-items:center; gap:10px;
            border:1px solid #e9d5dd; background:#fdf7f9; border-radius:12px; padding:10px 12px;
            transition:border .2s, box-shadow .2s, background .2s;
        }
        .input:focus-within{ border-color:#b46178; box-shadow:0 0 0 4px #b4617830; background:#fff; }
        .input input{ flex:1; border:0; outline:0; background:transparent; color:#3d0020; font-size:15px; }
        .pw-toggle{ position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:0; cursor:pointer; }

        .row-between{ display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .row-between a{ color:#8A1B2E; text-decoration:none; font-weight:600; }
        .row-between a:hover{ text-decoration:underline; }

        .actions{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:8px; }
        .btn-primary{
            padding:12px 22px; border-radius:999px; border:0; cursor:pointer;
            font-weight:800; color:#fff;
            background:linear-gradient(120deg,#8A1B2E,#5C0012);
            box-shadow:0 12px 26px #8A1B2E55;
        }
        .btn-primary:hover{ filter:brightness(1.05); }
        .link{ color:#8A1B2E; text-decoration:none; font-weight:600; }
        .link:hover{ text-decoration:underline; }

        /* Colonne droite = panneau bordeaux avec pétales (texture + teinte) */
        .right{
            position:relative;
            background:
                    radial-gradient(90% 140% at 110% 10%, #ef70ab 0%, transparent 60%),
                    radial-gradient(80% 120% at 10% 100%, #ae3664 0%, transparent 60%),
                    linear-gradient(135deg, #8A1B2E 0%, #5C0012 70%);
        }
        .right .welcome{
            position:absolute; inset:0; display:grid; place-items:center; padding:40px;
            color:#fff; text-align:center;
        }
        .right::after{
            content:""; position:absolute; inset:0;
            background:
                    radial-gradient(60% 60% at 70% 50%, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 60%),
                    linear-gradient(0deg, rgba(0,0,0,.14), rgba(0,0,0,0) 28%, rgba(0,0,0,.12) 100%);
            pointer-events:none;
        }
        .right .welcome h3{ font-size:clamp(22px,2.6vw,28px); margin:0 0 12px; }
        .right .welcome p{ max-width:32ch; line-height:1.5; margin:0 auto; }

        /* Lien visiteur sous la carte (hors carte => aucun “trait”) */
        .visitor{ margin-top:18px; text-align:center; }
        .visitor a{
            color:#fff; font-weight:700; text-decoration:none;
            background:rgba(255,255,255,.16); padding:10px 18px; border-radius:999px;
            transition:background .2s ease;
        }
        .visitor a:hover{ background:rgba(255,255,255,.26); }

        @media (max-width:800px){
            .split{ grid-template-columns:1fr; }
            .right{ min-height:300px; }
            .right .welcome{ padding:32px; }
        }
    </style>
</head>

<body class="logout">

<main>
    <article class="card">
        <div class="split">
            <!-- Formulaire -->
            <section class="left">
                <h2>Welcome back!</h2>
                <p class="desc">Connectez-vous pour retrouver vos bouquets préférés.</p>

                <form action="<?= $BASE ?>traitement_connexion.php" method="POST" class="form-login" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                    <label for="email">Adresse e-mail</label>
                    <div class="input">
                        <input type="email" id="email" name="email" required maxlength="50" autocomplete="email" autofocus placeholder="prenom.nom@email.com">
                    </div>

                    <label for="password">Mot de passe</label>
                    <div class="input" style="position:relative;">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                        <button type="button" class="pw-toggle" onclick="togglePassword('password', this)">👁</button>
                    </div>

                    <div class="row-between" style="margin-top:6px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:600; color:#5C0012;">
                            <input type="checkbox" name="remember" value="1"> Se souvenir de moi
                        </label>
                        <a href="<?= $BASE ?>interface_modification_mdp.php">Mot de passe oublié ?</a>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn-primary">Se connecter</button>
                        <a class="link" href="<?= $BASE ?>interface_inscription.php">Créer un compte</a>
                    </div>
                </form>
            </section>

            <!-- Panneau droit -->
            <aside class="right">
                <div class="welcome">
                    <div>
                        <h3>Bienvenue chez DK Bloom</h3>
                        <p>Des bouquets d’exception, une livraison soignée, et un service aux petits soins.</p>
                    </div>
                </div>
            </aside>
        </div>
    </article>

    <!-- Visiteur -->
    <div class="visitor">
        <a href="<?= $BASE ?>index.php">Continuer en tant que visiteur</a>
    </div>
</main>

<script>
    function togglePassword(id, btn){
        const el = document.getElementById(id);
        if(!el) return;
        if(el.type === 'password'){ el.type='text'; btn.textContent='🕶'; }
        else { el.type='password'; btn.textContent='👁'; }
    }
</script>

<!-- Expose BASE (toasts) -->
<script>window.DKBASE = <?= json_encode($BASE) ?>;</script>
<script src="<?= $BASE ?>js/commande.js"></script>
<?php if (!empty($_SESSION['toast'])):
    $t = $_SESSION['toast']; unset($_SESSION['toast']); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const msg  = <?= json_encode($t['text'], JSON_UNESCAPED_UNICODE) ?>;
            const type = <?= json_encode($t['type']) ?>;
            if (typeof window.toast === 'function') window.toast(msg, type);
            else if (typeof window.showToast === 'function') window.showToast(msg, type);
            else alert(msg);
        });
    </script>
<?php endif; ?>
</body>
</html>
