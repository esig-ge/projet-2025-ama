<?php
session_start();
require_once __DIR__ . '/../database/config/stripe.php';
require_once __DIR__ . '/../database/config/connexionBDD.php';

// 1) commande courante
$comId = $_SESSION['com_id'] ?? null;
if (!$comId) {
    header('Location: commande.php');
    exit;
}

// 2) total depuis la BDD
$sql = "SELECT SUM(p.PRO_PRIX * cp.CP_QTE_COMMANDEE) AS total
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :com";
$stmt = $pdo->prepare($sql);
$stmt->execute(['com' => $comId]);
$total = (float) ($stmt->fetchColumn() ?: 0);

if ($total <= 0) {
    header('Location: commande.php');
    exit;
}

$amountCents = (int) round($total * 100);

// 3) URLs succès/annulation
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$successUrl = $base . '/success.php?session_id={CHECKOUT_SESSION_ID}';
$cancelUrl  = $base . '/commande.php';

// 4) créer la session Checkout
try {
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => 'chf',
                'product_data' => [
                    'name' => 'Commande DK Bloom #' . $comId
                ],
                'unit_amount' => $amountCents,
            ],
            'quantity' => 1,
        ]],
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'metadata'    => ['com_id' => (string) $comId],
    ]);

    header('Location: ' . filter_var($session->url, FILTER_SANITIZE_URL));
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Erreur Stripe</h1>";
    echo "<p>Impossible de créer la session de paiement.</p>";
    // ⚠️ À masquer en production
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
