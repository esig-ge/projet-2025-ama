<?php
// /site/pages/interface_connexion.php
session_start();

// Base URL
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

        <form action="<?= $BASE ?>traitement_connexion.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

            <label for="email">Adresse e-mail :</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Mot de passe :</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Connexion">
<!--            <p><a href="--><?php //= $BASE ?><!--interface_modification_mdp.php">Mot de passe oublié ?</a></p>-->
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Expose BASE (si nécessaire pour ton toast) -->
<script>window.DKBASE = <?= json_encode($BASE) ?>;</script>
<!-- Charge le JS qui contient déjà ton système de toast (même que sur fleur/bouquet) -->
<script src="<?= $BASE ?>js/commande.js"></script>
<?php if (!empty($_SESSION['toast'])):
          $t = $_SESSION['toast'];
          unset($_SESSION['toast']); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const msg  = <?= json_encode($t['text'], JSON_UNESCAPED_UNICODE) ?>;
        const type = <?= json_encode($t['type']) ?>; // 'error' | 'success' | 'info'
        // Compat selon le nom de ta fonction toast
        if (typeof window.toast === 'function')       { window.toast(msg, type); }
        else if (typeof window.showToast === 'function') { window.showToast(msg, type); }
        else { alert(msg); }
        });
    </script>
    <?php endif; ?>
</body>
</html>
