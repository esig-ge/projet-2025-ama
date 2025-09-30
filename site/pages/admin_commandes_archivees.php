<?php
// /site/pages/admin_commandes_archivees.php — liste + désarchiver
session_start();

/* ===== Accès ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé.'); }

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Connexion BDD ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Paramètres / Filtres ===== */
$COM_STATUTS = [
    'en preparation',
    "en attente d'expédition",
    'expediee',
    'livree',
    'annulee'
];

/* Ordre d'affichage des sections */
$DISPLAY_ORDER = [
    'livree'                   => 'Livrées',
    'en preparation'           => 'En préparation',
    "en attente d'expédition"  => "En attente d'expédition",
    'expediee'                 => 'Expédiées',
    'annulee'                  => 'Annulées',
];

$filtreStatut = isset($_GET['statut']) && $_GET['statut'] !== '' ? (string)$_GET['statut'] : '';
$q            = trim($_GET['q'] ?? '');

/* ===== POST: désarchiver / MAJ statut (facultatif) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'unarchive') {
        $comId = (int)($_POST['com_id'] ?? 0);
        if ($comId > 0) {
            // S'assurer que la commande est bien archivée
            $st = $pdo->prepare("SELECT COM_ARCHIVE FROM COMMANDE WHERE COM_ID=?");
            $st->execute([$comId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && (int)$row['COM_ARCHIVE'] === 1) {
                $pdo->prepare("UPDATE COMMANDE SET COM_ARCHIVE=0, COM_ARCHIVED_AT=NULL WHERE COM_ID=?")->execute([$comId]);
                $_SESSION['flash'] = "Commande #$comId désarchivée.";
                header("Location: {$BASE}admin_commande.php"); exit; // retour logique vers la liste active
            } else {
                $_SESSION['flash'] = "Désarchivage impossible : commande non archivée.";
                header("Location: ".$_SERVER['REQUEST_URI']); exit;
            }
        }
    }
    if ($action === 'update') {
        // Optionnel : autoriser la MAJ de statut même dans les archives
        $comId     = (int)($_POST['com_id'] ?? 0);
        $comStatut = $_POST['com_statut'] ?? '';
        if ($comId > 0 && $comStatut !== '' && in_array($comStatut, $COM_STATUTS, true)) {
            try {
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=? WHERE COM_ID=?")->execute([$comStatut, $comId]);
                $_SESSION['flash'] = "Commande #$comId mise à jour.";
            } catch (Throwable $e) {
                $_SESSION['flash'] = "Échec mise à jour (#$comId).";
            }
        }
        header("Location: ".$_SERVER['REQUEST_URI']); exit;
    }
}

/* ===== Lecture (archivées), filtres, fallback montant ===== */
function get_commandes_archives(PDO $pdo, string $filtreStatut, string $q): array {
    $where  = ["c.COM_ARCHIVE = 1"];
    $params = [];
    if ($filtreStatut !== '') { $where[] = "c.COM_STATUT = ?"; $params[] = $filtreStatut; }
    if ($q !== '') {
        $where[] = "(per.PER_NOM LIKE ? OR per.PER_PRENOM LIKE ? OR p.PRO_NOM LIKE ? OR c.COM_ID LIKE ?)";
        $like = "%$q%";
        array_push($params, $like, $like, $like, $like);
    }
    $W = "WHERE ".implode(" AND ", $where);

    $sql = "
      SELECT 
        c.COM_ID                       AS commande_id,
        c.COM_DATE                     AS date_commande,
        c.COM_STATUT                   AS statut_commande,
        c.COM_ARCHIVED_AT               AS archived_at,
        per.PER_NOM                    AS nom_client,
        per.PER_PRENOM                 AS prenom_client,
        CONCAT_WS(' ', a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS) AS adresse_complete,
        GROUP_CONCAT(DISTINCT CONCAT(p.PRO_NOM, ' x', COALESCE(cp.CP_QTE_COMMANDEE,1))
                     ORDER BY p.PRO_NOM SEPARATOR ', ') AS produits,
        COALESCE(pa.PAI_MONTANT,
                 SUM(COALESCE(cp.CP_QTE_COMMANDEE,1) * COALESCE(p.PRO_PRIX,0)),
                 0) AS montant_commande
      FROM COMMANDE c
      JOIN CLIENT cli   ON c.PER_ID = cli.PER_ID
      JOIN PERSONNE per ON cli.PER_ID = per.PER_ID
      LEFT JOIN ADRESSE_CLIENT ac ON cli.PER_ID = ac.PER_ID
      LEFT JOIN ADRESSE a         ON ac.ADR_ID = a.ADR_ID
      LEFT JOIN COMMANDE_PRODUIT cp ON c.COM_ID = cp.COM_ID
      LEFT JOIN PRODUIT p            ON cp.PRO_ID = p.PRO_ID
      LEFT JOIN PAIEMENT pa          ON c.PAI_ID  = pa.PAI_ID
      $W
      GROUP BY c.COM_ID, c.COM_DATE, c.COM_STATUT, c.COM_ARCHIVED_AT,
               per.PER_NOM, per.PER_PRENOM,
               a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS,
               pa.PAI_MONTANT
      ORDER BY 
        CASE 
          WHEN c.COM_STATUT='livree' THEN 1
          WHEN c.COM_STATUT='en preparation' THEN 2
          WHEN c.COM_STATUT=\"en attente d'expédition\" THEN 3
          WHEN c.COM_STATUT='expediee' THEN 4
          WHEN c.COM_STATUT='annulee' THEN 5
          ELSE 99
        END,
        c.COM_DATE DESC,
        c.COM_ID DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$commandes = get_commandes_archives($pdo, $filtreStatut, $q);
$nb = count($commandes);

/* ===== Helpers UI ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeClass($s) {
    $s = strtolower((string)$s);
    return match ($s) {
        'livree'                  => 'badge-success',
        'expediee'                => 'badge-info',
        "en attente d'expédition" => 'badge-dark',
        'annulee'                 => 'badge-danger',
        default                   => 'badge-warn',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Commandes archivées — DK Bloom</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_admin.css">
    <style>
        :root{
            --bg:#ffffff; --text: rgba(97, 2, 2, 0.86); --muted:#6b7280;
            --brand:#8b1c1c; --brand-600:#6e1515; --brand-050:#fdeeee;
            --ok:#0b8f5a; --warn:#b46900; --warn-bg:#fff4e5;
            --danger:#b11226; --danger-bg:#ffe8ea;
            --line:#e5e7eb; --card:#ffffff; --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px;
        }
        body{background:var(--bg); color:var(--text);}
        .wrap{max-width:1150px;margin:26px auto;padding:0 16px;}
        h1{font-size:clamp(24px,2.4vw,34px);margin:0 0 16px;font-weight:800;color:#111}
        .sub{color:var(--muted);margin-bottom:18px}
        .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow);}
        .card-head{padding:14px 16px 10px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px}
        .card-body{padding:0}

        .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .toolbar .spacer{flex:1}
        select,input[type=text]{border:1px solid var(--line);border-radius:10px;padding:8px 10px;font-size:14px;background:#fff;color:#var(--text)}
        input[type=text]{min-width:240px;flex:1}
        .btn{appearance:none;border:1px solid var(--brand);color:#fff;background:var(--brand);padding:8px 14px;border-radius:10px;font-weight:700;cursor:pointer}
        .btn:hover{background:var(--brand-600);border-color:var(--brand-600)}
        .btn.ghost{background:#fff;color:var(--brand);border-color:var(--brand)}
        .btn.ghost:hover{background:#f9f5f5}
        .right-muted{color:var(--muted)}

        .table{width:100%;border-collapse:separate;border-spacing:0}
        .table thead th{
            position:sticky; top:0; z-index:2;
            background:#fafafa; color:#111; font-weight:700; text-transform:uppercase; font-size:12px; letter-spacing:.3px;
            padding:10px 12px; border-bottom:1px solid var(--line);
        }
        .table tbody td{padding:12px;border-top:1px solid var(--line);vertical-align:middle;background:#fff}
        .table tbody tr:nth-child(odd){background:#fff}
        .table tbody tr:nth-child(even){background:#fcfcfc}
        .table tbody tr:hover{background: rgba(120, 4, 57, 0.08)}
        td.montant{text-align:right;white-space:nowrap}

        /* Badges */
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
        .badge-warn{background:var(--warn-bg);color:var(--warn)}
        .badge-info{background:#fff7d1;color:#8a6a00}
        .badge-danger{background:var(--danger-bg);color:#var(--danger)}
        .badge-success{background:#e6f6ea;color:#155c2e}
        .badge-dark{background:#f1f1f3;color:#111}

        .row-form{display:flex;gap:8px;align-items:center;justify-content:flex-end}
        .row-form select{border:1px solid rgba(0,0,0,.1);border-radius:10px;padding:8px 10px;background:#fff;color:#111;min-width:210px}

        /* Sections */
        .section-row td{ padding:0; background:transparent; border-top:0; }
        .section-head{
            position:sticky; top:38px; z-index:1;
            padding:10px 12px; margin-top:8px;
            background:#f6f6f8; color:#111; font-weight:800;
            border-top:1px solid var(--line); border-bottom:1px solid var(--line);
            letter-spacing:.2px;
        }
        @media (max-width: 720px){ .section-head{ top:0; } }

        /* Action cell */
        .actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
    </style>
</head>
<body>
<div class="wrap">

    <h1>Commandes archivées</h1>
    <p class="sub">Historique des commandes. Vous pouvez <strong>désarchiver</strong> une commande pour la renvoyer dans la liste active.</p>

    <?php if(!empty($_SESSION['flash'])): ?>
        <div class="flash" style="margin:14px 0 0;background:#eef7ff;border:1px solid #cfe6ff;color:#0c4a6e;padding:10px 12px;border-radius:10px">
            <?= h($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-top:16px">
        <div class="card-head">
            <form class="toolbar" method="get" style="flex:1">
                <select name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($COM_STATUTS as $st): ?>
                        <option value="<?= h($st) ?>" <?= $st===$filtreStatut?'selected':'' ?>><?= h($st) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="q" placeholder="Recherche (client, produit, ID…)" value="<?= h($q) ?>">
                <button class="btn" type="submit">Filtrer</button>
                <a class="btn ghost" href="<?= h($_SERVER['PHP_SELF']) ?>">Réinit.</a>
            </form>
            <div class="right-muted"><?= (int)$nb ?> résultat(s)</div>
        </div>

        <div class="card-body">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:8%">ID</th>
                    <th style="width:14%">Date</th>
                    <th style="width:15%">Client</th>
                    <th style="width:25%">Adresse</th>
                    <th>Produits</th>
                    <th style="width:10%">Statut</th>
                    <th style="width:12%">Montant</th>
                    <th style="width:14%">Archivé le</th>
                    <th style="width:22%">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (!$commandes) {
                    echo '<tr><td colspan="9" style="text-align:center;padding:22px">Aucune commande archivée.</td></tr>';
                } else {
                    $current = null;
                    foreach ($commandes as $r):
                        $statut   = strtolower($r['statut_commande'] ?? '');
                        if ($statut !== $current) {
                            $current = $statut;
                            $label = $DISPLAY_ORDER[$statut] ?? ucfirst($statut);
                            echo '<tr class="section-row"><td colspan="9"><div class="section-head">'.$label.'</div></td></tr>';
                        }
                        ?>
                        <tr>
                            <td>#<?= (int)$r['commande_id'] ?></td>
                            <td><?= h($r['date_commande'] ?? '-') ?></td>
                            <td><?= h(($r['nom_client'] ?? '-') . ' ' . ($r['prenom_client'] ?? '')) ?></td>
                            <td><?= h($r['adresse_complete'] ?? '-') ?></td>
                            <td><?= h($r['produits'] ?? '-') ?></td>
                            <td><span class="badge <?= badgeClass($r['statut_commande'] ?? '') ?>"><?= h($r['statut_commande'] ?? '—') ?></span></td>
                            <td class="montant"><?= number_format((float)($r['montant_commande'] ?? 0), 2, '.', ' ') ?> CHF</td>
                            <td><?= h($r['archived_at'] ?? '—') ?></td>
                            <td>
                                <div class="actions">
                                    <!-- Désarchiver -->
                                    <form method="post" onsubmit="return confirm('Désarchiver cette commande pour la renvoyer dans la liste active ?');">
                                        <input type="hidden" name="action" value="unarchive">
                                        <input type="hidden" name="com_id" value="<?= (int)$r['commande_id'] ?>">
                                        <button class="btn" type="submit">Désarchiver</button>
                                    </form>

                                    <!-- (Optionnel) Changer le statut même depuis les archives -->
                                    <form method="post" class="row-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="com_id" value="<?= (int)$r['commande_id'] ?>">
                                        <select name="com_statut" title="Statut commande">
                                            <option value="">Statut…</option>
                                            <?php foreach ($COM_STATUTS as $opt): ?>
                                                <option value="<?= h($opt) ?>" <?= ($opt === ($r['statut_commande'] ?? '')) ? 'selected' : '' ?>><?= h($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn ghost" type="submit">Mettre à jour</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; } ?>
                </tbody>
            </table>
        </div>
    </div>

    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Dashboard</a>
        <a class="btn" href="<?= $BASE ?>admin_commande.php">Voir les commandes actives</a>
    </p>
</div>

</body>
</html>
