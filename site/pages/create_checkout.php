<?php
// /site/pages/create_checkout.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* ===== A) Base URL identique à commande.php ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';  // ex: /site/pages/

/* ===== B) Garde-fous ===== */
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
    }
    if (empty($_SESSION['per_id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit;
    }
    // CSRF envoyé par adresse_paiement.php
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf_checkout'] ?? '')) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'csrf_invalid']); exit;
    }

    /* ===== C) BDD + Stripe SDK ===== */
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';

    // vendor/autoload.php (même racine que /site/)
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('vendor/autoload.php introuvable (composer install manquant ?)');
    }
    require $autoload;

    $sk = getenv('STRIPE_SECRET_KEY');
    if (!$sk) throw new RuntimeException('STRIPE_SECRET_KEY manquante (.env)');

    \Stripe\Stripe::setApiKey($sk);

    $perId = (int)$_SESSION['per_id'];

    /* ===== D) Récupère la commande "en préparation" ===== */
    $st = $pdo->prepare("SELECT COM_ID
                         FROM COMMANDE
                         WHERE PER_ID = :p AND COM_STATUT = 'en préparation'
                         ORDER BY COM_ID DESC
                         LIMIT 1");
    $st->execute([':p'=>$perId]);
    $comId = (int)$st->fetchColumn();
    if ($comId <= 0) throw new RuntimeException('no_order');

    /* ===== E) Construit les line_items (montants en CENTIMES) =====
       ⚠ S’aligne sur les colonnes que tu utilises dans commande.php :
       - PRODUIT:  p.PRO_PRIX, cp.CP_QTE_COMMANDEE
       - SUPPLÉMENT: s.SUP_PRIX_UNITAIRE, cs.CS_QTE_COMMANDEE
       - EMBALLAGE: 0 CHF (NE PAS ENVOYER À STRIPE)
    */
    $items = [];

    // Produits
    $q = $pdo->prepare("
        SELECT cp.CP_QTE_COMMANDEE AS qty, p.PRO_NOM AS name, p.PRO_PRIX AS price
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :c
    ");
    $q->execute([':c'=>$comId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $amt = (int)round(((float)$r['price']) * 100);
        if ($amt <= 0) continue; // Stripe refuse 0
        $items[] = [
            'price_data' => [
                'currency' => 'chf',
                'unit_amount' => $amt,
                'product_data' => ['name' => (string)$r['name']],
            ],
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
        $amt = (int)round(((float)$r['price']) * 100);
        if ($amt <= 0) continue;
        $items[] = [
            'price_data' => [
                'currency' => 'chf',
                'unit_amount' => $amt,
                'product_data' => ['name' => (string)$r['name']],
            ],
            'quantity' => (int)$r['qty'],
        ];
    }

    // Emballages: 0 CHF → on les laisse dans le récap site, mais on NE LES ENVOIE PAS à Stripe.

    if (!$items) throw new RuntimeException('empty_items');

    /* ===== F) URLs absolues (basées sur $BASE comme commande.php) ===== */
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // $BASE pointe déjà vers /site/pages/
    $success_url = "{$scheme}://{$host}{$BASE}success.php?com_id={$comId}&session_id={CHECKOUT_SESSION_ID}";
    $cancel_url  = "{$scheme}://{$host}{$BASE}adresse_paiement.php";

    /* ===== G) Crée la Checkout Session ===== */
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'line_items' => $items,
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
        'client_reference_id' => (string)$comId,
        'customer_email' => $_SESSION['per_email'] ?? null,
        'payment_method_types' => ['card'], // ajouter d'autres méthodes plus tard si activées
    ]);

    // Marque la commande "en attente" avant paiement
    $pdo->prepare("UPDATE COMMANDE
                   SET COM_STATUT = 'en attente', STRIPE_SESSION_ID = :sid
                   WHERE COM_ID = :cid")
        ->execute([':sid'=>$session->id, ':cid'=>$comId]);

    echo json_encode(['ok'=>true, 'url'=>$session->url], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Reste en 200 pour que le front lise le JSON d'erreur
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
