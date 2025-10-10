<?php
// /site/pages/mes_commandes.php
declare(strict_types=1);
session_start();

if (empty($_SESSION['per_id'])) { header('Location: interface_connexion.php'); exit; }
$perId = (int)$_SESSION['per_id'];

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($s){ return $s ? date('d.m.Y', strtotime($s)) : '—'; }

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT COUNT(*) FROM COMMANDE WHERE PER_ID = :per AND COM_ARCHIVE = 0");
$st->execute([':per'=>$perId]);
$total = (int)$st->fetchColumn();

$sql = "
  SELECT COM_ID, COM_DATE, COM_STATUT
  FROM COMMANDE
  WHERE PER_ID = :per AND COM_ARCHIVE = 0
  ORDER BY COM_DATE DESC, COM_ID DESC
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
    <title>Mes commandes — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <style>
        :root { --header-h: 72px; }
        /* ===== Layout : footer collé en bas, contenu un peu plus bas ===== */
        html, body { height: 100%; }
        .page-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .page-content { flex: 1; }               /* prend tout l’espace restant */
        .wrap-orders {                           /* marge sous le header */
            max-width: 980px; margin: 0 auto; padding: calc(var(--header-h) + 16px) 16px 24px;
        }
        /* Si ton header est fixe et fait ~64px, monte à 64px+ pour padding-top */

        /* ===== Carte blanche propre ===== */
        .wrap-orders h1{color:#8A1B2E;margin:0 0 18px;text-align:center}
        .orders-card{
            background:#fff !important; color:#111 !important;
            border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.08);
            overflow:hidden;
        }
        .orders-card .card-head{
            padding:14px 16px; border-bottom:1px solid #e5e7eb;
            display:flex; align-items:center; justify-content:space-between; gap:12px;
        }
        .orders-card .card-head .count{color:#6b7280}
        .orders-card .card-body{padding:8px 16px 14px;background:#fff}

        table.orders{width:100%;border-collapse:separate;border-spacing:0}
        table.orders thead th{
            text-transform:uppercase; font-size:12px; letter-spacing:.3px;
            color:#6b7280; text-align:left; padding:10px 8px; border-bottom:1px solid #e5e7eb; background:#fff;
        }
        table.orders tbody td{padding:12px 8px; border-bottom:1px solid #f1f3f5; color:#1f2937; background:#fff}
        table.orders tbody tr:hover{background:#fafafa}
        table.orders tbody tr:last-child td{border-bottom:0}

        .btn{border:1px solid #8A1B2E;color:#8A1B2E;background:#fff;padding:8px 12px;border-radius:999px;
            font-weight:700;text-decoration:none;display:inline-block}
        .btn:hover{background:#f9f2f3}
        .btn.small{padding:6px 10px;border-radius:10px}

        .pager{display:flex;gap:6px;align-items:center;justify-content:center;margin:16px 0 6px}
        .pager a,.pager span{padding:6px 10px;border-radius:10px;border:1px solid #e5e7eb;text-decoration:none;color:#374151;background:#fff}
        .pager .cur{border-color:#8A1B2E;color:#8A1B2E;font-weight:700}
    </style>
</head>
<body>

<div class="page-shell">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="page-content">
        <div class="wrap-orders">
            <h1>Mes commandes</h1>

            <div class="orders-card">
                <div class="card-head">
                    <div class="count"><?= (int)$total ?> commande(s)</div>
                    <a class="btn small" href="<?= $BASE ?>info_perso.php">Retour au profil</a>
                </div>

                <div class="card-body">
                    <?php if (!$rows): ?>
                        <p>Aucune commande pour l’instant.</p>
                    <?php else: ?>
                        <table class="orders">
                            <thead>
                            <tr>
                                <th style="width:12%">#</th>
                                <th style="width:25%">Date</th>
                                <th>Statut</th>
                                <th style="width:18%"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= (int)$r['COM_ID'] ?></td>
                                    <td><?= h(fmtDate($r['COM_DATE'])) ?></td>
                                    <td><?= h($r['COM_STATUT'] ?: '—') ?></td>
                                    <td><a class="btn small" href="<?= $BASE ?>detail_commande.php?com_id=<?= (int)$r['COM_ID'] ?>">Détails</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php
                        $pages = max(1, (int)ceil($total / $perPage));
                        if ($pages > 1):
                            ?>
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
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</div>

</body>
</html>
