<?php
// /site/pages/interface_connexion.php
// Ce fichier correspond à la page de connexion pour les utilisateurs du site DK Bloom.
// Il contient le formulaire de connexion et la logique d’affichage (mais pas encore le traitement).

session_start();
// Démarrage de la session PHP : indispensable pour gérer les messages flash (ex: erreurs),
// les tokens CSRF (sécurité) et la persistance des infos de l’utilisateur connecté.


// Base URL + page base (pour liens & includes)
if (!isset($BASE)) {
    // dirname($_SERVER['PHP_SELF']) => récupère le chemin relatif du script en cours.
    // On "rtrim" pour enlever les éventuels slashs à la fin.
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');

    // Si on est à la racine ou que dirname renvoie '.' alors on force BASE = "/"
    // Sinon, on ajoute un slash à la fin.
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
// En gros, $BASE permet de construire des chemins relatifs corrects pour CSS, JS ou includes,
// peu importe si le site est en sous-dossier ou à la racine du serveur.


// CSRF token
if (empty($_SESSION['csrf'])) {
    // On génère un token CSRF unique pour cette session utilisateur,
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
// Ce token sera inséré dans le formulaire pour éviter les attaques
// En gros, ça empêche quelqu’un d’envoyer un faux formulaire à la place de l’utilisateur.
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
