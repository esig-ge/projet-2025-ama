<?php
// /site/pages/traitement_connexion.php
session_start();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Inputs
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// 1) Champs vides -> toast + retour
if ($email === '' || $password === '') {
    $_SESSION['toast'] = [
        'type' => 'error',
        'text' => 'Email et mot de passe requis.'
    ];
    header('Location: interface_connexion.php');
    exit;
}

// 2) Lookup utilisateur
$sql = "SELECT PER_ID, PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP
        FROM PERSONNE
        WHERE PER_EMAIL = :email
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 3) Vérification mot de passe
// NOTE: si tes mots de passe sont hashés, remplace par password_verify($password, $user['PER_MDP'])
if (!$user || ($password !== $user['PER_MDP'])) {
    $_SESSION['toast'] = [
        'type' => 'error',
        'text' => 'Identifiants incorrects, veuillez réessayer.'
    ];
    header('Location: interface_connexion.php');
    exit;
}

// 4) Connexion OK -> régénère l’ID et remplis la session AVANT de rediriger
session_regenerate_id(true);

$_SESSION['per_id']     = (int)$user['PER_ID'];
$_SESSION['per_email']  = $user['PER_EMAIL'];
$_SESSION['per_nom']    = $user['PER_NOM'];
$_SESSION['per_prenom'] = $user['PER_PRENOM'];
$_SESSION['is_admin']   = false; // par défaut

// 5) Toast de succès (sera affiché sur la page de destination)
$_SESSION['toast'] = [
    'type' => 'success',
    'text' => 'Connexion réussie'
];

// 6) Redirection admin vs client
if (strcasecmp($user['PER_EMAIL'], 'dk.bloom@gmail.com') === 0) {
    $_SESSION['is_admin'] = true;
    header('Location: adminAccueil.php');
    exit;
}

header('Location: index.php');
exit;
