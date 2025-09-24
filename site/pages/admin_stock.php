<?php
// /site/pages/adminStocks.php
session_start();

/* ===== Accès simple ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé.'); }

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Connexion BDD ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Paramètres ===== */
$threshold = 5;
$view  = $_GET['view']  ?? 'all';  // all | oos | low
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$off   = ($page - 1) * $limit;

/* ===== POST: mise à jour stock ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stock') {
    $type  = $_POST['type']  ?? '';
    $id    = (int)($_POST['id'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));

    try {
        switch ($type) {
            case 'Bouquet':
                $st = $pdo->prepare("UPDATE BOUQUET SET BOU_QTE_STOCK=:s WHERE PRO_ID=:id"); break;
            case 'Fleur':
                $st = $pdo->prepare("UPDATE FLEUR SET FLE_QTE_STOCK=:s WHERE PRO_ID=:id"); break;
            case 'Supplément':
                $st = $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK=:s WHERE SUP_ID=:id"); break;
            case 'Emballage':
                $st = $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK=:s WHERE EMB_ID=:id"); break;
            case 'Coffret':
                $st = $pdo->prepare("UPDATE COFFRET SET COF_QTE_STOCK=:s WHERE PRO_ID=:id"); break;
            default: throw new RuntimeException('Type inconnu.');
        }
        $st->execute([':s'=>$stock, ':id'=>$id]);
        $_SESSION['flash'] = "Stock mis à jour.";
    } catch (Throwable $e) {
        $_SESSION['flash'] = "Erreur mise à jour : " . $e->getMessage();
    }
    // redirect pour éviter re-post
    $params = $_GET; $qs = http_build_query($params);
    header("Location: ".$_SERVER['PHP_SELF'].'?'.$qs);
    exit;
}

/* ===== Requête : UNION + filtres ===== */
$bind = [':t'=>$threshold];

$whereView = " WHERE CAST(t.stock AS SIGNED) <= :t ";
if ($view === 'oos')     $whereView = " WHERE CAST(t.stock AS SIGNED) = 0 ";
elseif ($view === 'low') $whereView = " WHERE CAST(t.stock AS SIGNED) BETWEEN 1 AND :t ";

$whereSearch = "";
if ($q !== '') { $whereSearch = " AND t.nom LIKE :q "; $bind[':q'] = '%'.$q.'%'; }

$sqlBase = "
  FROM (
    SELECT 'Bouquet' AS type, p.PRO_NOM AS nom, b.BOU_QTE_STOCK AS stock, b.PRO_ID AS id
    FROM BOUQUET b JOIN PRODUIT p ON p.PRO_ID=b.PRO_ID
    UNION ALL
    SELECT 'Fleur', p.PRO_NOM, f.FLE_QTE_STOCK, f.PRO_ID
    FROM FLEUR f JOIN PRODUIT p ON p.PRO_ID=f.PRO_ID
    UNION ALL
    SELECT 'Supplément', s.SUP_NOM, s.SUP_QTE_STOCK, s.SUP_ID
    FROM SUPPLEMENT s
    UNION ALL
    SELECT 'Emballage', e.EMB_NOM, e.EMB_QTE_STOCK, e.EMB_ID
    FROM EMBALLAGE e
    UNION ALL
    SELECT 'Coffret', p.PRO_NOM, c.COF_QTE_STOCK, c.PRO_ID
    FROM COFFRET c JOIN PRODUIT p ON p.PRO_ID=c.PRO_ID
  ) t
";

$sqlCount = "SELECT COUNT(*) ".$sqlBase.$whereView.$whereSearch;
$stc = $pdo->prepare($sqlCount); $stc->execute($bind); $total = (int)$stc->fetchColumn();

$sqlRows = "SELECT type, nom, stock, id ".$sqlBase.$whereView.$whereSearch."
            ORDER BY CAST(t.stock AS SIGNED) ASC, t.nom ASC
            LIMIT {$limit} OFFSET {$off}";
$str = $pdo->prepare($sqlRows); $str->execute($bind); $rows = $str->fetchAll(PDO::FETCH_ASSOC);

