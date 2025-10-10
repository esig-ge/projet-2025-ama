<?php
// /site/pages/mes_commandes.php
declare(strict_types=1);
session_start();

/* ===== Bases + garde ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if (empty($_SESSION['per_id'])) { header('Location: '.$BASE.'interface_connexion.php'); exit; }
$perId = (int)$_SESSION['per_id'];

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($s){ return $s ? date('d.m.Y', strtotime($s)) : '—'; }

/* ===== Pagination ===== */
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM COMMANDE WHERE PER_ID=? AND COM_ARCHIVE=0")
    ->execute([$perId]) ?: 0;
$st = $pdo->prepare("SELECT COM_ID, COM_DATE, COM_STATUT
                     FROM COMMANDE
                     WHERE PER_ID=? AND COM_ARCHIVE=0
                     ORDER BY COM_DATE DESC, COM_ID DESC
                     LIMIT :lim OFFSET :off");
$st->bindValue(1, $perId, PDO::PARAM_INT);
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$pages = max(1, (int)ceil($total / $perPage));
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes commandes — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleInfoPerso.css">
    <style>
        .table { width:100%; border-collapse:collapse; }
        .table th, .table td { padding:10px 12px; border-bottom:1px solid #eee; }
        .pager { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
        .pager a, .pager span { padding:6px 10px; border:1px solid #8A1B2E; border-radius:8px; text-decoration:none; }
        .pager .active { background:#8A1B2E; color:#fff; border-color:#8A1B2E; }
        .btn { border:1px solid #8A1B2E; color:#8A1B2E; padding:6px 10px; border-radius:8px; text-decoration:none; }
        .btn:hover { background:#f9f2f3; }
    </style>
</head>
<body>
<?php include __DIR__.'/includes/header.php'; ?>

<main class="container" style="margin-top:20px">
    <h1>Mes commandes</h1>
    <p><a class="btn" href="<?= $BASE ?>info_perso.php">← Retour à mon compte</a></p>

    <?php if (!$rows): ?>
        <p>Aucune commande.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th style="width:10%">#</th><th style="width:25%">Date</th><th>Statut</th><th style="width:20%"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int)$r['COM_ID'] ?></td>
                    <td><?= h(fmtDate($r['COM_DATE'])) ?></td>
                    <td><?= h($r['COM_STATUT'] ?: '—') ?></td>
                    <td><a class="btn" href="<?= $BASE ?>detail_commande.php?com_id=<?= (int)$r['COM_ID'] ?>">Détails</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pagination">
                <?php for ($i=1; $i<=$pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $BASE ?>mes_commandes.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
