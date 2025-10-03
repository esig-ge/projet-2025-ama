<?php
// /site/pages/create_checkout.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

try {
    /* 0) Stripe + DB */
    require_once __DIR__ . '/../database/config/stripe.php';
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* 1) URLs absolues */
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $origin = $scheme . '://' . $host;

    $dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    $PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    $SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);
    $SITE_BASE = preg_replace('#//+#', '/', $SITE_BASE);

    $BASE_URL   = rtrim($origin, '/') . $SITE_BASE;
    $successUrl = $BASE_URL . 'pages/success_paiement.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = $BASE_URL . 'pages/adresse_paiement.php?canceled=1';

    /* 2) Garde-fous */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Méthode interdite.');
    if (($_POST['action'] ?? '') !== 'create_checkout') throw new RuntimeException('Action invalide.');
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide.');
    }
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié.');

    /* 3) COMMANDE en préparation */
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

    $chk = $pdo->prepare("
        SELECT 1 FROM COMMANDE
        WHERE COM_ID = :c AND PER_ID = :p AND COM_STATUT = 'en preparation'
        LIMIT 1
    ");
    $chk->execute([':c' => $comId, ':p' => $perId]);
    if (!$chk->fetchColumn()) throw new RuntimeException('Commande non valide.');

    /* 4) Charger toutes les lignes (produits, suppléments, emballages) */
    $rows = [];

    // PRODUITS
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

    // SUPPLÉMENTS
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

    // EMBALLAGES (gratuits) — quantité = CE_QTE, prix 0
    $sqlE = $pdo->prepare("
        SELECT e.EMB_NOM AS name,
               0 AS price,
               ce.CE_QTE AS qty
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :com
    ");
    $sqlE->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlE->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (!$rows) throw new RuntimeException('Votre panier est vide.');

    /* 5) Livraison + TVA depuis COMMANDE (ou session fallback) */
    $shippingCHF = 0.0;
    $tvaCHF      = 0.0;
    try {
        $st = $pdo->prepare("
            SELECT 
              COALESCE(COM_FRAIS_LIVRAISON, 0) AS shipping,
              COALESCE(COM_TVA_MONTANT, 0)     AS tva
            FROM COMMANDE
            WHERE COM_ID = :c
            LIMIT 1
        ");
        $st->execute([':c' => $comId]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $shippingCHF = (float)$r['shipping'];
            $tvaCHF      = (float)$r['tva'];
        }
    } catch (Throwable $ignored) {
        $shippingCHF = (float)($_SESSION['checkout_shipping_chf'] ?? 0);
        $tvaCHF      = (float)($_SESSION['checkout_tva_chf'] ?? 0);
    }

    /* 6) Construction des line_items (avec affichage des gratuits) */
    $currency   = 'chf';
    $lineItems  = [];
    $totalCents = 0;

    // on force l’affichage des gratuits (0.01 CHF) + coupon de compensation
    $DISPLAY_FREE_ITEMS_IN_CHECKOUT = true;
    $freeItemsCents = 0; // cumul des centimes à rembourser via coupon

    // TVA par tax_rate Stripe (si dispo), sinon ligne TVA séparée
    $taxRateId = getenv('STRIPE_TAX_RATE_ID') ?: null;

    foreach ($rows as $r) {
        $name = (string)($r['name'] ?? 'Article');
        $qty  = max(1, (int)($r['qty']  ?? 1));
        $prc  = (float)($r['price'] ?? 0.0);

        if ($prc <= 0) {
            if ($DISPLAY_FREE_ITEMS_IN_CHECKOUT) {
                $unit = 1; // 0.01 CHF
                $freeItemsCents += $unit * $qty;
            } else {
                // ne pas envoyer à Stripe, mais ils resteront sur ta facture côté serveur
                continue;
            }
        } else {
            $unit = (int) round($prc * 100);
        }

        $item = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => $name],
            ],
        ];
        if ($taxRateId) $item['tax_rates'] = [$taxRateId];

        $lineItems[] = $item;
        $totalCents += $unit * $qty;
    }

    // Livraison
    if ($shippingCHF > 0) {
        $unit = (int) round($shippingCHF * 100);
        $item = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => 'Livraison'],
            ],
        ];
        if ($taxRateId) $item['tax_rates'] = [$taxRateId];
        $lineItems[] = $item;
        $totalCents += $unit;
    }

    // TVA (si pas de tax_rate Stripe)
    if (!$taxRateId && $tvaCHF > 0) {
        $tvaUnit = (int) round($tvaCHF * 100);
        $lineItems[] = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $tvaUnit,
                'product_data' => ['name' => 'TVA'],
            ],
        ];
        $totalCents += $tvaUnit;
    }

    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');
    if ($totalCents <= 0 && $freeItemsCents === 0) {
        throw new RuntimeException('Montant total invalide (0).');
    }

    /* 7) Méthodes de paiement */
    $uiMethod = $_POST['pay_method'] ?? 'card';
    $allowed  = ['card', 'twint', 'revolut_pay'];
    $methods  = in_array($uiMethod, $allowed, true) ? [$uiMethod] : ['card'];

    /* 8) Prépare payload + remise pour gratuits */
    $payload = [
        'mode'                 => 'payment',
        'payment_method_types' => $methods,
        'line_items'           => $lineItems,
        'success_url'          => $successUrl,
        'cancel_url'           => $cancelUrl,
        'client_reference_id'  => (string)$comId,
        'metadata'             => [
            'per_id' => (string)$perId,
            'com_id' => (string)$comId,
        ],
    ];

    if ($DISPLAY_FREE_ITEMS_IN_CHECKOUT && $freeItemsCents > 0) {
        // coupon unique qui compense tous les centimes des gratuits
        $coupon = \Stripe\Coupon::create([
            'currency'   => $currency,
            'amount_off' => $freeItemsCents,
            'duration'   => 'once',
            'name'       => 'Remise articles offerts',
        ]);
        $payload['discounts'] = [['coupon' => $coupon->id]];
    }

    /* 9) Créer la session */
    $session = \Stripe\Checkout\Session::create($payload);

    echo json_encode(['ok' => true, 'url' => $session->url], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ]);
}
