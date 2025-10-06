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

    // Vérifie l'existence du compte (on ne révèle pas le résultat à l'utilisateur)
    $st = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
    $st->execute([':em' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // Message générique
    $_SESSION['toast_type'] = 'info';
    $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé.";

    if (!$user) {
        // Retourner à la page de demande (message générique déjà préparé)
        header('Location: ' . $BASE . 'interface_oubli_mdp.php');
        exit;
    }

    // Générer un code à 6 chiffres
    $code = random_int(100000, 999999);

    // Stocker le code en clair (par simplicité) et l'expiration (15 minutes)
    $upd = $pdo->prepare("UPDATE PERSONNE SET reset_token_hash = :code, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE PER_EMAIL = :em LIMIT 1");
    $upd->execute([':code' => $code, ':em' => $email]);

    // Préparer le lien de réinitialisation (optionnel)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $link   = $scheme . $_SERVER['HTTP_HOST'] . $BASE . "reinitialisation_mdp.php?email=" . urlencode($email);

    // Afficher le code/link en session pour pouvoir tester si mail() ne passe pas
    $_SESSION['dev_code']   = "Code : $code (valide 15 min)\nLien : $link";
    $_SESSION['toast_type'] = 'success';
    $_SESSION['toast_msg']  = "Si un compte existe, un e-mail a été envoyé (ou le code est affiché pour tester).";

    // Tenter d'envoyer le mail (si l'envoi échoue, l'utilisateur peut utiliser le code affiché)
    @mail(
        $email,
        "DK Bloom — Code de réinitialisation",
        "Bonjour,\n\nVotre code de réinitialisation est : $code\nValide 15 minutes.\n\nSi vous ne recevez pas l'email, utilisez le formulaire de réinitialisation et collez ce code.",
        "From: no-reply@tondomaine.ch\r\nContent-Type: text/plain; charset=UTF-8"
    );

    // Rediriger vers la page de réinitialisation (email en query pour pré-remplir)
    header('Location: ' . $BASE . 'reinitialisation_mdp.php?email=' . urlencode($email));
    exit;

} catch (Throwable $e) {
    error_log('[FORGOT_PWD] ' . $e->getMessage());
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Erreur interne, réessayez.";
    header('Location: ' . $BASE . 'interface_oubli_mdp.php');
    exit;
}
