<?php
// /site/pages/success.php
declare(strict_types=1);
session_start();

/* Base URL pour tes includes */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* Paramètres URL */
$comId     = (int)($_GET['com_id'] ?? 0);
$sessionId = $_GET['session_id'] ?? '';

/* (Optionnel) lecture du statut Stripe si clé dispo */
function get_key(string $which, string $localPath): ?string {
    $keys = is_file($localPath) ? require $localPath : [];
    return $keys[$which] ?? getenv($which) ?? ($_SERVER[$which] ?? ($_ENV[$which] ?? null));
}
$keysFile = __DIR__ . '/../config/keys.php';
$sk = get_key('STRIPE_SECRET_KEY', $keysFile);

$stripeStatus = null;
if ($sk && $sessionId) {
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $sk],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code < 400) {
        $resp = json_decode($raw, true);
        $stripeStatus = $resp['payment_status'] ?? null; // paid / unpaid / no_payment_required
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Paiement reçu</title>
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/commande.css">
    <style>
        .wrap{max-width:900px;margin:80px auto 40px;padding:0 16px}
        .card{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:24px}
        .btn-primary{display:inline-flex;gap:10px;background:#8b0000;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:700}
        .muted{color:#666}
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap">
    <div class="card">
        <h1>Merci pour votre commande !</h1>
        <p>Référence commande <strong>#<?= (int)$comId ?></strong>.</p>

        <?php if ($stripeStatus): ?>
            <p class="muted">Statut Stripe : <strong><?= htmlspecialchars($stripeStatus) ?></strong>.</p>
        <?php else: ?>
            <p class="muted">Nous confirmons votre paiement. Un email suivra dès que c’est validé.</p>
        <?php endif; ?>

        <p style="margin-top:16px">
            <a class="btn-primary" href="<?= $PAGE_BASE ?>interface_selection_produit.php">Continuer mes achats</a>
        </p>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
