<?php
// /site/pages/success.php
session_start();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>Paiement réussi</title>
    <link rel="stylesheet" href="<?= rtrim(dirname($_SERVER['PHP_SELF']),'/').'/'; ?>../css/style_header_footer.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main style="max-width:900px;margin:80px auto 40px;padding:0 16px;">
    <h1>Merci pour votre achat 💐</h1>
    <p>Votre paiement a été confirmé. Vous recevrez un email de confirmation sous peu.</p>
    <p><a href="../index.php">Retour à l’accueil</a></p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
