<?php
// /site/pages/traitement_oubli_mdp.php
session_start();

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $BASE . 'interface_oubli_mdp.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Adresse e-mail invalide.";
    header('Location: ' . $BASE . 'interface_oubli_mdp.php');
    exit;
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupère aussi le prénom
    $st = $pdo->prepare("SELECT PER_ID, PER_PRENOM FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
    $st->execute([':em' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // Message générique (ne divulgue rien)
    $_SESSION['toast_type'] = 'info';
    $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé.";

    if (!$user) {
        header('Location: ' . $BASE . 'interface_oubli_mdp.php');
        exit;
    }

    // 1) Code à 6 chiffres
    $code = random_int(100000, 999999);

    // 2) Stockage en clair + expiration 15 min
    $upd = $pdo->prepare("
        UPDATE PERSONNE
           SET reset_token_hash = :code,
               reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE PER_EMAIL = :em
         LIMIT 1
    ");
    $upd->execute([':code' => $code, ':em' => $email]);

    //Lien
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $link   = $scheme . $_SERVER['HTTP_HOST'] . $BASE . "reinitialisation_mdp.php?email=" . urlencode($email);

    // 4) Toast + note dev (utile si mail() ne marche pas)
    $_SESSION['dev_code']   = "Code : $code (valide 15 min)\nLien : $link";
    $_SESSION['toast_type'] = 'success';
    $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé (ou le code est affiché pour tester).";

    // 5) Email avec emojis et prénom (Chère + prénom), en UTF-8 propre
    $prenom = trim((string)($user['PER_PRENOM'] ?? ''));
    if ($prenom !== '') {
        $prenom = function_exists('mb_convert_case')
            ? mb_convert_case($prenom, MB_CASE_TITLE, 'UTF-8')
            : ucfirst(strtolower($prenom));
    }

    $salut   = "Coucou " . ($prenom !== '' ? $prenom : "cliente");
    $subject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader("DK Bloom — Code de réinitialisation", 'UTF-8', 'B', "\r\n")
        : "DK Bloom — Code de réinitialisation";

    // >>> Contenu tel que tu le veux (emojis conservés) <<<
    $message  = $salut . ",\n\n";
    $message .= "Voici ton code de réinitialisation 💖 : $code\n";
    $message .= "Il est valable 15 minutes.\n";
    $message .= "Merci de la confiance que tu nous accordes !.\n\n";
    $message .= "L’équipe DK Bloom 🌹";

    // En-têtes propres (utilise une adresse de TON domaine)
    $headers  = "From: DK Bloom <no-reply@dk-bloom.ch>\r\n";
    $headers .= "Reply-To: contact@dk-bloom.ch\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    @mail($email, $subject, $message, $headers);

    // 6) Redirection vers la page de réinitialisation
    header('Location: ' . $BASE . 'reinitialisation_mdp.php?email=' . urlencode($email));
    exit;

} catch (Throwable $e) {
    error_log('[FORGOT_PWD] ' . $e->getMessage());
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Erreur interne, réessayez.";
    header('Location: ' . $BASE . 'interface_oubli_mdp.php');
    exit;
}
