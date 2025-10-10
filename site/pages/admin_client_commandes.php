<?php
// /site/pages/admin_client_commandes.php
declare(strict_types=1);
session_start();

/* =========================
   0) Accès admin + base URL
   ========================= */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé à l’administrateur'); }

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* =========================
   1) Connexion BDD + helpers
   ========================= */
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =========================
   2) Paramètres
   ========================= */
$perId   = (int)($_GET['per_id'] ?? 0);
if ($perId <= 0) { http_response_code(400); exit('Paramètre per_id manquant.'); }

$q       = trim($_GET['q'] ?? '');          // recherche libre (id/statut)
$status  = trim($_GET['statut'] ?? '');     // filtre statut
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int)($_GET['pp'] ?? 20)));

$sort    = $_GET['sort'] ?? 'date';
$allowed = [
    'date'  => 'c.COM_DATE',
    'id'    => 'c.COM_ID',
    'total' => 'COALESCE(c.COM_MONTANT_TOTAL,0)'
];
$orderBy = $allowed[$sort] ?? $allowed['date'];

$dirOrd  = strtoupper($_GET['dir'] ?? 'DESC');
$dirOrd  = in_array($dirOrd, ['ASC','DESC'], true) ? $dirOrd : 'DESC';

/* =========================
   3) Infos client (header)
   ========================= */
$st = $pdo->prepare("
    SELECT p.PER_ID, p.PER_NOM, p.PER_PRENOM, p.PER_EMAIL
    FROM PERSONNE p INNER JOIN CLIENT c ON c.PER_ID = p.PER_ID
    WHERE p.PER_ID = :id
");
$st->execute([':id'=>$perId]);
$client = $st->fetch(PDO::FETCH_ASSOC);
if (!$client) { http_response_code(404); exit('Client introuvable.'); }

/* =========================
   4) WHERE dynamique
   ========================= */
$where = 'c.PER_ID = :pid';
$params = [':pid'=>$perId];

if ($q !== '') {
    $where .= " AND (CAST(c.COM_ID AS CHAR) LIKE :q OR c.COM_STATUT LIKE :q)";
    $params[':q'] = '%'.$q.'%';
}
if ($status !== '') {
    $where .= " AND c.COM_STATUT = :st";
    $params[':st'] = $status;
}

/* =========================
   5) Export CSV
   ========================= */
if (isset($_GET['export']) && $_GET['export']==='1') {
    $sqlCsv = "
        SELECT c.COM_ID, c.COM_DATE, c.COM_STATUT, COALESCE(c.COM_MONTANT_TOTAL,0) AS TOTAL_TTC
        FROM COMMANDE c
        WHERE $where
        ORDER BY $orderBy $dirOrd
    ";
    $st = $pdo->prepare($sqlCsv);
    foreach ($params as $k=>$v) $st->bindValue($k,$v);
    $st->execute();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes_client_'.$perId.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['COM_ID','Date','Statut','Total TTC (CHF)'], ';');
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$r['COM_ID'], $r['COM_DATE'], $r['COM_STATUT'], number_format((float)$r['TOTAL_TTC'],2,'.',"'")], ';');
    }
    fclose($out);
    exit;
}

/* =========================
   6) Compte + pagination
   ========================= */
$st = $pdo->prepare("SELECT COUNT(*) FROM COMMANDE c WHERE $where");
foreach ($params as $k=>$v) $st->bindValue($k,$v);
$st->execute();
$total = (int)$st->fetchColumn();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

/* =========================
   7) Récup commandes page
   ========================= */
