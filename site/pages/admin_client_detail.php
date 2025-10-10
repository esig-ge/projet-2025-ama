<?php
// /site/pages/admin_client_detail.php
declare(strict_types=1);
session_start();

/* =========================
   0) Accès admin + base URL
   ========================= */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé à l’administrateur'); }

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* =========================
   1) Connexion BDD + helpers
   ========================= */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function notnull($v, $fallback='—'){ return ($v!==null && $v!=='') ? $v : $fallback; }

/* =========================
   2) Paramètres d'entrée
   ========================= */
$perId = (int)($_GET['per_id'] ?? 0);
if ($perId <= 0) { http_response_code(400); exit('Paramètre per_id manquant.'); }

/* =========================
   3) Récup client + adresses (FACTURATION & LIVRAISON)
   =========================
   - Pas de ADR_IS_DEFAULT dans ta table -> on prend la + récente (ADR_ID max) par type via sous-requêtes.
*/
$sqlClient = "
    SELECT 
        p.PER_ID, p.PER_NOM, p.PER_PRENOM, p.PER_EMAIL,
        NULL AS PER_TEL,
        NULL AS PER_DATE_CREATION,

        /* ---- LIVRAISON (dernière adresse de type LIVRAISON) ---- */
        liv.ADR_RUE     AS LIV_RUE,
        liv.ADR_NUMERO  AS LIV_NUMERO,
        liv.ADR_NPA     AS LIV_NPA,
        liv.ADR_VILLE   AS LIV_VILLE,
        liv.ADR_PAYS    AS LIV_PAYS,

        /* ---- FACTURATION (dernière adresse de type FACTURATION) ---- */
        fac.ADR_RUE     AS FAC_RUE,
        fac.ADR_NUMERO  AS FAC_NUMERO,
        fac.ADR_NPA     AS FAC_NPA,
        fac.ADR_VILLE   AS FAC_VILLE,
        fac.ADR_PAYS    AS FAC_PAYS

    FROM PERSONNE p
    INNER JOIN CLIENT c ON c.PER_ID = p.PER_ID

    LEFT JOIN ADRESSE liv ON liv.ADR_ID = (
        SELECT a1.ADR_ID
        FROM ADRESSE_CLIENT ac1
        JOIN ADRESSE a1 ON a1.ADR_ID = ac1.ADR_ID
        WHERE ac1.PER_ID = p.PER_ID AND a1.ADR_TYPE = 'LIVRAISON'
        ORDER BY a1.ADR_ID DESC
        LIMIT 1
    )

    LEFT JOIN ADRESSE fac ON fac.ADR_ID = (
        SELECT a2.ADR_ID
        FROM ADRESSE_CLIENT ac2
        JOIN ADRESSE a2 ON a2.ADR_ID = ac2.ADR_ID
        WHERE ac2.PER_ID = p.PER_ID AND a2.ADR_TYPE = 'FACTURATION'
        ORDER BY a2.ADR_ID DESC
        LIMIT 1
    )

    WHERE p.PER_ID = :id
";
$st = $pdo->prepare($sqlClient);
$st->execute([':id'=>$perId]);
$client = $st->fetch(PDO::FETCH_ASSOC);
if (!$client) { http_response_code(404); exit("Client introuvable (#{$perId})."); }

/* =========================
   4) Statistiques simples
   ========================= */
$sqlStats = "
    SELECT
      COUNT(*)                              AS nb_cmd,
      COALESCE(SUM(COM_MONTANT_TOTAL),0.0) AS total_depense,
      MAX(COM_DATE)                        AS derniere_cmd
    FROM COMMANDE
    WHERE PER_ID = :id
";
$st = $pdo->prepare($sqlStats);
$st->execute([':id'=>$perId]);
$stats = $st->fetch(PDO::FETCH_ASSOC) ?: ['nb_cmd'=>0, 'total_depense'=>0, 'derniere_cmd'=>null];

/* =========================
   5) Dernières commandes + type de livraison lisible
   ========================= */
/* Dernières commandes + type de livraison (depuis LIV_MODE) */
$sqlLast = "
    SELECT 
        c.COM_ID,
        c.COM_DATE,
        c.COM_STATUT,
        COALESCE(c.COM_MONTANT_TOTAL,0.0) AS total_ttc,
        CASE
          WHEN COALESCE(l.LIV_MODE,'') = '' THEN '—'
          WHEN l.LIV_MODE COLLATE utf8mb4_general_ci LIKE '%geneve%'  THEN 'Livraison Genève'
          WHEN l.LIV_MODE COLLATE utf8mb4_general_ci LIKE '%suisse%'  THEN 'Livraison Suisse'
          WHEN l.LIV_MODE COLLATE utf8mb4_general_ci LIKE '%retrait%' THEN 'Retrait en boutique'
          ELSE l.LIV_MODE
        END AS liv_type_label
    FROM COMMANDE c
    LEFT JOIN LIVRAISON l ON l.LIV_ID = c.LIV_ID
    WHERE c.PER_ID = :id
    ORDER BY c.COM_DATE DESC
    LIMIT 5
