<?php
// /site/pages/traitement_inscription.php
session_start();

/* Connexion DB */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* ===== Inputs ===== */
$nom      = trim($_POST['lastname']  ?? '');
$prenom   = trim($_POST['firstname'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? '')); // on normalise l’e-mail
$tel      = trim($_POST['phone']     ?? '');
$password = $_POST['password']       ?? '';

/* ===== Helpers ===== */
function toast_and_back(string $msg, string $target = 'interface_inscription.php'): void {
    $_SESSION['toast'] = ['type'=>'error','text'=>$msg];
    header("Location: {$target}");
    exit;
}

/* ===== Normalisations ===== */
// Téléphone: garder uniquement les chiffres, ex: 0791234567
$tel = preg_replace('/\D+/', '', $tel);

/* ===== Validations ===== */
// Champs requis
if ($nom === '' || $prenom === '' || $email === '' || $tel === '' || $password === '') {
    toast_and_back("Tous les champs sont obligatoires.");
}
// Téléphone: 07x + 8 chiffres (= 10 au total)
if (!preg_match('/^07\d{8}$/', $tel)) {
    toast_and_back("Téléphone invalide. Format attendu: 07x xxx xx xx (10 chiffres).");
}
// Email
if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    toast_and_back("Adresse e-mail invalide (format attendu: luci@gmail.com).");
}
// Mot de passe: min 8, 1 maj, 1 min, 1 chiffre, 1 spécial
$pwdOk = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
if (!$pwdOk) {
    toast_and_back("Mot de passe invalide: min 8 caractères, avec 1 maj, 1 min, 1 chiffre et 1 caractère spécial.");
}

/* ===== Inscription avec réactivation éventuelle ===== */
try {
    // 1) On regarde s’il existe déjà un compte avec cet e-mail (actif ou inactif)
    $st = $pdo->prepare("SELECT PER_ID, PER_COMPTE_ACTIF, PER_NUM_TEL
                           FROM PERSONNE
                          WHERE LOWER(PER_EMAIL) = :e
                          LIMIT 1");
    $st->execute([':e' => $email]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $perId = (int)$existing['PER_ID'];

        // 1a) Si déjà ACTIF -> on bloque
        if ((int)$existing['PER_COMPTE_ACTIF'] === 1) {
            toast_and_back("Un compte actif existe déjà avec ce mail.");
        }

        // 1b) Compte INACTIF trouvé -> on réactive
        //     Avant, on vérifie que le téléphone n’appartient pas à un AUTRE compte ACTIF
        $stTel = $pdo->prepare("
            SELECT 1
              FROM PERSONNE
             WHERE PER_NUM_TEL = :tel
               AND PER_COMPTE_ACTIF = 1
               AND PER_ID <> :me
             LIMIT 1
        ");
        $stTel->execute([':tel' => $tel, ':me' => $perId]);
        if ($stTel->fetchColumn()) {
            toast_and_back("Un compte actif utilise déjà ce numéro de téléphone.");
        }

        // Réactivation: on met à jour les infos + mdp + activité
        $upd = $pdo->prepare("
            UPDATE PERSONNE
               SET PER_NOM           = :nom,
                   PER_PRENOM        = :prenom,
                   PER_EMAIL         = :email,
                   PER_MDP           = :pwd,          -- ⚠ en clair (comme ton login actuel)
                   PER_NUM_TEL       = :tel,
                   PER_COMPTE_ACTIF  = 1,
                   reset_token_hash = NULL,
                   reset_token_expires_at = NULL
             WHERE PER_ID = :id
             LIMIT 1
        ");
        $upd->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':pwd'    => $password,
            ':tel'    => $tel,
            ':id'     => $perId,
        ]);

        // On s’assure qu’il y a bien une ligne CLIENT (si déjà existante, rien à faire)
        $stCli = $pdo->prepare("SELECT 1 FROM CLIENT WHERE PER_ID = :id LIMIT 1");
        $stCli->execute([':id' => $perId]);
        if (!$stCli->fetchColumn()) {
            $pdo->prepare("INSERT INTO CLIENT (PER_ID) VALUES (:id)")->execute([':id' => $perId]);
        }

        $_SESSION['toast'] = ['type'=>'success','text'=>"Compte réactivé, vous pouvez vous connecter."];
        header('Location: interface_connexion.php'); exit;
    }

    // 2) Aucun compte avec cet e-mail -> création
    //    Vérifier que le téléphone n’est pas déjà utilisé par un autre compte ACTIF
    $stTel = $pdo->prepare("
        SELECT 1
          FROM PERSONNE
         WHERE PER_NUM_TEL = :tel
           AND PER_COMPTE_ACTIF = 1
         LIMIT 1
    ");
    $stTel->execute([':tel' => $tel]);
    if ($stTel->fetchColumn()) {
        toast_and_back("Un compte existe déjà avec ce numéro de téléphone.");
    }

    // Création de la personne (active)
    $ins = $pdo->prepare("
        INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL, PER_COMPTE_ACTIF)
        VALUES (:nom, :prenom, :email, :pwd, :tel, 1)
    ");
    $ins->execute([
        ':nom'    => $nom,
        ':prenom' => $prenom,
        ':email'  => $email,
        ':pwd'    => $password,   // ⚠ en clair (comme ton login actuel)
        ':tel'    => $tel,
    ]);

    $perId = (int)$pdo->lastInsertId();

    // Ligne CLIENT liée
    $pdo->prepare("INSERT INTO CLIENT (PER_ID) VALUES (:id)")
        ->execute([':id' => $perId]);

    $_SESSION['toast'] = ['type'=>'success','text'=>"Inscription réussie, connectez-vous maintenant!"];
    header('Location: interface_connexion.php'); exit;

} catch (Throwable $e) {
    // 23000 = contrainte (UNIQUE, FK, …)
    if ((int)$e->getCode() === 23000) {
        toast_and_back("Cet e-mail ou ce téléphone est déjà utilisé.");
    }
    toast_and_back("Erreur serveur pendant l'inscription. Réessayez.");
}
