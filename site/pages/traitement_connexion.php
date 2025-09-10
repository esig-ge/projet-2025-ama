<?php
// /site/pages/traitement_connexion.php
session_start();

// Calcule une base fiable pour toutes les redirections
$dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// CSRF
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    $_SESSION['message'] = "Session expirée, merci de réessayer.";
    header('Location: ' . $PAGE_BASE . 'interface_connexion.php'); exit;
}

// Inputs
$email = trim($_POST['email'] ?? '');
$mdp   = $_POST['mdp'] ?? '';

if ($email === '' || $mdp === '') {
    $_SESSION['message'] = "Veuillez remplir tous les champs.";
    header('Location: ' . $PAGE_BASE . 'interface_connexion.php'); exit;
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';

    $sql = "SELECT p.PER_ID, p.PER_NOM, p.PER_PRENOM, p.PER_EMAIL, p.PER_MDP,
                   c.PER_ID AS is_client
            FROM PERSONNE p
            LEFT JOIN CLIENT c ON c.PER_ID = p.PER_ID
            WHERE p.PER_EMAIL = :email
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($mdp, $user['PER_MDP'])) {
        $_SESSION['message'] = "Identifiants incorrects.";
        header('Location: ' . $PAGE_BASE . 'interface_connexion.php'); exit;
    }

    // OK -> connecter
    session_regenerate_id(true);
    $_SESSION['per_id']     = (int)$user['PER_ID'];
    $_SESSION['per_email']  = $user['PER_EMAIL'];
    $_SESSION['per_nom']    = $user['PER_NOM'];
    $_SESSION['per_prenom'] = $user['PER_PRENOM'];
    $_SESSION['is_client']  = !empty($user['is_client']);

    $_SESSION['message'] = "Bienvenue {$user['PER_PRENOM']} !";
    header('Location: ' . $PAGE_BASE . 'index.php'); // adapte si besoin (espace_client.php, etc.)
    exit;

} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    $_SESSION['message'] = "Erreur serveur. Réessayez.";
    header('Location: ' . $PAGE_BASE . 'interface_connexion.php'); exit;
}