";
$st = $pdo->prepare($sqlLast);
$st->execute([':id'=>$perId]);
$lastOrders = $st->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DK Bloom — Admin : Client #<?= h($client['PER_ID']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bordeaux:#8A1B2E; --bordeaux-2:#5C0012; --bg:#faf6f7; --card:#fff; --ring:#eee;
            --text:#222; --muted:#666;
        }
        *{box-sizing:border-box}
        body{margin:0;padding:24px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:#222}
        .container{max-width:1100px;margin:0 auto}
        .card{background:#fff;border:1px solid var(--ring);border-radius:14px;box-shadow:0 10px 24px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
        .topbar{display:flex;justify-content:space-between;align-items:baseline;padding:16px;border-bottom:1px solid #f0e1e6;background:#fff}
        h1{margin:0;color:var(--bordeaux-2)}
        .grid{padding:16px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .section h3{margin:0 0 8px;color:#4a0c19}
        .field{margin:6px 0}
        .label{display:block;font-size:.85em;color:var(--muted)}
        .value{font-weight:600}
        .btn{padding:10px 14px;border:1px solid var(--bordeaux);background:linear-gradient(180deg,var(--bordeaux),var(--bordeaux-2));color:#fff;border-radius:10px;text-decoration:none;display:inline-block}
        .btn.secondary{background:#fff;color:var(--bordeaux)}
        table{width:100%;border-collapse:collapse}
        thead th{background:#f8ebef;color:#4a0c19;font-weight:600;text-align:left;padding:10px;border-bottom:1px solid #e9d3d9}
        tbody td{padding:10px;border-bottom:1px solid #f0e1e6}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
    </style>
</head>
<body>
<div class="container">

    <div class="card topbar">
        <h1>Client #<?= h($client['PER_ID']) ?> — <?= h($client['PER_NOM']) ?> <?= h($client['PER_PRENOM']) ?></h1>
        <div class="actions">
            <a class="btn secondary" href="<?= h($BASE.'admin_clients.php') ?>">← Liste clients</a>
            <a class="btn" href="<?= h($BASE.'admin_client_commandes.php?per_id='.$client['PER_ID']) ?>">Historique commandes</a>
        </div>
    </div>

    <!-- Infos principales -->
    <div class="card">
        <div class="grid row">
            <div class="section">
                <h3>Informations</h3>
                <div class="field"><span class="label">Nom</span><span class="value"><?= h($client['PER_NOM']) ?></span></div>
                <div class="field"><span class="label">Prénom</span><span class="value"><?= h($client['PER_PRENOM']) ?></span></div>
                <div class="field"><span class="label">Email</span><span class="value"><a href="mailto:<?= h($client['PER_EMAIL']) ?>"><?= h($client['PER_EMAIL']) ?></a></span></div>
                <div class="field"><span class="label">Téléphone</span><span class="value"><?= h(notnull($client['PER_TEL'])) ?></span></div>
                <div class="field"><span class="label">Date d’inscription</span><span class="value"><?= h(notnull($client['PER_DATE_CREATION'])) ?></span></div>

                <div class="field">
                    <span class="label">Adresse de facturation</span>
                    <span class="value">
                        <?php
                        $f1 = trim((string)($client['FAC_RUE'] ?? ''));
                        $fnum = trim((string)($client['FAC_NUMERO'] ?? ''));
                        $f2 = trim(((string)($client['FAC_NPA'] ?? '')).' '.((string)($client['FAC_VILLE'] ?? '')));
                        $fp = trim((string)($client['FAC_PAYS'] ?? ''));
                        $ligne = trim($f1 . ($fnum ? ' '.$fnum : ''));
                        $f  = trim($ligne . ($f2 ? ', '.$f2 : '') . ($fp ? ', '.$fp : ''));
                        echo h($f !== '' ? $f : '—');
                        ?>
                    </span>
                </div>

                <div class="field">
                    <span class="label">Adresse de livraison</span>
                    <span class="value">
                        <?php
                        $l1 = trim((string)($client['LIV_RUE'] ?? ''));
                        $lnum = trim((string)($client['LIV_NUMERO'] ?? ''));
                        $l2 = trim(((string)($client['LIV_NPA'] ?? '')).' '.((string)($client['LIV_VILLE'] ?? '')));
                        $lp = trim((string)($client['LIV_PAYS'] ?? ''));
                        $ligne = trim($l1 . ($lnum ? ' '.$lnum : ''));
                        $l  = trim($ligne . ($l2 ? ', '.$l2 : '') . ($lp ? ', '.$lp : ''));
                        echo h($l !== '' ? $l : '—');
                        ?>
                    </span>
                </div>
            </div>

            <div class="section">
                <h3>Statistiques</h3>
                <div class="field"><span class="label">Nombre de commandes</span><span class="value"><?= h((string)($stats['nb_cmd'] ?? 0)) ?></span></div>
                <div class="field"><span class="label">Total dépensé (TTC)</span><span class="value"><?= number_format((float)($stats['total_depense'] ?? 0), 2, '.', "'") ?> CHF</span></div>
                <div class="field"><span class="label">Dernière commande</span><span class="value"><?= h(notnull($stats['derniere_cmd'])) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Dernières commandes -->
    <div class="card">
        <div class="topbar" style="border:none;">
            <h2 style="margin:0;color:#5C0012;">Dernières commandes</h2>
        </div>
        <div class="grid">
            <?php if (empty($lastOrders)): ?>
                <p style="color:#666">Aucune commande pour ce client.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Type de livraison</th>
                        <th>Total TTC</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lastOrders as $o): ?>
                        <tr>
                            <td>#<?= h($o['COM_ID']) ?></td>
                            <td><?= h($o['COM_DATE']) ?></td>
                            <td><?= h($o['COM_STATUT']) ?></td>
                            <td><?= h($o['liv_type_label']) ?></td>
                            <td><?= number_format((float)$o['total_ttc'], 2, '.', "'") ?> CHF</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
