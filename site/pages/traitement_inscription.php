<?php
// /site/pages/traitement_inscription.php
session_start();

// 0) CSRF
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    $_SESSION['message'] = "Session expirée, merci de réessayer.";
    header('Location: inscription.php'); exit;
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
} catch (Throwable $e) {
    error_log('DB error: '.$e->getMessage());
    $_SESSION['message'] = "Erreur serveur (base de données).";
    header('Location: inscription.php'); exit;
}

// 1) Inputs
$nom       = trim($_POST['lastname']  ?? '');
$prenom    = trim($_POST['firstname'] ?? '');
$email     = trim($_POST['email']     ?? '');
$tel_input = trim($_POST['phone']     ?? '');
$password_plain = $_POST['password']            ?? '';

if ($nom === '' || $prenom === '' || $email === '' || $password_plain === '' || $tel_input === '') {
    $_SESSION['message'] = "Tous les champs sont obligatoires.";
    header('Location: inscription.php'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Adresse e-mail invalide.";
    header('Location: inscription.php'); exit;
}

// 1.b) Téléphone → 0XXXXXXXXX (10 chiffres)
$tel_digits = preg_replace('/\D+/', '', $tel_input);
if (preg_match('/^41(\d{9})$/', $tel_digits, $m)) {
    $tel_digits = '0'.$m[1]; // +41xxxxxxxxx → 0xxxxxxxxx
}
if (!preg_match('/^0\d{9}$/', $tel_digits)) {
    $_SESSION['message'] = "Téléphone invalide. Format attendu : 0XXXXXXXXX.";
    header('Location: inscription.php'); exit;
}

// 1.c) Hash MDP
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);
if ($password_hash === false) {
    $_SESSION['message'] = "Impossible de sécuriser le mot de passe.";
    header('Location: inscription.php'); exit;
}

try {
    // 2) Doublons “amicaux”
    $stmt = $pdo->prepare('SELECT PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_EMAIL = :email OR PER_NUM_TEL = :tel LIMIT 1');
    $stmt->execute([':email' => $email, ':tel' => $tel_digits]);
    if ($row = $stmt->fetch()) {
        if (strcasecmp($row['PER_EMAIL'], $email) === 0) {
            $_SESSION['message'] = "Un compte existe déjà avec cet e-mail.";
        } else {
            $_SESSION['message'] = "Un compte existe déjà avec ce numéro de téléphone.";
        }
        header('Location: inscription.php'); exit;
    }

    // 3) Transaction PERSONNE + CLIENT
    $pdo->beginTransaction();

    $sql1 = "INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
             VALUES (:nom, :prenom, :email, :password, :tel)";
    $pdo->prepare($sql1)->execute([
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':email'    => $email,
        ':password' => $password_hash,
        ':tel'      => $tel_digits,
    ]);

    $perId = (int)$pdo->lastInsertId();
    if ($perId <= 0) { throw new RuntimeException('Insertion PERSONNE échouée'); }

    $pdo->prepare("INSERT INTO CLIENT (PER_ID) VALUES (:id)")
        ->execute([':id' => $perId]);

    $pdo->commit();

    // (Optionnel) connecter directement après inscription :
    // session_regenerate_id(true);
    // $_SESSION['per_id']   = $perId;
    // $_SESSION['per_email']= $email;
    // $_SESSION['per_nom']  = $nom;
    // $_SESSION['per_prenom']= $prenom;

    $_SESSION['message'] = "Inscription réussie ! Bienvenue {$prenom} {$nom} 🎉";
    header('Location: interface_connexion.php'); // redirige vers la page de connexion
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') {
        $_SESSION['message'] = "Cet e-mail ou ce téléphone est déjà utilisé.";
    } else {
        error_log('Signup PDO error: '.$e->getMessage());
        $_SESSION['message'] = "Une erreur est survenue lors de l'inscription.";
    }
    header('Location: inscription.php'); exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Signup error: '.$e->getMessage());
    $_SESSION['message'] = "Une erreur est survenue lors de l'inscription.";
    header('Location: inscription.php'); exit;
}
