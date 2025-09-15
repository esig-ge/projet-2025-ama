<?php

// Base URL avec slash final
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code']  ?? '');
    $p1    = $_POST['mdp'] ?? '';
    $p2    = $_POST['confirm_mdp'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !ctype_digit($code) || strlen($code) !== 6) {
        $_SESSION['message'] = "Entrées invalides.";
        header('Location: '.$BASE.'otp_verify.php'); exit;
    }
    if ($p1 === '' || $p2 === '' || $p1 !== $p2 || strlen($p1) < 8) {
        $_SESSION['message'] = "Mot de passe invalide ou non identique (min. 8 caractères).";
        header('Location: '.$BASE.'otp_verify.php'); exit;
    }

    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
        $pdo->beginTransaction();

        // 1) Utilisateur
        $st = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :em LIMIT 1");
        $st->execute([':em' => $email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            $pdo->rollBack();
            $_SESSION['message'] = "Code invalide ou expiré.";
            header('Location: '.$BASE.'otp_verify.php'); exit;
        }

        // 2) Dernier OTP actif non expiré
        $st2 = $pdo->prepare("SELECT PO_ID, PO_CODE_HASH, PO_ATTEMPTS
                              FROM PASSWORD_OTP
                              WHERE PER_ID = :id AND PO_USED_AT IS NULL AND PO_EXPIRES_AT > NOW()
                              ORDER BY PO_ID DESC LIMIT 1");
        $st2->execute([':id' => $u['PER_ID']]);
        $otp = $st2->fetch(PDO::FETCH_ASSOC);
        if (!$otp) {
            $pdo->rollBack();
            $_SESSION['message'] = "Code invalide ou expiré.";
            header('Location: '.$BASE.'otp_verify.php'); exit;
        }

        // 3) Vérifie tentatives
        if ((int)$otp['PO_ATTEMPTS'] >= 5) {
            $pdo->rollBack();
            $_SESSION['message'] = "Nombre de tentatives dépassé. Recommencez la procédure.";
            header('Location: '.$BASE.'otp_forgot.php'); exit;
        }

        // 4) Vérifie code
        if (!password_verify($code, $otp['PO_CODE_HASH'])) {
            $pdo->prepare("UPDATE PASSWORD_OTP SET PO_ATTEMPTS = PO_ATTEMPTS + 1 WHERE PO_ID = :po")
                ->execute([':po' => $otp['PO_ID']]);
            $pdo->commit();
            $_SESSION['message'] = "Code incorrect. Réessayez.";
            header('Location: '.$BASE.'otp_verify.php'); exit;
        }

        // 5) MAJ mot de passe + invalider OTP
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE PERSONNE SET PER_MDP = :h WHERE PER_ID = :id")
            ->execute([':h' => $hash, ':id' => $u['PER_ID']]);

        $pdo->prepare("UPDATE PASSWORD_OTP SET PO_USED_AT = NOW() WHERE PO_ID = :po")
            ->execute([':po' => $otp['PO_ID']]);

        // Nettoyage: supprime tous autres OTP non utilisés
        $pdo->prepare("DELETE FROM PASSWORD_OTP WHERE PER_ID = :id AND PO_USED_AT IS NULL")
            ->execute([':id' => $u['PER_ID']]);

        $pdo->commit();

        $_SESSION['message'] = "Votre mot de passe a été mis à jour.";
        header('Location: '.$BASE.'interface_connexion.php'); exit;

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['message'] = "Erreur serveur. Réessayez.";
        header('Location: '.$BASE.'otp_verify.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Changement mot de passe</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container">
    <div class="conteneur_form">
        <h2>Réinitialiser le mot de passe</h2>
        <form method="POST">
            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>

            <label for="code">Code reçu (6 chiffres) :</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required>

            <label for="mdp">Nouveau mot de passe :</label>
            <input type="password" id="mdp" name="mdp" required minlength="8">

            <label for="confirm_mdp">Confirmer le mot de passe :</label>
            <input type="password" id="confirm_mdp" name="confirm_mdp" required minlength="8">

            <button type="submit">Modifier</button>
        </form>

        <?php if (!empty($message)): ?>
            <p class="info"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <p style="margin-top:10px">
            Pas encore de code ? <a href="<?= $BASE ?>otp_forgot.php">Demander un code</a>
        </p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
