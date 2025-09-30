<?php
session_start();

/* ===== Connexion DB ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Helpers ===== */
function select_distincts(PDO $pdo, string $sql, string $col): array {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $vals = [];
    foreach ($rows as $r) {
        $v = trim((string)($r[$col] ?? ''));
        if ($v !== '') $vals[$v] = true;
    }
    return array_keys($vals);
}

/* ===== Update (POST) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $comId = (int)($_POST['com_id'] ?? 0);
    $livStatut = isset($_POST['liv_statut']) ? trim($_POST['liv_statut']) : null;
    $paiMode   = isset($_POST['pai_mode'])   ? trim($_POST['pai_mode'])   : null;
    $paiStatut = isset($_POST['pai_statut']) ? trim($_POST['pai_statut']) : null;

    if ($comId > 0) {
        $pdo->beginTransaction();
        try {
            // Récupère LIV_ID et PAI_ID liés à la commande
            $stmt = $pdo->prepare("SELECT LIV_ID, PAI_ID FROM COMMANDE WHERE COM_ID = ?");
            $stmt->execute([$comId]);
            $ids = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['LIV_ID'=>null,'PAI_ID'=>null];

            if ($ids && $ids['LIV_ID'] && $livStatut !== null && $livStatut !== '') {
                $u1 = $pdo->prepare("UPDATE LIVRAISON SET LIV_STATUT = ? WHERE LIV_ID = ?");
                $u1->execute([$livStatut, (int)$ids['LIV_ID']]);
            }
            if ($ids && $ids['PAI_ID']) {
                if ($paiMode !== null && $paiMode !== '') {
                    $u2 = $pdo->prepare("UPDATE PAIEMENT SET PAI_MODE = ? WHERE PAI_ID = ?");
                    $u2->execute([$paiMode, (int)$ids['PAI_ID']]);
                }
                if ($paiStatut !== null && $paiStatut !== '') {
                    $u3 = $pdo->prepare("UPDATE PAIEMENT SET PAI_STATUT = ? WHERE PAI_ID = ?");
                    $u3->execute([$paiStatut, (int)$ids['PAI_ID']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // Simple message d’erreur utilisateur — loggue côté serveur si besoin
            $_SESSION['message'] = "Échec de la mise à jour (#$comId).";
        }
    }
    // Post/redirect/get pour éviter un resoumission
    header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

/* ===== Sources pour remplir les <select> ===== */
$livreStats = select_distincts($pdo, "SELECT DISTINCT LIV_STATUT FROM LIVRAISON WHERE LIV_STATUT IS NOT NULL", 'LIV_STATUT');
$paiModes   = select_distincts($pdo, "SELECT DISTINCT PAI_MODE   FROM PAIEMENT  WHERE PAI_MODE   IS NOT NULL", 'PAI_MODE');
$paiStats   = select_distincts($pdo, "SELECT DISTINCT PAI_STATUT FROM PAIEMENT  WHERE PAI_STATUT IS NOT NULL", 'PAI_STATUT');

/* ===== Lecture des commandes (ordre croissant par ID) ===== */
function recup_donnee_commande(PDO $pdo): array
{
    $sql = "
        SELECT 
            c.COM_ID                                    AS commande_id,
            c.COM_DATE                                  AS date_commande,
            per.PER_NOM                                 AS nom_client,
            per.PER_PRENOM                              AS prenom_client,
            CONCAT_WS(' ', a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS) AS adresse_complete,
            GROUP_CONCAT(
                DISTINCT CONCAT(p.PRO_NOM, ' x', COALESCE(cp.CP_QTE_COMMANDEE,1))
                ORDER BY p.PRO_NOM SEPARATOR ', '
            )                                           AS produits,
            COALESCE(l.LIV_STATUT,'—')                  AS statut_livraison,
            COALESCE(pa.PAI_MONTANT,0)                  AS montant_commande,
            COALESCE(pa.PAI_MODE,'—')                   AS mode_paiement,
            COALESCE(pa.PAI_STATUT,'—')                 AS statut_paiement
        FROM COMMANDE c
        JOIN CLIENT    cli   ON c.PER_ID = cli.PER_ID
        JOIN PERSONNE  per   ON cli.PER_ID = per.PER_ID
        LEFT JOIN ADRESSE_CLIENT ac ON cli.PER_ID = ac.PER_ID
        LEFT JOIN ADRESSE a         ON ac.ADR_ID = a.ADR_ID
        LEFT JOIN COMMANDE_PRODUIT cp ON c.COM_ID = cp.COM_ID
        LEFT JOIN PRODUIT p            ON cp.PRO_ID = p.PRO_ID
        LEFT JOIN LIVRAISON l          ON c.LIV_ID  = l.LIV_ID
        LEFT JOIN PAIEMENT pa          ON c.PAI_ID  = pa.PAI_ID
        GROUP BY c.COM_ID, c.COM_DATE, per.PER_NOM, per.PER_PRENOM,
                 a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS,
                 l.LIV_STATUT, pa.PAI_MONTANT, pa.PAI_MODE, pa.PAI_STATUT
        ORDER BY c.COM_ID ASC";  /* ⇦ tri croissant par ID */
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$commandes = recup_donnee_commande($pdo);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Commandes</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/admin_catalogue.css">
    <style>
        /* Mini styles pour les selects dans le tableau */
        .table-actions { display:flex; gap:.5rem; align-items:center; }
        .table-actions select { padding:.2rem .4rem; border-radius:8px; border:1px solid rgba(255,255,255,.35); background:#760000; color:#fff; }
        .table-actions button { padding:.3rem .6rem; border-radius:999px; border:1px solid #fff3; background:#8d0e0e; color:#fff; cursor:pointer; }
        .table-actions button:hover { filter:brightness(1.1); }
        td.montant { text-align:right; white-space:nowrap; }
    </style>
</head>
<body>

<h1>COMMANDES</h1>

<div>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Nom client</th>
            <th>Prenom client</th>
            <th>Adresse</th>
            <th>Produit</th>
            <th>Statut de livraison</th>
            <th>Montant de la commande</th>
            <th>Mode de paiement</th>
            <th>Statut du paiement</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($commandes as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['commande_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['date_commande'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['nom_client'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['prenom_client'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['adresse_complete'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['produits'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['statut_livraison'] ?? '—') ?></td>
                <td class="montant"><?= number_format((float)($row['montant_commande'] ?? 0), 2, '.', ' ') ?> CHF</td>
                <td><?= htmlspecialchars($row['mode_paiement'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['statut_paiement'] ?? '—') ?></td>
                <td>
                    <form method="post" class="table-actions">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="com_id" value="<?= (int)$row['commande_id'] ?>">
                        <!-- Livraison -->
                        <select name="liv_statut" title="Statut livraison">
                            <option value="">Livraison…</option>
                            <?php foreach ($livreStats as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($opt === ($row['statut_livraison'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Paiement: mode -->
                        <select name="pai_mode" title="Mode de paiement">
                            <option value="">Mode…</option>
                            <?php foreach ($paiModes as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($opt === ($row['mode_paiement'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Paiement: statut -->
                        <select name="pai_statut" title="Statut de paiement">
                            <option value="">Statut…</option>
                            <?php foreach ($paiStats as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($opt === ($row['statut_paiement'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Enregistrer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
