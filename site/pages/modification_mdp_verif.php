<?php
session_start();

/* ---------- Base URL (avec slash final) ---------- */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ---------- Secret serveur pour signer le token ---------- */
/* ⚠️ Mets une longue chaîne aléatoire; garde-la secrète (env/config). */
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
    // 6 chiffres stables à partir du HMAC
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

    // Réponse générique côté UI (on ne révèle pas si le compte existe)
    $message = "Si un compte existe, un e-mail avec un code a été envoyé.";

    if ($email !== '') {
        try {
            /** @var PDO $pdo */
            $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifie que l’e-mail existe (silencieusement)
            $st = $pdo->prepare("SELECT PER_ID, PER_PRENOM FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
            $st->execute([':em' => $email]);
            $u = $st->fetch(PDO::FETCH_ASSOC);

            if ($u) {
                // Crée un token stateless (email + expiration + nonce), signé implicitement via HMAC dans make_code
                $exp   = time() + 15*60; // 15 minutes
                $nonce = bin2hex(random_bytes(16));
                $payload = ['e' => $email, 'x' => $exp, 'n' => $nonce];
                $token   = b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

                // Code calculé à partir du payload (sans stockage)
                $dev_code = make_code($email, $exp, $nonce);

                // En DEV on affiche le code; en prod on le "enverrait" par mail
                if ($IS_DEV) {
                    $message = "DEV: votre code est {$dev_code} (15 min).";
                } else {
                    // Pas d’e-mail pour l’instant; on loggue juste côté serveur
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
    <title>DK Bloom — Code de réinitialisation</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container">
    <div class="conteneur_form">
        <h2>Mot de passe oublié</h2>

        <form method="POST" action="">
            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Recevoir un code</button>
        </form>

        <?php if (!empty($message)): ?>
            <p class="info" style="margin-top:10px"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if (!empty($token)): ?>
            <form method="GET" action="<?= $BASE ?>modification_mdp_verif.php" style="margin-top:10px">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <button type="submit">Aller saisir le code</button>
            </form>
        <?php endif; ?>

        <p style="margin-top:12px">
            J’ai déjà un code → <a href="<?= $BASE ?>modification_mdp_verif.php">Saisir le code et changer le mot de passe</a>
        </p>

        <?php if (!$IS_DEV): ?>
            <p style="margin-top:8px;color:#aaa;font-size:0.9em">
                (Astuce: ajoute <code>?dev=1</code> à l’URL pour afficher le code en environnement de test.)
            </p>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
