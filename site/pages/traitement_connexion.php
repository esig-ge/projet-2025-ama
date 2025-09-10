<?php
// /site/pages/traitement_connexion.php
session_start();

// Chargement PDO (doit RETOURNER un PDO)
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Récupération inputs
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['message'] = 'Email et mot de passe requis.';
    header('Location: interface_connexion.php'); exit;
}

// Lookup utilisateur
$stmt = $pdo->prepare("SELECT PER_ID, PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP
                       FROM PERSONNE
                       WHERE PER_EMAIL = :email
                       LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérification mot de passe
if (!$user || ($password !== $user['PER_MDP'])) {
    $_SESSION['message'] = 'Identifiants incorrects.';
    header('Location: interface_connexion.php'); exit;
}

// Connexion
session_regenerate_id(true);
$_SESSION['per_id']     = (int)$user['PER_ID'];
$_SESSION['per_email']  = $user['PER_EMAIL'];
$_SESSION['per_nom']    = $user['PER_NOM'];
$_SESSION['per_prenom'] = $user['PER_PRENOM'];

header('Location: index.php'); exit;
