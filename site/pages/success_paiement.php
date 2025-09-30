<?php
// /site/pages/success_paiement.php
declare(strict_types=1);
session_start();

/* ========== Bootstrap Stripe & DB ========== */
require_once __DIR__ . '/../database/config/stripe.php';
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sessionId = $_GET['session_id'] ?? '';
if (!$sessionId) {
    http_response_code(400);
    exit('Session Stripe manquante.');
}

try {
    // On récupère la session + quelques infos utiles
    $session = \Stripe\Checkout\Session::retrieve([
        'id'     => $sessionId,
        'expand' => ['payment_intent', 'line_items.data.price.product']
    ]);

    $paid     = ($session->payment_status ?? '') === 'paid';
    $amount   = (int)($session->amount_total ?? 0); // en centimes
    $currency = strtoupper((string)($session->currency ?? 'CHF'));
    $orderId  = (int)($session->client_reference_id ?? 0);
    $email    = (string)($session->customer_details->email ?? '');

} catch (\Throwable $e) {
    http_response_code(500);
    exit('Erreur Stripe: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

/* ===== DÉTACHER LE PANIER PAYÉ + POSER UN TOAST POUR LA PROCHAINE PAGE =====
   - Si la session pointe encore sur la commande payée, on la libère.
   - On stocke un "flash toast" dans la session, à afficher sur la page panier/catalogue.
*/
if ($paid) {
    if ($orderId && isset($_SESSION['current_com_id']) && (int)$_SESSION['current_com_id'] === $orderId) {
        unset($_SESSION['current_com_id']);
    }
    // Flash toast générique (adapte le lecteur de toast de ton layout si besoin)
    $_SESSION['toast'] = [
        'type'    => 'success',       // success | info | warning | error
        'title'   => 'Paiement confirmé',
        'message' => '🎉 Paiement confirmé — votre panier a été vidé.',
        'ttl'     => time() + 60       // optionnel : validité de 60s
    ];
}

/* ===== Présentation simple ===== */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>DK Bloom — Paiement réussi</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../css/style_header_footer.css">
    <link rel="stylesheet" href="../css/style_connexion_inscription.css">
    <style>
        body{background:#faf7f7;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
        .wrap{max-width:900px;margin:40px auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.07);padding:28px}
        .ok{display:inline-block;padding:6px 10px;border-radius:999px;background:#e9f9ee;color:#17643b;font-weight:600;border:1px solid #b7ecc8}
        .ko{display:inline-block;padding:6px 10px;border-radius:999px;background:#fff4f4;color:#8a1b2e;font-weight:600;border:1px solid #f2c9cf}
        .items{margin-top:18px;border-top:1px solid #eee;padding-top:12px}
        .li{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #eee}
        .tot{display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;margin-top:8px}
        .cta{margin-top:22px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{appearance:none;border:0;border-radius:10px;padding:10px 14px;background:#5C0012;color:#fff;font-weight:700;cursor:pointer;text-decoration:none}
        .btn.sec{background:#eee;color:#333}
        .note{margin-top:16px;color:#666;font-size:.95rem}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Paiement <?= $paid ? '<span class="ok">confirmé</span>' : '<span class="ko">en attente</span>' ?></h1>
    <p>Merci pour votre commande <?= $email ? '— un reçu Stripe sera envoyé à <b>'.htmlspecialchars($email).'</b>.' : '.' ?></p>
    <?php if ($orderId): ?>
        <p><b>Commande #<?= htmlspecialchars((string)$orderId) ?></b></p>
    <?php endif; ?>

    <div class="items">
        <?php foreach (($session->line_items->data ?? []) as $it): ?>
            <div class="li">
                <div><?= htmlspecialchars($it->description ?? 'Article') ?> × <?= (int)$it->quantity ?></div>
                <div><?= number_format(((int)($it->amount_total ?? 0))/100, 2, '.', '\'') . ' ' . $currency ?></div>
            </div>
        <?php endforeach; ?>
        <div class="tot">
            <div>Total</div>
            <div><?= number_format($amount/100, 2, '.', '\'').' '.$currency ?></div>
        </div>
    </div>

    <div class="cta">
        <a class="btn" href="../pages/commande.php">Voir mes commandes</a>
        <a class="btn sec" href="../index.php">Continuer mes achats</a>
        <a class="btn sec" href="../pages/adresse_paiement.php">Revenir au paiement</a>
    </div>

    <p class="note">
        Note : le statut officiel de la commande est mis à jour par notre <i>webhook</i> Stripe.
        Si vous ne voyez pas encore “payé” dans votre espace, cela se mettra à jour d’ici quelques secondes.
        <?php if ($paid): ?>
            <br>Info : votre panier actif a été réinitialisé pour un nouvel achat.
        <?php endif; ?>
    </p>
</div>
</body>
</html>
