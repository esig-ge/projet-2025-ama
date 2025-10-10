<?php
// /site/pages/admin_commande.php — regroupement par sections + archivage
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

/* ===== Utils ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Envoi de l’e-mail de retrait prêt */
function sendPickupEmail(PDO $pdo, int $perId, int $comId, DateTime $debut, DateTime $fin): void {
    $st = $pdo->prepare("SELECT PER_EMAIL, PER_PRENOM FROM PERSONNE WHERE PER_ID = :id");
    $st->execute([':id'=>$perId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return;

    $email   = (string)$u['PER_EMAIL'];
    $prenom  = (string)($u['PER_PRENOM'] ?? 'Client');
    $dateDeb = $debut->format('d.m.Y H:i');
    $dateFin = $fin->format('d.m.Y H:i');

    $adresseBoutique = "DK Bloom, Rue des Roses 12, 1200 Genève";
    $horaires        = "Lun–Ven 10:00–18:30, Sam 10:00–17:00";

    $subject = "Ta commande #$comId est prête au retrait";
    $body = "Coucou $prenom,

Ta commande #$comId est prête pour être retirée en boutique.

Période de retrait : $dateDeb → $dateFin
Adresse : $adresseBoutique
Horaires : $horaires

Merci de te munir de ton numéro de commande et d’une pièce d’identité.

À très vite,
DK Bloom";

    @mb_send_mail($email, $subject, $body, "From: DK Bloom <no-reply@dkbloom.ch>\r\n");
}

/** Création d’une notification simple */
function addNotification(PDO $pdo, int $perId, int $comId, string $type, string $texte): void {
    $st = $pdo->prepare("INSERT INTO NOTIFICATION (PER_ID, COM_ID, NOT_TYPE, NOT_TEXTE)
                         VALUES (:per, :com, :type, :txt)");
    $st->execute([':per'=>$perId, ':com'=>$comId, ':type'=>$type, ':txt'=>$texte]);
}

/** Mise au statut 'en attente de ramassage' + fenêtre + notif + mail */
function setCommandePickupReady(PDO $pdo, int $comId): void {
    $st = $pdo->prepare("SELECT PER_ID FROM COMMANDE WHERE COM_ID=?");
    $st->execute([$comId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    $perId = (int)$row['PER_ID'];

    $debut = new DateTime('now');
    $fin   = (new DateTime('now'))->modify('+5 days');

    $up = $pdo->prepare("UPDATE COMMANDE
                         SET COM_STATUT = 'en attente de ramassage',
                             COM_RETRAIT_DEBUT = :deb,
                             COM_RETRAIT_FIN   = :fin
                         WHERE COM_ID = :id");
    $up->execute([
        ':deb'=>$debut->format('Y-m-d H:i:s'),
        ':fin'=>$fin->format('Y-m-d H:i:s'),
        ':id'=>$comId
    ]);

    addNotification($pdo, $perId, $comId, 'pickup_ready', "Votre commande #$comId est prête au retrait en boutique.");
    sendPickupEmail($pdo, $perId, $comId, $debut, $fin);
}

/* ===== Paramètres / Filtres ===== */
$COM_STATUTS = [
    'en preparation',
    "en attente d'expédition",
    'expediee',
    'en attente de ramassage',
    'livree',
    'annulee'
];

/* ===== Ordre des statuts + règle de transition ===== */
$STATUS_RANK = [
    'en preparation'             => 1,
    "en attente d'expédition"    => 2,
    'expediee'                   => 3,
    'en attente de ramassage'    => 3, // même niveau que expédiée pour le tri
    'livree'                     => 4,
    'annulee'                    => -1,
];

function can_transition_status(string $from, string $to, array $rank): bool {
    $from = strtolower(trim($from));
    $to   = strtolower(trim($to));
    if ($from === $to) return true;                 // idempotent
    if ($from === 'annulee') return ($to === 'annulee');
    if ($to   === 'annulee') return true;           // annulation toujours possible
    if ($from === '' || !isset($rank[$from])) return true;
    if (!isset($rank[$to])) return false;
    return $rank[$to] > $rank[$from];               // pas de latéralité
}

/* Libellés sections (affichage) */
$DISPLAY_ORDER = [
    'en preparation'           => 'En préparation',
    "en attente d'expédition"  => "En attente d'expédition",
    'expediee'                 => 'Expédiées',
    'en attente de ramassage'  => 'Prêtes au retrait',
    'livree'                   => 'Livrées',
    'annulee'                  => 'Annulées',
];

$filtreStatut = isset($_GET['statut']) && $_GET['statut'] !== '' ? (string)$_GET['statut'] : '';
$q            = trim($_GET['q'] ?? '');

/* ===== POST: mise à jour du statut / archivage ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $comId     = (int)($_POST['com_id'] ?? 0);
        $comStatut = $_POST['com_statut'] ?? '';
        if ($comId > 0 && $comStatut !== '' && in_array($comStatut, $COM_STATUTS, true)) {
            try {
                $st = $pdo->prepare("SELECT COM_STATUT, COM_ARCHIVE FROM COMMANDE WHERE COM_ID=?");
                $st->execute([$comId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $_SESSION['flash'] = "Commande #$comId introuvable.";
                } elseif ((int)($row['COM_ARCHIVE'] ?? 0) === 1) {
                    $_SESSION['flash'] = "Commande #$comId archivée — modification impossible.";
                } elseif (!can_transition_status((string)$row['COM_STATUT'], $comStatut, $STATUS_RANK)) {
                    $_SESSION['flash'] = "Transition refusée : on ne peut pas revenir en arrière (sauf annulation).";
                } else {
                    if ($comStatut === 'en attente de ramassage') {
                        setCommandePickupReady($pdo, $comId);
                        $_SESSION['flash'] = "Commande #$comId mise “en attente de ramassage”. Notification et e-mail envoyés.";
                    } else {
                        $pdo->prepare("UPDATE COMMANDE SET COM_STATUT=? WHERE COM_ID=?")->execute([$comStatut, $comId]);
                        $_SESSION['flash'] = "Commande #$comId mise à jour.";
                    }
                }
            } catch (Throwable $e) {
                $_SESSION['flash'] = "Échec mise à jour (#$comId).";
            }
        }
        header("Location: ".$_SERVER['REQUEST_URI']); exit;
    }

    if ($action === 'archive') {
        $comId = (int)($_POST['com_id'] ?? 0);
        if ($comId > 0) {
            $st = $pdo->prepare("SELECT COM_STATUT, COM_ARCHIVE FROM COMMANDE WHERE COM_ID=?");
            $st->execute([$comId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && strtolower($row['COM_STATUT'] ?? '') === 'livree' && (int)$row['COM_ARCHIVE'] === 0) {
                $pdo->prepare("UPDATE COMMANDE SET COM_ARCHIVE=1, COM_ARCHIVED_AT=NOW() WHERE COM_ID=?")->execute([$comId]);
                $_SESSION['flash'] = "Commande #$comId archivée.";
                header("Location: {$BASE}admin_commandes_archivees.php"); exit;
            } else {
                $_SESSION['flash'] = "Archivage impossible : statut non 'livrée' ou déjà archivée.";
                header("Location: ".$_SERVER['REQUEST_URI']); exit;
            }
        }
    }
}

/* ===== Lecture (non archivées), filtres, fallback montant ===== */
function get_commandes(PDO $pdo, string $filtreStatut, string $q): array {
    $where  = ["c.COM_ARCHIVE = 0"];
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
      GROUP BY c.COM_ID, c.COM_DATE, c.COM_STATUT,
               per.PER_NOM, per.PER_PRENOM,
               a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS,
               pa.PAI_MONTANT
      ORDER BY 
        CASE 
          WHEN c.COM_STATUT='en preparation' THEN 1
          WHEN c.COM_STATUT='en attente d''expédition' THEN 2
          WHEN c.COM_STATUT='expediee' THEN 3
          WHEN c.COM_STATUT='en attente de ramassage' THEN 3
          WHEN c.COM_STATUT='livree' THEN 4
          WHEN c.COM_STATUT='annulee' THEN 5
          ELSE 99
        END,
        /* Sépare clairement “expédiées” et “prêtes au retrait” à rang égal */
        CASE 
          WHEN c.COM_STATUT='expediee' THEN 0
          WHEN c.COM_STATUT='en attente de ramassage' THEN 1
          ELSE 2
        END,
        c.COM_DATE DESC,
        c.COM_ID DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
$commandes = get_commandes($pdo, $filtreStatut, $q);
$nb = count($commandes);

/* ===== Helpers UI ===== */
function badgeClass($s) {
    $s = strtolower((string)$s);
    return match ($s) {
        'livree'                  => 'badge-success',
        'expediee'                => 'badge-blue',
        "en attente d'expédition" => 'badge-dark',
        'en attente de ramassage' => 'badge-blue',
        'annulee'                 => 'badge-danger',
        default                   => 'badge-warn', // en preparation
    };
}

function norm_statut(string $s): string {
    $s = strtolower($s);
    // Apostrophe typographique → simple
    $s = str_replace('’', "'", $s);
    // Dé-accentuation rapide
    $from = ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','û','ù','ü','ç'];
    $to   = ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c'];
    $s = str_replace($from, $to, $s);
    // Espaces multiples → un espace
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des commandes — DK Bloom</title>

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
        select,input[type=text]{border:1px solid var(--line);border-radius:10px;padding:8px 10px;font-size:14px;background:#fff;color:var(--text)}
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
        .badge-danger{background:var(--danger-bg);color:var(--danger)}
        .badge-success{background:#e6f6ea;color:#155c2e}
        .badge-dark{background:#f1f1f3;color:#111}
        .badge-blue{ background:#e6f0ff; color:#0b4fb9; }

        .row-form{display:flex;gap:8px;align-items:center;justify-content:flex-end}
        .row-form select{border:1px solid rgba(0,0,0,.1);border-radius:10px;padding:8px 10px;background:#fff;color:var(--text);min-width:210px}

        .table td:last-child { min-width: 220px; white-space: normal; }
        .row-form { flex-direction: column; align-items: stretch; }
        .row-form select, .row-form button, .row-form a { width: 100%; text-align:center; }

        .section-row td{ padding:0; background:transparent; border-top:0; }
        .section-head{
            position:sticky; top:38px; z-index:1;
            padding:10px 12px; margin-top:8px;
            background:#f6f6f8; color:#111; font-weight:800;
            border-top:1px solid var(--line); border-bottom:1px solid var(--line);
            letter-spacing:.2px;
        }
        .btn-ghost{display:inline-block;text-decoration:none;border:1px solid var(--brand);color:var(--brand);padding:8px 14px;border-radius:10px;background:#fff}
        .btn-ghost:hover{background:#f9f5f5}

        /* Bouton Livraison — couleurs selon statut */
        .btn-livraison{
            display:inline-block;
            padding:8px 12px;
            font-size:13px;
            font-weight:700;
            border-radius:10px;
            text-decoration:none;
            border:1px solid transparent;
            transition:.2s ease;
        }

        .btn-livraison.default{
            background:#f3f4f6;
            color:#111;
            border-color:#e5e7eb;
        }
        .btn-livraison.default:hover{ background:#eceff3; }

        .btn-livraison.orange{
            background:#fff4e5;         /* orange pastel */
            color:#b46900;
            border-color: rgba(228, 175, 113, 0.89);
        }
        .btn-livraison.orange:hover{ background:#ffedd4; }

        .btn-livraison.green{
            background:#e6f6ea;         /* vert succès */
            color:#155c2e;
            border-color:#a8e1bc;
        }
        .btn-livraison.green:hover{ background:#d7f0e3; }

    </style>
</head>
<body>
<div class="wrap">

    <h1>Gestion des commandes</h1>
    <p class="sub">Surveillez, filtrez et mettez à jour rapidement les commandes. Les commandes “Livrées” peuvent être archivées.</p>

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
                    <th style="width:5%">ID</th>
                    <th style="width:15%">Date</th>
                    <th style="width:15%">Client</th>
                    <th style="width:25%">Adresse</th>
                    <th>Produits</th>
                    <th style="width:10%">Statut</th>
                    <th style="width:12%">Montant</th>
                    <th style="width:20%">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (!$commandes) {
                    echo '<tr><td colspan="8" style="text-align:center;padding:22px">Aucune commande.</td></tr>';
                } else {
                    $current = null;
                    foreach ($commandes as $r):
                        $statut   = strtolower($r['statut_commande'] ?? '');
                        if ($statut !== $current) {
                            $current = $statut;
                            $label = $DISPLAY_ORDER[$statut] ?? ucfirst($statut);
                            echo '<tr class="section-row"><td colspan="8"><div class="section-head">'.h($label).'</div></td></tr>';
                        }
                        $isLivree = ($statut === 'livree');
                        ?>
                        <tr>
                            <td class="archiver-cell">
                                <?php if ($isLivree): ?>
                                    <form method="post" class="archiver" onsubmit="return confirm('Archiver cette commande ?');">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="com_id" value="<?= (int)$r['commande_id'] ?>">
                                        <input type="checkbox" aria-label="Archiver la commande" onchange="this.form.requestSubmit()">
                                        <span>#<?= (int)$r['commande_id'] ?></span>
                                    </form>
                                    <small>Voulez-vous archiver cette commande ?</small>
                                <?php else: ?>
                                    #<?= (int)$r['commande_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['date_commande'] ?? '-') ?></td>
                            <td><?= h(($r['nom_client'] ?? '-') . ' ' . ($r['prenom_client'] ?? '')) ?></td>
                            <td><?= h($r['adresse_complete'] ?? '-') ?></td>
                            <td><?= h($r['produits'] ?? '-') ?></td>
                            <td><span class="badge <?= badgeClass($r['statut_commande'] ?? '') ?>"><?= h($r['statut_commande'] ?? '—') ?></span></td>
                            <td class="montant"><?= number_format((float)($r['montant_commande'] ?? 0), 2, '.', ' ') ?> CHF</td>
                            <td>
                                <form method="post" class="row-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="com_id" value="<?= (int)$r['commande_id'] ?>">
                                    <select name="com_statut" title="Statut commande">
                                        <option value="">Statut commande…</option>
                                        <?php
                                        $cur = (string)($r['statut_commande'] ?? '');
                                        foreach ($COM_STATUTS as $opt):
                                            $disabled = can_transition_status($cur, $opt, $STATUS_RANK) ? '' : 'disabled';
                                            $selected = ($opt === $cur) ? 'selected' : '';
                                            ?>
                                            <option value="<?= h($opt) ?>" <?= $selected ?> <?= $disabled ?>><?= h($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn" type="submit">Mettre à jour</button>

                                    <!-- Bouton Livraison (préremplit admin_livraisons) -->
                                    <?php
                                    $statutRaw = (string)($r['statut_commande'] ?? '');
                                    $statutNor = norm_statut($statutRaw); // ex: "en attente d'expedition", "expediee", "livree"

                                    $btnClass = 'default';
                                    if (in_array($statutNor, ["en attente d'expedition", 'expediee', 'en attente de ramassage'], true)) {
                                        $btnClass = 'orange';
                                    } elseif ($statutNor === 'livree') {
                                        $btnClass = 'green';
                                    }
                                    ?>
                                    <a class="btn-livraison <?= $btnClass ?>"
                                       href="<?= $BASE ?>admin_livraisons.php?prefill=1&com_id=<?= (int)$r['commande_id'] ?>"
                                       title="Pré-remplir une livraison pour cette commande">
                                        Livraison
                                    </a>



                                </form>
                            </td>
                        </tr>
                    <?php endforeach; } ?>
                </tbody>
            </table>
        </div>
    </div>

    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Retour au dashboard</a>
        <a class="btn" href="<?= $BASE ?>admin_commandes_archivees.php">Voir les archives</a>
    </p>
</div>

</body>
</html>
