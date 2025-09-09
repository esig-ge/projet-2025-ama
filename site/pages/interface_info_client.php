<?php
// interface_info_client.php
session_start();
require_once __DIR__ . '/../database/config/connexionBDD.php';  // adapte le chemin si besoin

// Redirige vers login si non connecté
if (empty($_SESSION['per_id'])) {
    header('Location: login.php');
    exit;
}

// Base URL avec slash final
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

$perId = (int) $_SESSION['per_id'];
$errors = [];
$okMsg  = '';

// ====== Charger infos actuelles ======
$stmt = $pdo->prepare("SELECT PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_ID = :id");
$stmt->execute(['id' => $perId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Sécu : si l'ID de session ne correspond à personne
    session_destroy();
    header('Location: login.php');
    exit;
}

// ====== Traitement formulaire ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage basique
    $nom    = trim($_POST['lastname']  ?? '');
    $prenom = trim($_POST['firstname'] ?? '');
    $email  = trim($_POST['email']     ?? '');
    $tel    = trim($_POST['phone']     ?? '');
    $pwd    = (string)($_POST['password'] ?? '');

    // Validations simples
    if ($nom === '')    { $errors[] = "Le nom est obligatoire."; }
    if ($prenom === '') { $errors[] = "Le prénom est obligatoire."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse e-mail est invalide.";
    }
    // Téléphone souple : 6 à 15 chiffres/espaces/+/-
    if ($tel !== '' && !preg_match('/^[0-9\s+\-]{6,15}$/', $tel)) {
        $errors[] = "Le numéro de téléphone est invalide.";
    }

    // Email déjà utilisé par quelqu’un d’autre ?
    if (!$errors) {
        $q = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :mail AND PER_ID <> :me LIMIT 1");
        $q->execute(['mail' => $email, 'me' => $perId]);
        if ($q->fetchColumn()) {
            $errors[] = "Cette adresse e-mail est déjà utilisée.";
        }
    }

    // Si OK → update
    if (!$errors) {
        try {
            if ($pwd !== '') {
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $sql  = "UPDATE PERSONNE
                 SET PER_NOM=:nom, PER_PRENOM=:prenom, PER_EMAIL=:email, PER_NUM_TEL=:tel, PER_MDP=:pwd
                 WHERE PER_ID=:id";
                $params = ['nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'tel'=>$tel,'pwd'=>$hash,'id'=>$perId];
            } else {
                $sql  = "UPDATE PERSONNE
                 SET PER_NOM=:nom, PER_PRENOM=:prenom, PER_EMAIL=:email, PER_NUM_TEL=:tel
                 WHERE PER_ID=:id";
                $params = ['nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'tel'=>$tel,'id'=>$perId];
            }
            $u = $pdo->prepare($sql);
            $u->execute($params);

            $okMsg = "Vos informations ont bien été mises à jour.";
            // Recharger pour afficher les nouvelles valeurs
            $stmt = $pdo->prepare("SELECT PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_ID = :id");
            $stmt->execute(['id' => $perId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $errors[] = "Erreur lors de la mise à jour. Réessayez plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Mes informations</title>

    <!-- CSS global (header/footer) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS formulaire (réutilisé connexion/inscription) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_info_client.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <div class="conteneur_form">
        <h2>Mes informations</h2>

        <?php if ($okMsg): ?>
            <div class="flash-ok"><?= htmlspecialchars($okMsg) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="flash-err">
                <ul style="margin:0 0 0 18px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <label for="firstname">Prénom</label>
            <input type="text" id="firstname" name="firstname" required
                   value="<?= htmlspecialchars($user['PER_PRENOM'] ?? '') ?>" placeholder="Votre prénom">

            <label for="lastname">Nom</label>
            <input type="text" id="lastname" name="lastname" required
                   value="<?= htmlspecialchars($user['PER_NOM'] ?? '') ?>" placeholder="Votre nom">

            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone"
                   value="<?= htmlspecialchars($user['PER_NUM_TEL'] ?? '') ?>"
                   placeholder="078 212 56 78" pattern="[0-9\s\-+]{6,15}">
            <div class="hint">Format accepté : chiffres, espaces, + et - (6 à 15 caractères).</div>

            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($user['PER_EMAIL'] ?? '') ?>" placeholder="exemple@mail.com">

            <label for="password">Nouveau mot de passe (optionnel)</label>
            <input type="password" id="password" name="password" placeholder="Laissez vide pour ne pas changer">

            <input type="submit" value="Enregistrer">
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
