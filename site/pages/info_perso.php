<?php
// /site/pages/info_perso.php
declare(strict_types=1);
session_start();

/* =========================
   0) GARDES + BASES
   ========================= */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if (empty($_SESSION['per_id'])) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Veuillez vous connecter pour accéder à vos informations.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================
   1) HELPERS
   ========================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetchOne(PDO $pdo, string $sql, array $p){
    $st=$pdo->prepare($sql); $st->execute($p);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function emailTakenByAnother(PDO $pdo, string $email, int $perId): bool {
    $st = $pdo->prepare("SELECT 1 FROM PERSONNE WHERE PER_EMAIL=:e AND PER_ID<>:id LIMIT 1");
    $st->execute([':e'=>$email, ':id'=>$perId]);
    return (bool)$st->fetchColumn();
}
function findDuplicateAddressId(PDO $pdo, int $perId, string $type,
                                string $rue, string $num, string $npa, string $ville, string $pays, ?int $excludeAdrId=null): ?int {
    $sql = "SELECT A.ADR_ID
            FROM ADRESSE A
            JOIN ADRESSE_CLIENT AC ON AC.ADR_ID = A.ADR_ID
            WHERE AC.PER_ID = :per AND A.ADR_TYPE = :type
              AND TRIM(LOWER(A.ADR_RUE))    = TRIM(LOWER(:rue))
              AND TRIM(LOWER(A.ADR_NUMERO)) = TRIM(LOWER(:num))
              AND TRIM(LOWER(A.ADR_NPA))    = TRIM(LOWER(:npa))
              AND TRIM(LOWER(A.ADR_VILLE))  = TRIM(LOWER(:ville))
              AND TRIM(LOWER(A.ADR_PAYS))   = TRIM(LOWER(:pays))";
    if ($excludeAdrId) $sql .= " AND A.ADR_ID <> :exclude";
    $params = [':per'=>$perId, ':type'=>$type, ':rue'=>$rue, ':num'=>$num, ':npa'=>$npa, ':ville'=>$ville, ':pays'=>$pays];
    if ($excludeAdrId) $params[':exclude'] = $excludeAdrId;
    $st = $pdo->prepare($sql); $st->execute($params);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}
function fmtDate($s){ return $s ? date('d.m.Y', strtotime($s)) : '—'; }

/* ---- COMMANDES (3 dernières) ---- */
function count_user_orders(PDO $pdo, int $perId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM COMMANDE WHERE PER_ID = ? AND COM_ARCHIVE = 0");
    $st->execute([$perId]);
    return (int)$st->fetchColumn();
}
function get_last_orders(PDO $pdo, int $perId): array {
    $sql = "SELECT c.COM_ID, c.COM_DATE, c.COM_STATUT,
                   l.LIV_MODE
            FROM COMMANDE c
            LEFT JOIN LIVRAISON l ON l.LIV_ID = c.LIV_ID
            WHERE c.PER_ID = ? AND c.COM_ARCHIVE = 0
            ORDER BY c.COM_DATE DESC, c.COM_ID DESC
            LIMIT 3";
    $st = $pdo->prepare($sql); $st->execute([$perId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


/* ---- NOTIFICATIONS (3 dernières) ---- */
function count_user_notifs(PDO $pdo, int $perId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATION WHERE PER_ID=?");
    $st->execute([$perId]);
    return (int)$st->fetchColumn();
}
function get_last_notifs(PDO $pdo, int $perId): array {
    $sql = "SELECT n.NOT_ID, n.COM_ID, n.NOT_TYPE, n.NOT_TEXTE, n.READ_AT,
                   COALESCE(n.CREATED_AT, n.NOT_DATE) AS CREATED_AT,
                   c.COM_RETRAIT_DEBUT, c.COM_RETRAIT_FIN
            FROM NOTIFICATION n
            LEFT JOIN COMMANDE c ON c.COM_ID = n.COM_ID
            WHERE n.PER_ID=?
            ORDER BY COALESCE(n.CREATED_AT, n.NOT_DATE) DESC, n.NOT_ID DESC
            LIMIT 3";
    $st = $pdo->prepare($sql); $st->execute([$perId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
/* Notifs pickup non lues */
function get_unread_pickup_notifs(PDO $pdo, int $perId): array {
    $sql = "SELECT n.NOT_ID, n.COM_ID, n.NOT_TEXTE,
                   COALESCE(n.CREATED_AT, n.NOT_DATE) AS CREATED_AT,
                   c.COM_RETRAIT_DEBUT, c.COM_RETRAIT_FIN
            FROM NOTIFICATION n
            LEFT JOIN COMMANDE c ON c.COM_ID = n.COM_ID
            WHERE n.PER_ID=? AND n.NOT_TYPE='pickup_ready' AND n.READ_AT IS NULL
            ORDER BY COALESCE(n.CREATED_AT, n.NOT_DATE) DESC, n.NOT_ID DESC";
    $st = $pdo->prepare($sql); $st->execute([$perId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   2) LECTURE DES DONNÉES
   ========================= */
$personne = fetchOne($pdo,
    "SELECT PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_ID=:id",
    [':id'=>$perId]
) ?: ['PER_NOM'=>'','PER_PRENOM'=>'','PER_EMAIL'=>'','PER_NUM_TEL'=>''];

$client = fetchOne($pdo, "SELECT CLI_DATENAISSANCE FROM CLIENT WHERE PER_ID=:id", [':id'=>$perId]);
if (!$client || !array_key_exists('CLI_DATENAISSANCE',$client)) {
    $pdo->prepare("INSERT IGNORE INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
                   VALUES (:id,NULL,0)")->execute([':id'=>$perId]);
    $client = ['CLI_DATENAISSANCE'=>null];
}

$sqlAdr = "SELECT A.ADR_ID, A.ADR_RUE, A.ADR_NUMERO, A.ADR_NPA, A.ADR_VILLE, A.ADR_PAYS, A.ADR_TYPE
           FROM ADRESSE A
           JOIN ADRESSE_CLIENT AC ON AC.ADR_ID = A.ADR_ID
           WHERE AC.PER_ID=:id AND A.ADR_TYPE=:type
           ORDER BY A.ADR_ID DESC LIMIT 1";
$adrLiv = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'LIVRAISON']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'','ADR_VILLE'=>'','ADR_PAYS'=>'Suisse'
];
$adrFac = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'FACTURATION']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'','ADR_VILLE'=>'','ADR_PAYS'=>'Suisse'
];

/* =========================
   3) TRAITEMENT POST
   ========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])) {
    $errors = [];
    $nom   = trim($_POST['nom'] ?? '');
    $pre   = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel   = trim($_POST['tel'] ?? '');

    if ($email !== '' && emailTakenByAnother($pdo, $email, $perId)) {
        $errors[] = "Cet e-mail est déjà utilisé par un autre compte.";
    }

    $dnaiss = trim($_POST['dnaiss'] ?? '');
    if ($dnaiss !== '') {
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $dnaiss)) {
        } elseif (preg_match('#^\d{2}/\d{2}/\d{4}$#', $dnaiss)) {
            [$d,$m,$y] = explode('/',$dnaiss); $dnaiss = "$y-$m-$d";
        } else { $errors[] = "Format de date invalide (utilisez AAAA-MM-JJ)."; }
    } else $dnaiss = null;

    // Adresses + “même adresse”
    $same = !empty($_POST['same_fac_to_liv']);
    $inFac = $_POST['facturation'] ?? [];
    $inLiv = $_POST['livraison']  ?? [];
    if ($same) {
        $inLiv = array_merge($inLiv, [
            'rue'=>$inFac['rue']??'', 'numero'=>$inFac['numero']??'', 'npa'=>$inFac['npa']??'',
            'ville'=>$inFac['ville']??'', 'pays'=>$inFac['pays']??'',
        ]);
    }
    $nf = [
        'id'=> isset($inFac['adr_id']) && ctype_digit((string)$inFac['adr_id']) ? (int)$inFac['adr_id'] : null,
        'rue'=>trim($inFac['rue']??''), 'num'=>trim($inFac['numero']??''), 'npa'=>trim($inFac['npa']??''),
        'ville'=>trim($inFac['ville']??''), 'pays'=>trim($inFac['pays']??''),
    ];
    $nl = [
        'id'=> isset($inLiv['adr_id']) && ctype_digit((string)$inLiv['adr_id']) ? (int)$inLiv['adr_id'] : null,
        'rue'=>trim($inLiv['rue']??''), 'num'=>trim($inLiv['numero']??''), 'npa'=>trim($inLiv['npa']??''),
        'ville'=>trim($inLiv['ville']??''), 'pays'=>trim($inLiv['pays']??''),
    ];

    $hasFac = ($nf['rue']!=='' || $nf['num']!=='' || $nf['npa']!=='' || $nf['ville']!=='' || $nf['pays']!=='');
    $hasLiv = ($nl['rue']!=='' || $nl['num']!=='' || $nl['npa']!=='' || $nl['ville']!=='' || $nl['pays']!=='');

    if ($hasFac) {
        if (findDuplicateAddressId($pdo,$perId,'FACTURATION',$nf['rue'],$nf['num'],$nf['npa'],$nf['ville'],$nf['pays'],$nf['id'])) {
            $errors[] = "L’adresse de FACTURATION existe déjà.";
        }
    }
    if ($hasLiv) {
        if (findDuplicateAddressId($pdo,$perId,'LIVRAISON',$nl['rue'],$nl['num'],$nl['npa'],$nl['ville'],$nl['pays'],$nl['id'])) {
            $errors[] = "L’adresse de LIVRAISON existe déjà.";
        }
    }

    if ($errors) {
        $_SESSION['toast_type']='error'; $_SESSION['toast_msg']=implode("<br>",$errors);
        header('Location: '.$BASE.'info_perso.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE PERSONNE
                       SET PER_NOM=:n, PER_PRENOM=:p, PER_EMAIL=:e, PER_NUM_TEL=:t
                       WHERE PER_ID=:id")
            ->execute([':n'=>$nom, ':p'=>$pre, ':e'=>$email, ':t'=>$tel, ':id'=>$perId]);

        $pdo->prepare("UPDATE CLIENT SET CLI_DATENAISSANCE=:d WHERE PER_ID=:id")
            ->execute([':d'=>$dnaiss, ':id'=>$perId]);

        $up = function(PDO $pdo,int $perId,array $n,string $type){
            $empty = ($n['rue']==='' && $n['num']==='' && $n['npa']==='' && $n['ville']==='' && $n['pays']==='');
            if ($empty) return;
            if ($n['id']) {
                $pdo->prepare("UPDATE ADRESSE SET ADR_RUE=:r, ADR_NUMERO=:num, ADR_NPA=:npa, ADR_VILLE=:v, ADR_PAYS=:p
                               WHERE ADR_ID=:id AND ADR_TYPE=:t")
                    ->execute([':r'=>$n['rue'],':num'=>$n['num'],':npa'=>$n['npa'],':v'=>$n['ville'],':p'=>$n['pays'],':id'=>$n['id'],':t'=>$type]);
                $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:per,:adr)")
                    ->execute([':per'=>$perId, ':adr'=>$n['id']]);
            } else {
                $pdo->prepare("INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE)
                               VALUES (:r,:num,:npa,:v,:p,:t)")
                    ->execute([':r'=>$n['rue'],':num'=>$n['num'],':npa'=>$n['npa'],':v'=>$n['ville'],':p'=>$n['pays'],':t'=>$type]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:per,:adr)")
                    ->execute([':per'=>$perId, ':adr'=>$newId]);
            }
        };
        $up($pdo,$perId,$nf,'FACTURATION');
        $up($pdo,$perId,$nl,'LIVRAISON');

        $pdo->commit();
        $_SESSION['toast_type']='success'; $_SESSION['toast_msg']="Vos informations ont été mises à jour.";
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['toast_type']='error'; $_SESSION['toast_msg']="Une erreur est survenue lors de la mise à jour.";
    }
    header('Location: '.$BASE.'info_perso.php'); exit;
}

/* =========================
   3bis) ACTIONS NOTIFS
   ========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ack_notif'])) {
    $nid = (int)($_POST['notif_id'] ?? 0);
    if ($nid>0) {
        $pdo->prepare("UPDATE NOTIFICATION SET READ_AT=NOW()
                       WHERE NOT_ID=:id AND PER_ID=:per AND READ_AT IS NULL")
            ->execute([':id'=>$nid, ':per'=>$perId]);
    }
    header('Location: '.$BASE.'info_perso.php#notifs'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ack_all_pickup'])) {
    $pdo->prepare("UPDATE NOTIFICATION SET READ_AT=NOW()
                   WHERE PER_ID=:per AND NOT_TYPE='pickup_ready' AND READ_AT IS NULL")
        ->execute([':per'=>$perId]);
    header('Location: '.$BASE.'info_perso.php'); exit;
}

/* =========================
   4) LISTES (aperçus 3 éléments)
   ========================= */
$totalOrders = count_user_orders($pdo,$perId);
$ordersList  = get_last_orders($pdo,$perId);

$totalNotifs = count_user_notifs($pdo,$perId);
$notifsList  = get_last_notifs($pdo,$perId);

/* Alerte pickup */
$pickupNotifs = get_unread_pickup_notifs($pdo,$perId);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Mes informations</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleInfoPerso.css">
    <style>
        /* Mini bouton à côté des titres */
        .btn-mini{font-size:12px; padding:4px 8px; border:1px solid #8A1B2E; color:#8A1B2E;
            border-radius:999px; text-decoration:none; line-height:1; font-weight:700}
        .btn-mini:hover{background:#f9f2f3}

        /* Bouton secondaire */
        .btn-secondary{border:1px solid #8A1B2E;color:#8A1B2E;background:#fff;padding:6px 10px;border-radius:8px;cursor:pointer;font-weight:700;text-decoration:none}
        .btn-secondary:hover{background:#f9f2f3}
        .btn-ack{background:#8A1B2E;color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:700}
        .btn-ack:hover{background:#6e1522}
        .btn-link{color:#8A1B2E;text-decoration:none;font-weight:700}
        .btn-link:hover{text-decoration:underline}

        /* Alerte pickup */
        .alert-pickup{background:#fff;border-left:4px solid #8A1B2E;padding:12px 14px;margin:12px auto 0;max-width:1100px;box-shadow:0 8px 18px rgba(0,0,0,.06);border-radius:10px;border:1px solid #eee}
        .alert-pickup strong{display:block;font-weight:800;margin-bottom:6px;color:#111}
        .alert-actions{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap}

        /* Grille principale : 2 colonnes, hauteurs égales */
        .grid-two {
            display:grid;
            grid-template-columns:minmax(0,2fr) minmax(0,1fr);
            gap:18px;
            align-items:stretch; /* ← la colonne droite prend la même hauteur que la gauche */
        }
        @media (max-width: 950px){ .grid-two{ grid-template-columns:1fr; } }

        /* Colonne droite qui occupe 100% de la hauteur de la rangée */
        .stack-right{display:flex;flex-direction:column;height:100%;gap:18px}
        .stack-right .card{flex:1;display:flex;flex-direction:column;min-height:0} /* ← 50% / 50% */

        /* Corps scrollable dans chaque carte droite */
        .card-body{min-height:0;overflow:auto}

        /* Table compacte */
        .orders.compact td, .orders.compact th{padding:8px 10px}

        /* Notifications */
        .notif-list{list-style:none;margin:0;padding:0}
        .notif-item{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid #eee;border-radius:10px;margin-bottom:10px;background:#fff}
        .notif-item.unread{background:#fff8f9;border-color:#f1d3d7}
        .notif-item .title{display:flex;align-items:center;gap:8px;font-weight:700;color:#111}
        .notif-item .meta{color:#6b7280;font-size:12px;margin-top:2px}
        .notif-item .dot{width:10px;height:10px;border-radius:50%;background:#e0112b;display:inline-block;box-shadow:0 0 0 2px rgba(255,255,255,.95)}
        .section-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Toast -->
<?php if (!empty($_SESSION['toast_msg'])): ?>
    <div id="toast" class="toast <?= ($_SESSION['toast_type'] ?? 'success') === 'error' ? 'error' : '' ?>">
        <?= $_SESSION['toast_msg']; ?>
    </div>
    <?php unset($_SESSION['toast_msg'], $_SESSION['toast_type']); ?>
<?php endif; ?>

<main class="container page-profile variant-white" aria-label="Mes informations">
    <div class="grid-two">

        <!-- ======= Colonne gauche : Profil ======= -->
        <section class="card" aria-label="Profil">
            <h2 class="section-title">Informations personnelles</h2>

            <form method="post" id="form-profile">
                <div class="form-row">
                    <div class="field">
                        <label for="nom">Nom</label>
                        <input id="nom" name="nom" required value="<?= h($personne['PER_NOM']) ?>">
                    </div>
                    <div class="field">
                        <label for="prenom">Prénom</label>
                        <input id="prenom" name="prenom" required value="<?= h($personne['PER_PRENOM']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" required value="<?= h($personne['PER_EMAIL']) ?>">
                    </div>
                    <div class="field">
                        <label for="tel">Téléphone (ex: 0791234567)</label>
                        <input id="tel" name="tel" pattern="0[0-9]{9}" value="<?= h($personne['PER_NUM_TEL']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="dnaiss">Date de naissance (client)</label>
                        <input type="date" id="dnaiss" name="dnaiss"
                               value="<?= h($client['CLI_DATENAISSANCE'] ? substr($client['CLI_DATENAISSANCE'],0,10) : '') ?>">
                    </div>
                    <div class="field"></div>
                </div>

                <hr class="sep">

                <h2 class="section-title">Adresse de FACTURATION</h2>
                <input type="hidden" name="facturation[adr_id]" value="<?= h($adrFac['ADR_ID']) ?>">
                <div class="form-row-3">
                    <div class="field"><label>Rue</label><input name="facturation[rue]" value="<?= h($adrFac['ADR_RUE']) ?>"></div>
                    <div class="field"><label>N°</label><input name="facturation[numero]" value="<?= h($adrFac['ADR_NUMERO']) ?>"></div>
                    <div class="field"><label>NPA</label><input name="facturation[npa]" value="<?= h($adrFac['ADR_NPA']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>Ville</label><input name="facturation[ville]" value="<?= h($adrFac['ADR_VILLE']) ?>"></div>
                    <div class="field"><label>Pays</label><input name="facturation[pays]" value="<?= h($adrFac['ADR_PAYS']) ?>"></div>
                </div>

                <div class="same-check">
                    <input type="checkbox" id="same_fac_to_liv" name="same_fac_to_liv" value="1">
                    <label for="same_fac_to_liv">Utiliser la même adresse pour la LIVRAISON</label>
                </div>

                <h2 class="section-title">Adresse de LIVRAISON</h2>
                <input type="hidden" name="livraison[adr_id]" value="<?= h($adrLiv['ADR_ID']) ?>">
                <div class="form-row-3">
                    <div class="field"><label>Rue</label><input name="livraison[rue]" value="<?= h($adrLiv['ADR_RUE']) ?>"></div>
                    <div class="field"><label>N°</label><input name="livraison[numero]" value="<?= h($adrLiv['ADR_NUMERO']) ?>"></div>
                    <div class="field"><label>NPA</label><input name="livraison[npa]" value="<?= h($adrLiv['ADR_NPA']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>Ville</label><input name="livraison[ville]" value="<?= h($adrLiv['ADR_VILLE']) ?>"></div>
                    <div class="field"><label>Pays</label><input name="livraison[pays]" value="<?= h($adrLiv['ADR_PAYS']) ?>"></div>
                </div>

                <div class="actions">
                    <a class="btn-secondary" href="<?= $BASE ?>commande.php">Aller au panier</a>
                    <button class="btn-ack" type="submit" name="update_profile" value="1">Appliquer</button>
                </div>

                <p class="muted">
                    Modifier le mot de passe ? <a class="btn-link" href="<?= $BASE ?>interface_oubli_mdp.php">Cliquez ici</a>.
                </p>
                <a class="btn-link" href="<?= $BASE ?>supprimer_compte.php">Supprimer le compte</a>
            </form>
        </section>

        <!-- ======= Colonne droite : deux cartes à hauteur égale ======= -->
        <aside class="stack-right">

            <!-- Mes commandes -->
            <section class="card" id="orders" aria-label="Mes commandes">
                <div class="section-head">
                    <h2 class="section-title" style="margin:0">Mes commandes</h2>
                    <?php if ($totalOrders > 3): ?>
                        <a class="btn-mini" href="<?= $BASE ?>mes_commandes.php">Voir plus</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php if (!$ordersList): ?>
                        <p>Aucune commande pour l’instant.</p>
                    <?php else: ?>
                        <table class="orders compact">
                            <thead><tr><th>#</th><th>Date</th><th>Statut</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($ordersList as $o): ?>
                                <tr>
                                    <td><?= (int)$o['COM_ID'] ?></td>
                                    <td><?= h(fmtDate($o['COM_DATE'])) ?></td>
                                    <?php
                                    $map = ['BOUT'=>'Retrait boutique','GVA'=>'Livraison Genève','CH'=>'Livraison Suisse'];
                                    ?>
                                    <td>
                                        <?= h($o['COM_STATUT'] ?: '—') ?>
                                        <?php if (!empty($o['LIV_MODE'])): ?>
                                            <span class="muted"> — <?= h($map[$o['LIV_MODE']] ?? $o['LIV_MODE']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a class="btn-secondary" href="<?= $BASE ?>detail_commande.php?com_id=<?= (int)$o['COM_ID'] ?>">Détails</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Notifications -->
            <section class="card" id="notifs" aria-label="Notifications">
                <div class="section-head">
                    <h2 class="section-title" style="margin:0">Notifications</h2>
                    <?php if ($totalNotifs > 3): ?>
                        <a class="btn-mini" href="<?= $BASE ?>mes_notifications.php">Voir plus</a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php if (empty($notifsList)): ?>
                        <p>Vous n’avez aucune notification.</p>
                    <?php else: ?>
                        <ul class="notif-list">
                            <?php foreach ($notifsList as $n):
                                $isUnread = empty($n['READ_AT']);
                                $deb = $n['COM_RETRAIT_DEBUT'] ? date('d.m.Y H:i', strtotime($n['COM_RETRAIT_DEBUT'])) : null;
                                $fin = $n['COM_RETRAIT_FIN']   ? date('d.m.Y H:i', strtotime($n['COM_RETRAIT_FIN']))   : null; ?>
                                <li class="notif-item <?= $isUnread ? 'unread' : '' ?>">
                                    <div class="left">
                                        <div class="title">
                                            <?php if ($isUnread): ?><span class="dot"></span><?php endif; ?>
                                            <span class="txt"><?= h($n['NOT_TEXTE']) ?><?php if($n['COM_ID']): ?> (commande #<?= (int)$n['COM_ID'] ?>)<?php endif; ?></span>
                                        </div>
                                        <div class="meta">
                                            <time datetime="<?= h($n['CREATED_AT']) ?>">
                                                <?= $n['CREATED_AT'] ? date('d.m.Y H:i', strtotime($n['CREATED_AT'])) : '' ?>
                                            </time>
                                            <?php if ($deb && $fin): ?> · Retrait: <?= h($deb) ?> → <?= h($fin) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="right">
                                        <?php if ($isUnread): ?>
                                            <form method="post">
                                                <input type="hidden" name="ack_notif" value="1">
                                                <input type="hidden" name="notif_id" value="<?= (int)$n['NOT_ID'] ?>">
                                                <button class="btn-ack" type="submit">J’ai compris</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="meta">Lu</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    // Copie FAC -> LIV
    (function(){
        const same=document.getElementById('same_fac_to_liv');
        const form=document.getElementById('form-profile');
        if(!same||!form) return;
        const fac=form.querySelectorAll('[name^="facturation["]');
        function sync(disable){
            fac.forEach(f=>{
                const key=f.name.match(/facturation\[(.+)\]/)?.[1];
                if(!key) return;
                const t=form.querySelector(`[name="livraison[${key}]"]`);
                if(!t) return;
                t.value=f.value;
                disable?t.setAttribute('disabled','disabled'):t.removeAttribute('disabled');
            });
        }
        same.addEventListener('change',()=>same.checked?sync(true):sync(false));
        fac.forEach(f=>f.addEventListener('input',()=>{ if(same.checked) sync(true); }));
    })();

    // Toast auto-hide
    (function(){
        const t=document.getElementById('toast');
        if(!t) return;
        requestAnimationFrame(()=>t.classList.add('show'));
        setTimeout(()=>t.classList.remove('show'),4000);
    })();
</script>

</body>
</html>
