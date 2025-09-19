<?php
// /site/pages/checkout.php
session_start();
header('Content-Type: application/json');

try {
    /* ===== 0) ENV + STRIPE + DB ===== */
    require_once __DIR__ . '/../database/config/env.php';
    loadProjectEnv();
    require_once __DIR__ . '/../database/config/stripe.php';        // setApiKey(...)
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';// $pdo

    /* ===== 1) URLs base (depuis /site/pages/) ===== */
    $dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\'); // /.../site/pages
    $PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';                       // /.../site/pages/
    $SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);                               // /.../site/

    $successUrl = $SITE_BASE . 'pages/success.php?cs={CHECKOUT_SESSION_ID}';
    $cancelUrl  = $SITE_BASE . 'pages/adresse_paiement.php?canceled=1';

    /* ===== 2) Vérif action ===== */
    if (($_POST['action'] ?? '') !== 'create_checkout') {
        throw new RuntimeException('Action invalide.');
    }

    /* ===== 3) Lire le panier/commande depuis la BDD ===== */
    $comId = (int)($_SESSION['com_id'] ?? 0);
    if ($comId <= 0) throw new RuntimeException('Panier introuvable.');
    // Optionnel: vérifier l’utilisateur
    $perId = (int)($_SESSION['per_id'] ?? 0);

    // PRODUITS
    $sqlP = $pdo->prepare("
        SELECT p.PRO_NOM AS name,
               p.PRO_PRIX AS price,         -- CHF
               cp.CP_QTE_COMMANDEE AS qty
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :com
    ");
    $sqlP->execute(['com' => $comId]);
    $rows = $sqlP->fetchAll(PDO::FETCH_ASSOC);

    // EMBALLAGES (prix 0.00)
    $sqlE = $pdo->prepare("
        SELECT e.EMB_NOM AS name,
               0.00      AS price,
               ce.CE_QTE AS qty
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :com
    ");
    $sqlE->execute(['com' => $comId]);
    $rows = array_merge($rows, $sqlE->fetchAll(PDO::FETCH_ASSOC));

    // SUPPLÉMENTS
    $sqlS = $pdo->prepare("
        SELECT s.SUP_NOM AS name,
               s.SUP_PRIX_UNITAIRE AS price,
               cs.CS_QTE_COMMANDEE AS qty
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :com
    ");
    $sqlS->execute(['com' => $comId]);
    $rows = array_merge($rows, $sqlS->fetchAll(PDO::FETCH_ASSOC));

    if (!$rows) throw new RuntimeException('Votre panier est vide.');

    /* ===== 4) Construit line_items Stripe ===== */
    $lineItems = [];
    $currency  = 'chf';
    $subtotal  = 0.0;

    foreach ($rows as $r) {
        $name = $r['name'] ?: 'Article';
        $qty  = max(1, (int)$r['qty']);
        $prc  = (float)$r['price'];            // CHF
        $subtotal += $qty * $prc;

        $lineItems[] = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => (int) round($prc * 100), // centimes
                'product_data' => ['name' => $name],
            ],
        ];
    }
    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');

    /* ===== 5) Crée la Session Checkout ===== */
    $session = \Stripe\Checkout\Session::create([
        'mode'        => 'payment',
        'line_items'  => $lineItems,
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'metadata'    => [
            'per_id' => (string)$perId,
            'com_id' => (string)$comId,
        ],
        // laissez Stripe choisir les moyens dispos (TWINT, carte…) selon votre compte
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    echo json_encode(['ok' => true, 'url' => $session->url], JSON_UNESCAPED_SLASHES);
    exit;

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
