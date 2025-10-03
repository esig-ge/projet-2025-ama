<?php
// /site/pages/facture_pdf.php
declare(strict_types=1);
session_start();

/* ===== 0) Garde ===== */
if (empty($_SESSION['per_id'])) { http_response_code(403); exit('Veuillez vous connecter.'); }
$perId = (int)$_SESSION['per_id'];

/* ===== 1) Connexion BDD & helpers ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function fetchOne(PDO $pdo,string $sql,array $p){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND TABLE_NAME = :t
                         AND COLUMN_NAME = :c LIMIT 1");
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (bool)$st->fetchColumn();
}
$fmt = fn($n) => number_format((float)$n, 2, '.', ' ') . ' CHF';
function img_to_data_uri(string $absPath): ?string {
    if (!is_file($absPath)) return null;
    $ext = strtolower((string)pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = in_array($ext, ['png','jpg','jpeg','gif','webp','svg']) ? "image/$ext" : 'application/octet-stream';
    return "data:$mime;base64," . base64_encode((string)file_get_contents($absPath));
}

/* ===== 2) Paramètre ===== */
$comId = isset($_GET['com_id']) && ctype_digit((string)$_GET['com_id']) ? (int)$_GET['com_id'] : 0;
if ($comId <= 0) { http_response_code(400); exit('Commande invalide.'); }

