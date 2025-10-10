<?php
// /site/pages/mes_notifications.php
declare(strict_types=1);
session_start();

/* ===== Garde ===== */
if (empty($_SESSION['per_id'])) { header('Location: interface_connexion.php'); exit; }
$perId = (int)$_SESSION['per_id'];

/* ===== Base path ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== PDO ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDT($s){ return $s ? date('d.m.Y H:i', strtotime($s)) : '—'; }

/* ===== Actions POST ===== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // Marquer 1 notif comme lue
    if (isset($_POST['ack_one'])) {
        $nid = (int)($_POST['notif_id'] ?? 0);
        if ($nid>0) {
            $st = $pdo->prepare("UPDATE NOTIFICATION SET READ_AT = NOW()
                                 WHERE NOT_ID = :nid AND PER_ID = :per AND READ_AT IS NULL");
            $st->execute([':nid'=>$nid, ':per'=>$perId]);
        }
        header('Location: '.$BASE.'mes_notifications.php'); exit;
    }
    // Marquer tout comme lu
    if (isset($_POST['ack_all'])) {
        $st = $pdo->prepare("UPDATE NOTIFICATION SET READ_AT = NOW()
                             WHERE PER_ID = :per AND READ_AT IS NULL");
        $st->execute([':per'=>$perId]);
        header('Location: '.$BASE.'mes_notifications.php'); exit;
    }
}

/* ===== Pagination ===== */
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ===== Totaux ===== */
$st = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATION WHERE PER_ID = :per");
$st->execute([':per'=>$perId]);
$total = (int)$st->fetchColumn();

/* ===== Liste paginée (⚠️ un seul type de paramètre nommé) ===== */
$sql = "
  SELECT n.NOT_ID, n.PER_ID, n.COM_ID, n.NOT_TYPE, n.NOT_TEXTE,
         COALESCE(n.CREATED_AT, n.NOT_DATE) AS CREATED_AT,
         n.READ_AT,
         c.COM_RETRAIT_DEBUT, c.COM_RETRAIT_FIN
  FROM NOTIFICATION n
  LEFT JOIN COMMANDE c ON c.COM_ID = n.COM_ID
  WHERE n.PER_ID = :per
  ORDER BY COALESCE(n.CREATED_AT, n.NOT_DATE) DESC, n.NOT_ID DESC
  LIMIT {$perPage} OFFSET {$offset}
