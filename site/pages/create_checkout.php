<?php
// /site/pages/create_checkout.php
declare(strict_types=1);
session_start();

/* Réponse JSON par défaut ; si navigation classique, on fera une redirection plus bas */
header('Content-Type: application/json; charset=utf-8');

function want_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept, 'application/json') !== false) || strcasecmp($xrw,'XMLHttpRequest') === 0;
}

/* ==== Garde-fous ==== */
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
    }
    if (empty($_SESSION['per_id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit;
    }
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf_checkout'] ?? '')) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'csrf_invalid']); exit;
    }

    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sk = getenv('STRIPE_SECRET_KEY');                 // sk_test_...
    if (!$sk) throw new RuntimeException('STRIPE_SECRET_KEY manquante');

    $perId = (int)$_SESSION['per_id'];

    /* ==== 1) Récupère la commande "en préparation" ==== */
    $st = $pdo->prepare("SELECT COM_ID
                           FROM COMMANDE
                          WHERE PER_ID=:p AND COM_STATUT='en préparation'
                       ORDER BY COM_ID DESC LIMIT 1");
    $st->execute([':p'=>$perId]);
    $comId = (int)$st->fetchColumn();
    if ($comId <= 0) throw new RuntimeException('no_order');

    /* ==== 2) Construit les line_items à partir de TA BDD ==== */
    $lineItems = [];

    // Produits
    $q = $pdo->prepare("
        SELECT cp.CP_QTE_COMMANDEE AS qty, p.PRO_NOM AS name, p.PRO_PRIX AS price
          FROM COMMANDE_PRODUIT cp
          JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
         WHERE cp.COM_ID = :c
    ");
    $q->execute([':c'=>$comId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $amt = (int)round(((float)$r['price'])*100);
        if ($amt <= 0) continue; // Stripe refuse 0 CHF
        $lineItems[] = [
            'name' => (string)$r['name'],
            'unit_amount' => $amt,
            'quantity' => (int)$r['qty'],
        ];
    }

    // Suppléments
    $q = $pdo->prepare("
        SELECT cs.CS_QTE_COMMANDEE AS qty, s.SUP_NOM AS name, s.SUP_PRIX_UNITAIRE AS price
          FROM COMMANDE_SUPP cs
          JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
         WHERE cs.COM_ID = :c
    ");
    $q->execute([':c'=>$comId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $amt = (int)round(((float)$r['price'])*100);
        if ($amt <= 0) continue;
        $lineItems[] = [
            'name' => (string)$r['name'],
            'unit_amount' => $amt,
            'quantity' => (int)$r['qty'],
        ];
    }

    if (!$lineItems) throw new RuntimeException('empty_items');

    /* ==== 3) URLs absolues ==== */
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $success_url = "{$scheme}://{$host}{$BASE}success.php?com_id={$comId}&session_id={CHECKOUT_SESSION_ID}";
    $cancel_url  = "{$scheme}://{$host}{$BASE}adresse_paiement.php";

    /* ==== 4) Appel REST Stripe (cURL) ==== */
    // Stripe attend des clefs bracketées : line_items[0][price_data][...]
    $params = [
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
        'client_reference_id' => (string)$comId,
        // tu peux activer d'autres PM si dispo sur ton compte
        'payment_method_types[]' => 'card',
    ];

    $i = 0;
    foreach ($lineItems as $it) {
        $params["line_items[$i][quantity]"]                               = (string)$it['quantity'];
        $params["line_items[$i][price_data][currency]"]                   = 'chf';
        $params["line_items[$i][price_data][unit_amount]"]                = (string)$it['unit_amount'];
        $params["line_items[$i][price_data][product_data][name]"]         = $it['name'];
        $i++;
    }

    // Requête HTTP
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$sk],
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new RuntimeException('cURL: '.curl_error($ch));
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resp = json_decode($raw, true);
    if ($code >= 400 || !isset($resp['id'], $resp['url'])) {
        $err = $resp['error']['message'] ?? ('HTTP '.$code.' / '.$raw);
        throw new RuntimeException('stripe_error: '.$err);
    }

    // Marque la commande "en attente" avant paiement
    $pdo->prepare("UPDATE COMMANDE
                      SET COM_STATUT='en attente', STRIPE_SESSION_ID=:sid
                    WHERE COM_ID=:cid")
        ->execute([':sid'=>$resp['id'], ':cid'=>$comId]);

    // Navigation classique = redirection, sinon JSON pour le fetch
    if (!want_json()) {
        header('Location: '.$resp['url']); exit;
    }
    echo json_encode(['ok'=>true,'url'=>$resp['url']], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (!want_json()) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo 'Erreur: '.$e->getMessage();
        exit;
    }
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
