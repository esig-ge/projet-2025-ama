<?php
// /site/pages/admin_livraisons.php
declare(strict_types=1);
session_start();

/* ===== Accès ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé.'); }

/* ===== Base URL ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== DB ===== */
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1
    ");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

/* ===== Statuts ===== */
$DELIV_STATUTS = ['à planifier','planifiée','en cours','livrée','échouée','retour'];
$STATUS_RANK_COM = [
    'en preparation'          => 1,
    "en attente d'expédition" => 2,
    'expediee'                => 3,
    'livree'                  => 4,
    'annulee'                 => -1
];
function can_transition_status(string $from, string $to, array $rank): bool {
    $from = strtolower($from); $to = strtolower($to);
    if ($from === $to) return true;
    if ($from === 'annulee') return ($to === 'annulee');
    if ($to   === 'annulee') return true;
    if (!isset($rank[$from]) || !isset($rank[$to])) return false;
    return $rank[$to] >= $rank[$from];
}

/* ===== Détection table LIVRAISON ===== */
$HAS_LIV = tableExists($pdo, 'LIVRAISON');

/* ===== Filtres GET ===== */
$statut = trim($_GET['statut'] ?? '');
$mode   = trim($_GET['mode']   ?? '');
$trans  = trim($_GET['trans']  ?? '');
$q      = trim($_GET['q']      ?? '');
$from   = trim($_GET['from']   ?? ''); // YYYY-MM-DD
$to     = trim($_GET['to']     ?? ''); // YYYY-MM-DD

/* ===== POST actions ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($HAS_LIV) {
            if ($action === 'update_livraison') {
                $livId = (int)($_POST['liv_id'] ?? 0);
                if ($livId <= 0) throw new RuntimeException("LIV_ID manquant.");

                $date  = $_POST['liv_date'] ?? null;                   // DATE
                $m     = $_POST['liv_mode'] ?? null;                   // LIV_MODE
                $tr    = $_POST['liv_transporteur'] ?? null;           // LIV_NOM_TRANSPORTEUR
                $suivi = $_POST['liv_suivi'] ?? null;                  // LIV_NUM_SUIVI_COMMANDE
                $frais = $_POST['liv_frais'] ?? null;                  // LIV_MONTANT_FRAIS
                $stt   = $_POST['liv_statut'] ?? '';                   // LIV_STATUT

                if ($stt !== '' && !in_array($stt, $DELIV_STATUTS, true)) {
                    throw new RuntimeException("Statut livraison invalide.");
                }

                $sql = "UPDATE LIVRAISON
                        SET LIV_DATE = :d,
                            LIV_MODE = :m,
                            LIV_NOM_TRANSPORTEUR = :tr,
                            LIV_NUM_SUIVI_COMMANDE = :su,
                            LIV_MONTANT_FRAIS = :fr,
                            LIV_STATUT = :st
                        WHERE LIV_ID = :id";
                $st = $pdo->prepare($sql);
                $st->execute([
                    ':d'=>$date ?: null,
                    ':m'=>$m ?: null,
                    ':tr'=>$tr ?: null,
                    ':su'=>$suivi ?: null,
                    ':fr'=>($frais === '' ? null : $frais),
                    ':st'=>($stt ?: null),
                    ':id'=>$livId
                ]);
                $_SESSION['flash'] = "Livraison #$livId mise à jour.";
            }

            if ($action === 'mark_delivered') {
                $livId = (int)($_POST['liv_id'] ?? 0);
                if ($livId <= 0) throw new RuntimeException("LIV_ID manquant.");

                // 1) LIVRAISON -> livrée
                $pdo->prepare("UPDATE LIVRAISON SET LIV_STATUT='livrée' WHERE LIV_ID=?")
                    ->execute([$livId]);

                // 2) COMMANDE liée par LIV_ID -> livree (respect progression)
                // (si la commande est au max 'expediee' on la pousse à 'livree')
                $pdo->prepare("
                    UPDATE COMMANDE
                       SET COM_STATUT='livree'
                     WHERE LIV_ID=? AND COM_STATUT IN ('expediee','en preparation', \"en attente d'expédition\")
                ")->execute([$livId]);

                $_SESSION['flash'] = "Livraison #$livId marquée livrée.";
            }

            if ($action === 'create_and_link_livraison') {
                $comId = (int)($_POST['com_id'] ?? 0);
                if ($comId <= 0) throw new RuntimeException("COM_ID manquant.");

                $pdo->prepare("INSERT INTO LIVRAISON (LIV_STATUT, LIV_MONTANT_FRAIS) VALUES ('à planifier', 0)")
                    ->execute();
                $livId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE COMMANDE SET LIV_ID=? WHERE COM_ID=?")->execute([$livId, $comId]);

                $_SESSION['flash'] = "Livraison #$livId créée et liée à la commande #$comId.";
            }
        } else {
            // Pas de table LIVRAISON: on agit uniquement sur COMMANDE
            if ($action === 'mark_expediee' || $action === 'mark_livree') {
                $comId = (int)($_POST['com_id'] ?? 0);
                if ($comId <= 0) throw new RuntimeException("COM_ID manquant.");

                $cur = $pdo->prepare("SELECT COM_STATUT FROM COMMANDE WHERE COM_ID=?");
                $cur->execute([$comId]);
                $row = $cur->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException("Commande introuvable.");

                $fromS = (string)$row['COM_STATUT'];
                $toS   = ($action === 'mark_expediee') ? 'expediee' : 'livree';
                if (!can_transition_status($fromS, $toS, $STATUS_RANK_COM)) {
                    throw new RuntimeException("Transition refusée (on ne peut pas revenir en arrière).");
                }
                $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=? WHERE COM_ID=?")->execute([$toS, $comId]);
                $_SESSION['flash'] = "Commande #$comId mise « $toS ». ";
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = "Erreur : " . $e->getMessage();
    }

    // PRG
    $qs = http_build_query($_GET);
    header("Location: ".$_SERVER['PHP_SELF'].($qs ? "?$qs" : '')); exit;
}

/* ===== Lecture ===== */
$params = [];
$w = [];

