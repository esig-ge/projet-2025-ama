<?php
// /site/pages/otp_forgot.php
session_start();

/* ---------- Base URL (avec slash final) ---------- */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ---------- Secret serveur pour signer le token ---------- */
const RESET_SECRET = 'CHANGE-MOI-EN-LONGUE-CHAINE-TRES-SECRETE-ET-ALEATOIRE';

/* ---------- Helpers ---------- */
function b64url_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_decode(string $s): string {
    $p = strlen($s) % 4; if ($p) { $s .= str_repeat('=', 4 - $p); }
    return base64_decode(strtr($s, '-_', '+/')) ?: '';
}
function make_code(string $email, int $exp, string $nonce): string {
    $msg  = $email . '|' . $exp . '|' . $nonce;
    $hmac = hash_hmac('sha256', $msg, RESET_SECRET, true);
    $int  = unpack('N', substr($hmac, 0, 4))[1];
    $code = str_pad((string) ($int % 1000000), 6, '0', STR_PAD_LEFT);
    return $code;
}

$IS_DEV = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || isset($_GET['dev']);
$message = '';
$dev_code = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = "Si un compte existe, un e-mail avec un code a été envoyé.";

    if ($email !== '') {
        try {
            /** @var PDO $pdo */
            $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $st = $pdo->prepare("SELECT PER_ID, PER_PRENOM FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
            $st->execute([':em' => $email]);
            $u = $st->fetch(PDO::FETCH_ASSOC);

            if ($u) {
                $exp   = time() + 15*60; // 15 minutes
                $nonce = bin2hex(random_bytes(16));
                $payload = ['e' => $email, 'x' => $exp, 'n' => $nonce];
                $token   = b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
                $dev_code = make_code($email, $exp, $nonce);

                if ($IS_DEV) {
                    $message = "DEV: votre code est {$dev_code} (15 min).";
                } else {
                    error_log("[OTP-STATELESS] Code {$dev_code} pour {$email}");
                }
            }
        } catch (Throwable $e) {
            error_log('[OTP_FORGOT_STATELESS] '.$e->getMessage());
            $message = "Une erreur est survenue. Réessayez.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Mot de passe oublié</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <style>
        :root{ --dk-bg-1:#5C0012; --dk-bg-2:#8A1B2E; }

        body.logout{
            min-height:100vh; margin:0;
            display:flex; justify-content:center; align-items:center;
            background:linear-gradient(120deg, var(--dk-bg-1), var(--dk-bg-2));
            font-family:system-ui, sans-serif;
        }

        .card{
            position:relative;
            width:min(940px,96vw);
            border-radius:24px;
            overflow:hidden;
            background:#fff;
            box-shadow:0 20px 60px rgba(0,0,0,.25);
        }
        .split{ display:grid; grid-template-columns:1.05fr 0.95fr; min-height:500px; }

        .left{ padding:32px clamp(20px,4vw,40px); background:#fff; }
        .left h2{ margin:0 0 6px; font-size:clamp(22px,2.6vw,28px); color:#5C0012; font-weight:900; }
        .left p.desc{ margin:0 0 18px; color:#7a2350; }

        form.form-forgot{ display:grid; gap:14px; }
        label{ font-size:14px; font-weight:600; color:#5C0012; }

        .input{
            display:flex; align-items:center; gap:10px;
            border:1px solid #e9d5dd; background:#fdf7f9;
            border-radius:12px; padding:10px 12px;
        }
        .input input{ flex:1; border:0; outline:0; background:transparent; font-size:15px; color:#3d0020; }

        .btn-primary{
            padding:12px 22px; border-radius:999px; border:0; cursor:pointer;
            font-weight:800; color:#fff;
            background:linear-gradient(120deg,#8A1B2E,#5C0012);
            box-shadow:0 12px 26px #8A1B2E55;
        }
        .btn-primary:hover{ filter:brightness(1.05); }

        .info{ color:#5C0012; font-size:.95rem; }

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
            background:linear-gradient(0deg, rgba(0,0,0,.18), rgba(0,0,0,0) 40%, rgba(0,0,0,.18) 100%);
        }

        .right .welcome h3{ font-size:clamp(22px,2.6vw,28px); margin:0 0 12px; }
        .right .welcome p{ max-width:32ch; line-height:1.5; margin:0 auto; }

        @media(max-width:800px){
            .split{ grid-template-columns:1fr; }
            .right{ min-height:240px; }
        }
    </style>
</head>
<body class="logout">

<main>
    <article class="card">
        <div class="split">
            <section class="left">
                <h2>Mot de passe oublié</h2>
                <p class="desc">Entrez votre adresse e-mail pour recevoir un code de réinitialisation.</p>

                <form method="POST" action="" class="form-forgot">
                    <label for="email">Adresse e-mail</label>
                    <div class="input">
                        <input type="email" id="email" name="email" required placeholder="prenom.nom@email.com">
                    </div>
                    <button type="submit" class="btn-primary">Recevoir un code</button>
                </form>

                <?php if (!empty($message)): ?>
                    <p class="info" style="margin-top:10px"><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>

                <?php if (!empty($token)): ?>
                    <form method="GET" action="<?= $BASE ?>modification_mdp_verif.php" style="margin-top:10px">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <button type="submit" class="btn-primary">Aller saisir le code</button>
                    </form>
                <?php endif; ?>

                <p style="margin-top:12px">
                    J’ai déjà un code → <a href="<?= $BASE ?>modification_mdp_verif.php">Saisir le code</a>
                </p>

                <?php if (!$IS_DEV): ?>
                    <p style="margin-top:8px;color:#aaa;font-size:0.9em">
                        (Astuce : ajoute <code>?dev=1</code> à l’URL en test pour afficher le code.)
                    </p>
                <?php endif; ?>
            </section>

            <aside class="right">
                <div class="welcome">
                    <div>
                        <h3>Réinitialisez votre accès</h3>
                        <p>Saisissez votre e-mail et suivez les étapes pour retrouver vos identifiants DK Bloom.</p>
                    </div>
                </div>
            </aside>
        </div>
    </article>
</main>

</body>
</html>
