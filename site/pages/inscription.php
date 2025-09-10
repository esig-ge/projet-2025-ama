<?php
// /site/pages/interface_inscription.php
session_start();

// Base URL
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
    <title>DK Bloom — Inscription</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <style>
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            flex: 1;
            padding-right: 45px; /* un peu plus de place */
            font-size: 1rem;     /* tu peux aussi augmenter si besoin */
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 1.4rem;   /* <-- augmenté (avant 1rem) */
            line-height: 1;      /* garde compact */
            padding: 4px;        /* clique plus confortable */
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <div class="conteneur_form">
        <h2>S'inscrire</h2>

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="flash" role="alert">
                <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <form action="<?= $BASE ?>traitement_inscription.php" method="POST">
            <label for="lastname">Nom</label>
            <input type="text" id="lastname" name="lastname" required>

            <label for="firstname">Prénom</label>
            <input type="text" id="firstname" name="firstname" required>

            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone" required>

            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Mot de passe</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)">👁</button>
            </div>

            <input type="submit" value="S'inscrire">
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    function togglePassword(fieldId, btn) {
        const field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
            btn.textContent = "🕶";
        } else {
            field.type = "password";
            btn.textContent = "👁";
        }
    }
</script>
</body>
</html>