$pages = max(1, (int)ceil($total / $limit));
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des stocks — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_admin.css">
    <style>
        :root{
            --bg:#ffffff;
            --text: rgba(97, 2, 2, 0.71);
            --muted:#6b7280;
            --brand:#8b1c1c;         /* bordeaux */
            --brand-600:#6e1515;
            --brand-050:#fdeeee;
            --ok:#0b8f5a;
            --warn:#b46900;          /* badge bientôt rupture */
            --warn-bg:#fff4e5;
            --danger:#b11226;        /* badge rupture */
            --danger-bg:#ffe8ea;
            --line:#e5e7eb;
            --card:#ffffff;
            --shadow:0 10px 24px rgba(0,0,0,.08);
            --radius:14px;
        }
        body{background:var(--bg); color:var(--text);}
        .wrap{max-width:1150px;margin:26px auto;padding:0 16px;}
        h1{font-size:clamp(24px,2.4vw,34px);margin:0 0 16px;font-weight:800;color:#111}
        .sub{color:var(--muted);margin-bottom:18px}

        /* card */
        .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow);}
        .card-head{padding:14px 16px 10px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px}
        .card-body{padding:14px 16px 6px}

        /* toolbar */
        .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .toolbar .spacer{flex:1}
        select,input[type=text],input[type=number]{border:1px solid var(--line);border-radius:10px;padding:8px 10px;font-size:14px;background:#fff;color:var(--text)}
        input[type=text]{min-width:220px}

        /* table */
        .table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:12px;border:1px solid var(--line)}
        .table thead th{
            position:sticky; top:0; z-index:1;
            background:#fafafa; color:#111; font-weight:700; text-transform:uppercase; font-size:12px; letter-spacing:.3px;
            padding:10px 12px; border-bottom:1px solid var(--line);
        }
        .table tbody td{padding:12px;border-top:1px solid var(--line);vertical-align:middle;}
        .table tbody tr:nth-child(odd){background: rgb(255, 255, 255)
        }
        .table tbody tr:nth-child(even){background:#fcfcfc}
        .table tbody tr:hover{background: rgba(120, 4, 57, 0.17)
        }

        /* badges */
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
        .badge-warn{background:var(--warn-bg);color:var(--warn)}
        .badge-danger{background:var(--danger-bg);color:var(--danger)}

        /* stock color */
        .stock-0{color:var(--danger);font-weight:800}

        /* buttons */
        .btn{appearance:none;border:1px solid var(--brand);color:#fff;background:var(--brand);padding:8px 14px;border-radius:10px;font-weight:700;cursor:pointer;white-space:nowrap}
        .btn:hover{background:var(--brand-600);border-color:var(--brand-600)}
        .btn.ghost{background:#fff;color:var(--brand);border-color:var(--brand)}
        .btn.ghost:hover{background:#f9f5f5}
        .btn.update{min-width:175px}

        /* inline form */
        .row-form{display:flex;gap:8px;align-items:center}
        .row-form{display:flex;gap:8px;align-items:center}
        .row-form .stock-input{width:90px}

        /* flash */
        .flash{margin:14px 0 0;background:#eef7ff;border:1px solid #cfe6ff;color:#0c4a6e;padding:10px 12px;border-radius:10px}

        /* pagination */
        .pagination{display:flex;gap:6px;justify-content:flex-end;margin:14px 2px 6px}
        .pagination a,.pagination span{padding:7px 12px;border:1px solid var(--line);border-radius:10px;text-decoration:none;color:var(--text);font-weight:600}
        .pagination .current{background:#f3f4f6}
    </style>
</head>
<body>
<div class="wrap">

    <h1>Gestion des produits en alerte stock</h1>
    <p class="sub">Surveillez et ajustez rapidement les stocks critiques (≤ <?= (int)$threshold ?>).</p>

    <?php if(!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-top:16px">
        <div class="card-head">
            <form class="toolbar" method="get">
                <select name="view">
                    <option value="all" <?= $view==='all'?'selected':'' ?>>Tous (≤ <?= (int)$threshold ?>)</option>
                    <option value="oos" <?= $view==='oos'?'selected':'' ?>>Rupture (= 0)</option>
                    <option value="low" <?= $view==='low'?'selected':'' ?>>Bientôt en rupture (1…<?= (int)$threshold ?>)</option>
                </select>
                <span style="color:var(--muted)">Seuil</span>
                <input type="number" min="1" value="<?= (int)$threshold ?>" disabled style="width:70px">
                <input type="text" name="q" placeholder="Recherche produit…" value="<?= h($q) ?>">
                <button class="btn" type="submit">Filtrer</button>
                <a class="btn ghost" href="<?= h($_SERVER['PHP_SELF']) ?>">Réinit.</a>
            </form>
            <div class="muted" style="color:var(--muted)"><?= (int)$total ?> résultat(s)</div>
        </div>

        <div class="card-body" style="padding:0">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:14%">Type</th>
                    <th>Produit</th>
                    <th style="width:10%">Stock</th>
                    <th style="width:18%">Statut</th>
                    <th style="width:26%">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" style="text-align:center;padding:22px">Aucun article à afficher.</td></tr>
                <?php else: foreach ($rows as $r):
                    $type  = $r['type'];
                    $nom   = $r['nom'];
                    $stock = (int)$r['stock'];
                    $id    = (int)$r['id'];
                    $isOOS = ($stock <= 0);
                    $badge = $isOOS
                        ? '<span class="badge badge-danger">Rupture de stock</span>'
                        : '<span class="badge badge-warn">Bientôt en rupture</span>';
                    ?>
                    <tr>
                        <td><?= h($type) ?></td>
                        <td><?= h($nom) ?></td>
                        <td class="<?= $isOOS ? 'stock-0':'' ?>"><?= $stock ?></td>
                        <td><?= $badge ?></td>
                        <td>
                            <form method="post" class="row-form">
                                <input type="hidden" name="action" value="update_stock">
                                <input type="hidden" name="type" value="<?= h($type) ?>">
                                <input type="hidden" name="id"   value="<?= $id ?>">
                                <input class="stock-input" type="number" name="stock" min="0" value="<?= max(0,$stock) ?>">
                                <button class="btn update" type="submit">Mettre à jour</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php for ($i=1; $i<=$pages; $i++):
                    $params = $_GET; $params['page']=$i; $url = $_SERVER['PHP_SELF'].'?'.http_build_query($params); ?>
                    <?= $i === $page ? '<span class="current">'.$i.'</span>' : '<a href="'.h($url).'">'.$i.'</a>' ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:16px">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Retour au dashboard</a>
    </p>
</div>
</body>
</html>
