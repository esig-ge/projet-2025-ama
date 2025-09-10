<?php
session_start();

/* 1) ENV & STRIPE (hors /pages/) */
require_once __DIR__ . '/../database/config/env.php';
loadProjectEnv();
require_once __DIR__ . '/../database/config/stripe.php';
require_once __DIR__ . '/../database/config/connexionBDD.php'; // si besoin DB

/* 2) Bases de chemins (assets dans /pages, API détectée) */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);

/* Détection API cart.php */
$api_fs_main  = __DIR__ . '/../api/cart.php';   // /site/api/cart.php
$api_fs_pages = __DIR__ . '/api/cart.php';      // /site/pages/api/cart.php
$API_URL_FS   = is_file($api_fs_main) ? $api_fs_main : (is_file($api_fs_pages) ? $api_fs_pages : null);
$API_URL      = is_file($api_fs_main) ? $SITE_BASE . 'api/cart.php'
    : (is_file($api_fs_pages) ? $PAGE_BASE . 'api/cart.php' : $SITE_BASE . 'api/cart.php');

/* ---------- Helpers JSON ---------- */
function json_fail(string $msg, int $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok(array $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Récup panier depuis api/cart.php sans requête HTTP ----------
   On simule ?action=list et on "capture" la sortie de cart.php                  */
function getCartItemsFromApi(?string $api_fs_path): array {
    if (!$api_fs_path || !is_file($api_fs_path)) return [];
    $old_get = $_GET;
    $_GET['action'] = 'list';
    ob_start();
    include $api_fs_path;            // cart.php echo du JSON
    $out = ob_get_clean();
    $_GET = $old_get;
    $data = json_decode($out, true);
    if (!is_array($data) || empty($data['ok'])) return [];
    // On accepte 'items' ou 'lines' selon ton implémentation
    $items = $data['items'] ?? $data['lines'] ?? [];
    return is_array($items) ? $items : [];
}

/* ---------- Construire les line_items Stripe à partir du panier ----------
   On s’attend à des objets du type { id,name,price,qty,img }                   */
function toStripeLineItems(array $items, string $currency = 'chf'): array {
    $out = [];
    foreach ($items as $it) {
        $name  = (string)($it['name'] ?? $it['pro_nom'] ?? 'Article');
        $qty   = (int)   ($it['qty']  ?? $it['quantite'] ?? 1);
        $price = (float) ($it['price']?? $it['prix'] ?? $it['unit_price'] ?? 0.0);
        $img   = (string)($it['img']  ?? $it['image'] ?? '');

        if ($qty < 1)   { $qty = 1; }
        if ($price < 0) { $price = 0; }

        $out[] = [
            'price_data' => [
                'currency'     => strtolower($currency),
                'product_data' => [
                    'name'   => $name,
                    'images' => $img ? [$img] : [],
                ],
                // Stripe attend des centimes
                'unit_amount'  => (int) round($price * 100),
            ],
            'quantity' => $qty,
        ];
    }
    return $out;
}

/* ====================================================================== */
/* =============== TRAITEMENT : création de session Stripe ============== */
/* ====================================================================== */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'create_checkout') {
    try {
        // 1) Récupère le panier depuis l’API locale
        $cartItems = getCartItemsFromApi($API_URL_FS);
        if (!$cartItems) {
            // Plan B: si tu as une COMMANDE en session, tu peux la recharger ici depuis la BDD
            // json_fail('Panier vide ou introuvable.', 422);
            // Pour t’aider en DEV : on loggue le retour brut
            error_log('[checkout] Cart empty or api/cart.php not reachable. Path=' . $API_URL_FS);
            json_fail('Panier vide ou introuvable.', 422);
        }

        // 2) Construit les line_items Stripe
        $currency   = strtolower(getenv('STRIPE_CURRENCY') ?: 'CHF');
        $line_items = toStripeLineItems($cartItems, $currency);

        if (!$line_items) json_fail('Aucun article valide à payer.', 422);

        // 3) Récupère infos de facturation / livraison postées par la page "adresses & paiement"
        $bill = [
            'name'    => trim(($_POST['bill_firstname'] ?? '') . ' ' . ($_POST['bill_lastname'] ?? '')),
            'email'   => trim($_POST['bill_email'] ?? ''),
            'phone'   => trim($_POST['bill_phone'] ?? ''),
            'address' => [
                'line1'       => trim($_POST['bill_address'] ?? ''),
                'postal_code' => trim($_POST['bill_postal'] ?? ''),
                'city'        => trim($_POST['bill_city'] ?? ''),
                'country'     => strtoupper(trim($_POST['bill_country'] ?? 'CH')),
            ],
        ];
        $ship = [
            'name'    => trim(($_POST['ship_firstname'] ?? '') . ' ' . ($_POST['ship_lastname'] ?? '')),
            'phone'   => trim($_POST['ship_phone'] ?? ''),
            'address' => [
                'line1'       => trim($_POST['ship_address'] ?? ''),
                'postal_code' => trim($_POST['ship_postal'] ?? ''),
                'city'        => trim($_POST['ship_city'] ?? ''),
                'country'     => strtoupper(trim($_POST['ship_country'] ?? 'CH')),
            ],
        ];
        $same = ($_POST['same_as_billing'] ?? '0') === '1';

        if ($same) {
            $ship = [
                'name'    => $bill['name'],
                'phone'   => $bill['phone'],
                'address' => $bill['address'],
            ];
        }

        // 4) Crée la Checkout Session
        $successUrl = $SITE_BASE . 'success.php?sid={CHECKOUT_SESSION_ID}';
        $cancelUrl  = $SITE_BASE . 'cancel.php';

        $session = \Stripe\Checkout\Session::create([
            'mode'        => 'payment',
            'line_items'  => $line_items,
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,

            // facultatif mais utile
            'customer_email'   => $bill['email'] ?: null,
            'customer_details' => [
                'name'    => $bill['name'] ?: null,
                'email'   => $bill['email'] ?: null,
                'phone'   => $bill['phone'] ?: null,
                'address' => $bill['address'],
            ],
            'shipping' => [
                'name'    => $ship['name'] ?: null,
                'phone'   => $ship['phone'] ?: null,
                'address' => $ship['address'],
            ],
            'metadata' => [
                'com_id'           => (string)($_SESSION['com_id'] ?? ''),
                'same_as_billing'  => $same ? '1' : '0',
                'origin'           => 'checkout.php',
            ],
        ]);

        // 5) Renvoie JSON {ok:true,url:"…"}
        json_ok(['url' => $session->url]);

        // Si tu préfères rediriger côté serveur :
        // header('Location: ' . $session->url);
        // exit;

    } catch (\Throwable $e) {
        error_log('[checkout] error: ' . $e->getMessage());
        json_fail('Erreur paiement: ' . $e->getMessage(), 500);
    }
}

/* ====================================================================== */
/* =================== AFFICHAGE GET (page récap) ======================= */
/* ====================================================================== */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Paiement</title>

    <!-- ASSETS : DANS /site/pages/ -->
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/checkout.css">

    <script>
        window.DKBASE  = <?= json_encode($PAGE_BASE) ?>; // pour images
        window.API_URL = <?= json_encode($API_URL) ?>;    // cart.php détecté
        console.debug('[checkout] PAGE_BASE=', DKBASE, 'API_URL=', API_URL);
    </script>
    <script src="<?= $PAGE_BASE ?>js/checkout.js" defer></script>
    <!-- Stripe.js (si tu l’utilises en front pour d’autres cas) -->
    <script src="https://js.stripe.com/v3/"></script>
</head>

<body onload="initCheckout && initCheckout()">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap" role="main">
    <h1 class="page-title">Paiement sécurisé</h1>

    <section class="card">
        <div id="cart-lines" class="cart-lines">Chargement…</div>

        <div class="sum">
            <div><span>Produits</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div><span>TVA</span><span id="sum-tva">0.00 CHF</span></div>
            <div class="total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>
        </div>

        <!-- Ce formulaire n’est pas utilisé pour Stripe ici.
             La page adresses & paiement POSTe directement create_checkout. -->
        <form id="pay-form" onsubmit="return false;">
            <p class="form-msg" aria-live="polite">
                Pour payer, utilise la page “Adresses & paiement”, bouton <strong>Payer maintenant</strong>.
            </p>
            <a class="btn-primary" href="<?= $PAGE_BASE ?>adresse_paiement.php">Aller à la page de paiement</a>
        </form>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
