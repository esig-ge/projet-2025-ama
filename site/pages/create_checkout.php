<?php
// /site/pages/create_checkout.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

try {
    /* 0) Stripe + DB */
    // ==> IMPORTANT : ce fichier doit inclure /app/libs/stripe/init.php
    // via: require_once __DIR__ . '/../../../app/libs/stripe/init.php';
    // cf. /site/database/config/stripe.php
    require_once __DIR__ . '/../database/config/stripe.php';

    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* 1) URLs de redirection */
    // 1) Construire des URLs ABSOLUES pour Stripe
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $origin = $scheme . '://' . $host;

// /site/pages/… -> /site/
    $dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');   // ex: /site/pages
    $PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';   // ex: /site/pages/
    $SITE_BASE = preg_replace('#/pages/$#', '/', $PAGE_BASE);         // ex: /site/

// Base absolue (avec domaine)
    $BASE_URL  = rtrim($origin, '/') . $SITE_BASE;                    // ex: https://esig-sandbox.ch/2526_grep/t25_6_v21/site/

    $successUrl = $BASE_URL . 'pages/success.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = $BASE_URL . 'pages/adresse_paiement.php?canceled=1';


    /* 2) Vérifs requête */
    if (($_POST['action'] ?? '') !== 'create_checkout') {
        throw new RuntimeException('Action invalide.');
    }
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide.');
    }
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié.');

    // COMMANDE courante
    $comId = (int)($_SESSION['current_com_id'] ?? 0);
    if ($comId <= 0) {
        $st = $pdo->prepare("
            SELECT COM_ID
            FROM COMMANDE
            WHERE PER_ID = :p AND COM_STATUT = 'en preparation'
            ORDER BY COM_ID DESC
            LIMIT 1
        ");
        $st->execute([':p' => $perId]);
        $comId = (int)$st->fetchColumn();
    }
    if ($comId <= 0) throw new RuntimeException('Panier introuvable.');

    // Vérifier appartenance + statut
    $chk = $pdo->prepare("
        SELECT 1
        FROM COMMANDE
        WHERE COM_ID = :c AND PER_ID = :p AND COM_STATUT = 'en preparation'
        LIMIT 1
    ");
    $chk->execute([':c' => $comId, ':p' => $perId]);
    if (!$chk->fetchColumn()) throw new RuntimeException('Commande non valide.');

    /* 3) Récup des lignes facturables */
    $rows = [];

    // Produits
    $sqlP = $pdo->prepare("
        SELECT p.PRO_NOM AS name,
               p.PRO_PRIX AS price,
               cp.CP_QTE_COMMANDEE AS qty
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :com
    ");
    $sqlP->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlP->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // Suppléments
    $sqlS = $pdo->prepare("
        SELECT s.SUP_NOM AS name,
               s.SUP_PRIX_UNITAIRE AS price,
               cs.CS_QTE_COMMANDEE AS qty
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :com
    ");
    $sqlS->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlS->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (!$rows) throw new RuntimeException('Votre panier est vide.');

    /* 4) Construire les line_items Stripe (ignorer prix <= 0) */
    $currency  = 'chf';
    $lineItems = [];
    foreach ($rows as $r) {
        $name = (string)($r['name'] ?? 'Article');
        $qty  = max(1, (int)($r['qty']  ?? 1));
        $prc  = (float)($r['price'] ?? 0.0);

        if ($prc <= 0) continue; // pas d’emballages gratuits

        $lineItems[] = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => (int) round($prc * 100),
                'product_data' => ['name' => $name],
            ],
        ];
    }
    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');

    /* 5) Créer la Session Checkout */
    $session = \Stripe\Checkout\Session::create([
        'mode'        => 'payment',
        'line_items'  => $lineItems,
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'metadata'    => [
            'per_id' => (string)$perId,
            'com_id' => (string)$comId,
        ],
        'client_reference_id'       => (string)$comId,
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    echo json_encode(['ok' => true, 'url' => $session->url], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
