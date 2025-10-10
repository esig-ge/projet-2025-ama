<?php
// /site/pages/reinitialisation_mdp.php
session_start();

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Récupérer l'info
$devNote = $_SESSION['dev_code'] ?? null;
unset($_SESSION['dev_code']);

/* ===== Toast (venant d'une redirection) ===== */
$toastType = $_SESSION['toast_type'] ?? null;
$toastMsg  = $_SESSION['toast_msg']  ?? null;
unset($_SESSION['toast_type'], $_SESSION['toast_msg']);

$email   = $_GET['email'] ?? ($_POST['email'] ?? '');
$error   = '';
$success = '';
$devNote = $_SESSION['dev_code'] ?? null;
unset($_SESSION['dev_code']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code'] ?? '');
    $p1    = trim($_POST['new_password'] ?? '');
    $p2    = trim($_POST['new_password2'] ?? '');

    if (!$email || !$code) {
        $error = "Veuillez saisir l'e-mail et le code reçus.";
    } elseif (strlen($p1) < 4) {
        $error = "Mot de passe trop court (min 4 caractères).";
    } elseif ($p1 !== $p2) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            /** @var PDO $pdo */
            $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Récupérer le code stocké et l'expiration
            $st = $pdo->prepare("SELECT reset_token_hash, reset_token_expires_at FROM PERSONNE WHERE PER_EMAIL = ? LIMIT 1");
            $st->execute([$email]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $error = "Compte introuvable.";
            } elseif ($row['reset_token_hash'] !== $code) {
                $error = "Code incorrect.";
            } elseif (strtotime($row['reset_token_expires_at']) < time()) {
                $error = "Code expiré.";
            } else {
                // Mettre à jour le mot de passe (en clair, comme demandé)
                $upd = $pdo->prepare("UPDATE PERSONNE SET PER_MDP = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE PER_EMAIL = ? LIMIT 1");
                $upd->execute([$p1, $email]);

                $success = "Mot de passe changé avec succès.";
                $_SESSION['toast_type'] = 'success';
                $_SESSION['toast_msg']  = $success;
            }

        } catch (Throwable $e) {
            error_log('[RESET_PWD] ' . $e->getMessage());
            $error = "Erreur interne.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Réinitialisation</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_reset_mdp.css">
</head>
<body class="reset-page">
<main class="reset-card">
    <h1>Réinitialisation du mot de passe</h1>

    <?php if ($error): ?>
        <p class="note err"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="note ok"><?= htmlspecialchars($success) ?></p>
        <p><a href="<?= $BASE ?>interface_connexion.php" class="btn-primary" style="text-decoration:none;">Me connecter</a></p>
    <?php else: ?>
        <form method="post" class="reset-form" autocomplete="off">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

            <label class="reset-label" for="code">Code reçu</label>
            <div class="reset-input">
                <input id="code" name="code" type="text" required maxlength="6"
                       inputmode="numeric" autocomplete="one-time-code" autocapitalize="off" autocorrect="off"
                       placeholder="123456">
            </div>

            <label class="reset-label" for="new_password">Nouveau mot de passe</label>
            <div class="reset-input">
                <input type="password" id="new_password" name="new_password" required
                       minlength="4" autocomplete="new-password" placeholder="Nouveau mot de passe">
                <button type="button" class="toggle-pw" data-target="new_password" aria-label="Afficher/masquer">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

            <label class="reset-label" for="new_password2">Confirmez le mot de passe</label>
            <div class="reset-input">
                <input type="password" id="new_password2" name="new_password2" required
                       minlength="4" autocomplete="new-password" placeholder="Confirmez le mot de passe">
                <button type="button" class="toggle-pw" data-target="new_password2" aria-label="Afficher/masquer">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

            <button type="submit" class="btn-primary">Changer le mot de passe</button>
        </form>
    <?php endif; ?>

    <p class="reset-links">Besoin d’un nouveau code ? <a href="<?= $BASE ?>interface_oubli_mdp.php">Recommencer</a></p>
</main>

<?php if ($toastMsg): ?>
    <div class="toast <?= htmlspecialchars($toastType ?: 'info') ?>" id="toast">
        <div><?= htmlspecialchars($toastMsg) ?></div>
        <button class="toast-close" aria-label="Fermer">&times;</button>
    </div>
<?php endif; ?>
<script>
    // ===== Afficher / masquer mot de passe =====
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        const input = document.getElementById(btn.dataset.target);
        btn.addEventListener('click', () => {
            const svg = btn.querySelector('.eye-icon');
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            // Petite animation
            btn.style.color = visible ? '#8A1B2E' : '#E45C84';
            svg.style.transform = visible ? 'scale(1)' : 'scale(1.1)';
        });
    });

    // ===== Champ code : autoriser le coller et nettoyer =====
    const codeInput = document.getElementById('code');
    if (codeInput) {
        // Nettoie tout ce qui n'est pas chiffre, et coupe à 6
        const normalize = v => v.replace(/\D/g, '').slice(0, 6);

        // Au collage : on force la valeur propre
        codeInput.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text') || '';
            codeInput.value = normalize(text);
        });

        // À la saisie : on garde uniquement des chiffres (utile si clavier alpha)
        codeInput.addEventListener('input', () => {
            const cleaned = normalize(codeInput.value);
            if (codeInput.value !== cleaned) codeInput.value = cleaned;
        });
    }
</script>

<script>
    (function(){
        const t = document.getElementById('toast');
        if (!t) return;
        const btn = t.querySelector('.toast-close');
        setTimeout(()=>t.classList.add('show'),120);
        setTimeout(()=>t.classList.remove('show'),4600);
        if (btn) btn.addEventListener('click', ()=>t.classList.remove('show'));
    })();
</script>

<?php
// Considère "dev" si: localhost, esig-sandbox, ou ?dev=1
$IS_DEV = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false)
    || (stripos($_SERVER['HTTP_HOST'] ?? '', 'esig-sandbox') !== false)
    || isset($_GET['dev']);
?>
<?php if ($devNote && $IS_DEV): ?>
    <script>
        (function(){
            const note = <?= json_encode($devNote, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
            console.groupCollapsed('%c🧩 DK Bloom — reset (DEV)', 'color:#8A1B2E;font-weight:bold');
            console.log(note);
            console.groupEnd();
        })();
    </script>
<?php endif; ?>

</body>
</html>
