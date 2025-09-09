<?php
session_start();
require_once __DIR__ . '/../../config/connexionBDD.php';

// Base URL avec slash final
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
$sessionId = htmlspecialchars($_GET['session_id'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Merci</title>

    <!-- CSS global (header/footer) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS spécifique à la page (optionnel si tu as un fichier dédié) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/checkout.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container" style="text-align:center; padding:32px 0;">
    <h1>Merci pour votre commande 🌹</h1>
    <p>Vous allez recevoir un e-mail de confirmation. La livraison est estimée sous ~7 jours ouvrables.</p>
    <?php if ($sessionId): ?>
        <p>Votre paiement a été accepté.<br>Référence Stripe&nbsp;: <strong><?= $sessionId ?></strong></p>
    <?php else: ?>
        <p>Votre paiement a été accepté.</p>
    <?php endif; ?>
    <p><a class="button" href="<?= $BASE ?>catalogue.php">← Retour au catalogue</a></p>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