if ($HAS_LIV) {
    $w[] = "c.COM_ARCHIVE = 0";
    $sql = "
      SELECT
        c.COM_ID,
        c.COM_DATE,
        c.COM_STATUT,

        l.LIV_ID,
        l.LIV_DATE,
        l.LIV_MODE,
        l.LIV_NOM_TRANSPORTEUR,
        l.LIV_NUM_SUIVI_COMMANDE,
        l.LIV_MONTANT_FRAIS,
        l.LIV_STATUT,

        -- Statut calculé si l.LIV_STATUT est NULL
        CASE
          WHEN l.LIV_STATUT IS NOT NULL THEN l.LIV_STATUT
          WHEN c.COM_STATUT='livree' THEN 'livrée'
          WHEN c.COM_STATUT='expediee' THEN 'en cours'
          WHEN c.COM_STATUT IN ('en preparation', \"en attente d'expédition\") THEN 'à planifier'
          WHEN c.COM_STATUT='annulee' THEN 'échouée'
          ELSE 'à planifier'
        END AS LIV_STATUT_CALC,

        per.PER_NOM, per.PER_PRENOM,
        CONCAT_WS(' ', a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS) AS adresse
      FROM COMMANDE c
      LEFT JOIN LIVRAISON l       ON l.LIV_ID = c.LIV_ID
      JOIN CLIENT cli             ON c.PER_ID = cli.PER_ID
      JOIN PERSONNE per           ON cli.PER_ID = per.PER_ID
      LEFT JOIN ADRESSE_CLIENT ac ON cli.PER_ID = ac.PER_ID
      LEFT JOIN ADRESSE a         ON ac.ADR_ID = a.ADR_ID
    ";

    // Filtres
    if ($statut !== '') {
        $w[] = "(
          CASE
            WHEN l.LIV_STATUT IS NOT NULL THEN l.LIV_STATUT
            WHEN c.COM_STATUT='livree' THEN 'livrée'
            WHEN c.COM_STATUT='expediee' THEN 'en cours'
            WHEN c.COM_STATUT IN ('en preparation', \"en attente d'expédition\") THEN 'à planifier'
            WHEN c.COM_STATUT='annulee' THEN 'échouée'
            ELSE 'à planifier'
          END
        ) = ?";
        $params[] = $statut;
    }
    if ($mode   !== '') { $w[] = "l.LIV_MODE = ?";                 $params[] = $mode; }
    if ($trans  !== '') { $w[] = "l.LIV_NOM_TRANSPORTEUR = ?";     $params[] = $trans; }
    if ($from   !== '') { $w[] = "DATE(l.LIV_DATE) >= ?";          $params[] = $from; }
    if ($to     !== '') { $w[] = "DATE(l.LIV_DATE) <= ?";          $params[] = $to; }
    if ($q      !== '') {
        $w[] = "(per.PER_NOM LIKE ? OR per.PER_PRENOM LIKE ? OR l.LIV_NUM_SUIVI_COMMANDE LIKE ? OR c.COM_ID LIKE ?)";
        $like = "%$q%"; array_push($params, $like, $like, $like, $like);
    }

    if ($w) $sql .= " WHERE ".implode(" AND ", $w);

    $sql .= " ORDER BY
                CASE
                  WHEN (CASE
                          WHEN l.LIV_STATUT IS NOT NULL THEN l.LIV_STATUT
                          WHEN c.COM_STATUT='livree' THEN 'livrée'
                          WHEN c.COM_STATUT='expediee' THEN 'en cours'
                          WHEN c.COM_STATUT IN ('en preparation', \"en attente d'expédition\") THEN 'à planifier'
                          WHEN c.COM_STATUT='annulee' THEN 'échouée'
                          ELSE 'à planifier'
                        END) = 'livrée' THEN 3
                  WHEN (CASE
                          WHEN l.LIV_STATUT IS NOT NULL THEN l.LIV_STATUT
                          WHEN c.COM_STATUT='livree' THEN 'livrée'
                          WHEN c.COM_STATUT='expediee' THEN 'en cours'
                          ELSE 'à planifier'
                        END) IN ('planifiée','en cours') THEN 1
                  ELSE 2
                END,
                l.LIV_DATE IS NULL,
                l.LIV_DATE ASC,
                c.COM_ID DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Fallback sans table LIVRAISON
    $sql = "
      SELECT
        c.COM_ID,
        c.COM_DATE,
        c.COM_STATUT,
        NULL AS LIV_ID,
        NULL AS LIV_DATE,
        NULL AS LIV_MODE,
        NULL AS LIV_NOM_TRANSPORTEUR,
        NULL AS LIV_NUM_SUIVI_COMMANDE,
        NULL AS LIV_MONTANT_FRAIS,
        CASE
          WHEN c.COM_STATUT='livree' THEN 'livrée'
          WHEN c.COM_STATUT='expediee' THEN 'en cours'
          WHEN c.COM_STATUT IN ('en preparation', \"en attente d'expédition\") THEN 'à planifier'
          WHEN c.COM_STATUT='annulee' THEN 'échouée'
          ELSE 'à planifier'
        END AS LIV_STATUT_CALC,
        per.PER_NOM, per.PER_PRENOM,
        CONCAT_WS(' ', a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS) AS adresse
      FROM COMMANDE c
      JOIN CLIENT cli             ON c.PER_ID = cli.PER_ID
      JOIN PERSONNE per           ON cli.PER_ID = per.PER_ID
      LEFT JOIN ADRESSE_CLIENT ac ON cli.PER_ID = ac.PER_ID
      LEFT JOIN ADRESSE a         ON ac.ADR_ID = a.ADR_ID
      WHERE c.COM_ARCHIVE = 0
    ";

    if ($statut !== '') {
        $sql .= " AND (CASE
          WHEN c.COM_STATUT='livree' THEN 'livrée'
          WHEN c.COM_STATUT='expediee' THEN 'en cours'
          WHEN c.COM_STATUT IN ('en preparation', \"en attente d'expédition\") THEN 'à planifier'
          WHEN c.COM_STATUT='annulee' THEN 'échouée'
          ELSE 'à planifier' END) = ?";
        $params[] = $statut;
    }
    if ($q !== '') {
        $sql .= " AND (per.PER_NOM LIKE ? OR per.PER_PRENOM LIKE ? OR c.COM_ID LIKE ?)";
        $like = "%$q%"; array_push($params, $like, $like, $like);
    }

    $sql .= " ORDER BY
                (CASE WHEN c.COM_STATUT='livree' THEN 3
                      WHEN c.COM_STATUT='expediee' THEN 1
                      ELSE 2 END),
                c.COM_DATE ASC, c.COM_ID DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}
$nb = count($rows);

/* ===== UI helpers ===== */
function badgeClassLiv($s){
    $s = strtolower((string)$s);
    return match($s){
        'livrée'      => 'badge-success',
        'en cours'    => 'badge-blue',
        'planifiée'   => 'badge-dark',
        'échouée','retour' => 'badge-danger',
        default       => 'badge-warn' // à planifier
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des livraisons — DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_admin.css">
    <style>
        :root{
            --bg:#ffffff; --text: rgba(97, 2, 2, 0.86); --muted:#6b7280;
            --brand:#8b1c1c; --brand-600:#6e1515; --brand-050:#fdeeee;
            --ok:#0b8f5a; --warn:#b46900; --warn-bg:#fff4e5;
            --danger:#b11226; --danger-bg:#ffe8ea; --line:#e5e7eb;
            --card:#ffffff; --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px;
        }
        body{background:var(--bg); color:var(--text);}
        .wrap{max-width:1200px;margin:26px auto;padding:0 16px;}
        h1{font-size:clamp(24px,2.4vw,34px);margin:0 0 16px;font-weight:800;color:#111}
        .sub{color:var(--muted);margin-bottom:18px}
        .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow);}
        .card-head{padding:14px 16px 10px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px}
        .card-body{padding:0}
        .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .toolbar .spacer{flex:1}
        select,input[type=text],input[type=date],input[type=number]{border:1px solid var(--line);border-radius:10px;padding:8px 10px;font-size:14px;background:#fff;color:var(--text)}
        input[type=text]{min-width:220px}
        .btn{appearance:none;border:1px solid var(--brand);color:#fff;background:var(--brand);padding:8px 14px;border-radius:10px;font-weight:700;cursor:pointer}
        .btn:hover{background:var(--brand-600);border-color:var(--brand-600)}
        .btn.ghost{background:#fff;color:var(--brand);border-color:var(--brand)}
        .btn.ghost:hover{background:#f9f5f5}

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
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
        .badge-warn{background:var(--warn-bg);color:var(--warn)}
        .badge-danger{background:var(--danger-bg);color:var(--danger)}
        .badge-success{background:#e6f6ea;color:#155c2e}
        .badge-dark{background:#f1f1f3;color:#111}
        .badge-blue{background:#e6f0ff;color:#0b4fb9}
        .row-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .row-form input[type=text], .row-form input[type=date]{min-width:140px}
        .row-form .note{min-width:220px}
        .hint{color:var(--muted); font-size:12px}
        .flash{margin:14px 0 0;background:#eef7ff;border:1px solid #cfe6ff;color:#0c4a6e;padding:10px 12px;border-radius:10px}
        @media (max-width: 900px){
            .table thead { display:none; }
            .table tr { display:block; margin-bottom:12px; border:1px solid var(--line); border-radius:10px; }
            .table td { display:block; border:none; width:100%!important; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Gestion des livraisons</h1>
    <p class="sub">
        Planifiez, suivez et mettez à jour les livraisons clients.
        <?= $HAS_LIV ? '' : '<span class="hint">Mode simplifié : aucune table LIVRAISON détectée, on se base sur les commandes.</span>' ?>
    </p>

    <?php if(!empty($_SESSION['flash'])): ?>
        <div class="flash"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-top:16px">
        <div class="card-head">
            <form class="toolbar" method="get" style="flex:1">
                <select name="statut">
                    <option value="">Tous statuts</option>
                    <?php foreach ($DELIV_STATUTS as $s): ?>
                        <option value="<?= h($s) ?>" <?= $s===$statut?'selected':'' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($HAS_LIV): ?>
                    <input type="text" name="mode" placeholder="Mode (poste, coursier…)" value="<?= h($mode) ?>">
                    <input type="text" name="trans" placeholder="Transporteur" value="<?= h($trans) ?>">
                    <input type="date" name="from" value="<?= h($from) ?>">
                    <input type="date" name="to" value="<?= h($to) ?>">
                <?php else: ?>
                    <span class="hint">Filtre date/mode dispo si table LIVRAISON existe.</span>
                <?php endif; ?>
                <input type="text" name="q" placeholder="Recherche (client, ID cmd, suivi…)" value="<?= h($q) ?>">
                <button class="btn" type="submit">Filtrer</button>
                <a class="btn ghost" href="<?= h($_SERVER['PHP_SELF']) ?>">Réinit.</a>
            </form>
            <div class="right-muted" style="color:#6b7280"><?= (int)$nb ?> résultat(s)</div>
        </div>

        <div class="card-body">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:10%">Cmd./Liv.</th>
                    <th style="width:12%">Date</th>
                    <th style="width:16%">Client</th>
                    <th>Adresse</th>
                    <th style="width:12%">Mode</th>
                    <th style="width:12%">Transporteur</th>
                    <th style="width:12%">Suivi</th>
                    <th style="width:8%">Frais</th>
                    <th style="width:10%">Statut</th>
                    <th style="width:36%">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" style="text-align:center;padding:22px">Aucune livraison.</td></tr>
                <?php else:
                    foreach ($rows as $r):
                        $comId = (int)$r['COM_ID'];
                        $livId = $r['LIV_ID'] ? (int)$r['LIV_ID'] : null;
                        $statutAff = $r['LIV_STATUT'] ?? $r['LIV_STATUT_CALC'];
                        ?>
                        <tr>
                            <td>
                                #<?= $comId ?>
                                <?php if ($livId): ?>
                                    <small style="color:#6b7280"> / L#<?= $livId ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['LIV_DATE'] ? substr($r['LIV_DATE'],0,10) : '—') ?></td>
                            <td><?= h(($r['PER_NOM'] ?? '').' '.($r['PER_PRENOM'] ?? '')) ?></td>
                            <td><?= h($r['adresse'] ?? '-') ?></td>
                            <td><?= h($r['LIV_MODE'] ?? '—') ?></td>
                            <td><?= h($r['LIV_NOM_TRANSPORTEUR'] ?? '—') ?></td>
                            <td><?= h($r['LIV_NUM_SUIVI_COMMANDE'] ?? '—') ?></td>
                            <td><?= $r['LIV_MONTANT_FRAIS'] !== null ? number_format((float)$r['LIV_MONTANT_FRAIS'],2,'.',' ') . ' CHF' : '—' ?></td>
                            <td><span class="badge <?= badgeClassLiv($statutAff) ?>"><?= h($statutAff) ?></span></td>
                            <td>
                                <?php if ($HAS_LIV): ?>
                                    <?php if ($livId): ?>
                                        <form method="post" class="row-form">
                                            <input type="hidden" name="action" value="update_livraison">
                                            <input type="hidden" name="liv_id" value="<?= (int)$livId ?>">

                                            <input type="date" name="liv_date" value="<?= h($r['LIV_DATE'] ? substr($r['LIV_DATE'],0,10) : '') ?>">
                                            <input type="text" name="liv_mode" placeholder="Mode" value="<?= h($r['LIV_MODE'] ?? '') ?>">
                                            <input type="text" name="liv_transporteur" placeholder="Transporteur" value="<?= h($r['LIV_NOM_TRANSPORTEUR'] ?? '') ?>">
                                            <input type="text" name="liv_suivi" placeholder="N° suivi" value="<?= h($r['LIV_NUM_SUIVI_COMMANDE'] ?? '') ?>">
                                            <input type="number" step="0.01" min="0" name="liv_frais" placeholder="Frais" value="<?= h($r['LIV_MONTANT_FRAIS'] ?? '') ?>">

                                            <select name="liv_statut" title="Statut">
                                                <option value="">—</option>
                                                <?php foreach ($DELIV_STATUTS as $s): ?>
                                                    <option value="<?= h($s) ?>" <?= ((string)$statutAff === $s)?'selected':'' ?>><?= h($s) ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button class="btn" type="submit">Enregistrer</button>
                                            <?php if (strtolower((string)$statutAff) !== 'livrée'): ?>
                                                <button class="btn" name="action" value="mark_delivered">Marquer livrée</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="row-form">
                                            <span class="hint">Pas de livraison liée à cette commande.</span>
                                            <input type="hidden" name="com_id" value="<?= (int)$comId ?>">
                                            <button class="btn" name="action" value="create_and_link_livraison">Créer & lier une livraison</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post" class="row-form">
                                        <span class="hint">Sans table LIVRAISON, on peut seulement changer le statut de commande :</span>
                                        <input type="hidden" name="com_id" value="<?= (int)$comId ?>">
                                        <button class="btn" name="action" value="mark_expediee">Marquer expédiée</button>
                                        <button class="btn" name="action" value="mark_livree">Marquer livrée</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Retour au dashboard</a>
        <a class="btn" href="<?= $BASE ?>admin_commande.php">Voir les commandes</a>
    </p>

    <?php if(!$HAS_LIV): ?>
        <details style="margin-top:12px">
            <summary><strong>💡 Activer les filtres avancés</strong></summary>
            <pre style="white-space:pre-wrap;background:#f8fafc;padding:12px;border:1px solid #e5e7eb;border-radius:10px">
      </pre>
        </details>
    <?php endif; ?>
</div>
</body>
</html>