";
$st = $pdo->prepare($sql);
$st->execute([':per'=>$perId]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$pages = max(1, (int)ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes notifications — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <style>
        /* ----- Layout : footer en bas + marge sous header fixe ----- */
        html, body { height: 100%; }
        .page-shell { min-height: 100vh; display:flex; flex-direction:column; }
        .page-content { flex:1; }
        :root { --header-h: 72px; } /* ajuste à la hauteur réelle du header */
        .wrap-notifs { max-width: 980px; margin: 0 auto; padding: calc(var(--header-h) + 16px) 16px 24px; }
        h1 { color:#8A1B2E; text-align:center; margin:0 0 18px; }

        /* ----- Carte blanche ----- */
        .card{ background:#fff !important; color:#111 !important; border:1px solid #e5e7eb;
            border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.08); overflow:hidden; }
        .card-head{ padding:14px 16px; border-bottom:1px solid #e5e7eb; display:flex; gap:12px;
            align-items:center; justify-content:space-between; }
        .count{ color:#6b7280; }
        .btn{ border:1px solid #8A1B2E; color:#8A1B2E; background:#fff; padding:8px 12px; border-radius:999px;
            font-weight:700; text-decoration:none; display:inline-block; }
        .btn:hover{ background:#f9f2f3; }
        .btn.small{ padding:6px 10px; border-radius:10px; }

        /* ----- Liste ----- */
        .list{ list-style:none; margin:0; padding:0; }
        .item{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
            padding:12px 16px; border-bottom:1px solid #f1f3f5; background:#fff; }
        .item:last-child{ border-bottom:0; }
        .item.unread{ background:#fff8f9; }
        .title{ font-weight:700; color:#111; margin-bottom:2px; }
        .meta{ color:#6b7280; font-size:12px; }
        .actions{ white-space:nowrap; display:flex; gap:6px; }
        .dot{ width:10px; height:10px; border-radius:50%; background:#e0112b; display:inline-block; margin-right:6px; }

        /* ----- Pager ----- */
        .pager{ display:flex; gap:6px; align-items:center; justify-content:center; margin:16px 0 6px; }
        .pager a, .pager span { padding:6px 10px; border-radius:10px; border:1px solid #e5e7eb;
            text-decoration:none; color:#374151; background:#fff; }
        .pager .cur{ border-color:#8A1B2E; color:#8A1B2E; font-weight:700; }
    </style>
</head>
<body>

<div class="page-shell">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="page-content">
        <div class="wrap-notifs">
            <h1>Mes notifications</h1>

            <div class="card">
                <div class="card-head">
                    <div class="count"><?= (int)$total ?> notification(s)</div>
                    <div class="actions">
                        <a class="btn small" href="<?= $BASE ?>info_perso.php">Retour au profil</a>
                        <?php if ($total > 0): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="ack_all" value="1">
                                <button type="submit" class="btn small">Tout marquer comme lu</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$rows): ?>
                    <div style="padding:16px">Aucune notification.</div>
                <?php else: ?>
                    <ul class="list">
                        <?php foreach ($rows as $n):
                            $isUnread = empty($n['READ_AT']);
                            $deb = $n['COM_RETRAIT_DEBUT'] ? date('d.m.Y H:i', strtotime($n['COM_RETRAIT_DEBUT'])) : null;
                            $fin = $n['COM_RETRAIT_FIN']   ? date('d.m.Y H:i', strtotime($n['COM_RETRAIT_FIN']))   : null;
                            ?>
                            <li class="item <?= $isUnread ? 'unread' : '' ?>">
                                <div class="left">
                                    <div class="title">
                                        <?php if ($isUnread): ?><span class="dot"></span><?php endif; ?>
                                        <?= h($n['NOT_TEXTE']) ?><?php if($n['COM_ID']): ?> (commande #<?= (int)$n['COM_ID'] ?>)<?php endif; ?>
                                    </div>
                                    <div class="meta">
                                        <?= fmtDT($n['CREATED_AT']) ?>
                                        <?php if ($deb && $fin): ?> · Retrait: <?= h($deb) ?> → <?= h($fin) ?><?php endif; ?>
                                        <?php if (!$isUnread && $n['READ_AT']): ?> · Lu le <?= fmtDT($n['READ_AT']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <?php if ($isUnread): ?>
                                        <form method="post">
                                            <input type="hidden" name="ack_one" value="1">
                                            <input type="hidden" name="notif_id" value="<?= (int)$n['NOT_ID'] ?>">
                                            <button class="btn small" type="submit">Marquer lu</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($pages > 1): ?>
                        <div class="pager">
                            <?php if ($page > 1): ?>
                                <a href="?page=1">«</a>
                                <a href="?page=<?= $page-1 ?>">‹</a>
                            <?php endif; ?>
                            <span class="cur"><?= $page ?></span>
                            <?php if ($page < $pages): ?>
                                <a href="?page=<?= $page+1 ?>">›</a>
                                <a href="?page=<?= $pages ?>">»</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</div>

<!-- Optionnel : calcule auto de la hauteur du header fixe -->
<script>
    (function () {
        const header = document.querySelector('header, .header, #header');
        if (!header) return;
        const setPad = () => {
            const h = header.offsetHeight || 72;
            document.documentElement.style.setProperty('--header-h', h + 'px');
        };
        setPad();
        window.addEventListener('resize', setPad);
    })();
</script>
</body>
</html>
