<?php
// /site/pages/mes_notifications.php
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
/* Choix auto de la colonne de date */
function notif_ts_col(PDO $pdo): string {
    $st = $pdo->query("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='NOTIFICATION'
          AND COLUMN_NAME IN ('NOT_CREATED_AT','CREATED_AT','NOT_DATE')
    ");
    $cols = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    foreach (['NOT_CREATED_AT','CREATED_AT','NOT_DATE'] as $c) if (in_array($c,$cols,true)) return $c;
    return 'CREATED_AT';
}
$tsCol = notif_ts_col($pdo);

/* ===== Actions (marquer lu) ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ack_notif'])) {
    $nid = (int)($_POST['notif_id'] ?? 0);
    if ($nid>0) {
        $st = $pdo->prepare("UPDATE NOTIFICATION SET READ_AT=NOW() WHERE NOT_ID=:id AND PER_ID=:per AND READ_AT IS NULL");
        $st->execute([':id'=>$nid, ':per'=>$perId]);
    }
    header('Location: '.$BASE.'mes_notifications.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ack_all'])) {
    $pdo->prepare("UPDATE NOTIFICATION SET READ_AT=NOW() WHERE PER_ID=? AND READ_AT IS NULL")->execute([$perId]);
    header('Location: '.$BASE.'mes_notifications.php'); exit;
}

/* ===== Pagination ===== */
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM NOTIFICATION WHERE PER_ID=?")
    ->execute([$perId]) ?: 0;

/* Liste paginée */
$sql = "
 SELECT n.NOT_ID, n.COM_ID, n.NOT_TYPE, n.NOT_TEXTE, n.READ_AT,
        COALESCE(n.NOT_CREATED_AT, n.CREATED_AT, n.NOT_DATE) AS CREATED_AT,
        c.COM_RETRAIT_DEBUT, c.COM_RETRAIT_FIN
 FROM NOTIFICATION n
 LEFT JOIN COMMANDE c ON c.COM_ID = n.COM_ID
 WHERE n.PER_ID = ?
 ORDER BY n.`$tsCol` DESC, n.NOT_ID DESC
 LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
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
    <title>Mes notifications — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleInfoPerso.css">
    <style>
        .notif-list { list-style:none; margin:0; padding:0; }
        .item { border:1px solid #eee; border-radius:10px; padding:10px 12px; margin-bottom:10px; background:#fff; display:flex; justify-content:space-between; gap:10px; }
        .item.unread { background:#fff8f9; border-color:#f1d3d7; }
        .meta { color:#6b7280; font-size:12px; margin-top:2px; }
        .dot { width:10px; height:10px; border-radius:50%; background:#e0112b; display:inline-block; margin-right:8px; }
        .pager { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
        .pager a, .pager span { padding:6px 10px; border:1px solid #8A1B2E; border-radius:8px; text-decoration:none; }
        .pager .active { background:#8A1B2E; color:#fff; border-color:#8A1B2E; }
        .btn { border:1px solid #8A1B2E; color:#8A1B2E; padding:6px 10px; border-radius:8px; text-decoration:none }
        .btn:hover{ background:#f9f2f3; }
        .btn-ack{ background:#8A1B2E; color:#fff; border:none; padding:6px 10px; border-radius:8px; cursor:pointer; font-weight:700; }
        .btn-ack:hover{ background:#6e1522; }
        .head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    </style>
</head>
<body>
<?php include __DIR__.'/includes/header.php'; ?>

<main class="container" style="margin-top:20px">
    <div class="head">
        <h1>Mes notifications</h1>
        <div>
            <a class="btn" href="<?= $BASE ?>info_perso.php">← Retour à mon compte</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="ack_all" value="1">
                <button class="btn-ack" type="submit">Tout marquer comme lu</button>
            </form>
        </div>
    </div>

    <?php if (!$rows): ?>
        <p>Aucune notification.</p>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($rows as $n): $unread = empty($n['READ_AT']); ?>
                <li class="item <?= $unread ? 'unread':'' ?>">
                    <div>
                        <div style="font-weight:700">
                            <?php if ($unread): ?><span class="dot"></span><?php endif; ?>
                            <?= h($n['NOT_TEXTE']) ?><?php if ($n['COM_ID']): ?> (commande #<?= (int)$n['COM_ID'] ?>)<?php endif; ?>
                        </div>
                        <div class="meta">
                            <?= $n['CREATED_AT'] ? date('d.m.Y H:i', strtotime($n['CREATED_AT'])) : '' ?>
                            <?php if ($n['COM_RETRAIT_DEBUT'] && $n['COM_RETRAIT_FIN']): ?>
                                · Retrait: <?= h(date('d.m.Y H:i', strtotime($n['COM_RETRAIT_DEBUT']))) ?>
                                → <?= h(date('d.m.Y H:i', strtotime($n['COM_RETRAIT_FIN']))) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($unread): ?>
                            <form method="post">
                                <input type="hidden" name="ack_notif" value="1">
                                <input type="hidden" name="notif_id" value="<?= (int)$n['NOT_ID'] ?>">
                                <button class="btn-ack" type="submit">J’ai compris</button>
                            </form>
                        <?php else: ?>
                            <span class="meta">Lu</span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pagination">
                <?php for ($i=1; $i<=$pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $BASE ?>mes_notifications.php?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
