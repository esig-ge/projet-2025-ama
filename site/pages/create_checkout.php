<?php
// /site/pages/create_checkout.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../database/config/stripe.php';
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // URLs absolues (Stripe l'exige)
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

    // Garde-fous
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Méthode interdite.');
    if (($_POST['action'] ?? '') !== 'create_checkout') throw new RuntimeException('Action invalide.');
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide.');
    }
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié.');

    // COMMANDE en préparation
    $comId = (int)($_SESSION['current_com_id'] ?? 0);
    if ($comId <= 0) {
        $st = $pdo->prepare("
            SELECT COM_ID FROM COMMANDE
             WHERE PER_ID=:p AND COM_STATUT='en preparation'
             ORDER BY COM_ID DESC LIMIT 1
        ");
        $st->execute([':p'=>$perId]);
        $comId = (int)$st->fetchColumn();
    }
    if ($comId <= 0) throw new RuntimeException('Panier introuvable.');

    $chk = $pdo->prepare("
        SELECT 1 FROM COMMANDE
         WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1
    ");
    $chk->execute([':c'=>$comId, ':p'=>$perId]);
    if (!$chk->fetchColumn()) throw new RuntimeException('Commande non valide.');

    // Charger toutes les lignes
    $rows = [];

    // PRODUITS
    $sqlP = $pdo->prepare("
        SELECT p.PRO_NOM AS name, p.PRO_PRIX AS price, cp.CP_QTE_COMMANDEE AS qty
          FROM COMMANDE_PRODUIT cp
          JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
         WHERE cp.COM_ID = :com
    ");
    $sqlP->execute([':com'=>$comId]);
    $rows = array_merge($rows, $sqlP->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // SUPPLÉMENTS
    $sqlS = $pdo->prepare("
        SELECT s.SUP_NOM AS name, s.SUP_PRIX_UNITAIRE AS price, cs.CS_QTE_COMMANDEE AS qty
          FROM COMMANDE_SUPP cs
          JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
         WHERE cs.COM_ID = :com
    ");
    $sqlS->execute([':com'=>$comId]);
    $rows = array_merge($rows, $sqlS->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // EMBALLAGES (gratuits) — CE_QTE, prix 0
    $sqlE = $pdo->prepare("
        SELECT e.EMB_NOM AS name, 0 AS price, ce.CE_QTE AS qty
          FROM COMMANDE_EMBALLAGE ce
          JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
         WHERE ce.COM_ID = :com
    ");
    $sqlE->execute([':com'=>$comId]);
    $rows = array_merge($rows, $sqlE->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (!$rows) throw new RuntimeException('Votre panier est vide.');

    // Lire Livraison + TVA depuis COMMANDE + LIVRAISON
    $shippingCHF = 0.0;
    $tvaCHF      = 0.0;
    $q = $pdo->prepare("
        SELECT 
          COALESCE(l.LIV_MONTANT_FRAIS, 0) AS shipping,
          COALESCE(c.COM_TVA_MONTANT, 0)   AS tva
        FROM COMMANDE c
        LEFT JOIN LIVRAISON l ON l.LIV_ID = c.LIV_ID
        WHERE c.COM_ID = :c
        LIMIT 1
    ");
    $q->execute([':c'=>$comId]);
    if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $shippingCHF = (float)$r['shipping'];
        $tvaCHF      = (float)$r['tva'];
    }

    // Construire les line_items
    $currency  = 'chf';
    $lineItems = [];
    $totalCents = 0;

    // Afficher les gratuits (0.01 CHF) + coupon de compensation
    $SHOW_FREE = true;
    $freeCents = 0;
// MODE TTC : on force à ne PAS utiliser de tax_rate Stripe
    $taxRateId = null; // important : pas de TVA calculée côté Stripe

// ... et on n’ajoute PAS de ligne TVA manuellement
    $ADD_TVA_LINE = false;
    $taxRateId = getenv('STRIPE_TAX_RATE_ID') ?: null; // si tu utilises une tax rate Stripe

    foreach ($rows as $r) {
        $name = (string)($r['name'] ?? 'Article');
        $qty  = max(1, (int)($r['qty'] ?? 1));
        $prc  = (float)($r['price'] ?? 0.0);

        if ($prc <= 0) {
            if ($SHOW_FREE) {
                $unit = 1; // 0.01 CHF
                $freeCents += $unit * $qty;
            } else {
                continue; // ne pas envoyer à Stripe
            }
        } else {
            $unit = (int)round($prc * 100);
        }

        $item = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => $name],
            ],
        ];

        $lineItems[] = $item;
        $totalCents += $unit * $qty;
    }

    // Livraison
    if ($shippingCHF > 0) {
        $unit = (int)round($shippingCHF * 100);
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
    // MODE TTC : on n’ajoute PAS la TVA à Stripe (elle est déjà incluse dans les prix)
    $ADD_TVA_LINE = false;

    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');
    if ($totalCents <= 0 && $freeCents === 0) throw new RuntimeException('Montant total invalide (0).');

    // Méthodes de paiement
    $uiMethod = $_POST['pay_method'] ?? 'card';
    $allowed  = ['card', 'twint', 'revolut_pay'];
    $methods  = in_array($uiMethod, $allowed, true) ? [$uiMethod] : ['card'];

    // Payload Stripe (+ remise pour gratuits)
    $payload = [
        'mode'                 => 'payment',
        'payment_method_types' => $methods,
        'line_items'           => $lineItems,
        'success_url'          => $successUrl,
        'cancel_url'           => $cancelUrl,
        'client_reference_id'  => (string)$comId,
        'metadata'             => ['per_id'=>(string)$perId, 'com_id'=>(string)$comId],
    ];

    if ($SHOW_FREE && $freeCents > 0) {
        $coupon = \Stripe\Coupon::create([
            'currency'   => $currency,
            'amount_off' => $freeCents,
            'duration'   => 'once',
            'name'       => 'Remise articles offerts',
        ]);
        $payload['discounts'] = [['coupon' => $coupon->id]];
    }

    $session = \Stripe\Checkout\Session::create($payload);

    echo json_encode(['ok'=>true, 'url'=>$session->url], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
