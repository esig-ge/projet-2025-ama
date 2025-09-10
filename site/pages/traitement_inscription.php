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

if ($nom === '' || $prenom === '' || $email === '' || $tel === '' || $password === '') {
    $_SESSION['message'] = "Tous les champs sont obligatoires.";
    header('Location: inscription.php'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Adresse e-mail invalide.";
    header('Location: inscription.php'); exit;
}

// Vérifie si l'email existe déjà
$stmt = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :email LIMIT 1");
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    $_SESSION['message'] = "Un compte existe déjà avec cet e-mail.";
    header('Location: inscription.php'); exit;
}

// Insertion PERSONNE
$sql1 = "INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
         VALUES (:nom, :prenom, :email, :password, :tel)";
$pdo->prepare($sql1)->execute([
    ':nom'      => $nom,
    ':prenom'   => $prenom,
    ':email'    => $email,
    ':password' => $password, // en clair (⚠️ pas recommandé en prod)
    ':tel'      => $tel,
]);

$perId = (int)$pdo->lastInsertId();

// Insertion CLIENT
$pdo->prepare("INSERT INTO CLIENT (PER_ID) VALUES (:id)")
    ->execute([':id' => $perId]);

$_SESSION['message'] = "Inscription réussie, bienvenue {$prenom} {$nom} !";
header('Location: interface_connexion.php'); exit;