/* ===== 3) Données commande ===== */
$head = fetchOne($pdo, "
  SELECT c.COM_ID, c.PER_ID, c.COM_DATE, c.COM_STATUT, c.LIV_ID, c.PAI_ID
  FROM COMMANDE c
  WHERE c.COM_ID = :cid AND c.PER_ID = :per
  LIMIT 1", [':cid'=>$comId, ':per'=>$perId]);
if (!$head) { http_response_code(404); exit('Commande introuvable.'); }

$paiement = fetchOne($pdo, "SELECT PAI_MODE, PAI_STATUT, PAI_MONTANT FROM PAIEMENT WHERE PAI_ID = :pid",
    [':pid'=>(int)($head['PAI_ID'] ?? 0)]) ?: [];

$adresse = null;
if (columnExists($pdo, 'COMMANDE','ADR_ID_LIVRAISON')) {
    $adresse = fetchOne($pdo, "SELECT a.ADR_RUE,a.ADR_NUMERO,a.ADR_NPA,a.ADR_VILLE,a.ADR_PAYS
    FROM COMMANDE c JOIN ADRESSE a ON a.ADR_ID=c.ADR_ID_LIVRAISON
    WHERE c.COM_ID=:cid LIMIT 1", [':cid'=>$comId]);
}
if(!$adresse && columnExists($pdo,'COMMANDE','ADR_ID')){
    $adresse = fetchOne($pdo,"SELECT a.ADR_RUE,a.ADR_NUMERO,a.ADR_NPA,a.ADR_VILLE,a.ADR_PAYS
    FROM COMMANDE c JOIN ADRESSE a ON a.ADR_ID=c.ADR_ID
    WHERE c.COM_ID=:cid LIMIT 1", [':cid'=>$comId]);
}
if(!$adresse && !empty($head['LIV_ID']) && columnExists($pdo,'LIVRAISON','ADR_ID')){
    $adresse = fetchOne($pdo,"SELECT a.ADR_RUE,a.ADR_NUMERO,a.ADR_NPA,a.ADR_VILLE,a.ADR_PAYS
    FROM LIVRAISON l JOIN ADRESSE a ON a.ADR_ID=l.ADR_ID
    WHERE l.LIV_ID=:lid LIMIT 1", [':lid'=>(int)$head['LIV_ID']]);
}

$st = $pdo->prepare("SELECT p.PRO_NOM, p.PRO_PRIX AS prix_u, COALESCE(cp.CP_QTE_COMMANDEE,1) AS qte
                     FROM COMMANDE_PRODUIT cp JOIN PRODUIT p ON p.PRO_ID=cp.PRO_ID
                     WHERE cp.COM_ID=:cid ORDER BY p.PRO_NOM");
$st->execute([':cid'=>$comId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

$supps = [];
try {
    $st = $pdo->prepare("SELECT s.SUP_NOM, s.SUP_PRIX_UNITAIRE AS prix_u, COALESCE(cs.CS_QTE_COMMANDEE,1) AS qte
                       FROM COMMANDE_SUPP cs JOIN SUPPLEMENT s ON s.SUP_ID=cs.SUP_ID
                       WHERE cs.COM_ID=:cid ORDER BY s.SUP_NOM");
    $st->execute([':cid'=>$comId]); $supps = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

$embs = [];
try {
    $st = $pdo->prepare("SELECT e.EMB_NOM, 0 AS prix_u, 1 AS qte
                       FROM COMMANDE_EMBALLAGE ce JOIN EMBALLAGE e ON e.EMB_ID=ce.EMB_ID
                       WHERE ce.COM_ID=:cid ORDER BY e.EMB_NOM");
    $st->execute([':cid'=>$comId]); $embs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

/* ===== 4) Totaux ===== */
$subtotal = array_reduce($items, fn($a,$it)=>$a + (float)$it['prix_u']*(int)$it['qte'], 0.0);
$suppTotal= array_reduce($supps, fn($a,$sp)=>$a + (float)$sp['prix_u']*(int)$sp['qte'], 0.0);
$embTotal = array_reduce($embs,  fn($a,$em)=>$a + (float)$em['prix_u']*(int)$em['qte'], 0.0);
$grandTotal= $subtotal + $suppTotal + $embTotal;
$paidAmount = isset($paiement['PAI_MONTANT']) ? ((float)$paiement['PAI_MONTANT']/100.0) : null;

/* ===== 4bis) Livraison & TVA ===== */
/* 1) Frais de livraison : on tente plusieurs colonnes/tables, sinon 0 */
$shipping = 0.0;

// a) COMMANDE.COM_FRAIS_LIVRAISON ?
if (columnExists($pdo, 'COMMANDE', 'COM_FRAIS_LIVRAISON')) {
    $s = fetchOne($pdo, "SELECT COALESCE(COM_FRAIS_LIVRAISON,0) AS f FROM COMMANDE WHERE COM_ID=:cid", [':cid'=>$comId]);
    if ($s && isset($s['f'])) $shipping = (float)$s['f'];
}
// b) LIVRAISON.LIV_FRAIS ? (si LIV_ID existe)
elseif (!empty($head['LIV_ID']) && columnExists($pdo, 'LIVRAISON', 'LIV_FRAIS')) {
    $s = fetchOne($pdo, "SELECT COALESCE(LIV_FRAIS,0) AS f FROM LIVRAISON WHERE LIV_ID=:lid", [':lid'=>(int)$head['LIV_ID']]);
    if ($s && isset($s['f'])) $shipping = (float)$s['f'];
}

/* 2) TVA : taux (prends ta valeur depuis la BDD si tu l’as, sinon fallback 8.1%) */
$TVA_RATE = 0.081;  // 8.1% CH
// Exemple si tu as une table PARAMETRE(tva) :
// $row = fetchOne($pdo, "SELECT VALEUR FROM PARAMETRE WHERE CLE='TVA_RATE' LIMIT 1", []);
// if ($row) $TVA_RATE = (float)$row['VALEUR'];

/* 3) Tes prix semblent être TTC (c’était l’intention jusqu’ici).
      On calcule donc un éclaté HT + TVA à partir du TTC. */
$total_ttc = $grandTotal + $shipping;
$base_ht   = $total_ttc / (1.0 + $TVA_RATE);
$tva_amt   = $total_ttc - $base_ht;

/* Arrondis (comme Stripe : 2 décimales) */
$shipping  = round($shipping, 2);
$base_ht   = round($base_ht, 2);
$tva_amt   = round($tva_amt, 2);
$total_ttc = round($total_ttc, 2);


/* ===== 5) HTML facture ===== */
$logoUri = img_to_data_uri(__DIR__.'/../img/logo.png');
$when    = date('d.m.Y', strtotime((string)$head['COM_DATE']));
$statut  = (string)$head['COM_STATUT'];
$addrHtml= $adresse
    ? h($adresse['ADR_RUE'].' '.$adresse['ADR_NUMERO']).'<br>'.h($adresse['ADR_NPA'].' '.$adresse['ADR_VILLE']).'<br>'.h($adresse['ADR_PAYS'])
    : '<span style="color:#888">Aucune adresse</span>';

$rows = '';
foreach ($items as $it){ $line=(float)$it['prix_u']*(int)$it['qte'];
    $rows .= '<tr><td>'.h($it['PRO_NOM']).'</td><td class="r">'.$fmt((float)$it['prix_u']).'</td><td class="r">'.(int)$it['qte'].'</td><td class="r">'.$fmt($line).'</td></tr>';
}
if($supps){ $rows.='<tr class="section"><td colspan="4"><b>Suppléments</b></td></tr>';
    foreach ($supps as $sp){ $line=(float)$sp['prix_u']*(int)$sp['qte'];
        $rows.='<tr><td>'.h($sp['SUP_NOM']).'</td><td class="r">'.$fmt((float)$sp['prix_u']).'</td><td class="r">'.(int)$sp['qte'].'</td><td class="r">'.$fmt($line).'</td></tr>';
    }}
if($embs){ $rows.='<tr class="section"><td colspan="4"><b>Emballages</b></td></tr>';
    foreach ($embs as $em){ $line=(float)$em['prix_u']*(int)$em['qte'];
        $rows.='<tr><td>'.h($em['EMB_NOM']).'</td><td class="r">'.$fmt((float)$em['prix_u']).'</td><td class="r">'.(int)$em['qte'].'</td><td class="r">'.$fmt($line).'</td></tr>';
    }}

$html = '
<!doctype html><html lang="fr"><meta charset="utf-8">
<style>
@page{margin:30px 28px}body{font-family:DejaVu Sans,Arial,sans-serif;color:#1b1b1b}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.brand{color:#5C0012}.logo{height:46px}.h-sep{height:2px;background:#5C0012;opacity:.12;margin:10px 0 16px}
.small{color:#666;font-size:12px}h1{font-size:20px;margin:6px 0 2px;color:#5C0012}
.meta{display:flex;gap:22px;margin:6px 0 16px}.meta .box{background:#f7f1f2;border-radius:10px;padding:10px 12px}
.meta .k{font-size:11px;color:#6b6b6b;display:block}.addr{line-height:1.4}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:8px 6px;border-bottom:1px solid #eee}
th{text-align:left;color:#444}.r{text-align:right}.section td{background:#faf5f6;font-size:12px;color:#5C0012}
.totals{margin-top:10px}.trow{display:flex;justify-content:space-between;padding:4px 0}
.grand{border-top:1px solid #ddd;margin-top:6px;padding-top:8px;font-weight:700}.badge{display:inline-block;background:#f1e7e9;color:#8A1B2E;padding:4px 8px;border-radius:999px;font-size:12px}
.footer{margin-top:18px;font-size:11px;color:#666}
</style>
<body>
<div class="header"><div><div class="brand"><strong>DK Bloom</strong></div><div class="small">Bouquets & cadeaux — Genève</div></div>'.
    ($logoUri ? '<img class="logo" src="'.$logoUri.'" alt="DK Bloom">' : '') .
    '</div>
<div class="h-sep"></div>
<h1>Facture #'.(int)$head['COM_ID'].'</h1>
<div class="meta">
  <div class="box"><span class="k">Date</span>'.$when.'</div>
  <div class="box"><span class="k">Statut commande</span><span class="badge">'.h($statut).'</span></div>
  <div class="box"><span class="k">Paiement</span>'.h((string)($paiement['PAI_MODE'] ?? '—')).' — '.h((string)($paiement['PAI_STATUT'] ?? '—')).'</div>
</div>
<div class="addr"><strong>Adresse de livraison</strong><br>'.$addrHtml.'</div>
<h2 style="font-size:16px;margin:14px 0 6px;color:#8A1B2E">Détail</h2>
<table><thead><tr><th>Article</th><th class="r">PU</th><th class="r">Qté</th><th class="r">Total</th></tr></thead>
<tbody>'.$rows.'</tbody></table>
<div class="totals">
  <div class="trow"><span>Sous-total produits</span><strong>'.$fmt($subtotal).'</strong></div>'.
    ($suppTotal>0 ? '<div class="trow"><span>Suppléments</span><strong>'.$fmt($suppTotal).'</strong></div>' : '').
    ($embTotal>0 ? '<div class="trow"><span>Emballages</span><strong>'.$fmt($embTotal).'</strong></div>' : '').
    '<div class="trow grand"><span>Total</span><strong>'.$fmt($grandTotal).'</strong></div>'.
    ($paidAmount!==null ? '<div class="trow"><span>Montant payé</span><strong>'.$fmt($paidAmount).'</strong></div>' : '').
    '</div>
<div class="footer">Prix affichés TTC. Pour la Suisse, la TVA est incluse le cas échéant. En cas de question, contactez-nous depuis la page Contact.</div>
</body></html>';

/* ===== 6) PDFMonkey — génération synchrone puis téléchargement ===== */

// 0) CONFIG
$PDFMONKEY_API_KEY = getenv('PDFMONKEY_API_KEY') ?: 'soyoppLu1LxJJPXq14J4';
$PDFMONKEY_TEMPLATE_ID = '5B6A2713-DF19-4A54-BFB0-4679F46B950E';

// 1) Construire le payload attendu par ton template
$lines = [];
foreach ($items as $it) {
    $qty  = (int)$it['qte'];
    $unit = (float)$it['prix_u'];
    $lines[] = [
        'group' => 'Produit',
        'name'  => (string)$it['PRO_NOM'],
        'qty'   => $qty,
        'unit'  => round($unit, 2),
        'total' => round($qty * $unit, 2),
    ];
}
if (!empty($supps)) {
    foreach ($supps as $sp) {
        $qty  = (int)$sp['qte'];
        $unit = (float)$sp['prix_u'];
        $lines[] = [
            'group' => 'Supplément',
            'name'  => (string)$sp['SUP_NOM'],
            'qty'   => $qty,
            'unit'  => round($unit, 2),
            'total' => round($qty * $unit, 2),
        ];
    }
}
if (!empty($embs)) {
    foreach ($embs as $em) {
        $qty  = (int)$em['qte'];
        $unit = (float)$em['prix_u'];
        $lines[] = [
            'group' => 'Emballage',
            'name'  => (string)$em['EMB_NOM'],
            'qty'   => $qty,
            'unit'  => round($unit, 2),
            'total' => round($qty * $unit, 2),
        ];
    }
}

$payload = [
    'invoice' => [
        'number' => (int)$head['COM_ID'],
        'date'   => date('Y-m-d', strtotime((string)$head['COM_DATE'])),
        'status' => (string)$head['COM_STATUT'],
        'payment'=> [
            'mode'   => (string)($paiement['PAI_MODE'] ?? '—'),
            'status' => (string)($paiement['PAI_STATUT'] ?? '—'),
        ],
    ],
    'customer' => [
        'address1' => $adresse ? ($adresse['ADR_RUE'].' '.$adresse['ADR_NUMERO']) : '',
        'zip'      => $adresse['ADR_NPA']   ?? '',
        'city'     => $adresse['ADR_VILLE'] ?? '',
        'country'  => $adresse['ADR_PAYS']  ?? '',
    ],
    'lines' => $lines,
    'totals' => [
        'subtotal'     => round($subtotal, 2),
        'supplements'  => round($suppTotal, 2),
        'packaging'    => round($embTotal, 2),
        'grand_total'  => round($grandTotal, 2),
        'paid'         => $paidAmount !== null ? round($paidAmount, 2) : null,
        'currency'     => 'CHF',
    ],

    'totals' => [
        'subtotal'       => round($subtotal, 2),     // produits (TTC)
        'supplements'    => round($suppTotal, 2),    // TTC
        'packaging'      => round($embTotal, 2),     // TTC
        'shipping'       => $shipping,               // frais de livraison
        'vat_rate'       => $TVA_RATE,               // ex: 0.081
        'vat_rate_pct'   => round($TVA_RATE * 100, 2), // 8.1
        'vat_amount'     => $tva_amt,                // montant TVA
        'total_excl_vat' => $base_ht,               // HT
        'grand_total'    => $total_ttc,             // TTC (produits+supp+emb+livraison)
        'paid'           => $paidAmount !== null ? round($paidAmount, 2) : null,
        'currency'       => 'CHF',
    ],

    'branding' => [
        'company' => 'DK Bloom',
        'city'    => 'Genève',
        'logo'    => null, // Astuce: si tu veux, héberge le logo en HTTPS et utilise son URL dans le template
    ],
];

// 2) Appel API synchrone
function http_json($method, $url, array $headers, array $body = null, int $timeout = 25) {
    $ch = curl_init($url);
    $h  = array_merge($headers, ['Accept: application/json']);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $h,
    ]);
    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $h[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $resp, $err];
}

$docBody = [
    'document' => [
        'document_template_id' => $PDFMONKEY_TEMPLATE_ID,
        'status'  => 'pending',             // queue + génération
        'payload' => $payload,              // tes données
        'meta'    => ['_filename' => 'Facture_DK_Bloom_COM-'.$head['COM_ID'].'_'.date('Ymd').'.pdf'],
    ],
];

[$code, $resp, $err] = http_json(
    'POST',
    'https://api.pdfmonkey.io/api/v1/documents/sync',
    ['Authorization: Bearer '.$PDFMONKEY_API_KEY],
    $docBody,
    60 // timeout un peu plus large
);

if ($err || $code >= 400) {
    http_response_code(500);
    exit('PDFMonkey erreur HTTP '.$code.' : '.$err.' / '.$resp);
}

$data = json_decode($resp, true);
$card = $data['document_card'] ?? null;
if (!$card) {
    http_response_code(500);
    exit('Réponse inattendue de PDFMonkey.');
}

// 3) Récupérer l’URL de téléchargement (selon la doc, sync attend le succès, mais on gère le cas contraire)
$downloadUrl = $card['download_url'] ?? null;
$docId       = $card['id'] ?? null;

if (!$downloadUrl && $docId) {
    // fallback: refetch le Document complet pour un download_url frais
    [$code2, $resp2, $err2] = http_json(
        'GET',
        'https://api.pdfmonkey.io/api/v1/documents/'.$docId,
        ['Authorization: Bearer '.$PDFMONKEY_API_KEY]
    );
    if (!$err2 && $code2 < 400) {
        $doc = json_decode($resp2, true)['document'] ?? null;
        $downloadUrl = $doc['download_url'] ?? null;
    }
}

if (!$downloadUrl) {
    http_response_code(502);
    exit('PDF généré mais pas encore disponible (download_url manquant). Réessaie dans quelques secondes.');
}

// 4) Télécharger le binaire et le streamer au navigateur
$fname = $card['filename'] ?? ('Facture_DK_Bloom_COM-'.$head['COM_ID'].'_'.date('Ymd').'.pdf');

$ch = curl_init($downloadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 60,
]);
$pdfBinary = curl_exec($ch);
$codePdf   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($codePdf >= 400 || !$pdfBinary) {
    http_response_code(502);
    exit('Impossible de récupérer le PDF ('.$codePdf.').');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.strlen($pdfBinary));
echo $pdfBinary;
exit;
