<?php
/* supprimer_compte.php (soft delete) */
declare(strict_types=1);
session_start();

/* on: base + garde + csrf */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if (empty($_SESSION['per_id'])) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Veuillez vous connecter pour supprimer votre compte.";
    header('Location: '.$BASE.'interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* on récupère l’e-mail pour confirmation */
$st = $pdo->prepare("SELECT PER_EMAIL, PER_PRENOM, PER_NOM FROM PERSONNE WHERE PER_ID = :id");
$st->execute([':id' => $perId]);
$me = $st->fetch(PDO::FETCH_ASSOC) ?: ['PER_EMAIL'=>'','PER_PRENOM'=>'','PER_NOM'=>''];
$confirmEmail = (string)$me['PER_EMAIL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

    /* on: CSRF */
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = "Session expirée. Veuillez réessayer.";
        header('Location: '.$BASE.'supprimer_compte.php'); exit;
    }

    /* on: confirmation e-mail */
    $typed  = preg_replace('/\s+/', '', strtolower(trim($_POST['confirm_username'] ?? '')));
    $expect = preg_replace('/\s+/', '', strtolower($confirmEmail));
    if ($typed === '' || $typed !== $expect) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = "L’e-mail saisi ne correspond pas à celui de votre compte.";
        header('Location: '.$BASE.'supprimer_compte.php'); exit;
    }

    try {
        $pdo->beginTransaction();

        /* -----------------------
           SOFT DELETE : on décoche l'activité
           -----------------------
           - on garde toutes les lignes liées (commandes, adresses, paiements)
           - on purge les tokens pour empêcher reset password
           - on peut anonymiser plus tard si RGPD demandé (optionnel)
        */
        $upd = $pdo->prepare("
            UPDATE PERSONNE
               SET PER_COMPTE_ACTIF = 0,
                   reset_token_hash = NULL,
                   reset_token_expires_at = NULL
             WHERE PER_ID = :id
             LIMIT 1
        ");
        $upd->execute([':id' => $perId]);

        /* optionnel : si tu as une table CLIENT avec flag, on la met aussi inactive
           $pdo->prepare('UPDATE CLIENT SET CLI_ACTIF=0 WHERE PER_ID=:id')->execute([':id'=>$perId]);
        */

        $pdo->commit();

        /* on déconnecte l'utilisateur */
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        header('Location: '.$BASE.'goodbye.php'); exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = "Erreur lors de la désactivation : ".$e->getMessage();
        header('Location: '.$BASE.'info_perso.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Supprimer mon compte — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <style>
        .center-wrap{min-height:calc(100vh - 160px);display:grid;place-items:center;padding:24px;}
        .delete-card{max-width:420px;background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,.08);
            padding:28px;text-align:center}
        .delete-card .icon{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;margin:0 auto 12px;background:#ffe9ea}
        .delete-card h1{margin:.2rem 0 .4rem 0;font-size:1.6rem}
        .warn{color: rgba(97, 2, 2, 0.76);font-weight:700;font-size:.9rem;margin-bottom:10px}
        .muted{color:#666;font-size:.92rem;margin-bottom:16px}
        .field{margin:14px 0;text-align:left}
        .field label{display:block;margin-bottom:6px;font-weight:600}
        .field input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:12px}
        .actions{display:flex;gap:10px;margin-top:10px}
        .btn{flex:1;padding:12px 14px;border-radius:12px;border:none;cursor:pointer;font-weight:700}
        .btn.secondary{background:#f3f4f6}
        .btn.danger{background:#ae3664;color:#fff}
        .toast{position:fixed;left:50%;top:16px;transform:translateX(-50%) translateY(-20px);
            padding:12px 16px;border-radius:10px;background:#333;color:#fff;opacity:0;pointer-events:none;transition:.25s ease;z-index:9999}
        .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
        .toast.error{background:#b12020}
        .toast.success{background:#0a7}
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<?php if (!empty($_SESSION['toast_msg'])): ?>
    <div id="toast" class="toast <?= ($_SESSION['toast_type'] ?? 'success') === 'error' ? 'error' : 'success' ?>" role="status" aria-live="polite">
        <?= $_SESSION['toast_msg']; ?>
    </div>
    <?php unset($_SESSION['toast_msg'], $_SESSION['toast_type']); ?>
<?php endif; ?>

<main class="center-wrap" aria-label="Supprimer mon compte">
    <section class="delete-card" role="dialog" aria-labelledby="title" aria-describedby="desc">
        <div class="icon" aria-hidden="true">🗑️</div>
        <h1 id="title">Supprimer mon compte</h1>
        <p class="warn">Cette opération supprime votre compte.</p>
        <p id="desc" class="muted">
            On désactive votre compte et on garde l’historique (commandes, factures).
            Vous pourrez vous réinscrire plus tard avec le même e-mail.
        </p>

        <form method="post" onsubmit="return confirm('Confirmez la désactivation de votre compte ?');" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <div class="field">
                <label for="confirm">Retapez votre e-mail pour confirmer</label>
                <input id="confirm" name="confirm_username" autocomplete="off" required>
            </div>

            <div class="actions">
                <a class="btn secondary" href="<?= $BASE ?>info_perso.php">Annuler</a>
                <button class="btn danger" type="submit" name="delete_account" value="1">Supprimer</button>
            </div>
        </form>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
    (function(){
        const t = document.getElementById('toast');
        if (!t) return;
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => t.classList.remove('show'), 4000);
    })();
</script>
</body>
</html>
