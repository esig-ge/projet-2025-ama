<?php
// site/api/create-intent.php
session_start();
header('Content-Type: application/json');

try {
    // ENV + STRIPE
    require_once __DIR__ . '/../database/config/env.php';
    loadProjectEnv();
    require_once __DIR__ . '/../database/config/stripe.php';

    // (Optionnel) Connexion BDD si tu dois recalculer le panier côté serveur
    // require_once __DIR__ . '/../database/config/connexionBDD.php';

    // -------- 1) Calcul du montant ----------
    // Idéal: recalculer le total côté serveur (sécurité) à partir de la COMMANDE courante ou du panier en session.
    // Pour démo on lit un total reçu (à éviter en prod) :
    $amount_chf = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    if ($amount_chf <= 0) { throw new Exception('Montant invalide'); }

    // Stripe veut des "cents"
    $amount_in_rappen = (int) round($amount_chf * 100); // CHF → rappen

    // -------- 2) Créer PaymentIntent ----------
    // Automatique: laisse Stripe gérer la méthode de paiement
    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amount_in_rappen,
        'currency' => 'chf',
        'automatic_payment_methods' => ['enabled' => true],
        // 'metadata' => ['com_id' => $_SESSION['com_id'] ?? 'dev'],
    ]);

    // -------- 3) Réponse JSON ----------
    $pk = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
    echo json_encode([
        'ok' => true,
        'publishableKey' => $pk,
        'clientSecret' => $intent->client_secret,
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
