<?php
// /site/pages/traitement_oubli_mdp.php
session_start();

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Secret partagé (si tu gardes l'OTP stateless ailleurs) ===== */
const RESET_SECRET = 'CHANGE-MOI-EN-LONGUE-CHAINE-TRES-SECRETE-ET-ALEATOIRE';

/* ===== Helpers ===== */
function b64url_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function make_code(string $email, int $exp, string $nonce): string {
    $msg  = $email . '|' . $exp . '|' . $nonce;
    $hmac = hash_hmac('sha256', $msg, RESET_SECRET, true);
    $int  = unpack('N', substr($hmac, 0, 4))[1];
    return str_pad((string)($int % 1000000), 6, '0', STR_PAD_LEFT);
}

$IS_DEV = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['toast_type'] = 'info';
    $_SESSION['toast_msg']  = "Utilisez le formulaire pour demander un code.";
    header('Location: ' . $BASE . 'interface_oubli_mdp.php'); exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Adresse e-mail invalide.";
    header('Location: ' . $BASE . 'interface_oubli_mdp.php'); exit;
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // On vérifie l'existence, mais on ne divulgue rien
    $st = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
    $st->execute([':em' => $email]);
    $user = $st->fetch();

    // Message générique pour tous les cas
    $_SESSION['toast_type'] = 'info';
    $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé.";

    // ...
    if ($user) {
        // 1) Générer token + code
        $exp     = time() + 15 * 60;
        $nonce   = bin2hex(random_bytes(16));
        $payload = ['e' => $email, 'x' => $exp, 'n' => $nonce];
        $token   = b64url_encode(json_encode($payload));
        $code    = make_code($email, $exp, $nonce);

        // 2) Lien
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $lien   = $scheme . $_SERVER['HTTP_HOST'] . $BASE . "reinitialisation_mdp.php?token=" . urlencode($token);

        // 3) Toujours poser une note pour tester même si le mail n'arrive pas
        $_SESSION['dev_code']   = "Votre code : $code (valide 15 min)\nLien : $lien";
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé.";

        // 4) En prod, tentative d'email (facultatif tant que SMTP pas prêt)
        if (!$IS_DEV) {
            @mail(
                $email,
                "Réinitialisation DK Bloom",
                "Bonjour,\n\nCode : $code\nLien : $lien\nValide 15 min.\n\nDK Bloom",
                "From: no-reply@tondomaine.ch\r\nContent-Type: text/plain; charset=UTF-8"
            );
        }

        // 5) ➜ Aller sur la page de réinit AVEC token
        header('Location: ' . $BASE . 'reinitialisation_mdp.php?token=' . urlencode($token));
        exit;
    }

// ➜ Email inconnu : on revient sur la page d'oubli (toast info déjà posé au-dessus)
    header('Location: ' . $BASE . 'interface_oubli_mdp.php');
    exit;

} catch (Throwable $e) {
    error_log('[FORGOT_PWD] ' . $e->getMessage());
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Erreur interne.";
}

// Si e-mail inconnu ou erreur → on va quand même sur la page, sans token
header('Location: ' . $BASE . 'reinitialisation_mdp.php'); exit;
