<?php
// /site/pages/admin_clients.php
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

/* ===================================================
   2) Paramètres UI : recherche, tri, pagination, export
   =================================================== */
$q       = trim($_GET['q']   ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int)($_GET['pp'] ?? 20))); // 5..100

// Tri sécurisé (whitelist)
$sort    = $_GET['sort'] ?? 'nom';
$allowed = [
    'id'   => 'p.PER_ID',
    'nom'  => 'p.PER_NOM',
    'prenom' => 'p.PER_PRENOM',
    'email'  => 'p.PER_EMAIL'
];
$orderBy = $allowed[$sort] ?? $allowed['nom'];

$dirOrd  = strtoupper($_GET['dir'] ?? 'ASC');
$dirOrd  = in_array($dirOrd, ['ASC','DESC'], true) ? $dirOrd : 'ASC';

// Filtre WHERE (nom/prénom/email) — tables attendues: PERSONNE + CLIENT
$where = '1=1';
$params = [];

if ($q !== '') {
    $where .= ' AND (p.PER_NOM LIKE :q OR p.PER_PRENOM LIKE :q OR p.PER_EMAIL LIKE :q)';
    $params[':q'] = '%'.$q.'%';
}

/* =========================
   3) Export CSV (si demandé)
   ========================= */
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $sqlCsv = "
        SELECT p.PER_ID, p.PER_NOM, p.PER_PRENOM, p.PER_EMAIL
        FROM PERSONNE p
        INNER JOIN CLIENT c ON c.PER_ID = p.PER_ID
        WHERE $where
        ORDER BY $orderBy $dirOrd
    ";
    $st = $pdo->prepare($sqlCsv);
    foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
    $st->execute();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PER_ID','Nom','Prénom','Email'], ';');
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$r['PER_ID'], $r['PER_NOM'], $r['PER_PRENOM'], $r['PER_EMAIL']], ';');
    }
    fclose($out);
    exit;
}

/* =========================
   4) Compte total + pagination
   ========================= */
$sqlCount = "
    SELECT COUNT(*) AS N
    FROM PERSONNE p
    INNER JOIN CLIENT c ON c.PER_ID = p.PER_ID
    WHERE $where
";
$st = $pdo->prepare($sqlCount);
foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
$st->execute();
$total = (int)$st->fetchColumn();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

/* =========================
   5) Récup des clients page
   ========================= */
$sql = "
    SELECT
      p.PER_ID,
      p.PER_NOM,
      p.PER_PRENOM,
      p.PER_EMAIL
    FROM PERSONNE p
    INNER JOIN CLIENT c ON c.PER_ID = p.PER_ID
    WHERE $where
    ORDER BY $orderBy $dirOrd
    LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $st->bindValue($k, $v); }
