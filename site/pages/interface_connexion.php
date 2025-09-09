<?php
// Base URL avec slash final
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Connexion</title>

    <!-- CSS global (header/footer) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS spécifique à cette page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <div class="conteneur_form">
        <h2>Connectez-vous</h2>

        <form action="" method="POST">
            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>

            <label for="mdp">Mot de passe :</label>
            <input type="password" id="mdp" name="mdp" required>

            <br><br>

            <input type="submit" value="Connexion">
            <p><a href="<?= $BASE ?>interface_modification_mdp.php">Mot de passe oublié ?</a></p>
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
