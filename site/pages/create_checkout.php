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

    /* ---------- Helpers ---------- */
    $to_cents = static function ($chf): int {
        return (int) round(((float)$chf) * 100); // toujours ENTIER
    };
    $is_reduced = static function (array $row): bool {
        $kind = strtolower((string)($row['kind'] ?? ''));
        $sub  = strtolower((string)($row['subtype'] ?? ''));
        return $kind === 'produit' && in_array($sub, ['fleur','bouquet'], true);
    };

    /* ---------- URLs absolues (Stripe) ---------- */
    $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $origin     = $scheme . '://' . $host;
    $dir        = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    $PAGE_BASE  = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    $SITE_BASE  = preg_replace('#pages/$#', '', $PAGE_BASE);
    $SITE_BASE  = preg_replace('#//+#', '/', $SITE_BASE);
    $BASE_URL   = rtrim($origin, '/') . $SITE_BASE;
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

    // fallback session
    if ($shippingCHF <= 0 && isset($_SESSION['ship_mode'])) {
        $ship_mode = $_SESSION['ship_mode'] ?? 'standard';
        $ship_zone = $_SESSION['ship_zone'] ?? 'geneve';
        $shippingCHF = ($ship_mode === 'standard')
            ? (($ship_zone === 'suisse') ? 10.00 : 5.00)
            : 0.00;
    }

    /* ---------- Pré-calculs en CENTIMES ---------- */
    $currency    = 'chf';
    $lineItems   = [];
    $totalCents  = 0;
    $freeCents   = 0;

    // Sommes HT par taux
    $baseReducedC = 0;
    $baseNormalC  = 0;

    foreach ($rows as $r) {
        $qty   = max(1, (int)($r['qty'] ?? 1));
        $price = (float)($r['price'] ?? 0.0);
        $unitC = $price > 0 ? $to_cents($price) : 0;
        if ($unitC <= 0) continue; // gratuits: on les traitera à part pour l'affichage éventuel

        $ltC = $unitC * $qty;
        if ($is_reduced($r)) $baseReducedC += $ltC; else $baseNormalC += $ltC;
    }

    // Ventilation livraison en centimes
    $shippingC = $to_cents($shippingCHF);
    $goodsC    = $baseReducedC + $baseNormalC;
    $shipRedC  = 0;
    $shipNorC  = 0;
    if ($shippingC > 0 && $goodsC > 0) {
        $shipRedC = (int) floor($shippingC * ($baseReducedC / $goodsC)); // arrondi bas
        $shipNorC = $shippingC - $shipRedC;                              // reste → somme exacte
    }

    /* ---------- Tax rates Stripe (optionnel mais recommandé) ---------- */
    $TAX_REDUCED_ID = defined('STRIPE_TAX_REDUCED_ID') ? STRIPE_TAX_REDUCED_ID : null; // 2.6 %
    $TAX_STANDARD_ID= defined('STRIPE_TAX_STANDARD_ID')? STRIPE_TAX_STANDARD_ID: null; // 8.1 %
    $canUseStripeTax= $TAX_REDUCED_ID && $TAX_STANDARD_ID;

    /* ---------- Lignes articles (payants) ---------- */
    foreach ($rows as $r) {
        $name  = (string)($r['name'] ?? 'Article');
        $qty   = max(1, (int)($r['qty'] ?? 1));
        $price = (float)($r['price'] ?? 0.0);

        if ($price <= 0) {
            // articles gratuits → on les affiche visuellement à 0.01 CHF puis on compense par coupon total
            $freeCents += 1 * $qty;
            $unit = 1;
        } else {
            $unit = $to_cents($price);
        }

        $item = [
            'quantity'   => $qty,
            'price_data' => [
                'currency'     => $currency,
                'unit_amount'  => $unit,
                'product_data' => ['name' => $name],
            ],
        ];

        if ($price > 0 && $canUseStripeTax) {
            $item['tax_rates'] = [$is_reduced($r) ? $TAX_REDUCED_ID : $TAX_STANDARD_ID];
        }

        $lineItems[] = $item;
        $totalCents += $unit * $qty;
    }

    /* ---------- Livraison scindée ---------- */
    if ($shippingC > 0) {
        if ($canUseStripeTax) {
            if ($shipRedC > 0) {
                $lineItems[] = [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => $shipRedC,
                        'product_data' => ['name' => 'Livraison (part 2.6 %)'],
                    ],
                    'tax_rates'   => [$TAX_REDUCED_ID],
                ];
                $totalCents += $shipRedC;
            }
            if ($shipNorC > 0) {
                $lineItems[] = [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => $shipNorC,
                        'product_data' => ['name' => 'Livraison (part 8.1 %)'],
                    ],
                    'tax_rates'   => [$TAX_STANDARD_ID],
                ];
                $totalCents += $shipNorC;
            }
        } else {
            // Pas de tax rates disponibles → une seule ligne livraison
            $lineItems[] = [
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => $shippingC,
                    'product_data' => ['name' => ($shippingCHF >= 10.00 ? 'Livraison Suisse (48h)' : 'Livraison Genève (48h)')],
                ],
            ];
            $totalCents += $shippingC;
        }
    }

    /* ---------- Fallback TVA unique si pas de tax rates ---------- */
    if (!$canUseStripeTax && $tvaCHF > 0) {
        $tvaC = $to_cents($tvaCHF);
        if ($tvaC > 0) {
            $lineItems[] = [
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => $tvaC,
                    'product_data' => ['name' => 'TVA (calculée)'],
                ],
            ];
            $totalCents += $tvaC;
        }
    }

    /* ---------- Coupon exact pour compenser les gratuits ---------- */
    if ($freeCents > 0) {
        // On a ajouté +1 cent par gratuit → on compense EXACTEMENT la somme
        $coupon = \Stripe\Coupon::create([
            'currency'   => $currency,
            'amount_off' => $freeCents,
            'duration'   => 'once',
            'name'       => 'Remise articles offerts',
        ]);
        $discounts = [['coupon' => $coupon->id]];
    } else {
        $discounts = null;
    }

    if (!$lineItems) throw new RuntimeException('Aucun article facturable.');
    if ($totalCents <= 0) throw new RuntimeException('Montant total invalide (0).');

    // Méthodes de paiement
    $uiMethod = $_POST['pay_method'] ?? 'card';
    $allowed  = ['card', 'twint', 'revolut_pay'];
    $methods  = in_array($uiMethod, $allowed, true) ? [$uiMethod] : ['card'];

    /* ---------- Créer la Checkout Session ---------- */
    $payload = [
        'mode'                  => 'payment',
        'payment_method_types'  => $methods,
        'line_items'            => $lineItems,
        'success_url'           => $successUrl,
        'cancel_url'            => $cancelUrl,
        'allow_promotion_codes' => false,
        'client_reference_id'   => (string)$comId,
        'metadata'              => ['per_id' => (string)$perId, 'com_id' => (string)$comId],
    ];
    if ($discounts) { $payload['discounts'] = $discounts; }

    $session = \Stripe\Checkout\Session::create($payload);

    $expectedChf = $totalCents / 100.0;

    /* ---------- PRÉ-CRÉER le paiement + lier à la commande ---------- */
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO PAIEMENT
            (PER_ID, PAI_MODE, PAI_MONTANT, PAI_MONNAIE, PAI_STATUT, PAI_DATE, PAI_LAST_EVENT_TYPE)
        VALUES
            (:per, :mode, :montant, :monnaie, :statut, NOW(), :evt)
    ");
    $ins->execute([
        ':per'     => $perId,
        ':mode'    => $methods[0],
        ':montant' => $expectedChf,
        ':monnaie' => strtoupper($currency),
        ':statut'  => 'en_attente',
        ':evt'     => 'checkout.session.created',
    ]);
    $paiId = (int)$pdo->lastInsertId();

    $upd = $pdo->prepare("
       UPDATE COMMANDE
   SET PAI_ID            = :pai,
       STRIPE_SESSION_ID = :sid,
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
