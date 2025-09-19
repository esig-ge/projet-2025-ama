<?php
// /site/pages/traitement_connexion.php
session_start();

// Base URL pour redirections robustes
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Inputs
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');
$csrf     = (string)($_POST['csrf'] ?? '');

// CSRF
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    $_SESSION['toast'] = ['type'=>'error','text'=>'Session expirée, veuillez réessayer.'];
    header('Location: '.$BASE.'interface_connexion.php'); exit;
}

// Champs vides
if ($email === '' || $password === '') {
    $_SESSION['toast'] = ['type'=>'error','text'=>'Email et mot de passe requis.'];
    header('Location: '.$BASE.'interface_connexion.php'); exit;
}

// Email valide + longueur
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 50) {
    $_SESSION['toast'] = ['type'=>'error','text'=>'Adresse e-mail invalide.'];
    header('Location: '.$BASE.'interface_connexion.php'); exit;
}

// Lookup utilisateur
$sql = "SELECT PER_ID, PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP
        FROM PERSONNE
        WHERE PER_EMAIL = :email
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérification mot de passe (non hashé, choix assumé)
if (!$user || ($password !== $user['PER_MDP'])) {
    $_SESSION['toast'] = ['type'=>'error','text'=>'Identifiants incorrects, veuillez réessayer.'];
    header('Location: '.$BASE.'interface_connexion.php'); exit;
}

// Connexion OK
session_regenerate_id(true);
$_SESSION['per_id']     = (int)$user['PER_ID'];
$_SESSION['per_email']  = $user['PER_EMAIL'];
$_SESSION['per_nom']    = $user['PER_NOM'];
$_SESSION['per_prenom'] = $user['PER_PRENOM'];

// Rôle admin via table ADMINISTRATEUR
$stAdm = $pdo->prepare("SELECT 1 FROM ADMINISTRATEUR WHERE PER_ID = :id LIMIT 1");
$stAdm->execute([':id' => (int)$user['PER_ID']]);
$_SESSION['is_admin'] = (bool)$stAdm->fetchColumn();

// Toast succès
$_SESSION['toast'] = ['type'=>'success','text'=>'Connexion réussie'];

// Redirection
header('Location: '.$BASE.($_SESSION['is_admin'] ? 'adminAccueil.php' : 'index.php'));
exit;
