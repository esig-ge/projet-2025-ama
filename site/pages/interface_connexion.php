<?php
// /site/pages/interface_connexion.php
session_start();

// Base URL + page base (pour liens & includes)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Connexion</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <div class="conteneur_form">
        <h2>Connectez-vous</h2>

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="flash" role="alert" style="margin:10px 0;background:#f8d7da;border:1px solid #f5c2c7;padding:10px;border-radius:8px;">
                <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form action="<?= $BASE ?>traitement_connexion.php" method="POST" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required autocomplete="email">

            <label for="mdp">Mot de passe :</label>
            <input type="password" id="mdp" name="mdp" required autocomplete="current-password">

            <br><br>
            <input type="submit" value="Connexion">
            <p><a href="<?= $BASE ?>interface_modification_mdp.php">Mot de passe oublié ?</a></p>
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
