<?php
// /site/pages/detail_commande.php
declare(strict_types=1);
session_start();

/* ===== Gardes ===== */
if (empty($_SESSION['per_id'])) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Veuillez vous connecter pour voir cette commande.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

/* ===== Base paths ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function fetchOne(PDO $pdo,string $sql,array $p){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = :t
           AND COLUMN_NAME  = :c
         LIMIT 1
    ");
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (bool)$st->fetchColumn();
}

/* ===== Param ===== */
$comId = isset($_GET['com_id']) && ctype_digit((string)$_GET['com_id']) ? (int)$_GET['com_id'] : 0;
if ($comId <= 0) { http_response_code(400); exit('Commande invalide.'); }

/* ===== 1) En-tête commande ===== */
$sqlHead = "
  SELECT c.COM_ID, c.PER_ID, c.COM_DATE, c.COM_STATUT, c.LIV_ID, c.PAI_ID
    FROM COMMANDE c
   WHERE c.COM_ID = :cid AND c.PER_ID = :per
   LIMIT 1";
$head = fetchOne($pdo, $sqlHead, [':cid'=>$comId, ':per'=>$perId]);
if (!$head) { http_response_code(404); exit("Commande introuvable."); }

/* Paiement & livraison */
$paiement = fetchOne($pdo, "
  SELECT PAI_ID, PAI_MODE, PAI_STATUT, PAI_MONTANT
    FROM PAIEMENT
   WHERE PAI_ID = :pid
", [':pid' => (int)($head['PAI_ID'] ?? 0)]) ?: [];

$livraison = fetchOne($pdo, "
  SELECT LIV_ID, LIV_STATUT, LIV_MODE, COALESCE(LIV_MONTANT_FRAIS,0) AS LIV_FRAIS
    FROM LIVRAISON
   WHERE LIV_ID = :lid
", [':lid' => (int)($head['LIV_ID'] ?? 0)]) ?: [];

/* Libellé lisible du mode de livraison */
$livModeRaw = (string)($livraison['LIV_MODE'] ?? '');
$livModeLabel = match (strtoupper($livModeRaw)) {
    'BOUT' => 'Retrait en boutique',
    'GVA'  => 'Standard — Genève',
    'CH'   => 'Standard — Suisse',
    default => ($livModeRaw !== '' ? $livModeRaw : '—'),
};

/* ===== 2) Adresse de livraison ===== */
$adresse = null;

if (columnExists($pdo, 'COMMANDE', 'ADR_ID_LIVRAISON')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
        FROM COMMANDE c
        JOIN ADRESSE  a ON a.ADR_ID = c.ADR_ID_LIVRAISON
       WHERE c.COM_ID = :cid
       LIMIT 1
    ", [':cid'=>$comId]);
}
if (!$adresse && columnExists($pdo, 'COMMANDE', 'ADR_ID')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
        FROM COMMANDE c
        JOIN ADRESSE  a ON a.ADR_ID = c.ADR_ID
       WHERE c.COM_ID = :cid
       LIMIT 1
    ", [':cid'=>$comId]);
}
if (!$adresse && !empty($head['LIV_ID']) && columnExists($pdo, 'LIVRAISON', 'ADR_ID')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
        FROM LIVRAISON l
        JOIN ADRESSE  a ON a.ADR_ID = l.ADR_ID
       WHERE l.LIV_ID = :lid
       LIMIT 1
    ", [':lid'=>(int)$head['LIV_ID']]);
}
if (!$adresse) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
        FROM ADRESSE_CLIENT ac
        JOIN ADRESSE a ON a.ADR_ID = ac.ADR_ID
       WHERE ac.PER_ID = :per AND a.ADR_TYPE = 'LIVRAISON'
       ORDER BY a.ADR_ID DESC
       LIMIT 1
    ", [':per'=>$perId]);
}

/* ===== 3) Lignes produits =====
   (on prend CP_TYPE_PRODUIT pour calculer la TVA par taux) */
$items = [];
$st = $pdo->prepare("
  SELECT 
    cp.PRO_ID,
    p.PRO_NOM,
    p.PRO_PRIX AS prix_u,
    COALESCE(cp.CP_QTE_COMMANDEE, 1) AS qte,
    LOWER(COALESCE(cp.CP_TYPE_PRODUIT, '')) AS type_produit
  FROM COMMANDE_PRODUIT cp
  JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
  WHERE cp.COM_ID = :cid
  ORDER BY p.PRO_NOM
");
$st->execute([':cid'=>$comId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== 4) Suppléments ===== */
$supps = [];
try {
    $st = $pdo->prepare("
      SELECT s.SUP_NOM, s.SUP_PRIX_UNITAIRE AS prix_u, COALESCE(cs.CS_QTE_COMMANDEE, 1) AS qte
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
       WHERE cs.COM_ID = :cid
       ORDER BY s.SUP_NOM
    ");
    $st->execute([':cid'=>$comId]);
    $supps = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* table absente -> on ignore */ }

/* ===== 5) Emballages ===== (prix 0) */
$embs = [];
$st = $pdo->prepare("
  SELECT e.EMB_NOM, 0 AS prix_u, 1 AS qte
    FROM COMMANDE_EMBALLAGE ce
    JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
   WHERE ce.COM_ID = :cid
   ORDER BY e.EMB_NOM
");
$st->execute([':cid'=>$comId]);
$embs = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== 5bis) Totaux enregistrés (si présents) ===== */
$totaux = fetchOne($pdo, "
  SELECT 
    COALESCE(c.COM_TVA_TAUX, 0)      AS TVA_TAUX,
    COALESCE(c.COM_TVA_MONTANT, 0)   AS TVA_CHF,
    COALESCE(l.LIV_MONTANT_FRAIS, 0) AS LIV_CHF
  FROM COMMANDE c
  LEFT JOIN LIVRAISON l ON l.LIV_ID = c.LIV_ID
  WHERE c.COM_ID = :cid
  LIMIT 1
", [':cid'=>$comId]) ?: ['TVA_TAUX'=>0,'TVA_CHF'=>0,'LIV_CHF'=>0];

$tvaTauxSaved = (float)$totaux['TVA_TAUX'];
$tvaCHFSaved  = (float)$totaux['TVA_CHF'];
$livCHF       = (float)$totaux['LIV_CHF'];

/* ===== 6) Calculs (TTC) + TVA (fallback/affichage) ===== */
$fmt = fn($n) => number_format((float)$n, 2, '.', ' ') . ' CHF';

$subtotal  = 0.0; foreach ($items as $it){  $subtotal  += (float)$it['prix_u'] * (int)$it['qte']; }
$suppTotal = 0.0; foreach ($supps as $sp){  $suppTotal += (float)$sp['prix_u'] * (int)$sp['qte']; }
$embTotal  = 0.0; foreach ($embs  as $em){  $embTotal  += (float)$em['prix_u'] * (int)$em['qte']; }

$grandHTlike = $subtotal + $suppTotal + $embTotal; // tous les PU sont TTC dans ce site
$totalPaye   = $grandHTlike + $livCHF;

/* TVA – si non stockée, on la re-calcul pour l’affichage */
$RATE_REDUCED = 0.026; // fleurs & bouquets
$RATE_NORMAL  = 0.081; // suppléments, emballages, coffrets

$base_reduced = 0.0;
$base_normal  = 0.0;

foreach ($items as $it) {
    $line = (float)$it['prix_u'] * (int)$it['qte'];
    $t = strtolower($it['type_produit'] ?? '');
    if ($t === 'fleur' || $t === 'bouquet') $base_reduced += $line;
    else                                   $base_normal  += $line; // coffret → normal
}
foreach ($supps as $sp) { $base_normal += (float)$sp['prix_u'] * (int)$sp['qte']; }
// emballages gratuits → base 0, mais restent au taux normal si un jour tarifés

$ship_red = 0.0; $ship_norm = 0.0;
$goods_total = $base_reduced + $base_normal;
if ($livCHF > 0 && $goods_total > 0) {
    $ship_red  = $livCHF * ($base_reduced / $goods_total);
    $ship_norm = $livCHF - $ship_red;
}
$tva_reduced = round(($base_reduced + $ship_red)  * $RATE_REDUCED, 2);
$tva_normal  = round(($base_normal  + $ship_norm) * $RATE_NORMAL,  2);
$tvaCalc     = $tva_reduced + $tva_normal;

/* Valeurs pour affichage */
$tvaDisplay  = ($tvaCHFSaved > 0) ? $tvaCHFSaved : $tvaCalc;
$tvaRateInfo = ($tvaTauxSaved > 0) ? $tvaTauxSaved : null;

$paidAmount  = isset($paiement['PAI_MONTANT']) ? (float)$paiement['PAI_MONTANT'] : null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Détails commande #<?= (int)$head['COM_ID'] ?></title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_detail_commande.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container dk-order" aria-label="Détails commande">
    <a class="back" href="<?= $BASE ?>info_perso.php">← Retour</a>
    <h1>Commande #<?= (int)$head['COM_ID'] ?></h1>

    <div class="grid">
        <!-- Colonne gauche -->
        <section class="card">
            <div class="meta">
                <div><b>Date</b><?= h(date('d.m.Y', strtotime((string)$head['COM_DATE']))) ?></div>
                <div><b>Statut</b><span class="badge"><?= h((string)$head['COM_STATUT']) ?></span></div>
                <div>
                    <b>Livraison</b>
                    <?php if ($livModeLabel !== '—'): ?>
                        <span class="badge"><?= h($livModeLabel) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($livraison['LIV_STATUT'])): ?>
                        <span class="badge" style="margin-left:6px;opacity:.85">
            <?= h((string)$livraison['LIV_STATUT']) ?>
        </span>
                    <?php endif; ?>
                </div>


            </div>

            <style>
                .badge { background:#f6eef0; color:#8A1B2E; padding:4px 8px; border-radius:999px; font-weight:700; }
                .badge.muted{ background:#f3f4f6; color:#6b7280; }

            </style>

            <h2 class="section-title">Articles</h2>
            <?php if (!$items): ?>
                <p class="muted">Aucune ligne produit enregistrée.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr><th>Article</th><th class="right">PU</th><th class="right">Qté</th><th class="right">Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): $line = (float)$it['prix_u'] * (int)$it['qte']; ?>
                        <tr>
                            <td><?= h((string)$it['PRO_NOM']) ?></td>
                            <td class="right"><?= h($fmt((float)$it['prix_u'])) ?></td>
                            <td class="right"><?= (int)$it['qte'] ?></td>
                            <td class="right"><?= h($fmt($line)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($supps): ?>
                <h2 class="section-title">Suppléments</h2>
                <table>
                    <thead><tr><th>Supplément</th><th class="right">PU</th><th class="right">Qté</th><th class="right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($supps as $sp): $line = (float)$sp['prix_u'] * (int)$sp['qte']; ?>
                        <tr>
                            <td><?= h((string)$sp['SUP_NOM']) ?></td>
                            <td class="right"><?= h($fmt((float)$sp['prix_u'])) ?></td>
                            <td class="right"><?= (int)$sp['qte'] ?></td>
                            <td class="right"><?= h($fmt($line)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($embs): ?>
                <h2 class="section-title">Emballages</h2>
                <table>
                    <thead><tr><th>Emballage</th><th class="right">PU</th><th class="right">Qté</th><th class="right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($embs as $em): $line = (float)$em['prix_u'] * (int)$em['qte']; ?>
                        <tr>
                            <td><?= h((string)$em['EMB_NOM']) ?></td>
                            <td class="right"><?= h($fmt((float)$em['prix_u'])) ?></td>
                            <td class="right"><?= (int)$em['qte'] ?></td>
                            <td class="right"><?= h($fmt($line)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="totals">
                <div class="row"><span>Sous-total produits</span><strong><?= h($fmt($subtotal)) ?></strong></div>
                <?php if ($suppTotal>0): ?>
                    <div class="row"><span>Suppléments</span><strong><?= h($fmt($suppTotal)) ?></strong></div>
                <?php endif; ?>
                <?php if ($embTotal>0):  ?>
                    <div class="row"><span>Emballages</span><strong><?= h($fmt($embTotal)) ?></strong></div>
                <?php endif; ?>
                <?php if ($livCHF>0): ?>
                    <div class="row"><span>Livraison</span><strong><?= h($fmt($livCHF)) ?></strong></div>
                <?php endif; ?>

                <!-- TVA toujours affichée (calculée si non stockée) -->
                <div class="row" style="opacity:.95">
                    <span>TVA<?= $tvaRateInfo ? ' ('.h(number_format($tvaRateInfo,1)).'%)' : '' ?></span>
                    <strong><?= h($fmt($tvaDisplay)) ?></strong>
                </div>
                <?php if ($tvaCalc > 0 && !$tvaRateInfo): ?>
                    <div class="row" style="font-size:12px;color:#6b7280;margin-top:-6px">
                        <span>Détail (calculé) : 2,6 % <?= h($fmt($tva_reduced)) ?> • 8,1 % <?= h($fmt($tva_normal)) ?></span>
                        <span></span>
                    </div>
                <?php endif; ?>

                <div class="row grand"><span>Total</span><strong><?= h($fmt($totalPaye)) ?></strong></div>
            </div>
        </section>

        <!-- Colonne droite -->
        <aside class="card">
            <h2 class="section-title">Adresse de livraison</h2>
            <?php if ($adresse): ?>
                <p class="addr">
                    <?= h((string)$adresse['ADR_RUE']) ?> <?= h((string)$adresse['ADR_NUMERO']) ?><br>
                    <?= h((string)$adresse['ADR_NPA']) ?> <?= h((string)$adresse['ADR_VILLE']) ?><br>
                    <?= h((string)$adresse['ADR_PAYS']) ?>
                </p>
            <?php else: ?>
                <p class="muted">Aucune adresse de livraison enregistrée.</p>
            <?php endif; ?>

            <div style="margin-top:16px">
                <a class="btn" href="<?= $BASE ?>info_perso.php">Mes commandes</a>
            </div>
            <div style="margin-top:10px">
                <a class="btn" href="<?= $BASE ?>facture_pdf.php?com_id=<?= (int)$head['COM_ID'] ?>">
                    Télécharger la facture (PDF)
                </a>
            </div>
            <p class="muted" style="margin-top:10px">
                Besoin d’aide ? Contactez-nous via la page <a href="<?= $BASE ?>contact.php">Contact</a>.
            </p>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
