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

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function fetchOne(PDO $pdo,string $sql,array $p){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function columnExists(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = :t
              AND COLUMN_NAME  = :c
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (bool)$st->fetchColumn();
}

/* ===== Param ===== */
$comId = isset($_GET['com_id']) && ctype_digit((string)$_GET['com_id']) ? (int)$_GET['com_id'] : 0;
if ($comId <= 0) { http_response_code(400); exit('Commande invalide.'); }

/* ===== 1) En-tête commande (avec contrôle d’appartenance) ===== */
$sqlHead = "
  SELECT 
    c.COM_ID, c.PER_ID, c.COM_DATE, c.COM_STATUT,
    c.LIV_ID, c.PAI_ID
  FROM COMMANDE c
  WHERE c.COM_ID = :cid AND c.PER_ID = :per
  LIMIT 1";
$head = fetchOne($pdo, $sqlHead, [':cid'=>$comId, ':per'=>$perId]);
if (!$head) { http_response_code(404); exit("Commande introuvable."); }

/* Paiement & livraison (sans supposer les colonnes d’adresse) */
$paiement = fetchOne($pdo, "
  SELECT PAI_ID, PAI_MODE, PAI_STATUT, PAI_MONTANT
  FROM PAIEMENT WHERE PAI_ID = :pid
", [':pid' => (int)($head['PAI_ID'] ?? 0)]) ?: [];

$livraison = fetchOne($pdo, "
  SELECT LIV_ID, LIV_STATUT
  FROM LIVRAISON WHERE LIV_ID = :lid
", [':lid' => (int)($head['LIV_ID'] ?? 0)]) ?: [];

/* ===== 2) Adresse de livraison — détection schéma =====
   Ordre de priorité:
   A) COMMANDE.ADR_ID_LIVRAISON (si existe)
   B) COMMANDE.ADR_ID (si existe)
   C) LIVRAISON.ADR_ID (si existe)
   D) Fallback: dernière adresse LIVRAISON de l’utilisateur
*/
$adresse = null;

if (columnExists($pdo, 'COMMANDE', 'ADR_ID_LIVRAISON')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
      FROM COMMANDE c
      JOIN ADRESSE a ON a.ADR_ID = c.ADR_ID_LIVRAISON
      WHERE c.COM_ID = :cid
      LIMIT 1
    ", [':cid'=>$comId]);
}

if (!$adresse && columnExists($pdo, 'COMMANDE', 'ADR_ID')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
      FROM COMMANDE c
      JOIN ADRESSE a ON a.ADR_ID = c.ADR_ID
      WHERE c.COM_ID = :cid
      LIMIT 1
    ", [':cid'=>$comId]);
}

if (!$adresse && !empty($head['LIV_ID']) && columnExists($pdo, 'LIVRAISON', 'ADR_ID')) {
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
      FROM LIVRAISON l
      JOIN ADRESSE a ON a.ADR_ID = l.ADR_ID
      WHERE l.LIV_ID = :lid
      LIMIT 1
    ", [':lid'=>(int)$head['LIV_ID']]);
}

if (!$adresse) {
    // Fallback: dernière adresse de type LIVRAISON pour ce client
    $adresse = fetchOne($pdo, "
      SELECT a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
      FROM ADRESSE_CLIENT ac
      JOIN ADRESSE a ON a.ADR_ID = ac.ADR_ID
      WHERE ac.PER_ID = :per AND a.ADR_TYPE = 'LIVRAISON'
      ORDER BY a.ADR_ID DESC
      LIMIT 1
    ", [':per'=>$perId]);
}

/* ===== 3) Lignes produits ===== */
$items = [];
$st = $pdo->prepare("
  SELECT 
    cp.PRO_ID,
    p.PRO_NOM,
    p.PRO_PRIX AS prix_u,
    COALESCE(cp.CP_QTE_COMMANDEE, 1) AS qte
  FROM COMMANDE_PRODUIT cp
  JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
  WHERE cp.COM_ID = :cid
  ORDER BY p.PRO_NOM
");
$st->execute([':cid'=>$comId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== 4) (Optionnel) Suppléments ===== */
$supps = [];
try {
    $st = $pdo->prepare("
  SELECT 
    s.SUP_NOM,
    s.SUP_PRIX_UNITAIRE AS prix_u,
    COALESCE(cs.CS_QTE_COMMANDEE, 1) AS qte
  FROM COMMANDE_SUPP cs
  JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
  WHERE cs.COM_ID = :cid
  ORDER BY s.SUP_NOM
");
    $st->execute([':cid'=>$comId]);
    $supps = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* table absente -> on ignore */ }

/* ===== 5) (Optionnel) Emballages ===== */
$embs = [];
$sqlEmb = "
  SELECT 
    e.EMB_NOM,        
    0 AS prix_u,
    1 AS qte
  FROM COMMANDE_EMBALLAGE ce
  JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
  WHERE ce.COM_ID = :cid
  ORDER BY e.EMB_NOM
";
$st = $pdo->prepare($sqlEmb);
$st->execute([':cid'=>$comId]);
$embs = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== 6) Totaux ===== */
$fmt = fn($n) => number_format((float)$n, 2, '.', ' ') . ' CHF';
$subtotal = 0.0; foreach ($items as $it){ $subtotal += (float)$it['prix_u'] * (int)$it['qte']; }
$suppTotal= 0.0; foreach ($supps as $sp){ $suppTotal+= (float)$sp['prix_u'] * (int)$sp['qte']; }
$embTotal = 0.0; foreach ($embs as $em){ $embTotal += (float)$em['prix_u'] * (int)$em['qte']; }
$grandTotal = $subtotal + $suppTotal + $embTotal;
$paidAmount = isset($paiement['PAI_MONTANT']) ? (float)$paiement['PAI_MONTANT'] : null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Détails commande #<?= (int)$head['COM_ID'] ?></title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <style>
        :root{
            --bordeaux:#5C0012; --bordeaux-2:#8A1B2E; --ink:#1b1b1b; --paper:#fff; --muted:#6b6b6b;
            --radius:20px; --shadow:0 10px 30px rgba(0,0,0,.12);
        }
        body{ background:#faf7f8; color:var(--ink); font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; }
        .container{ max-width:1100px; margin:24px auto; padding:0 16px; }
        h1{ font-size:1.6rem; color:var(--bordeaux); margin:8px 0 16px; }
        .grid{ display:grid; grid-template-columns: 1.2fr .8fr; gap:18px; }
        .card{ background:var(--paper); border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; }
        .meta{ display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px; }
        .meta div{ background:#f7f1f2; border-radius:14px; padding:10px 12px; }
        .meta b{ display:block; font-size:.85rem; color:var(--muted); }
        table{ width:100%; border-collapse:collapse; }
        th, td{ padding:10px 8px; border-bottom:1px solid #eee; text-align:left; }
        th{ font-size:.9rem; color:#444; }
        .right{ text-align:right; }
        .section-title{ color:var(--bordeaux-2); font-size:1.1rem; margin:8px 0 12px; }
        .totals{ margin-top:12px; }
        .totals .row{ display:flex; justify-content:space-between; padding:6px 0; }
        .totals .grand{ border-top:1px solid #eee; margin-top:6px; padding-top:10px; font-weight:700; }
        .badge{ display:inline-block; padding:6px 10px; border-radius:999px; font-size:.85rem; background:#f1e7e9; color:var(--bordeaux-2); }
        .back{ display:inline-block; margin-bottom:12px; text-decoration:none; color:var(--bordeaux-2); }
        .addr{ line-height:1.4; color:#333; }
        @media(max-width:900px){ .grid{ grid-template-columns:1fr; } }
        .btn{ display:inline-block; padding:10px 14px; border-radius:12px; background:var(--bordeaux); color:#fff; text-decoration:none; }
        .muted{ color:var(--muted); font-size:.9rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container" aria-label="Détails commande">
    <a class="back" href="<?= $BASE ?>info_perso.php">← Retour</a>
    <h1>Commande #<?= (int)$head['COM_ID'] ?></h1>

    <div class="grid">
        <!-- Colonne gauche: lignes -->
        <section class="card">
            <div class="meta">
                <div><b>Date</b><?= h(date('d.m.Y', strtotime((string)$head['COM_DATE']))) ?></div>
                <div><b>Statut</b><span class="badge"><?= h((string)$head['COM_STATUT']) ?></span></div>
                <div><b>Paiement</b><?= h((string)($paiement['PAI_MODE'] ?? '—')) ?> — <?= h((string)($paiement['PAI_STATUT'] ?? '—')) ?></div>
                <div><b>Livraison</b><?= h((string)($livraison['LIV_STATUT'] ?? '—')) ?></div>
            </div>

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
                <h2 class="section-title" style="margin-top:16px">Suppléments</h2>
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
                <h2 class="section-title" style="margin-top:16px">Emballages</h2>
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
                <?php if ($suppTotal>0): ?><div class="row"><span>Suppléments</span><strong><?= h($fmt($suppTotal)) ?></strong></div><?php endif; ?>
                <?php if ($embTotal>0):  ?><div class="row"><span>Emballages</span><strong><?= h($fmt($embTotal)) ?></strong></div><?php endif; ?>
                <div class="row grand"><span>Total</span><strong><?= h($fmt($grandTotal)) ?></strong></div>
                <?php if ($paidAmount !== null): ?>
                    <div class="row"><span class="muted">Montant facturé</span><strong><?= h($fmt($paidAmount/100)) ?></strong></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Colonne droite: adresse + actions -->
        <aside class="card">
            <h2 class="section-title">Adresse de livraison</h2>
            <?php if ($adresse): ?>
                <p class="addr">
                    <?= h((string)$adresse['ADR_RUE']) ?> <?= h((string)$adresse['ADR_NUMERO']) ?><br>
                    <?= h((string)$adresse['ADR_NPA']) ?> <?= h((string)$adresse['ADR_VILLE']) ?><br>
                    <?= h((string)$adresse['ADR_PAYS']) ?>
                </p>
            <?php else: ?>
                <p class="muted">Aucune adresse de livraison enregistrée pour cette commande.</p>
            <?php endif; ?>

            <div style="margin-top:16px">
                <a class="btn" href="<?= $BASE ?>info_perso.php">Mes commandes</a>
            </div>

            <!-- 🔽 Nouveau bouton PDF -->
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
