<?php
// /site/pages/interface_inscription.php
session_start();

// Base URL
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Inscription</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <style>
        :root{
            --dk-bg-1:#5C0012;
            --dk-bg-2:#8A1B2E;
        }

        body.logout{
            min-height:100vh;
            margin:0;
            display:flex;
            justify-content:center;
            align-items:center; /* ajoute ça */
            background:linear-gradient(120deg, var(--dk-bg-1), var(--dk-bg-2));
        }


        .card{
            position:relative;
            width:min(940px,96vw);
            border-radius:24px;
            overflow:hidden;
            background:#fff;
            box-shadow:0 20px 60px rgba(0,0,0,.25);
        }

        .split{
            display:grid;
            grid-template-columns: 1.05fr 0.95fr;
            min-height: 520px;
        }

        /* gauche */
        .left{
            padding:32px clamp(20px,4vw,40px);
            background:#fff;
            position:relative;
            z-index:2;
        }
        .left h2{ margin:0 0 6px; font-size:clamp(22px,2.6vw,28px); color:#5C0012; font-weight:900; }
        .left p.desc{ margin:0 0 18px; color:#7a2350; }

        .form-inscription{ display:grid; gap:14px; }
        .input{ display:flex; align-items:center; gap:10px; border:1px solid #e9d5dd;
            background:#fdf7f9; border-radius:12px; padding:10px 12px; }
        .input:focus-within{ border-color:#b46178; box-shadow:0 0 0 4px #b4617830; background:#fff; }
        .input input{ flex:1; border:0; outline:0; background:transparent; color:#3d0020; font-size:15px; }

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

        /* droite */
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
        .right .welcome h3{ font-size:clamp(22px,2.6vw,28px); margin:0 0 10px; }
        .right .welcome p{ max-width:40ch; opacity:.95; margin:0 auto; }

        /* séparateur pétales */


        /* visiteur */
        .visitor{ margin-top:18px; text-align:center; }
        .visitor a{
            color:#fff; font-weight:700; text-decoration:none;
            background:rgba(255,255,255,.16); padding:10px 18px; border-radius:999px;
            transition:background .2s ease;
        }
        .visitor a:hover{ background:rgba(255,255,255,.26); }

        .password-hint {
            font-size: 12px;             /* plus petit */
            color: #8A1B2E;              /* rouge bordeaux DK Bloom */
            margin-top: 4px;             /* petit espace sous le champ */
            opacity: 0.9;                /* léger adoucissement */
        }

    </style>
</head>

<body class="logout">

<main>
    <article class="card">
        <div class="split">
            <!-- gauche -->
            <section class="left">
                <h2>Hello!</h2>
                <p class="desc">Créez votre compte — élégance & livraison soignée.</p>

                <form action="<?= $BASE ?>traitement_inscription.php" method="POST" class="form-inscription" novalidate>
                    <label for="lastname">Nom</label>
                    <div class="input"><input type="text" id="lastname" name="lastname" required placeholder="Dupont" autocomplete="family-name"></div>

                    <label for="firstname">Prénom</label>
                    <div class="input"><input type="text" id="firstname" name="firstname" required placeholder="Alice" autocomplete="given-name"></div>

                    <label for="phone">Téléphone</label>
                    <div class="input"><input type="tel" id="phone" name="phone" required placeholder="079 123 45 67"></div>

                    <label for="email">Adresse e-mail</label>
                    <div class="input"><input type="email" id="email" name="email" required placeholder="prenom.nom@email.com"></div>

                    <label for="password">Mot de passe</label>
                    <div class="input" style="position:relative;">
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="toggle-password"
                                onclick="togglePassword('password', this)"
                                style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:0; cursor:pointer;">👁</button>
                    </div>
                    <p class="password-hint">Le mot de passe doit comporter une majuscule, une minuscule, un numéro et un caractère spécial.</p>

                    <div class="actions">
                        <button type="submit" class="btn-primary">S’inscrire</button>
                        <a class="link" href="<?= $BASE ?>interface_connexion.php">Déjà inscrit ?</a>
                    </div>
                </form>
            </section>

            <!-- droite -->
            <aside class="right">
                <div class="welcome">
                    <div>
                        <h3>Bienvenue !</h3>
                        <p>Découvrez DK Bloom et ses bouquets d’exception, conçus avec soin.</p>
                    </div>
                </div>
                <div class="petal-edge" aria-hidden="true"></div>
            </aside>
        </div>
    </article>

    <!-- Visiteur -->
    <div class="visitor">
        <a href="<?= $BASE ?>index.php">Continuer en tant que visiteur</a>
    </div>
</main>

<script>
    function togglePassword(fieldId, btn) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        if (field.type === "password") { field.type = "text"; btn.textContent = "🕶"; }
        else { field.type = "password"; btn.textContent = "👁"; }
    }
</script>
</body>
</html>
