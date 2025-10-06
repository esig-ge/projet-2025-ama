<?php
// /site/pages/reinitialisation_mdp.php
session_start();

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Secret partagé (si tu utilises encore l'OTP stateless) ===== */
const RESET_SECRET = 'CHANGE-MOI-EN-LONGUE-CHAINE-TRES-SECRETE-ET-ALEATOIRE';

/* ===== Helpers ===== */
function b64url_decode(string $s): string {
    $p = strlen($s) % 4; if ($p) { $s .= str_repeat('=', 4 - $p); }
    return base64_decode(strtr($s, '-_', '+/')) ?: '';
}
function make_code(string $email, int $exp, string $nonce): string {
    $msg  = $email.'|'.$exp.'|'.$nonce;
    $hmac = hash_hmac('sha256', $msg, RESET_SECRET, true);
    $int  = unpack('N', substr($hmac, 0, 4))[1];
    return str_pad((string)($int % 1000000), 6, '0', STR_PAD_LEFT);
}
function mask_email(string $e): string {
    if (!str_contains($e, '@')) return $e;
    [$a, $b] = explode('@', $e, 2);
    return substr($a, 0, 2) . str_repeat('•', max(0, strlen($a) - 2)) . '@' . $b;
}

/* ===== Toast (venant d'une redirection) ===== */
$toastType = $_SESSION['toast_type'] ?? null;
$toastMsg  = $_SESSION['toast_msg']  ?? null;
unset($_SESSION['toast_type'], $_SESSION['toast_msg']);

/* ===== État ===== */
$token   = $_GET['token'] ?? ($_POST['token'] ?? '');
$payload = null;
$error   = '';
$success = '';
$devNote = $_SESSION['dev_code'] ?? null; unset($_SESSION['dev_code']);

/* ===== Contrôle du token ===== */
if (!$token) {
    $error = "Lien invalide.";
} else {
    $payload = json_decode(b64url_decode($token), true);
    if (!is_array($payload) || !isset($payload['e'], $payload['x'], $payload['n'])) {
        $error = "Lien invalide.";
    } elseif (time() > (int)$payload['x']) {
        $error = "Lien expiré. Veuillez recommencer la procédure.";
    }
}

/* ===== Soumission du formulaire (pas de redirection ici) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $payload) {
    $code = trim($_POST['code'] ?? '');
    $p1   = $_POST['new_password']  ?? '';
    $p2   = $_POST['new_password2'] ?? '';

    if (!preg_match('/^\d{6}$/', $code)) {
        $error = "Code à 6 chiffres requis.";
    } elseif (strlen($p1) < 8) {
        $error = "Mot de passe trop court (min. 8).";
    } elseif ($p1 !== $p2) {
        $error = "Les mots de passe diffèrent.";
    } else {
        $expected = make_code($payload['e'], (int)$payload['x'], $payload['n']);
        if (!hash_equals($expected, $code)) {
            $error = "Code invalide.";
        } else {
            try {
                /** @var PDO $pdo */
                $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $hash = password_hash($p1, PASSWORD_DEFAULT);
                $st = $pdo->prepare("UPDATE PERSONNE SET PER_MDP = :p WHERE PER_EMAIL = :e LIMIT 1");
                $st->execute([':p' => $hash, ':e' => $payload['e']]);

                $success   = "Mot de passe changé avec succès.";
                $toastType = 'success';
                $toastMsg  = $success;

                // On “consomme” le token côté UI
                $token = '';
                $payload = null;
            } catch (Throwable $e) {
                error_log('[RESET_PWD_UPDATE] ' . $e->getMessage());
                $error     = "Erreur interne.";
                $toastType = 'error';
                $toastMsg  = $error;
            }
        }
    }

    // Si erreur lors du POST → prépare un toast erreur à l’affichage
    if ($error && !$toastMsg) {
        $toastType = 'error';
        $toastMsg  = $error;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DK Bloom — Réinitialisation</title>
    <!-- Garde bien tes 3 CSS pour conserver le visuel -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_reset_mdp.css">
</head>
<body class="reset-page">
<main class="reset-card">
    <h1>Réinitialisation du mot de passe</h1>

    <?php if ($devNote): ?>
        <p class="note"><?= nl2br(htmlspecialchars($devNote)) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="note err"><?= htmlspecialchars($error) ?></p>
        <p class="reset-links">Besoin d’un nouveau code ? <a href="<?= $BASE ?>interface_oubli_mdp.php">Recommencer</a></p>
    <?php elseif ($success): ?>
        <p class="note ok"><?= htmlspecialchars($success) ?></p>
        <p><a href="<?= $BASE ?>interface_connexion.php" class="btn-primary" style="text-decoration:none;">Me connecter</a></p>
    <?php else: ?>
        <!-- Formulaire (token valide) -->
        <p>Compte : <?= htmlspecialchars(mask_email($payload['e'])) ?></p>
        <form method="post" class="reset-form" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label class="reset-label" for="code">Code à 6 chiffres</label>
            <div class="reset-input">
                <input id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
                       placeholder="123456" required>
            </div>

            <label class="reset-label" for="new_password">Nouveau mot de passe</label>
            <div class="reset-input">
                <input type="password" id="new_password" name="new_password" minlength="8"
                       autocomplete="new-password" required>
            </div>

            <label class="reset-label" for="new_password2">Confirmer le mot de passe</label>
            <div class="reset-input">
                <input type="password" id="new_password2" name="new_password2" minlength="8"
                       autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn-primary">Changer le mot de passe</button>
        </form>
        <p class="reset-links">Besoin d’un nouveau code ? <a href="<?= $BASE ?>interface_oubli_mdp.php">Recommencer</a></p>
    <?php endif; ?>
</main>

<?php if ($toastMsg): ?>
    <div class="toast <?= htmlspecialchars($toastType ?: 'info') ?>" id="toast">
        <div><?= htmlspecialchars($toastMsg) ?></div>
        <button class="toast-close" aria-label="Fermer">&times;</button>
    </div>
<?php endif; ?>

<script>
    (function(){
        const t = document.getElementById('toast');
        if(!t) return;
        const btn = t.querySelector('.toast-close');
        setTimeout(()=>t.classList.add('show'), 120);
        setTimeout(()=>t.classList.remove('show'), 4600);
        if(btn) btn.addEventListener('click', () => t.classList.remove('show'));
    })();
</script>
</body>
</html>
