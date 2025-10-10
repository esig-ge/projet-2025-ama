<?php
// /site/pages/admin_livraisons.php
declare(strict_types=1);
session_start();

/* ========= 0) Accès + Base URL ========= */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé.'); }

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ========= 1) BDD + CSRF ========= */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

/* ========= 2) Helpers ========= */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function flash(?string $type = null, ?string $msg = null): void {
    if ($type !== null) { $_SESSION['toast_type'] = $type; }
    if ($msg  !== null) { $_SESSION['toast_msg']  = $msg;  }
}
function take(string $key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/* ========= 2bis) Préremplissage depuis l'URL ========= */
$prefill = [
    'com_id'       => (int)($_GET['com_id'] ?? 0),
    'mode'         => strtoupper(trim((string)($_GET['mode'] ?? ''))), // GVA|CH|BOUT|RETRAIT
    'transporteur' => trim((string)($_GET['transporteur'] ?? '')),
    'num_suivi'    => trim((string)($_GET['num_suivi'] ?? '')),
];

if ($prefill['com_id'] > 0 && $prefill['mode'] === '') {
    // Fallback: lire COM_MODE si non fourni
    $st = $pdo->prepare("SELECT L.LIV_MODE FROM COMMANDE C JOIN LIVRAISON L ON C.LIV_ID = L.LIV_ID WHERE C.COM_ID = :cid LIMIT 1");
    $st->execute([':cid' => $prefill['com_id']]);
    $m = strtoupper((string)$st->fetchColumn());
    if (in_array($m, ['GVA','CH','BOUT','RETRAIT'], true)) {
        $prefill['mode'] = $m;
    }
}
// Normalisation d’affichage
$prefill_mode_for_select = ($prefill['mode']==='RETRAIT') ? 'retrait' : $prefill['mode'];

// Si le transporteur n’est pas dans la liste, on bascule sur "Autre" et on pré-remplit le champ texte
$knownCarriers = ['','DHL','Poste','DPD'];
$isOtherCarrier = ($prefill['transporteur'] !== '' && !in_array($prefill['transporteur'], $knownCarriers, true));
$prefill_autre  = $isOtherCarrier ? $prefill['transporteur'] : '';

/* ========= 3) Insertion (POST) ========= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        flash('error', "Jeton CSRF invalide.");
        header("Location: {$BASE}admin_livraisons.php"); exit;
    }

    $comId         = (int) trim((string)($_POST['com_id'] ?? 0)); // lier une commande existante
    $mode          = trim((string)($_POST['mode'] ?? ''));        // GVA | CH | BOUT | retrait
    $transporteur  = trim((string)($_POST['transporteur'] ?? ''));
    $autreTransp   = trim((string)($_POST['transporteur_autre'] ?? ''));
    $numSuivi      = trim((string)($_POST['num_suivi'] ?? ''));

    // Modes acceptés (tolère 'retrait' comme alias)
    $allowedModes = ['GVA','CH','BOUT','retrait'];
    if (!in_array($mode, $allowedModes, true)) {
        flash('error', "Mode invalide (GVA, CH, BOUT ou retrait).");
        header("Location: {$BASE}admin_livraisons.php"); exit;
    }
    $modeNorm = ($mode === 'retrait') ? 'retrait' : $mode; // On garde tel quel dans ta table

    // Transporteur
    if ($transporteur === 'Autre') {
        if ($autreTransp === '') {
            flash('error', "Veuillez préciser le transporteur (Autre).");
            header("Location: {$BASE}admin_livraisons.php"); exit;
        }
        $transporteur = $autreTransp;
    }

    if ($numSuivi === '') {
        flash('error', "Le numéro de suivi est obligatoire.");
        header("Location: {$BASE}admin_livraisons.php"); exit;
    }

    // Unicité sur LIV_NUM_SUIVI_COMMANDE
    $st = $pdo->prepare("SELECT 1 FROM LIVRAISON WHERE LIV_NUM_SUIVI_COMMANDE = :sv LIMIT 1");
    $st->execute([':sv' => $numSuivi]);
    if ($st->fetchColumn()) {
        flash('error', "Ce numéro de suivi existe déjà.");
        header("Location: {$BASE}admin_livraisons.php"); exit;
    }

    // Frais auto: GVA=5, CH=10, BOUT/retrait=0
    $fee = 0.00;
    if ($modeNorm === 'GVA')  $fee = 5.00;
    if ($modeNorm === 'CH')   $fee = 10.00;
    if ($modeNorm === 'BOUT' || $modeNorm === 'retrait') $fee = 0.00;

    // Insertion
    $sql = "INSERT INTO LIVRAISON
            (LIV_MODE, LIV_NOM_TRANSPORTEUR, LIV_NUM_SUIVI_COMMANDE, LIV_MONTANT_FRAIS, LIV_DATE)
            VALUES (:mode, :tr, :sv, :fee, NOW())";
    $stmt = $pdo->prepare($sql);
    $ok   = $stmt->execute([
        ':mode' => $modeNorm,
        ':tr'   => $transporteur ?: null,
        ':sv'   => $numSuivi,
        ':fee'  => $fee,
    ]);

    if ($ok) {
        $livId = (int)$pdo->lastInsertId();

        // Lier la COMMANDE: COMMANDE.LIV_ID -> LIVRAISON.LIV_ID
        if ($comId > 0) {
            $pdo->prepare("UPDATE COMMANDE SET LIV_ID = :lid WHERE COM_ID = :cid LIMIT 1")
                ->execute([':lid'=>$livId, ':cid'=>$comId]);
        }

        flash('success', "Livraison #{$livId} ajoutée avec succès.");
    } else {
        flash('error', "Échec de l’ajout de la livraison.");
    }

    header("Location: {$BASE}admin_livraisons.php"); exit;
}

/* ========= 4) Filtre de recherche (GET) ========= */
$q = trim((string)take('q', ''));

$params = [];
$where  = [];
if ($q !== '') {
    if (ctype_digit($q)) {
        // par COM_ID exact ou par N° suivi
        $where[]          = " (C.COM_ID = :qnum OR L.LIV_NUM_SUIVI_COMMANDE LIKE :qlike) ";
        $params[':qnum']  = (int)$q;
        $params[':qlike'] = "%{$q}%";
    } else {
        $where[]           = " (L.LIV_NUM_SUIVI_COMMANDE LIKE :qlike OR L.LIV_NOM_TRANSPORTEUR LIKE :qlike2) ";
        $params[':qlike']  = "%{$q}%";
        $params[':qlike2'] = "%{$q}%";
    }
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ========= 5) Liste (JOIN: COMMANDE.LIV_ID -> LIVRAISON.LIV_ID) ========= */
$sqlList = "
SELECT
  L.LIV_ID,
  L.LIV_MODE,
  L.LIV_NOM_TRANSPORTEUR,
  L.LIV_NUM_SUIVI_COMMANDE,
  L.LIV_MONTANT_FRAIS,
  L.LIV_DATE,
  C.COM_ID,
  C.COM_STATUT,
  C.TOTAL_PAYER_CHF
FROM LIVRAISON L
LEFT JOIN COMMANDE C ON C.LIV_ID = L.LIV_ID
WHERE C.COM_STATUT IN ('en attente d''expédition', 'expediee', 'en attente de ramassage')
ORDER BY L.LIV_DATE DESC
LIMIT 200
";

$st = $pdo->prepare($sqlList);
$st->execute($params);
$livraisons = $st->fetchAll(PDO::FETCH_ASSOC);

/* ========= 6) UI ========= */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Admin • Livraisons</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root{ --primary:#8A1B2E; --bg:#f6f6f8; --card:#ffffff; --muted:#6b7280; }
        *{box-sizing:border-box}
        body{margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:var(--bg); color:#111}
        header{position:sticky; top:0; background:#fff; border-bottom:1px solid #eee; padding:12px 18px; z-index:10}
        header h1{margin:0; font-size:18px}
        .wrap{max-width:1200px; margin:20px auto; padding:0 16px}
        .grid{display:grid; grid-template-columns: 360px 1fr; gap:16px}
        @media (max-width:960px){ .grid{grid-template-columns:1fr} }
        .card{background:var(--card); border:1px solid #eee; border-radius:16px; box-shadow:0 1px 2px rgba(0,0,0,.03)}
        .card .head{padding:14px 16px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between}
        .card .head h2{margin:0; font-size:16px}
        .card .body{padding:16px}
        form .row{display:flex; gap:10px}
        form label{display:block; font-size:12px; color:#374151; margin-bottom:6px}
        input[type="text"], select{
            width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; font-size:14px; background:#fff;
        }
        .hint{font-size:12px; color:var(--muted); margin-top:6px}
        .btn{display:inline-flex; align-items:center; gap:8px; border:0; background:var(--primary); color:#fff; padding:10px 14px; border-radius:12px; font-weight:600; cursor:pointer}
        .toast{margin-bottom:16px; padding:12px 14px; border-radius:12px; font-size:14px}
        .toast.ok{background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0}
        .toast.err{background:#fef2f2; color:#991b1b; border:1px solid #fecaca}
        .tools{display:flex; gap:8px; align-items:center}
        .tools input[type="text"]{max-width:300px}
        table{width:100%; border-collapse:separate; border-spacing:0}
        th,td{padding:10px 12px; text-align:left; font-size:14px}
        thead th{position:sticky; top:0; background:#fafafa; border-bottom:1px solid #eee; z-index:5}
        tbody tr+tr td{border-top:1px solid #f3f4f6}
        .tag{display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; background:#f3f4f6; color:#111}
        .tag.mode{background:#fff1f2; color:#9f1239; border:1px solid #ffe4e6}
        .tag.tr{background:#eef2ff; color:#3730a3; border:1px solid #e0e7ff}
        .small{color:var(--muted); font-size:12px}
        .right{text-align:right; white-space:nowrap}

        .top-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .top-bar .left { display:flex; gap:10px; align-items:center; }
        .btn-outline, .btn-primary { display:inline-block; padding:8px 14px; font-size:14px; font-weight:600; border-radius:50px; text-decoration:none; transition:all .2s ease; }
        .btn-outline { color:var(--primary); border:1.5px solid var(--primary); background:transparent; }
        .btn-outline:hover { background:var(--primary); color:#fff; }
        .btn-primary { background:var(--primary); color:#fff; border:1.5px solid var(--primary); }
        .btn-primary:hover { background:#a1263e; }
        .btn-livraison {
            display: inline-block;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .btn-livraison.orange {
            background: #fff5e6;
            color: #b86b00;
            border: 1px solid #f4d3a0;
        }

        .btn-livraison.orange:hover {
            background: #ffeed2;
        }

        .btn-livraison.green {
            background: #e8f6ed;
            color: #0b7a2a;
            border: 1px solid #a9e0b7;
        }

        .btn-livraison.green:hover {
            background: #d8f0e2;
        }

        .btn-livraison.default {
            background: #f1f1f1;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-livraison.default:hover {
            background: #e9e9e9;
        }

    </style>
</head>
<body>
<header>
    <div class="top-bar">
        <div class="left">
            <a href="<?= $BASE ?>adminAccueil.php" class="btn-outline">← Retour au dashboard</a>
            <a href="<?= $BASE ?>admin_commande.php" class="btn-primary">Commandes</a>
        </div>
        <h1>Gestion des livraisons</h1>
    </div>
</header>

<div class="wrap">
    <?php if (!empty($_SESSION['toast_msg'])): ?>
        <div class="toast <?= $_SESSION['toast_type']==='success'?'ok':'err' ?>">
            <?= h($_SESSION['toast_msg']) ?>
        </div>
        <?php $_SESSION['toast_type']=$_SESSION['toast_msg']=null; endif; ?>

    <div class="grid">
        <!-- ===== Col. gauche : mini formulaire ===== -->
        <section class="card">
            <div class="head"><h2>Ajouter une livraison</h2></div>
            <div class="body">
                <form method="post" action="">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                    <div class="row" style="margin-bottom:12px">
                        <div style="flex:1">
                            <label for="com_id">Commande (optionnel)</label>
                            <input type="text" id="com_id" name="com_id" inputmode="numeric"
                                   placeholder="COM_ID (ex: 1234)"
                                   value="<?= $prefill['com_id'] ?: '' ?>">
                            <div class="hint">Reliera COMMANDE.LIV_ID à la livraison.</div>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:12px">
                        <div style="flex:1">
                            <label for="mode">Mode</label>
                            <select id="mode" name="mode" required>
                                <option value="">— Sélectionner —</option>
                                <option value="GVA"     <?= ($prefill_mode_for_select==='GVA')     ? 'selected' : '' ?>>GVA (Genève)</option>
                                <option value="CH"      <?= ($prefill_mode_for_select==='CH')      ? 'selected' : '' ?>>CH (Suisse)</option>
                                <option value="BOUT"    <?= ($prefill_mode_for_select==='BOUT')    ? 'selected' : '' ?>>BOUT (Retrait)</option>
                                <option value="retrait" <?= ($prefill_mode_for_select==='retrait') ? 'selected' : '' ?>>retrait (alias)</option>
                            </select>
                        </div>
                        <div style="flex:1">
                            <label for="transporteur">Transporteur</label>
                            <select id="transporteur" name="transporteur" required>
                                <option value="">— Sélectionner —</option>
                                <option <?= ($prefill['transporteur']==='DHL')   ? 'selected' : '' ?>>DHL</option>
                                <option <?= ($prefill['transporteur']==='Poste') ? 'selected' : '' ?>>Poste</option>
                                <option <?= ($prefill['transporteur']==='DPD')   ? 'selected' : '' ?>>DPD</option>
                                <option <?= $isOtherCarrier ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>

                    <div id="bloc-autre" class="row" style="margin-bottom:12px; display:none">
                        <div style="flex:1">
                            <label for="transporteur_autre">Autre transporteur</label>
                            <input type="text" id="transporteur_autre" name="transporteur_autre"
                                   placeholder="Nom du transporteur"
                                   value="<?= h($prefill_autre) ?>">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:12px">
                        <div style="flex:1">
                            <label for="num_suivi">N° de suivi</label>
                            <input type="text" id="num_suivi" name="num_suivi"
                                   placeholder="Ex: DXH123456789"
                                   value="<?= h($prefill['num_suivi']) ?>" required>
                        </div>
                    </div>

                    <button class="btn" type="submit">Enregistrer</button>
                </form>
            </div>
        </section>

        <!-- ===== Col. droite : liste + recherche ===== -->
        <section class="card">
            <div class="head">
                <h2>Liste des livraisons</h2>
                <form class="tools" method="get" action="">
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Rechercher (COM_ID, suivi, transporteur)">
                    <button class="btn" type="submit">Rechercher</button>
                </form>
            </div>
            <div class="body" style="padding:0">
                <div style="overflow:auto; max-height:70vh;">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>COM</th>
                            <th>Mode</th>
                            <th>Transporteur</th>
                            <th>Frais</th>
                            <th>N° suivi</th>
                            <th>Statut commande</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$livraisons): ?>
                            <tr><td colspan="8" class="small" style="padding:16px">Aucune livraison.</td></tr>
                        <?php else: foreach ($livraisons as $L): ?>
                            <tr>
                                <td>#<?= (int)$L['LIV_ID'] ?></td>
                                <td><span class="small"><?= h($L['LIV_DATE']) ?></span></td>
                                <td>
                                    <?php if (!empty($L['COM_ID'])): ?>
                                        <a href="<?= h($BASE.'admin_commande.php?com_id='.(int)$L['COM_ID']) ?>">#<?= (int)$L['COM_ID'] ?></a>
                                    <?php else: ?>
                                        <span class="small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="tag mode"><?= h($L['LIV_MODE']) ?></span></td>
                                <td><span class="tag tr"><?= h($L['LIV_NOM_TRANSPORTEUR'] ?? '—') ?></span></td>
                                <td class="right"><?= number_format((float)$L['LIV_MONTANT_FRAIS'], 2, '.', ' ') ?> CHF</td>
                                <td class="right"><?= h($L['LIV_NUM_SUIVI_COMMANDE'] ?? '—') ?></td>
                                <td><span class="small"><?= h($L['COM_STATUT'] ?? '—') ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    const sel   = document.getElementById('transporteur');
    const bloc  = document.getElementById('bloc-autre');
    const autre = document.getElementById('transporteur_autre');

    function toggleAutre(){
        // Ouvre le bloc si "Autre" est choisi OU si un texte "autre" est déjà prérempli
        if (sel.value === 'Autre' || (autre && autre.value.trim() !== '')) {
            bloc.style.display = 'flex';
            if (autre) autre.required = true;
        } else {
            bloc.style.display = 'none';
            if (autre) { autre.required = false; autre.value = ''; }
        }
    }
    sel.addEventListener('change', toggleAutre);
    // init (et scroll si on vient de la page commandes)
    toggleAutre();
    (function(){
        const url = new URL(window.location.href);
        if (url.searchParams.get('prefill') === '1') {
            const form = document.querySelector('form[method="post"]');
            if (form) {
                form.scrollIntoView({behavior:'smooth', block:'start'});
                form.style.boxShadow = '0 0 0 3px rgba(138,27,46,.2)';
                setTimeout(()=>{ form.style.boxShadow = ''; }, 1200);
            }
        }
    })();
</script>
</body>
</html>
