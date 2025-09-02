<?php
// public/api/create_payment_intent.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/connexionBDD.php';
require_once __DIR__ . '/../../config/stripe.php';

use Stripe\PaymentIntent;

// Config
const TVA_RATE = 0.077;           // adapte (2.5% si nécessaire)
const SHIPPING_FLAT = 7.00;
const FREE_SHIPPING_FROM = 80.00;
const CURRENCY = 'chf';

// 1) Récupération du panier envoyé par le front
$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? []; // [{id, qty}]
if (!$items) { echo json_encode(['error' => 'Panier vide']); exit; }

// 2) Anti-triche : relecture des prix depuis la BDD
$ids = array_map('intval', array_column($items, 'id'));
$in  = implode(',', $ids);

$sql = "SELECT id, sku, nom, prix, image FROM PRODUIT WHERE id IN ($in)";
$stmt = $pdo->query($sql);
$map = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[$row['id']] = $row; }

$subtotal = 0.0; $lines = [];
foreach ($items as $it) {
    $pid = (int)$it['id']; $qty = max(1, (int)($it['qty'] ?? 1));
    if (!isset($map[$pid])) continue;
    $p = $map[$pid];
    $lineTotal = (float)$p['prix'] * $qty;
    $subtotal += $lineTotal;
    $lines[] = [
        'sku' => $p['sku'], 'name' => $p['nom'],
        'price' => (float)$p['prix'], 'qty' => $qty, 'image' => $p['image']
    ];
}

$shipping = ($subtotal >= FREE_SHIPPING_FROM) ? 0.0 : SHIPPING_FLAT;
$tva      = round(($subtotal + $shipping) * TVA_RATE, 2);
$total    = round($subtotal + $shipping + $tva, 2);

// 3) Création du PaymentIntent
try {
    $pi = PaymentIntent::create([
        'amount' => (int) round($total * 100),   // en centimes
        'currency' => CURRENCY,
        'automatic_payment_methods' => ['enabled' => true], // cartes, TWINT, etc.
        'metadata' => [
            'items_json' => json_encode(array_map(fn($l)=>['sku'=>$l['sku'],'qty'=>$l['qty'],'price'=>$l['price']], $lines)),
            'subtotal' => (string)$subtotal,
            'shipping' => (string)$shipping,
            'tva'      => (string)$tva,
        ],
    ]);

    echo json_encode([
        'clientSecret'   => $pi->client_secret,
        'publishableKey' => STRIPE_PUBLISHABLE_KEY,
        'breakdown'      => [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tva'      => $tva,
            'total'    => $total,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
