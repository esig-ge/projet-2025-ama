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

    /* ---------- URLs absolues (Stripe) ---------- */
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $origin  = $scheme . '://' . $host;
    $dir     = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    $PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    $SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);
    $SITE_BASE = preg_replace('#//+#', '/', $SITE_BASE);
    $BASE_URL  = rtrim($origin, '/') . $SITE_BASE;
    $successUrl = $BASE_URL . 'pages/success_paiement.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = $BASE_URL . 'pages/adresse_paiement.php?canceled=1';

    /* ---------- Gardes ---------- */
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Méthode interdite.');
    }
    if (($_POST['action'] ?? '') !== 'create_checkout') {
        throw new RuntimeException('Action invalide.');
    }
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide.');
    }
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié.');

    /* ---------- Récup commande "en preparation" ---------- */
    $comId = (int)($_SESSION['current_com_id'] ?? 0);
    if ($comId <= 0) {
        $st = $pdo->prepare("
            SELECT COM_ID FROM COMMANDE
            WHERE PER_ID=:p AND COM_STATUT='en preparation'
            ORDER BY COM_ID DESC LIMIT 1
        ");
        $st->execute([':p' => $perId]);
        $comId = (int)$st->fetchColumn();
    }
    if ($comId <= 0) throw new RuntimeException('Panier introuvable.');

    $chk = $pdo->prepare("
        SELECT 1 FROM COMMANDE
        WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1
    ");
    $chk->execute([':c' => $comId, ':p' => $perId]);
    if (!$chk->fetchColumn()) throw new RuntimeException('Commande non valide.');

    /* ---------- Charger lignes panier ---------- */
    $rows = [];

    // PRODUITS
    $sqlP = $pdo->prepare("
        SELECT 'produit' AS kind, p.PRO_NOM AS name, p.PRO_PRIX AS price,
               cp.CP_QTE_COMMANDEE AS qty, cp.CP_TYPE_PRODUIT AS subtype
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :com
    ");
    $sqlP->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlP->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // SUPPLÉMENTS
    $sqlS = $pdo->prepare("
        SELECT 'supplement' AS kind, s.SUP_NOM AS name, s.SUP_PRIX_UNITAIRE AS price,
               cs.CS_QTE_COMMANDEE AS qty, 'supplement' AS subtype
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :com
    ");
    $sqlS->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlS->fetchAll(PDO::FETCH_ASSOC) ?: []);

    // EMBALLAGES (gratuits)
    $sqlE = $pdo->prepare("
        SELECT 'emballage' AS kind, e.EMB_NOM AS name, 0 AS price,
               ce.CE_QTE AS qty, 'emballage' AS subtype
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :com
    ");
    $sqlE->execute([':com' => $comId]);
    $rows = array_merge($rows, $sqlE->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (!$rows) throw new RuntimeException('Votre panier est vide.');

    /* ---------- Livraison + TVA stockées sur COMMANDE ---------- */
    $shippingCHF = 0.0;
    $tvaCHF = 0.0;
    $q = $pdo->prepare("
        SELECT COALESCE(l.LIV_MONTANT_FRAIS,0) AS shipping,
               COALESCE(c.COM_TVA_MONTANT,0)   AS tva
        FROM COMMANDE c
        LEFT JOIN LIVRAISON l ON l.LIV_ID = c.LIV_ID
        WHERE c.COM_ID = :c
        LIMIT 1
    ");
    $q->execute([':c' => $comId]);
    if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $shippingCHF = (float)$r['shipping'];
        $tvaCHF      = (float)$r['tva'];
    }

    // (fallback éventuel depuis la session, inchangé)
    if ($shippingCHF <= 0 && isset($_SESSION['ship_mode'])) {
        $ship_mode = $_SESSION['ship_mode'] ?? 'standard';
        $ship_zone = $_SESSION['ship_zone'] ?? 'geneve';
        $shippingCHF = ($ship_mode === 'standard')
            ? (($ship_zone === 'suisse') ? 10.00 : 5.00)
            : 0.00;
    }

    /* ---------- Construction des line_items (TTC) ---------- */
    $currency   = 'chf';
    $lineItems  = [];
    $totalCents = 0;

    $SHOW_FREE = true;
    $freeCents = 0;

    foreach ($rows as $r) {
        $name = (string)($r['name'] ?? 'Article');
        $qty  = max(1, (int)($r['qty'] ?? 1));
        $prc  = (float)($r['price'] ?? 0.0);

        if ($prc <= 0) {
            if ($SHOW_FREE) {
                $unit = 1; // 0.01 CHF pour afficher l'article gratuit
                $freeCents += $unit * $qty;
            } else {
                continue;
            }
        } else {
            $unit = (int)round($prc * 100);
        }

        $lineItems[] = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => $name],
            ],
        ];
        $totalCents += $unit * $qty;
    }

    // Livraison
    if ($shippingCHF > 0) {
        $unit = (int)round($shippingCHF * 100);
        $lineItems[] = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => ($shippingCHF >= 10.00 ? 'Livraison Suisse (48h)' : 'Livraison Genève (48h)')],
            ],
        ];
        $totalCents += $unit;
    }

    // TVA (ligne dédiée si tu veux qu’elle apparaisse dans Stripe)
    if ($tvaCHF > 0) {
        $taxUnit = (int)round($tvaCHF * 100);
        $lineItems[] = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $taxUnit,
                'product_data' => ['name' => 'TVA'],
            ],
        ];
        $totalCents += $taxUnit;
    }

    // Peti arrondi si souhaité
    $applyCashRounding = true;
    if ($applyCashRounding) {
        $lineItems[] = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => 1,
                'product_data' => ['name' => 'Arrondi espèces (0.05)'],
            ],
        ];
        $totalCents += 1;
    }

    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');
    if ($totalCents <= 0 && $freeCents === 0) throw new RuntimeException('Montant total invalide (0).');

    // Méthodes de paiement
    $uiMethod = $_POST['pay_method'] ?? 'card';
    $allowed  = ['card', 'twint', 'revolut_pay'];
    $methods  = in_array($uiMethod, $allowed, true) ? [$uiMethod] : ['card'];

    // Créer la Checkout Session
    $payload = [
        'mode'                  => 'payment',
        'payment_method_types'  => $methods,
        'line_items'            => $lineItems,
        'success_url'           => $successUrl,
        'cancel_url'            => $cancelUrl,
        'client_reference_id'   => (string)$comId,
        'metadata'              => ['per_id' => (string)$perId, 'com_id' => (string)$comId],
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

    $expectedChf = $totalCents / 100.0;

    /* ---------- PRÉ-CRÉER le paiement + lier à la commande ---------- */
    $pdo->beginTransaction();

    // 1) Créer la ligne PAIEMENT "en_attente"
    $ins = $pdo->prepare("
        INSERT INTO PAIEMENT
            (PER_ID, PAI_MODE, PAI_MONTANT, PAI_MONNAIE, PAI_STATUT, PAI_DATE, PAI_LAST_EVENT_TYPE)
        VALUES
            (:per, :mode, :montant, :monnaie, :statut, NOW(), :evt)
    ");
    $ins->execute([
        ':per'     => $perId,
        ':mode'    => $methods[0],     // 'card' | 'twint' | 'revolut_pay'
        ':montant' => $expectedChf,    // total TTC attendu
        ':monnaie' => strtoupper($currency), // 'CHF'
        ':statut'  => 'en_attente',
        ':evt'     => 'checkout.session.created',
    ]);
    $paiId = (int)$pdo->lastInsertId();

    // 2) Lier PAIEMENT à la COMMANDE et stocker la session Stripe
    $upd = $pdo->prepare("
        UPDATE COMMANDE
           SET PAI_ID            = :pai,
               STRIPE_SESSION_ID = :sid,
               COM_STATUT        = 'en attente de paiement',
               TOTAL_PAYER_CHF   = :expected
         WHERE COM_ID = :cid AND PER_ID = :pid
         LIMIT 1
    ");
    $upd->execute([
        ':pai'      => $paiId,
        ':sid'      => (string)$session->id,
        ':expected' => $expectedChf,
        ':cid'      => $comId,
        ':pid'      => $perId,
    ]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'url' => $session->url], JSON_UNESCAPED_SLASHES);
    exit;

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
