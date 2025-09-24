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
        <a class="nav-item" href="<?= $BASE ?>admin_catalogue.php"><span class="ico">💐</span> <span>Produits</span></a>
        <a class="nav-item" href="<?= $BASE ?>admin_commande.php"><span class="ico">🧾</span> <span>Commandes</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminClients.php"><span class="ico">👤</span> <span>Clients</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminPromos.php"><span class="ico">🏷️</span> <span>Promotions</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminAvis.php"><span class="ico">⭐</span> <span>Avis</span></a>
        <a class="nav-item" href="<?= $BASE ?>admin_messages.php"><span class="ico">📩</span> <span>Messages</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminParametres.php"><span class="ico">⚙️</span> <span>Paramètres</span></a>
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
            <div class="kpi-label">Commandes (7 jours)</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['orders_week']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label">Revenu (7 jours)</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['revenue_week']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label">Panier moyen</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['avg_basket']) ?></div>
            <div class="kpi-trend spark" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label">Produits en catalogue</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['products']) ?></div>
            <div class="kpi-trend bar" aria-hidden="true"></div>
        </article>
        <article class="kpi-card">
            <div class="kpi-label">Clients</div>
            <div class="kpi-value"><?= htmlspecialchars((string)$kpi['clients']) ?></div>
            <div class="kpi-trend bar" aria-hidden="true"></div>
        </article>
        <article class="kpi-card alert">
            <div class="kpi-label">Alertes stock</div>
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
                <h2>Raccourcis</h2>
            </div>
            <div class="quick-actions">
                <a class="qa" href="<?= $BASE ?>adminProduits.php">+ Ajouter un produit</a>
                <a class="qa" href="<?= $BASE ?>adminPromos.php">Créer un code promo</a>
                <a class="qa" href="<?= $BASE ?>adminClients.php">Lister les clients</a>
                <a class="qa" href="<?= $BASE ?>adminAvis.php">Modérer les avis</a>
                <a class="qa" href="<?= $BASE ?>adminParametres.php">Paramètres</a>
            </div>
        </article>
    </section>

    <section class="grid-2">
        <article class="card">
            <div class="card-head">
                <h2>Ventes du mois (placeholder)</h2>
            </div>
            <div class="chart-placeholder">Graphique à ajouter ici</div>
        </article>

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
</main>

<script>
    document.getElementById('burger')?.addEventListener('click', () => {
        document.body.classList.toggle('aside-closed');
    });
</script>

</body>
</html>
