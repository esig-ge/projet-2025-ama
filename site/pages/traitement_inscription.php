<?php
// /site/pages/traitement_inscription.php
session_start();

// Connexion DB
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Inputs
$nom      = trim($_POST['lastname']  ?? '');
$prenom   = trim($_POST['firstname'] ?? '');
$email    = trim($_POST['email']     ?? '');
$tel      = trim($_POST['phone']     ?? '');
$password = $_POST['password']       ?? '';

// ===== Helpers
function toast_and_back(string $msg, string $target = 'interface_inscription.php'): void {
    $_SESSION['toast'] = ['type'=>'error','text'=>$msg];
    header("Location: {$target}");
    exit;
}

// ===== Normalisations
// Téléphone: garder uniquement les chiffres, pour stocker "0791234567"
$tel = preg_replace('/\D+/', '', $tel);

// ===== Validations
// Champs requis
if ($nom === '' || $prenom === '' || $email === '' || $tel === '' || $password === '') {
    toast_and_back("Tous les champs sont obligatoires.");
}

// Téléphone: 07x + 8 chiffres (= 10 au total), ex: 0791234567
if (!preg_match('/^07\d{8}$/', $tel)) {
    toast_and_back("Téléphone invalide. Format attendu: 07x xxx xx xx (10 chiffres).");
}

// Email: forme minimale %@%.% + filtre robuste
if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    toast_and_back("Adresse e-mail invalide (format attendu: luci@gmail.com).");
}

// Mot de passe: min 8, 1 maj, 1 min, 1 chiffre, 1 spécial
$pwdOk = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
if (!$pwdOk) {
    toast_and_back("Mot de passe invalide: min 8 caractères, avec 1 maj, 1 min, 1 chiffre et 1 caractère spécial.");
}

// ===== Unicité mail/téléphone
$stm = $pdo->prepare("SELECT PER_EMAIL, PER_NUM_TEL FROM PERSONNE
                      WHERE PER_EMAIL = :email OR PER_NUM_TEL = :tel
                      LIMIT 1");
$stm->execute([':email' => $email, ':tel' => $tel]);
if ($dup = $stm->fetch(PDO::FETCH_ASSOC)) {
    if (strcasecmp($dup['PER_EMAIL'] ?? '', $email) === 0) {
        toast_and_back("Un compte existe déjà avec ce mail.");
    }
    if (($dup['PER_NUM_TEL'] ?? '') === $tel) {
        toast_and_back("Un compte existe déjà avec ce numéro de téléphone.");
    }
    toast_and_back("Un compte existe déjà avec ces informations.");
}


// Insertion PERSONNE
try{
    $sql1 = "INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
             VALUES (:nom, :prenom, :email, :password, :tel)";
    $pdo->prepare($sql1)->execute([
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':email'    => $email,
        ':password' => $password,
        ':tel'      => $tel,
    ]);

    $perId = (int)$pdo->lastInsertId();

    // Insertion CLIENT
    $pdo->prepare("INSERT INTO CLIENT (PER_ID) VALUES (:id)")
        ->execute([':id' => $perId]);

    // Succès → toast + redirection vers page de connexion
    $_SESSION['toast'] = ['type'=>'success','text'=>"Inscription réussie, connectez-vous maintenant!"];
    header('Location: interface_connexion.php'); exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // 23000 = violation contrainte (UNIQUE, FK, …)
    if ((int)$e->getCode() === 23000) {
        toast_and_back("Cet e-mail ou ce téléphone est déjà utilisé.");
    }
    toast_and_back("Erreur serveur pendant l'inscription. Réessayez.");
}