$st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Génère URL avec paramètres (pour liens de pagination/sort)
function qurl(array $merge = []): string {
    $all = array_merge($_GET, $merge);
    return '?' . http_build_query($all);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DK Bloom — Admin : Clients</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bordeaux:#800000;
            --bordeaux-2:#5C0012;
            --bg:#faf6f7;
            --card:#ffffff;
            --text:#222;
            --muted:#666;
            --ring: rgba(0,0,0,0.08);
        }
        *{box-sizing:border-box}
        body{
            margin:0; padding:24px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #fde7ec 0, transparent 50%) no-repeat,
            radial-gradient(1200px 600px at 110% 10%, #ffeef2 0, transparent 50%) no-repeat,
            var(--bg);
            color:var(--text);
        }
        .container{max-width:1100px;margin:0 auto}
        h1{
            margin:0 0 18px; letter-spacing:.5px;
            color:var(--bordeaux-2)
        }
        .toolbar{
            display:flex; flex-wrap: wrap; gap:8px; align-items:center; justify-content:space-between;
            margin: 10px 0 18px;
        }
        .toolbar .left, .toolbar .right {display:flex; gap:8px; align-items:center}
        .card{
            background:var(--card);
            border:1px solid var(--ring);
            border-radius:14px;
            box-shadow:0 10px 24px rgba(0,0,0,.06);
            overflow:hidden;
        }
        form.search { display:flex; gap:8px; }
        input[type="text"]{
            padding:10px 12px; border:1px solid #ddd; border-radius:10px; min-width:260px;
        }
        select{
            padding:10px 12px; border:1px solid #ddd; border-radius:10px;
        }
        .btn{
            padding:10px 14px; border:1px solid var(--bordeaux);
            background:linear-gradient(180deg, #8A1B2E, #5C0012);
            color:#fff; border-radius:10px; text-decoration:none; display:inline-block;
        }
        .btn.secondary{
            background:#fff; color:var(--bordeaux);
            border:1px solid var(--bordeaux);
        }
        table{
            width:100%; border-collapse:collapse;
        }
        thead th{
            background: #f8ebef;
            color:#4a0c19; font-weight:600; text-align:left; padding:12px;
            border-bottom:1px solid #e9d3d9;
        }
        tbody td{
            padding:12px; border-bottom:1px solid #f0e1e6;
            vertical-align: top;
        }
        tbody tr:hover { background:#fff7f9; }
        .muted{ color:var(--muted); font-size:.92em }
        .meta { font-size:.92em; color:#3b3b3b }
        .pagination{
            display:flex; gap:6px; flex-wrap:wrap; margin:16px 0 0; align-items:center;
        }
        .page{
            padding:8px 12px; border:1px solid #ddd; border-radius:8px; text-decoration:none; color:#333; background:#fff;
        }
        .page.active{ background:var(--bordeaux); color:#fff; border-color:var(--bordeaux); }
        .count{ color:#555; }
        .grid { padding: 6px 16px 20px; }
        .topbar{
            display:flex; align-items:baseline; justify-content:space-between; gap:10px; padding:16px;
            border-bottom:1px solid #f0e1e6; background:#fff;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar card">
        <h1>Clients</h1>
        <div class="count"><?= h($total) ?> résultat<?= $total>1?'s':'' ?></div>
    </div>

    <div class="toolbar">
        <div class="left">
            <form class="search" method="get" action="">
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Recherche (nom, prénom, email)…">
                <select name="sort">
                    <option value="nom"   <?= $sort==='nom'?'selected':'' ?>>Tri par nom</option>
                    <option value="prenom"<?= $sort==='prenom'?'selected':'' ?>>Tri par prénom</option>
                    <option value="email" <?= $sort==='email'?'selected':'' ?>>Tri par email</option>
                    <option value="id"    <?= $sort==='id'?'selected':'' ?>>Tri par ID</option>
                </select>
                <select name="dir">
                    <option value="ASC"  <?= $dirOrd==='ASC'?'selected':'' ?>>Ascendant</option>
                    <option value="DESC" <?= $dirOrd==='DESC'?'selected':'' ?>>Descendant</option>
                </select>
                <select name="pp" title="Par page">
                    <?php foreach ([10,20,30,50,100] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage===$opt?'selected':'' ?>><?= $opt ?>/page</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Appliquer</button>
            </form>
        </div>
        <div class="right">
            <a class="btn secondary" href="<?= h(qurl(['export'=>1])) ?>">Exporter CSV</a>
            <a class="btn" href="<?= h($BASE.'adminAccueil.php') ?>">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <div class="grid">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom &amp; Prénom</th>
                    <th>Email</th>
                    <th class="muted">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4" class="muted">Aucun client trouvé.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="meta">#<?= h($r['PER_ID']) ?></span></td>
                        <td>
                            <strong><?= h($r['PER_NOM']) ?></strong> <?= h($r['PER_PRENOM']) ?><br>
                            <span class="muted">Pers. ID: <?= h($r['PER_ID']) ?></span>
                        </td>
                        <td>
                            <a href="mailto:<?= h($r['PER_EMAIL']) ?>"><?= h($r['PER_EMAIL']) ?></a>
                        </td>
                        <td>
                            <!-- Liens d’exemple (ajuste les routes si besoin) -->
                            <a class="page" href="<?= h($BASE.'admin_client_detail.php?per_id='.$r['PER_ID']) ?>">Voir</a>
                            <a class="page" href="<?= h($BASE.'admin_client_commandes.php?per_id='.$r['PER_ID']) ?>">Commandes</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="pagination">
                    <?php
                    $prev = max(1, $page-1);
                    $next = min($pages, $page+1);
                    ?>
                    <?php if ($page > 1): ?>
                        <a class="page" href="<?= h(qurl(['page'=>1])) ?>">« Première</a>
                        <a class="page" href="<?= h(qurl(['page'=>$prev])) ?>">‹ Précédente</a>
                    <?php endif; ?>

                    <?php
                    // petite fenêtre autour de la page courante
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
</div>
</body>
</html>
