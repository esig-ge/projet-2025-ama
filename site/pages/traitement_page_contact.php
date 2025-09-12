<?php
session_start();

$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

$nom     = isset($_POST['nom'])     ? trim($_POST['nom'])     : '';
$prenom  = isset($_POST['prenom'])  ? trim($_POST['prenom'])  : '';
$email   = isset($_POST['email'])   ? trim($_POST['email'])   : '';
$tel     = isset($_POST['tel'])     ? trim($_POST['tel'])     : '';
$sujet   = isset($_POST['sujet'])   ? trim($_POST['sujet'])   : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$consent = isset($_POST['consent']) ? 1 : 0;

if ($nom === '' || $email === '' || $sujet === '' || $message === '' || !$consent) {
    $_SESSION['form_errors'] = ["Merci de remplir tous les champs obligatoires."];
    header('Location: contact.php'); // redirige vers ta page contact
    exit;
}

// Insertion en base
$sql = "INSERT INTO DEMANDE_CONTACT 
        (DEM_NOM, DEM_PRENOM, DEM_EMAIL, DEM_TEL, DEM_SUJET, DEM_MESSAGE) 
        VALUES (:nom, :prenom, :email, :tel, :sujet, :msg)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nom'    => $nom,
    ':prenom' => $prenom,
    ':email'  => $email,
    ':tel'    => $tel,
    ':sujet'  => $sujet,
    ':msg'    => $message,
]);

// Confirmation
$_SESSION['message'] = "Merci $nom, ta demande a bien été envoyée.";
header('Location: contact.php');
exit;


/*
<h1>page infos envoyer par le client a l'admin. donc si l'admin se connect il peit voir sa rebrique infos_client.
</h1>*/
