<?php
// /site/pages/admin_stock.php
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
                $st = $pdo->prepare("UPDATE BOUQUET SET BOU_QTE_STOCK=:s WHERE PRO_ID=:id");
                break;
            case 'Fleur':
                $st = $pdo->prepare("UPDATE FLEUR SET FLE_QTE_STOCK=:s WHERE PRO_ID=:id");
                break;
            case 'Supplément':
                $st = $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK=:s WHERE SUP_ID=:id");
                break;
            case 'Emballage':
                $st = $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK=:s WHERE EMB_ID=:id");
                break;
            case 'Coffret':
                $st = $pdo->prepare("UPDATE COFFRET SET COF_QTE_STOCK=:s WHERE PRO_ID=:id");
                break;
            default:
                throw new RuntimeException('Type inconnu.');
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
if ($view === 'oos')       $whereView = " WHERE CAST(t.stock AS SIGNED) = 0 ";
elseif ($view === 'low')   $whereView = " WHERE CAST(t.stock AS SIGNED) BETWEEN 1 AND :t ";

$whereSearch = "";
if ($q !== '') {
    $whereSearch = " AND t.nom LIKE :q ";
    $bind[':q'] = '%'.$q.'%';
}

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
$stc = $pdo->prepare($sqlCount);
$stc->execute($bind);
$total = (int)$stc->fetchColumn();

$sqlRows = "
  SELECT type, nom, stock, id
  ".$sqlBase.$whereView.$whereSearch."
  ORDER BY CAST(t.stock AS SIGNED) ASC, t.nom ASC
  LIMIT {$limit} OFFSET {$off}
";
$str = $pdo->prepare($sqlRows);
$str->execute($bind);
$rows = $str->fetchAll(PDO::FETCH_ASSOC);

/* ===== Pagination ===== */
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
        /* ===== Mise en page ===== */
        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin:12px 0}
        .filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .flash{margin:8px 0;padding:10px 12px;border-radius:8px;background: #ffffff;border:1px solid #cfe6ff;color:#064a7a}
        .pagination{display:flex;gap:6px;justify-content:flex-end;margin-top:10px}
        .pagination a,.pagination span{padding:6px 10px;border-radius:6px;border:1px solid #eee;text-decoration:none}
        .pagination .current{background:#f5f5f5;font-weight:600}

        /* ===== Thème bordeaux pour le tableau ===== */
        .table.like {width:100%;border-collapse:collapse;margin-top:20px;border-radius:8px;overflow:hidden}
        .table.like .row{display:grid;grid-template-columns:1fr 2fr 1fr 2fr 2fr;padding:10px;border-bottom:1px solid #fff;background:#800000;color:#fff}
        .table.like .row.head{background:#5c0000;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .table.like .row:nth-child(even):not(.head){background:#990000}
        .table.like .row.empty{background:#800000;text-align:center;font-style:italic}
        .table.like .row div{padding:6px 10px}

        /* ===== Statuts ===== */
        .status-badge{border-radius:6px;padding:3px 10px;font-size:.85em;font-weight:700;white-space:nowrap}
        .status-oos{background:#ffebeb;color:#a30000}
        .status-low{background:#fff2e0;color:#9a5a00}

        /* ===== Formulaire ligne ===== */
        .stock-input{width:90px}
        .btn, button{background:#fff;color:#800000;border:1px solid #fff;padding:6px 14px;border-radius:4px;cursor:pointer;font-weight:700;white-space:nowrap}
        .btn:hover, button:hover{background:#f5f5f5;color:#5c0000}
        .btn.ghost{background:transparent;border-color:#fff;color:#fff}
        .btn.update{min-width:170px;} /* bouton plus long */
    </style>
</head>
<body class="adm">
<main class="container" style="padding:16px 20px">

    <h1>Gestion des produits en alerte stock</h1>

    <?php if(!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <form class="toolbar" method="get">
        <div class="filters">
            <select name="view">
                <option value="all" <?= $view==='all'?'selected':'' ?>>Tous (≤ <?= (int)$threshold ?>)</option>
                <option value="oos" <?= $view==='oos'?'selected':'' ?>>Rupture (= 0)</option>
                <option value="low" <?= $view==='low'?'selected':'' ?>>Bientôt en rupture (1…<?= (int)$threshold ?>)</option>
            </select>
            <label>Seuil
                <input type="number" min="1" name="t" value="<?= (int)$threshold ?>" disabled style="width:60px">
            </label>
            <input type="text" name="q" placeholder="Recherche produit…" value="<?= h($q) ?>" />
            <button class="btn" type="submit">Filtrer</button>
            <a class="btn ghost" href="<?= h($_SERVER['PHP_SELF']) ?>">Réinit.</a>
        </div>
        <div><?= (int)$total ?> résultat(s)</div>
    </form>

    <div class="table like">
        <div class="row head"><div>Type</div><div>Produit</div><div>Stock</div><div>Statut</div><div>Action</div></div>

        <?php if (!$rows): ?>
            <div class="row empty">Aucun article à afficher.</div>
        <?php else: foreach ($rows as $r):
            $type  = $r['type'];
            $nom   = $r['nom'];
            $stock = (int)$r['stock'];
            $id    = (int)$r['id'];
            $isOOS = ($stock <= 0);
            $badge = $isOOS ? '<span class="status-badge status-oos">Rupture de stock</span>'
                : '<span class="status-badge status-low">Bientôt en rupture</span>';
            $stockStyle = $isOOS ? ' style="color:#ffcccc;font-weight:700"' : '';
            ?>
            <div class="row">
                <div><?= h($type) ?></div>
                <div><?= h($nom) ?></div>
                <div<?= $stockStyle ?>><?= $stock ?></div>
                <div><?= $badge ?></div>
                <div>
                    <form method="post" style="display:flex;gap:8px;align-items:center">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="type" value="<?= h($type) ?>">
                        <input type="hidden" name="id"   value="<?= $id ?>">
                        <input class="stock-input" type="number" name="stock" min="0" value="<?= max(0,$stock) ?>">
                        <button class="btn update" type="submit">Mettre à jour</button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($i=1; $i<=$pages; $i++):
                $params = $_GET; $params['page']=$i; $url = $_SERVER['PHP_SELF'].'?'.http_build_query($params);
                ?>
                <?= $i === $page ? '<span class="current">'.$i.'</span>' : '<a href="'.h($url).'">'.$i.'</a>' ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <p style="margin-top:16px">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Retour au dashboard</a>
    </p>
</main>
</body>
</html>
