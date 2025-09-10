<?php session_start();


require_once '../database/config/connexionBDD.php';
$db = dbConnect();

$nom = isset($_POST['lastname']) ? trim($_POST['lastname']) : null;
$prenom = isset($_POST['firstname']) ? trim($_POST['firstname']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$telephone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$mot_de_passe = isset($_POST['password']) ? $_POST['password'] : null;

//verif
if (!$nom || !$prenom || !$email || !$mot_de_passe) {
    $_SESSION['message'] = "Tous les champs sont obligatoires.";
    header('Location: inscription.php');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Adresse email invalide.";
    header('Location: inscription.php');
    exit();
}

$sql = "INSERT INTO personne (PER_NOM, PER_PRENOM, PER_NUM_TEL, PER_EMAIL, PER_MDP)
        VALUES (:nom, :prenom, :telephone, :email, :mdp)";
$stmt = $db->prepare($sql);
$stmt->execute([
    ':nom' => $nom,
    ':prenom' => $prenom,
    ':telephone' => $telephone,
    ':email' => $email,
    ':mdp' => $mot_de_passe
]);

// Récupérer l’ID de la PERSONNE qu’on vient de créer
$idPersonne = $db->lastInsertId();

// 2) Créer le CLIENT lié à cette PERSONNE
$sql = "INSERT INTO CLIENT (PER_ID) VALUES (:per_id)";
$stmt = $db->prepare($sql);
$stmt->execute([
    ':per_id'    => $idPersonne,
]);
$_SESSION['message'] = "Inscription réussie ! Bienvenue $prenom $nom";
header('Location: index.php');
exit();