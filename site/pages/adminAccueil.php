<?php
// /site/pages/adminAccueil.php
session_start();

/* ===== 0) Accès (simple) =====
   Adapte selon ta logique: adm_id, is_admin, rôle, etc.
*/
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) {
    http_response_code(403);
    echo "<h1 style='font-family:Arial,sans-serif;margin:48px;text-align:center'>Accès réservé à l’administrateur</h1>";
    exit;
}

/* ===== 1) Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== 2) Connexion BDD ===== */
$pdo = null;
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    if ($pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Throwable $e) { /* silencieux: on affiche des tirets si indispo */ }

/* ===== 3) KPIs (tentatives souples + fallback) ===== */
function kpi_try(PDO $pdo = null, string $sql = null, array $p = []) {
    if (!$pdo || !$sql) return null;
    try { $st=$pdo->prepare($sql); $st->execute($p); $v=$st->fetchColumn(); return $v; }
    catch(Throwable $e){ return null; }
}
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekAgo = (new DateTimeImmutable('-7 days'))->format('Y-m-d');

$kpi = [
    'orders_week'   => '–',
    'revenue_week'  => '–',
    'avg_basket'    => '–',
    'products'      => '–',
    'clients'       => '–',
    'stock_alerts'  => '–'
];

/* ===== 3.a) Essaie des noms de colonnes "habituels"
   -> Si ta table/colonne diffère, remplace l’SQL dessous
*/
if ($pdo) {
    // commandes 7 jours
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo]);
    if ($v !== null) $kpi['orders_week'] = (int)$v;

    // chiffre d'affaires 7 jours (essaie com_total ou com_montant ou total)
    $v = kpi_try($pdo, "SELECT SUM(com_total) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo])
        ?? kpi_try($pdo, "SELECT SUM(com_montant) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo])
        ?? kpi_try($pdo, "SELECT SUM(total) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo]);
    if ($v !== null) $kpi['revenue_week'] = number_format((float)$v, 2, '.', ' ') . " CHF";

    // panier moyen (même logique de colonnes)
    $v = kpi_try($pdo, "SELECT AVG(com_total) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo])
        ?? kpi_try($pdo, "SELECT AVG(com_montant) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo])
        ?? kpi_try($pdo, "SELECT AVG(total) FROM COMMANDE WHERE com_date >= :d", [':d'=>$weekAgo]);
    if ($v !== null) $kpi['avg_basket'] = number_format((float)$v, 2, '.', ' ') . " CHF";

    // nb produits (essaie PRODUIT)
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM PRODUIT");
    if ($v !== null) $kpi['products'] = (int)$v;

    // nb clients (essaie CLIENT)
    $v = kpi_try($pdo, "SELECT COUNT(*) FROM CLIENT");
    if ($v !== null) $kpi['clients'] = (int)$v;

    // alertes stock (essaie stock <= 5) en supposant colonnes *_qte_stock
    $v = kpi_try($pdo, "
        SELECT SUM(x.c) FROM (
            SELECT COUNT(*) c FROM PRODUIT WHERE (pro_qte_stock <= 5) OR (pro_qte_stock IS NULL)
            UNION ALL
            SELECT COUNT(*) c FROM FLEUR   WHERE (fle_qte_stock <= 5) OR (fle_qte_stock IS NULL)
            UNION ALL
            SELECT COUNT(*) c FROM BOUQUET WHERE (bou_qte_stock <= 5) OR (bou_qte_stock IS NULL)
        ) t
    ");
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

    <link rel="stylesheet" href="<?= $BASE ?>css/style_admin.css">
    <link rel="icon" href="<?= $BASE ?>img/favicon.ico">
</head>
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
        <a class="nav-item" href="<?= $BASE ?>adminProduits.php"><span class="ico">💐</span> <span>Produits</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminCommandes.php"><span class="ico">🧾</span> <span>Commandes</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminClients.php"><span class="ico">👤</span> <span>Clients</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminPromos.php"><span class="ico">🏷️</span> <span>Promotions</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminAvis.php"><span class="ico">⭐</span> <span>Avis</span></a>
        <a class="nav-item" href="<?= $BASE ?>adminParametres.php"><span class="ico">⚙️</span> <span>Paramètres</span></a>
    </nav>
    <div class="adm-footer">© <?= date('Y') ?> DK Bloom</div>
</aside>

<main class="adm-main">
    <header class="adm-topbar">
        <button class="burger" id="burger" aria-label="Menu">☰</button>
        <div class="welcome">
            <h1>Tableau de bord</h1>
            <p>Bienvenue, <?= $adminName ?></p>
        </div>
        <div class="top-actions">
            <a class="btn ghost" href="<?= $BASE ?>index.php">Voir le site</a>
            <a class="btn" href="<?= $BASE ?>logout.php">Se déconnecter</a>
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
                <a class="link" href="<?= $BASE ?>adminCommandes.php">Tout voir</a>
            </div>
            <div class="table like">
                <div class="row head"><div>#</div><div>Date</div><div>Client</div><div>Total</div><div>Statut</div></div>
                <?php if ($pdo): ?>
                    <?php
                    // essaie un SELECT générique; adapte les colonnes si besoin
                    try {
                        $q = $pdo->query("SELECT com_id, com_date, com_total, com_statut FROM COMMANDE ORDER BY com_date DESC LIMIT 6");
                        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $r) {
                            $id = (int)($r['com_id'] ?? 0);
                            $d  = htmlspecialchars(substr((string)($r['com_date'] ?? ''),0,16));
                            $t  = number_format((float)($r['com_total'] ?? 0), 2,'.',' ') . ' CHF';
                            $s  = htmlspecialchars((string)($r['com_statut'] ?? '—'));
                            echo "<div class='row'><div>$id</div><div>$d</div><div>—</div><div>$t</div><div>$s</div></div>";
                        }
                        if (empty($rows)) echo "<div class='row empty'>Aucune commande pour le moment.</div>";
                    } catch (Throwable $e) {
                        echo "<div class='row empty'>Connecte la requête à ta BDD (colonnes com_id, com_date, com_total, com_statut).</div>";
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
            <div class="chart-placeholder">Graphique à brancher (Chart.js / Recharts ou image SVG)</div>
        </article>

        <article class="card">
            <div class="card-head">
                <h2>Produits en alerte stock</h2>
            </div>
            <ul class="list">
                <li>Les articles <em>≤ 5</em> unités seront listés ici lorsque branché à la BDD.</li>
            </ul>
        </article>
    </section>

    <footer class="adm-bottom">Dernière mise à jour: <?= date('d.m.Y H:i') ?></footer>
</main>

<script>
    document.getElementById('burger')?.addEventListener('click', () => {
        document.body.classList.toggle('aside-open');
    });
</script>
</body>
</html>
