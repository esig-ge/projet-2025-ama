<?php
// /site/pages/adminAccueil.php
session_start();

/* ===== Accès simple ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) {
    http_response_code(403);
    echo "<h1 style='font-family:Arial,sans-serif;margin:48px;text-align:center'>Accès réservé à l’administrateur</h1>";
    exit;
}

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';


/* ===== Connexion BDD ===== */
$pdo = null;
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    if ($pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Throwable $e) { /* silencieux */ }

/* ===== Helpers ===== */
function kpi_try(PDO $pdo = null, string $sql = null, array $p = []) {
    if (!$pdo || !$sql) return null;
    try { $st=$pdo->prepare($sql); $st->execute($p); return $st->fetchColumn(); }
    catch(Throwable $e){ return null; }
}
$today   = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekAgo = (new DateTimeImmutable('-7 days'))->format('Y-m-d');

/* ===== KPIs ===== */
$kpi = [
    'orders_week'   => '–',
    'revenue_week'  => '–',
    'avg_basket'    => '–',
    'products'      => '–',
    'clients'       => '–',
    'stock_alerts'  => '–'
];

if ($pdo) {
    // 1) Nombre de commandes sur 7 jours
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM COMMANDE WHERE COM_DATE >= :d", [':d'=>$weekAgo]);
    if ($v !== null) $kpi['orders_week'] = (int)$v;

    // 2) Chiffre d'affaires 7 jours (sum lignes × prix)
    $sqlRevenue = "
      SELECT COALESCE(SUM(cp.CP_QTE_COMMANDEE * p.PRO_PRIX),0)
      FROM COMMANDE c
      JOIN COMMANDE_PRODUIT cp ON cp.COM_ID = c.COM_ID
      JOIN PRODUIT p          ON p.PRO_ID  = cp.PRO_ID
      WHERE c.COM_DATE >= :d
    ";
    $v = kpi_try($pdo, $sqlRevenue, [':d'=>$weekAgo]);
    if ($v !== null) $kpi['revenue_week'] = number_format((float)$v, 2, '.', ' ') . " CHF";

    // 3) Panier moyen 7 jours (AVG du total par commande)
    $sqlAvg = "
      SELECT COALESCE(AVG(t.total),0) FROM (
        SELECT c.COM_ID, SUM(cp.CP_QTE_COMMANDEE * p.PRO_PRIX) AS total
        FROM COMMANDE c
        JOIN COMMANDE_PRODUIT cp ON cp.COM_ID = c.COM_ID
        JOIN PRODUIT p          ON p.PRO_ID  = cp.PRO_ID
        WHERE c.COM_DATE >= :d
        GROUP BY c.COM_ID
      ) t
    ";
    $v = kpi_try($pdo, $sqlAvg, [':d'=>$weekAgo]);
    if ($v !== null) $kpi['avg_basket'] = number_format((float)$v, 2, '.', ' ') . " CHF";

    // 4) Nombre de produits au catalogue
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM PRODUIT");
    if ($v !== null) $kpi['products'] = (int)$v;

    // 5) Nombre de clients
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM CLIENT");
    if ($v !== null) $kpi['clients'] = (int)$v;

    // 6) Alertes stock (≤ 5) sur FLEUR, BOUQUET, SUPPLEMENT, EMBALLAGE, COFFRET
    $sqlStockAlerts = "
      SELECT
        COALESCE( (SELECT COUNT(*) FROM FLEUR      WHERE FLE_QTE_STOCK      <= 5), 0 ) +
        COALESCE( (SELECT COUNT(*) FROM BOUQUET    WHERE BOU_QTE_STOCK      <= 5), 0 ) +
        COALESCE( (SELECT COUNT(*) FROM SUPPLEMENT WHERE SUP_QTE_STOCK      <= 5), 0 ) +
        COALESCE( (SELECT COUNT(*) FROM EMBALLAGE  WHERE EMB_QTE_STOCK      <= 5), 0 ) +
        COALESCE( (SELECT COUNT(*) FROM COFFRET    WHERE COF_QTE_STOCK      <= 5), 0 )
    ";
    $v = kpi_try($pdo, $sqlStockAlerts);
    if ($v !== null) $kpi['stock_alerts'] = (int)$v;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$admId = (int)($_SESSION['adm_id'] ?? 0); // pour persister les todos par admin

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Tableau de bord Admin</title>
    <link rel="icon" href="<?= $BASE ?>img/favicon.ico">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_admin.css">
</head>
<body class="adm">
<body class="adm">
<aside class="adm-sidebar">
    <div class="brand">
        <img src="<?= $BASE ?>img/logo.jpg" alt="DK Bloom" class="brand-logo">
        <span class="brand-name">DK Bloom</span>
    </div>
    <nav class="adm-nav">
        <a class="nav-item active" href="<?= $BASE ?>adminAccueil.php">
            <span class="ico">🏠</span> <span>Dashboard</span>
        </a>
        <a class="nav-item" href="<?= $BASE ?>admin_clients.php"><span class="ico">👤</span> <span>Clients</span></a>
        <a class="nav-item" href="<?= $BASE ?>admin_modifier_article.php"><span class="ico">💐</span> <span>Produits</span></a>
        <a class="nav-item" href="<?= $BASE ?>admin_commande.php"><span class="ico">🧾</span> <span>Commandes</span></a>
        <a class="nav-item" href="<?= $BASE ?>admin_livraisons.php"><span class="ico">📦</span> <span>Livraisons</span></a>

    </nav>
    <div class="adm-footer">© <?= date('Y') ?> DK Bloom</div>
</aside>

<main class="adm-main">
    <header class="adm-topbar">
        <button class="burger" id="burger" aria-label="Menu">☰</button>
        <div class="welcome">
            <h1>Tableau de bord</h1>
            <p>Bienvenue au dashboard administrateur!</p>
        </div>
        <div class="top-actions">
            <a class="btn ghost" href="<?= $BASE ?>index.php">Voir le site</a>
            <a class="btn" href="<?= $BASE ?>admin_deconnexion.php">Se déconnecter</a>

        </div>
    </header>

    <section class="kpi-grid">
        <article class="kpi-card">
            <div class="kpi-label"  style="color: black;">Commandes (7 jours)</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['orders_week']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label" style="color: black;">Revenu (7 jours)</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['revenue_week']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label" style="color: black;">Panier moyen</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['avg_basket']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label" style="color: black;">Produits en catalogue</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['products']) ?></div>
            <div class="kpi-trend bar" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label" style="color: black;">Clients</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['clients']) ?></div>
            <div class="kpi-trend bar" aria-hidden="true"></div>
        </article>
        <article class="kpi-card alert">
            <div class="kpi-label" style="color: black;">Alertes stocks</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['stock_alerts']) ?></div>
            <div class="kpi-badge">⚠️ À vérifier</div>
        </article>
    </section>

    <section class="grid-2">
        <article class="card">
            <div class="card-head">
                <h2>Commandes récentes</h2>
                <a class="link" href="<?= $BASE ?>admin_commande.php">Tout voir</a>
            </div>
            <div class="table like">
                <div class="row head"><div>#</div><div>Date</div><div>Client</div><div>Total</div><div>Statut</div></div>
                <?php if ($pdo): ?>
                    <?php
                    try {
                        // On calcule le total par commande via les lignes
                        $sql = "
                          SELECT
                            c.COM_ID,
                            c.COM_DATE,
                            COALESCE(CONCAT(pe.PER_PRENOM, ' ', pe.PER_NOM), '—') AS client,
                            COALESCE(SUM(cp.CP_QTE_COMMANDEE * p.PRO_PRIX), 0) AS total,
                            c.COM_STATUT
                          FROM COMMANDE c
                          LEFT JOIN CLIENT   cl ON cl.PER_ID = c.PER_ID
                          LEFT JOIN PERSONNE pe ON pe.PER_ID = cl.PER_ID
                          LEFT JOIN COMMANDE_PRODUIT cp ON cp.COM_ID = c.COM_ID
                          LEFT JOIN PRODUIT p          ON p.PRO_ID  = cp.PRO_ID
                          GROUP BY c.COM_ID
                          ORDER BY c.COM_DATE DESC
                          LIMIT 6
                        ";
                        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

                        if ($rows) {
                            foreach ($rows as $r) {
                                $id = (int)$r['COM_ID'];
                                $d  = htmlspecialchars(substr((string)$r['COM_DATE'],0,16));
                                $cli= htmlspecialchars((string)$r['client']);
                                $t  = number_format((float)$r['total'], 2, '.', ' ') . ' CHF';
                                $s  = htmlspecialchars((string)$r['COM_STATUT']);
                                echo "<div class='row'><div>$id</div><div>$d</div><div>$cli</div><div>$t</div><div>$s</div></div>";
                            }
                        } else {
                            echo "<div class='row empty'>Aucune commande pour le moment.</div>";
                        }
                    } catch (Throwable $e) {
                        echo "<div class='row empty'>Problème de lecture de la BDD</div>";
                    }
                    ?>
                <?php else: ?>
                    <div class="row empty">BDD non disponible en environnement actuel.</div>
                <?php endif; ?>
            </div>
        </article>

        <article class="card">
            <div class="card-head">
                <h2>To-Do List</h2>
            </div>

            <div class="todo">
                <div class="todo-top">
                    <div class="progress-wrap" aria-label="Avancement">
                        <div class="progress-bar" id="todo-progress"></div>
                    </div>
                    <div class="progress-meta"><span id="todo-count">0/0</span> fait</div>
                </div>

                <form class="todo-add" id="todo-add">
                    <input class="todo-input" id="todo-input" type="text" placeholder="Ajouter une tâche… (Entrée)" maxlength="140" />
                    <button class="btn" type="submit">Ajouter</button>
                </form>

                <div class="todo-filters">
                    <button class="chip is-active" data-filter="all">Tout</button>
                    <button class="chip" data-filter="open">À faire</button>
                    <button class="chip" data-filter="done">Fait</button>
                </div>

                <ul class="todo-list" id="todo-list" aria-live="polite"></ul>

                <div class="todo-actions">
                    <button class="btn ghost" id="todo-clear-done">Supprimer cochées</button>
                    <button class="btn ghost" id="todo-clear-all">Tout effacer</button>
                </div>
            </div>
        </article>

    </section>

    <section class="grid-2">
        <?php
        /* ====== Filtres ====== */
        // années disponibles dans la BDD
        $years = $pdo->query("SELECT DISTINCT YEAR(COM_DATE) AS y FROM COMMANDE ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        $yearSelected = (int)($_GET['year'] ?? (date('Y')));
        $excludeCanceled = (isset($_GET['excl']) ? (int)$_GET['excl'] : 1); // 1 = exclure (défaut)
        $STATUT_ANNULE = 'annulee'; // adapte si besoin

        // garde de sécurité si l’année n’existe pas
        if ($years && !in_array($yearSelected, array_map('intval', $years), true)) {
            $yearSelected = (int)$years[0];
        }

        /* ====== Données par mois ====== */
        $sqlMonths = "
  SELECT 
      DATE_FORMAT(c.COM_DATE, '%Y-%m') AS ym,
      SUM(cp.CP_QTE_COMMANDEE * p.PRO_PRIX) AS total_chf,
      COUNT(DISTINCT c.COM_ID) AS nb_commandes,
      SUM(cp.CP_QTE_COMMANDEE) AS produits_vendus
  FROM COMMANDE c
  JOIN COMMANDE_PRODUIT cp ON cp.COM_ID = c.COM_ID
  JOIN PRODUIT p           ON p.PRO_ID  = cp.PRO_ID
  WHERE YEAR(c.COM_DATE) = :y
    ".($excludeCanceled ? "AND (c.COM_STATUT IS NULL OR c.COM_STATUT <> :ann)" : "")."
  GROUP BY ym
  ORDER BY ym ASC
";
        $st = $pdo->prepare($sqlMonths);
        $st->bindValue(':y', $yearSelected, PDO::PARAM_INT);
        if ($excludeCanceled) $st->bindValue(':ann', $STATUT_ANNULE, PDO::PARAM_STR);
        $st->execute();
        $months = $st->fetchAll(PDO::FETCH_ASSOC);

        /* ====== Produit le plus vendu / mois ====== */
        $sqlTop = "
  SELECT x.PRO_ID, pr.PRO_NOM, x.qte
  FROM (
      SELECT cp.PRO_ID, SUM(cp.CP_QTE_COMMANDEE) AS qte
      FROM COMMANDE c
      JOIN COMMANDE_PRODUIT cp ON cp.COM_ID = c.COM_ID
      WHERE YEAR(c.COM_DATE)=:y
        AND DATE_FORMAT(c.COM_DATE,'%Y-%m') = :ym
        ".($excludeCanceled ? "AND (c.COM_STATUT IS NULL OR c.COM_STATUT <> :ann)" : "")."
      GROUP BY cp.PRO_ID
      ORDER BY qte DESC
      LIMIT 1
  ) x
  JOIN PRODUIT pr ON pr.PRO_ID = x.PRO_ID
";
        $stTop = $pdo->prepare($sqlTop);

        // label FR
        function fr_month_label_from_ym(string $ym): string {
            static $mois = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
            [$y,$m] = array_map('intval', explode('-', $ym));
            return ($mois[$m] ?? $ym) . " $y";
        }

        // préparer lignes + totaux année
        $rows = [];
        $sumCA=0.0; $sumCmd=0; $sumProd=0;

        foreach ($months as $m) {
            $ym   = $m['ym'];
            $ca   = (float)$m['total_chf'];
            $cmd  = (int)$m['nb_commandes'];
            $prod = (int)$m['produits_vendus'];

            $params = [':y'=>$yearSelected, ':ym'=>$ym];
            if ($excludeCanceled) $params[':ann'] = $STATUT_ANNULE;
            $stTop->execute($params);
            $top = $stTop->fetch(PDO::FETCH_ASSOC);

            $rows[] = [
                'label'   => fr_month_label_from_ym($ym),
                'total'   => $ca,
                'cmd'     => $cmd,
                'prod'    => $prod,
                'top_nom' => $top['PRO_NOM'] ?? '—',
                'top_qte' => isset($top['qte']) ? (int)$top['qte'] : 0,
            ];

            $sumCA  += $ca;
            $sumCmd += $cmd;
            $sumProd+= $prod;
        }
        ?>

        <article class="card" style="margin-top:16px">
            <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2 style="margin:0">Ventes par mois — <?= (int)$yearSelected ?></h2>

                <!-- Filtres -->
                <form method="get" style="display:flex;gap:8px;align-items:center;">
                    <label for="year" style="font-size:.9rem;color:#555;">Année</label>
                    <select id="year" name="year" onchange="this.form.submit()" style="padding:6px 8px;border:1px solid #ddd;border-radius:8px;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= (int)$y ?>" <?= ((int)$y === $yearSelected ? 'selected' : '') ?>><?= (int)$y ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label style="display:flex;gap:6px;align-items:center;font-size:.9rem;color:#555;">
                        <input type="checkbox" name="excl" value="1" <?= $excludeCanceled ? 'checked' : '' ?> onchange="this.form.submit()">
                        Exclure annulées
                    </label>
                </form>
            </div>

            <table class="table-ventes" style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08)">
                <thead>
                <tr style="background:#8A1B2E;color:#fff">
                    <th style="text-align:left;padding:10px">Mois</th>
                    <th style="text-align:right;padding:10px">Total ventes (CHF)</th>
                    <th style="text-align:right;padding:10px">Commandes</th>
                    <th style="text-align:right;padding:10px">Produits vendus</th>
                    <th style="text-align:left;padding:10px">Produit le plus vendu</th>
                    <th style="text-align:right;padding:10px">Qté</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align:center;padding:14px;color:#777">Aucune donnée pour cette année.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="padding:10px"><?= htmlspecialchars($r['label']) ?></td>
                            <td style="padding:10px;text-align:right"><?= number_format($r['total'], 2, '.', "'") ?> CHF</td>
                            <td style="padding:10px;text-align:right"><?= (int)$r['cmd'] ?></td>
                            <td style="padding:10px;text-align:right"><?= (int)$r['prod'] ?></td>
                            <td style="padding:10px"><?= htmlspecialchars($r['top_nom']) ?></td>
                            <td style="padding:10px;text-align:right"><?= (int)$r['top_qte'] ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Ligne Total année -->
                    <tr style="background:#fff6f7;font-weight:700">
                        <td style="padding:10px">Total année <?= (int)$yearSelected ?></td>
                        <td style="padding:10px;text-align:right"><?= number_format($sumCA, 2, '.', "'") ?> CHF</td>
                        <td style="padding:10px;text-align:right"><?= (int)$sumCmd ?></td>
                        <td style="padding:10px;text-align:right"><?= (int)$sumProd ?></td>
                        <td style="padding:10px" colspan="2"></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </article>

        <style>
            .table-ventes {
                width:100%;
                border-collapse:collapse;
                background:#fff;
                box-shadow:0 2px 6px rgba(0,0,0,0.1);
                border-radius:8px;
                overflow:hidden;
            }
            .table-ventes th {
                background:#8A1B2E;
                color:#fff;
                text-align:left;
                padding:10px;
            }
            .table-ventes td {
                padding:10px;
                border-bottom:1px solid #eee;
            }

        </style>

    <?php
        /* ===== Liste des produits en alerte stock (TOP 5) ===== */
        $threshold   = 5;
        $lowStocks   = [];
        $lowCount    = 0;
        $STOCKS_URL  = $BASE . 'admin_stock.php'; // <-- adapte si ta page des stocks est ailleurs

        if ($pdo) {
            try {
                // Compteur total pour "Voir plus"
                $sqlCount = "
      SELECT COUNT(*) FROM (
        SELECT b.BOU_QTE_STOCK AS stock FROM BOUQUET b
        UNION ALL SELECT f.FLE_QTE_STOCK FROM FLEUR f
        UNION ALL SELECT s.SUP_QTE_STOCK FROM SUPPLEMENT s
        UNION ALL SELECT e.EMB_QTE_STOCK FROM EMBALLAGE e
        UNION ALL SELECT c.COF_QTE_STOCK FROM COFFRET c
      ) t
      WHERE CAST(t.stock AS SIGNED) <= :t
    ";
                $stc = $pdo->prepare($sqlCount);
                $stc->execute([':t' => (int)$threshold]);
                $lowCount = (int)$stc->fetchColumn();

                // Top 5 détaillé
                $sqlLow = "
      SELECT type, nom, stock
      FROM (
        SELECT 'Bouquet'    AS type, p.PRO_NOM AS nom, b.BOU_QTE_STOCK AS stock
        FROM BOUQUET b JOIN PRODUIT p ON p.PRO_ID = b.PRO_ID
        UNION ALL
        SELECT 'Fleur'      AS type, p.PRO_NOM AS nom, f.FLE_QTE_STOCK AS stock
        FROM FLEUR f   JOIN PRODUIT p ON p.PRO_ID = f.PRO_ID
        UNION ALL
        SELECT 'Supplément' AS type, s.SUP_NOM AS nom, s.SUP_QTE_STOCK AS stock
        FROM SUPPLEMENT s
        UNION ALL
        SELECT 'Emballage'  AS type, e.EMB_NOM AS nom, e.EMB_QTE_STOCK AS stock
        FROM EMBALLAGE e
        UNION ALL
        SELECT 'Coffret'    AS type, p.PRO_NOM AS nom, c.COF_QTE_STOCK AS stock
        FROM COFFRET c JOIN PRODUIT p ON p.PRO_ID = c.PRO_ID
      ) t
      WHERE CAST(t.stock AS SIGNED) <= :t
      ORDER BY CAST(t.stock AS SIGNED) ASC, t.nom ASC
      LIMIT 5
    ";
                $st = $pdo->prepare($sqlLow);
                $st->execute([':t' => (int)$threshold]);
                $lowStocks = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                echo "<!-- lowStock error: ".htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')." -->";
                $lowStocks = [];
            }
        }
        ?>

        <style>
            .status-badge{display:inline-block;padding:.25rem .5rem;border-radius:999px;font-size:.8rem;font-weight:600}
            .status-oos{background:#fde7e7;color:#a30000}          /* rupture (rouge pastel) */
            .status-low{background:#fff3e0;color:#9a5a00}          /* bientôt rupture (orange pastel) */
        </style>

        <article class="card">
            <div class="card-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <h2 style="margin:0">Produits en alerte stock</h2>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="muted">Seuil ≤ <?= (int)$threshold ?> unités</div>
                    <?php if ($lowCount > 5): ?>
                        <a class="link" href="<?= htmlspecialchars($STOCKS_URL) ?>">Voir plus (<?= (int)$lowCount ?>)</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$pdo): ?>
                <ul class="list"><li>BDD non disponible.</li></ul>
            <?php elseif (empty($lowStocks)): ?>
                <ul class="list"><li>Aucune alerte stock 🎉</li></ul>
            <?php else: ?>
                <div class="table like">
                    <div class="row head">
                        <div>Type</div><div>Produit</div><div>Stock</div><div>Statut</div>
                    </div>
                    <?php foreach ($lowStocks as $it):
                        $type  = htmlspecialchars($it['type']);
                        $nom   = htmlspecialchars($it['nom']);
                        $stock = (int)$it['stock'];
                        $isOOS = ($stock <= 0);
                        $statusLabel = $isOOS ? 'Rupture de stock' : 'Bientôt en rupture';
                        $statusClass = $isOOS ? 'status-oos' : 'status-low';
                        $stockStyle  = $isOOS ? ' style="color:#a30000;font-weight:600"' : '';
                        ?>
                        <div class="row">
                            <div><?= $type ?></div>
                            <div><?= $nom ?></div>
                            <div<?= $stockStyle ?>><?= $stock ?></div>
                            <div><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

    </section>
    <footer class="adm-bottom">Dernière mise à jour: <?= date('d.m.Y H:i') ?></footer>

    <style>
        .todo{--bord:#e7e7ea;--ink:#4a2b2b;--rose:#8d0e0e;--rose-2:#b24a4a;--bg:#fff7f8}
        .todo{display:flex;flex-direction:column;gap:12px}
        .todo-top{display:flex;align-items:center;gap:12px}
        .progress-wrap{flex:1;height:10px;background:#f1e7e9;border-radius:999px;overflow:hidden;box-shadow:inset 0 0 0 1px #f0dfe3}
        .progress-bar{height:100%;width:0%;background:linear-gradient(90deg,var(--rose),var(--rose-2));transition:width .25s ease}
        .progress-meta{font-size:.9rem;color:#7b5b5b}
        .todo-add{display:flex;gap:8px}
        .todo-input{flex:1;padding:10px 12px;border:1px solid var(--bord);border-radius:12px;background:#fff;outline:none}
        .todo-input:focus{border-color:#d7b6bb;box-shadow:0 0 0 4px #f7e8eb}
        .todo-filters{display:flex;gap:8px;flex-wrap:wrap;margin-top:2px}
        .chip{padding:6px 10px;border:1px solid var(--bord);border-radius:999px;background:#fff;color:var(--ink);cursor:pointer;font-size:.9rem}
        .chip.is-active{border-color:#e7c6cc;background:var(--bg)}
        .todo-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
        .todo-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--bord);border-radius:14px;background:#fff;transition:background .2s}
        .todo-item.dragging{opacity:.6}
        .todo-check{width:20px;height:20px;border:1.5px solid #caa6ab;border-radius:6px;display:grid;place-items:center;cursor:pointer;flex:0 0 20px;background:#fff}
        .todo-check input{appearance:none;width:0;height:0;position:absolute}
        .todo-check .mark{opacity:0;transform:scale(.7);transition:all .2s}
        .todo-check.checked{background:var(--bg);border-color:#b87b85}
        .todo-check.checked .mark{opacity:1;transform:scale(1)}
        .todo-label{flex:1}
        .todo-item.done .todo-label{color:#9a7a7a;text-decoration:line-through}
        .todo-del{border:none;background:transparent;cursor:pointer;font-size:18px;line-height:1;color:#9a646a}
        .todo-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:2px}
        .btn.ghost{background:#fff;border:1px solid var(--bord);color:#7b5b5b}
    </style>

</main>

<script>
    document.getElementById('burger')?.addEventListener('click', () => {
        document.body.classList.toggle('aside-closed');
    });
</script>

<script>
    (() => {
        const API = '<?= $BASE ?>api/todo.php';
        const $list   = document.getElementById('todo-list');
        const $input  = document.getElementById('todo-input');
        const $form   = document.getElementById('todo-add');
        const $count  = document.getElementById('todo-count');
        const $bar    = document.getElementById('todo-progress');
        const $chips  = document.querySelectorAll('.todo-filters .chip');
        const $clearD = document.getElementById('todo-clear-done');
        const $clearA = document.getElementById('todo-clear-all');

        let todos = [];
        let filter = 'all';

        // --- util fetch ---
        async function post(data) {
            const body = new URLSearchParams(data);
            const r = await fetch(API, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
            return r.json();
        }
        async function load() {
            const r = await fetch(API + '?op=list');
            const j = await r.json();
            todos = (j.items || []).map(x => ({
                id: String(x.TODO_ID),
                text: x.TEXTE,
                done: Number(x.DONE) === 1
            }));
            render();
        }

        // --- events ---
        $form.addEventListener('submit', async (e)=>{
            e.preventDefault();
            const t = ($input.value||'').trim();
            if (!t) return;
            $input.value = '';
            // Optimiste
            const tempId = 'tmp_'+Date.now();
            todos.push({id:tempId, text:t, done:false});
            render();
            // Persist
            const j = await post({op:'add', text:t});
            if (j.ok && j.item) {
                const i = todos.findIndex(x=>x.id===tempId);
                if (i>=0) todos[i].id = String(j.item.TODO_ID || j.item.todo_id || j.item.id || todos[i].id);
            } else {
                // rollback
                todos = todos.filter(x=>x.id!==tempId);
                alert("Erreur ajout tâche");
            }
            render();
        });

        $chips.forEach(ch=>{
            ch.addEventListener('click', ()=>{
                $chips.forEach(c=>c.classList.remove('is-active'));
                ch.classList.add('is-active');
                filter = ch.dataset.filter;
                render();
            });
        });

        $clearD.addEventListener('click', async ()=>{
            todos = todos.filter(t=>!t.done);
            render();
            await post({op:'clear_done'});
        });

        $clearA.addEventListener('click', async ()=>{
            if (!confirm('Tout effacer ?')) return;
            todos = [];
            render();
            await post({op:'clear_all'});
        });

        // --- DnD re-order ---
        let dragId = null;
        $list.addEventListener('dragstart', e=>{
            const li = e.target.closest('li.todo-item');
            dragId = li?.dataset.id || null;
            li?.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        $list.addEventListener('dragend', e=>{
            e.target.closest('li.todo-item')?.classList.remove('dragging');
        });
        $list.addEventListener('dragover', e=>{
            e.preventDefault();
            const after = getDragAfterElement($list, e.clientY);
            const dragging = $list.querySelector('.dragging');
            if(!dragging) return;
            if(after==null) $list.appendChild(dragging); else $list.insertBefore(dragging, after);
        });
        $list.addEventListener('drop', async ()=>{
            const ids = [...$list.querySelectorAll('.todo-item')].map(li=>li.dataset.id);
            // local reorder
            todos.sort((a,b)=> ids.indexOf(a.id) - ids.indexOf(b.id));
            render();
            // persist order
            const form = new URLSearchParams(); form.append('op','reorder');
            ids.forEach(id=>form.append('ids[]', id));
            await fetch(API, {method:'POST', body:form});
        });

        // --- render helpers ---
        function render(){
            const filtered = todos.filter(t=>{
                if(filter==='open') return !t.done;
                if(filter==='done') return t.done;
                return true;
            });

            $list.innerHTML = '';
            filtered.forEach(t=>{
                const li = document.createElement('li');
                li.className = 'todo-item'+(t.done?' done':'');
                li.draggable = true;
                li.dataset.id = t.id;

                const check = document.createElement('button');
                check.className = 'todo-check'+(t.done?' checked':'');
                check.innerHTML = '<span class="mark">✔</span>';
                check.addEventListener('click', async ()=>{
                    t.done = !t.done; render();
                    await post({op:'toggle', id:t.id, done: t.done ? 1 : 0});
                });

                const lbl = document.createElement('div');
                lbl.className = 'todo-label';
                lbl.textContent = t.text;
                lbl.title = 'Double-clic pour renommer';
                lbl.addEventListener('dblclick', async ()=>{
                    const nv = prompt('Modifier la tâche :', t.text);
                    if (nv===null) return;
                    t.text = (nv||'').trim(); render();
                    await post({op:'edit', id:t.id, text:t.text});
                });

                const del = document.createElement('button');
                del.className = 'todo-del';
                del.title = 'Supprimer';
                del.innerHTML = '🗑️';
                del.addEventListener('click', async ()=>{
                    const keep = todos; // rollback possible
                    todos = todos.filter(x=>x.id!==t.id); render();
                    const j = await post({op:'delete', id:t.id});
                    if (!j.ok) { todos = keep; render(); alert('Suppression échouée'); }
                });

                li.appendChild(check);
                li.appendChild(lbl);
                li.appendChild(del);
                $list.appendChild(li);
            });

            const total = todos.length;
            const done  = todos.filter(t=>t.done).length;
            $count.textContent = `${done}/${total}`;
            $bar.style.width = total? `${(done/total)*100}%` : '0%';
        }

        function getDragAfterElement(container, y){
            const els = [...container.querySelectorAll('.todo-item:not(.dragging)')];
            return els.reduce((closest, child)=>{
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height/2;
                if(offset < 0 && offset > closest.offset) {
                    return { offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // go
        load();
    })();
</script>


</body>
</html>