$sql = "
    SELECT c.COM_ID, c.COM_DATE, c.COM_STATUT, COALESCE(c.COM_MONTANT_TOTAL,0) AS TOTAL_TTC
    FROM COMMANDE c
    WHERE $where
    ORDER BY $orderBy $dirOrd
    LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function qurl(array $merge=[]): string {
    $all = array_merge($_GET, $merge);
    return '?' . http_build_query($all);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DK Bloom — Admin : Commandes client #<?= h($client['PER_ID']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{--bordeaux:#8A1B2E;--bordeaux-2:#5C0012;--bg:#faf6f7;--card:#fff;--ring:#eee;--text:#222;--muted:#666}
        *{box-sizing:border-box}
        body{margin:0;padding:24px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
        .container{max-width:1100px;margin:0 auto}
        .card{background:var(--card);border:1px solid var(--ring);border-radius:14px;box-shadow:0 10px 24px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
        .topbar{display:flex;justify-content:space-between;align-items:baseline;padding:16px;border-bottom:1px solid #f0e1e6;background:#fff}
        h1{margin:0;color:var(--bordeaux-2)}
        .grid{padding:16px}
        .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0 18px}
        input[type="text"],select{padding:10px 12px;border:1px solid #ddd;border-radius:10px}
        .btn{padding:10px 14px;border:1px solid var(--bordeaux);background:linear-gradient(180deg,var(--bordeaux),var(--bordeaux-2));color:#fff;border-radius:10px;text-decoration:none;display:inline-block}
        .btn.secondary{background:#fff;color:var(--bordeaux)}
        table{width:100%;border-collapse:collapse}
        thead th{background:#f8ebef;color:#4a0c19;font-weight:600;text-align:left;padding:10px;border-bottom:1px solid #e9d3d9}
        tbody td{padding:10px;border-bottom:1px solid #f0e1e6}
        .pagination{display:flex;gap:6px;flex-wrap:wrap;margin:16px 0 0;align-items:center}
        .page{padding:8px 12px;border:1px solid #ddd;border-radius:8px;text-decoration:none;color:#333;background:#fff}
        .page.active{background:var(--bordeaux);color:#fff;border-color:var(--bordeaux)}
    </style>
</head>
<body>
<div class="container">

    <div class="card topbar">
        <h1>Commandes — <?= h($client['PER_NOM']) ?> <?= h($client['PER_PRENOM']) ?> (ID <?= h($client['PER_ID']) ?>)</h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn secondary" href="<?= h($BASE.'admin_clients.php') ?>">← Clients</a>
            <a class="btn" href="<?= h($BASE.'admin_client_detail.php?per_id='.$client['PER_ID']) ?>">Fiche client</a>
        </div>
    </div>

    <div class="grid">
        <form class="toolbar" method="get" action="">
            <input type="hidden" name="per_id" value="<?= h($client['PER_ID']) ?>">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Recherche (ID, statut)…">
            <select name="statut">
                <option value="">Tous statuts</option>
                <?php foreach (['En préparation','En attente d\'expédition','Expédié','Livré','Annulé'] as $st): ?>
                    <option value="<?= h($st) ?>" <?= $status===$st?'selected':'' ?>><?= h($st) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <option value="date"  <?= $sort==='date'?'selected':'' ?>>Trier par date</option>
                <option value="id"    <?= $sort==='id'?'selected':'' ?>>Trier par ID</option>
                <option value="total" <?= $sort==='total'?'selected':'' ?>>Trier par total</option>
            </select>
            <select name="dir">
                <option value="DESC" <?= $dirOrd==='DESC'?'selected':'' ?>>Descendant</option>
                <option value="ASC"  <?= $dirOrd==='ASC'?'selected':''  ?>>Ascendant</option>
            </select>
            <select name="pp" title="Par page">
                <?php foreach ([10,20,30,50,100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $perPage===$opt?'selected':'' ?>><?= $opt ?>/page</option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Appliquer</button>
            <a class="btn secondary" href="<?= h(qurl(['export'=>1])) ?>">Exporter CSV</a>
        </form>

        <?php if (empty($rows)): ?>
            <p style="color:#666">Aucune commande trouvée.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Total TTC</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>#<?= h($r['COM_ID']) ?></td>
                        <td><?= h($r['COM_DATE']) ?></td>
                        <td><?= h($r['COM_STATUT']) ?></td>
                        <td><?= number_format((float)$r['TOTAL_TTC'], 2, '.', "'") ?> CHF</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $prev = max(1, $page-1);
            $next = min($pages, $page+1);
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="page" href="<?= h(qurl(['page'=>1])) ?>">« Première</a>
                    <a class="page" href="<?= h(qurl(['page'=>$prev])) ?>">‹ Précédente</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page-2);
                $end   = min($pages, $page+2);
                for ($i=$start; $i<=$end; $i++):
                    ?>
                    <a class="page <?= $i===$page?'active':'' ?>" href="<?= h(qurl(['page'=>$i])) ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a class="page" href="<?= h(qurl(['page'=>$next])) ?>">Suivante ›</a>
                    <a class="page" href="<?= h(qurl(['page'=>$pages])) ?>">Dernière »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
