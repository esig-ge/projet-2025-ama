<?php
// /site/pages/interface_oubli_mdp.php
session_start();

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Message flash / toast ===== */
$flash      = $_SESSION['flash']      ?? null;
$toastType  = $_SESSION['toast_type'] ?? null;
$toastMsg   = $_SESSION['toast_msg']  ?? null;
unset($_SESSION['flash'], $_SESSION['toast_type'], $_SESSION['toast_msg']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DK Bloom — Mot de passe oublié</title>
    <!-- Conserve bien ces 3 CSS pour garder le visuel -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_reset_mdp.css">
</head>
<body class="reset-page">
<main class="reset-card">
    <h1>Mot de passe oublié</h1>
    <p>Entrez votre adresse e-mail pour recevoir un code de réinitialisation.</p>

    <?php if ($flash): ?>
        <p class="note"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <form action="<?= $BASE ?>traitement_oubli_mdp.php" method="post" class="reset-form" autocomplete="off">
        <label for="email" class="reset-label">Adresse e-mail</label>
        <div class="reset-input">
            <input type="email" id="email" name="email" required autocomplete="email" placeholder="prenom.nom@email.com" autofocus>
        </div>
        <button type="submit" class="btn-primary">Recevoir un code</button>
    </form>

    <p class="reset-links">
        J’ai déjà un code → <a href="<?= $BASE ?>reinitialisation_mdp.php">Saisir le code</a>
    </p>
</main>

<?php if ($toastMsg): ?>
    <div class="toast <?= htmlspecialchars($toastType ?: 'info') ?>" id="toast">
        <div><?= htmlspecialchars($toastMsg) ?></div>
        <button class="toast-close" aria-label="Fermer">&times;</button>
    </div>
<?php endif; ?>

<script>
    (function(){
        const t = document.getElementById('toast');
        if (!t) return;
        const btn = t.querySelector('.toast-close');
        setTimeout(()=>t.classList.add('show'), 120);
        setTimeout(()=>t.classList.remove('show'), 4600);
        if (btn) btn.addEventListener('click', ()=>t.classList.remove('show'));
    })();
</script>
</body>
</html>
