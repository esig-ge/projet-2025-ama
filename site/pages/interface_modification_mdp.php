<?php



// Base URL avec slash final
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    } catch (Throwable $e) {
        $_SESSION['message'] = "Une erreur est survenue.";
        header('Location: '.$BASE.'modification_mdp_verif.php'); exit;
    }

    $email = trim($_POST['email'] ?? '');
    // Réponse générique pour ne pas révéler l’existence d’un compte
    $_SESSION['message'] = "Si un compte existe, un e-mail avec un code a été envoyé.";
    header('Location: '.$BASE.'modification_mdp_verif.php.php');

    if ($email === '') exit;

    // Cherche l’utilisateur
    $st = $pdo->prepare("SELECT PER_ID, PER_PRENOM FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
    $st->execute([':em' => $email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) exit;

    // Invalide les anciens OTP non utilisés
    $pdo->prepare("DELETE FROM PASSWORD_OTP WHERE PER_ID = :id AND PO_USED_AT IS NULL")
        ->execute([':id' => $u['PER_ID']]);

    // Génère le code 6 chiffres
    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $exp  = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

    $pdo->prepare("INSERT INTO PASSWORD_OTP (PER_ID, PO_CODE_HASH, PO_EXPIRES_AT)
                   VALUES (:id, :h, :e)")
        ->execute([':id' => $u['PER_ID'], ':h' => $hash, ':e' => $exp]);

    // Envoi de l'e-mail
    require __DIR__ . '/../../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Configure ton SMTP (Infomaniak: adapte identifiants)
        $mail->isSMTP();
        $mail->Host = 'smtp.infomaniak.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@ton-domaine.ch';
        $mail->Password = '********';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@ton-domaine.ch', 'DK Bloom');
        $mail->addAddress($email);
        $mail->Subject = 'Votre code de réinitialisation';
        $prenom = $u['PER_PRENOM'] ?? 'client';
        $mail->isHTML(true);
        $mail->Body = "
          <p>Bonjour {$prenom},</p>
          <p>Voici votre code de réinitialisation (valable 15 minutes) :</p>
          <p style='font-size:22px;letter-spacing:3px'><b>{$code}</b></p>
          <p>Entrez ce code sur la page de vérification pour définir un nouveau mot de passe.</p>
          <p>Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.</p>
        ";
        $mail->AltBody = "Code de réinitialisation (15 min): {$code}";
        $mail->send();
    } catch (Throwable $e) {
        // Option: log serveur
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Code de réinitialisation</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container">
    <div class="conteneur_form">
        <h2>Mot de passe oublié</h2>
        <form method="POST">
            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Recevoir un code</button>
        </form>
        <?php if (!empty($message)): ?>
            <p class="info"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <p style="margin-top:10px">
            Déjà un code ? <a href="<?= $BASE ?>modification_mdp_verif.php.php">Clique ici pour le saisir</a>
        </p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
