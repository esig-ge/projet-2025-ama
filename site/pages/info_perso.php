<?php
// /site/pages/info_perso.php
session_start();

// Base URL (slash final)
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

// --------- helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetchOne(PDO $pdo, string $sql, array $p){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }

// --------- 1) PERSONNE + CLIENT (date de naissance) ----------
$personne = fetchOne($pdo,
    "SELECT PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_ID=:id",
    [':id'=>$perId]
) ?: ['PER_NOM'=>'','PER_PRENOM'=>'','PER_EMAIL'=>'','PER_NUM_TEL'=>''];

$client   = fetchOne($pdo,
    "SELECT CLI_DATENAISSANCE FROM CLIENT WHERE PER_ID=:id",
    [':id'=>$perId]
) ?: ['CLI_DATENAISSANCE'=>null];

// Si pas encore dans CLIENT, on crée l’entrée (utile si imports anciens)
if (!$client || !array_key_exists('CLI_DATENAISSANCE', $client)) {
    $pdo->prepare("INSERT IGNORE INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE) VALUES (:id, NULL, 0)")
        ->execute([':id'=>$perId]);
    $client = ['CLI_DATENAISSANCE'=>null];
}

// --------- 2) Adresses LIVRAISON & FACTURATION ----------
$sqlAdr = "SELECT A.ADR_ID, A.ADR_RUE, A.ADR_NUMERO, A.ADR_NPA, A.ADR_VILLE, A.ADR_PAYS, A.ADR_TYPE
           FROM ADRESSE A
           JOIN ADRESSE_CLIENT AC ON AC.ADR_ID = A.ADR_ID
           WHERE AC.PER_ID = :id AND A.ADR_TYPE = :type
           ORDER BY A.ADR_ID DESC LIMIT 1";

$adrLiv  = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'LIVRAISON']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'','ADR_VILLE'=>'','ADR_PAYS'=>'Suisse','ADR_TYPE'=>'LIVRAISON'
];
$adrFac  = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'FACTURATION']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'','ADR_VILLE'=>'','ADR_PAYS'=>'Suisse','ADR_TYPE'=>'FACTURATION'
];

// ===== Fonctions de vérif des doublons =====
function findDuplicateAddressId(PDO $pdo, int $perId, string $type, string $rue, string $num, string $npa, string $ville, string $pays, ?int $excludeAdrId = null): ?int {
    $sql = "SELECT A.ADR_ID
            FROM ADRESSE A
            JOIN ADRESSE_CLIENT AC ON AC.ADR_ID = A.ADR_ID
            WHERE AC.PER_ID = :per
              AND A.ADR_TYPE = :type
              AND TRIM(LOWER(A.ADR_RUE))    = TRIM(LOWER(:rue))
              AND TRIM(LOWER(A.ADR_NUMERO)) = TRIM(LOWER(:num))
              AND TRIM(LOWER(A.ADR_NPA))    = TRIM(LOWER(:npa))
              AND TRIM(LOWER(A.ADR_VILLE))  = TRIM(LOWER(:ville))
              AND TRIM(LOWER(A.ADR_PAYS))   = TRIM(LOWER(:pays))";
    if ($excludeAdrId) $sql .= " AND A.ADR_ID <> :exclude";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':per'=>$perId, ':type'=>$type, ':rue'=>$rue, ':num'=>$num, ':npa'=>$npa, ':ville'=>$ville, ':pays'=>$pays,
        ...($excludeAdrId ? [':exclude'=>$excludeAdrId] : [])
    ]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

function emailTakenByAnother(PDO $pdo, string $email, int $perId): bool {
    $st = $pdo->prepare("SELECT 1 FROM PERSONNE WHERE PER_EMAIL=:e AND PER_ID<>:id LIMIT 1");
    $st->execute([':e'=>$email, ':id'=>$perId]);
    $st->execute([':e'=>$email, ':id'=>$perId]);
    return (bool)$st->fetchColumn();
}

// --------- 3) TRAITEMENT POST (mise à jour) ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])) {
    $errors = [];

    // a) PERSONNE
    $nom    = trim($_POST['nom'] ?? '');
    $pre    = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $tel    = trim($_POST['tel'] ?? '');

    if ($email !== '' && emailTakenByAnother($pdo, $email, $perId)) {
        $errors[] = "Erreur — cet e-mail est déjà utilisé.";
    }

    // b) CLIENT (date de naissance — on accepte dd/mm/yyyy)
    $dnaiss = trim($_POST['dnaiss'] ?? '');
    if ($dnaiss && preg_match('#^\d{2}/\d{2}/\d{4}$#',$dnaiss)) {
        [$d,$m,$y] = explode('/',$dnaiss); $dnaiss = "$y-$m-$d";
    }
    if ($dnaiss === '') $dnaiss = null;

    // c) Adresses
    $sameFacToLiv = isset($_POST['same_fac_to_liv']) && $_POST['same_fac_to_liv'] === '1';

    $inFac = $_POST['facturation'] ?? [];
    $inLiv = $_POST['livraison']  ?? [];

    if ($sameFacToLiv) {
        $inLiv = array_merge($inLiv, [
            'rue'    => $inFac['rue']    ?? '',
            'numero' => $inFac['numero'] ?? '',
            'npa'    => $inFac['npa']    ?? '',
            'ville'  => $inFac['ville']  ?? '',
            'pays'   => $inFac['pays']   ?? '',
        ]);
    }

    // Normalisation min
    $nf = [
        'id'    => isset($inFac['adr_id']) && ctype_digit((string)$inFac['adr_id']) ? (int)$inFac['adr_id'] : null,
        'rue'   => trim($inFac['rue']    ?? ''), 'num' => trim($inFac['numero'] ?? ''), 'npa' => trim($inFac['npa'] ?? ''),
        'ville' => trim($inFac['ville']  ?? ''), 'pays'=> trim($inFac['pays']  ?? ''),
    ];
    $nl = [
        'id'    => isset($inLiv['adr_id']) && ctype_digit((string)$inLiv['adr_id']) ? (int)$inLiv['adr_id'] : null,
        'rue'   => trim($inLiv['rue']    ?? ''), 'num' => trim($inLiv['numero'] ?? ''), 'npa' => trim($inLiv['npa'] ?? ''),
        'ville' => trim($inLiv['ville']  ?? ''), 'pays'=> trim($inLiv['pays']  ?? ''),
    ];

    // Vérifs doublons adresses
    if ($nf['rue']!=='' || $nf['num']!=='' || $nf['npa']!=='' || $nf['ville']!=='' || $nf['pays']!=='') {
        $dupFacId = findDuplicateAddressId($pdo, $perId, 'FACTURATION', $nf['rue'],$nf['num'],$nf['npa'],$nf['ville'],$nf['pays'], $nf['id']);
        if ($dupFacId) { $errors[] = "Erreur — l’adresse de FACTURATION existe déjà."; }
    }
    if ($nl['rue']!=='' || $nl['num']!=='' || $nl['npa']!=='' || $nl['ville']!=='' || $nl['pays']!=='') {
        $dupLivId = findDuplicateAddressId($pdo, $perId, 'LIVRAISON', $nl['rue'],$nl['num'],$nl['npa'],$nl['ville'],$nl['pays'], $nl['id']);
        if ($dupLivId) { $errors[] = "Erreur — l’adresse de LIVRAISON existe déjà."; }
    }

    if ($errors) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = implode("<br>", $errors);
        header('Location: '.$BASE.'info_perso.php'); exit;
    }

    // Si ok, on applique les mises à jour
    $pdo->beginTransaction();
    try {
        // PERSONNE
        $pdo->prepare("UPDATE PERSONNE
                       SET PER_NOM=:n, PER_PRENOM=:p, PER_EMAIL=:e, PER_NUM_TEL=:t
                       WHERE PER_ID=:id")
            ->execute([':n'=>$nom, ':p'=>$pre, ':e'=>$email, ':t'=>$tel, ':id'=>$perId]);

        // CLIENT
        $pdo->prepare("UPDATE CLIENT SET CLI_DATENAISSANCE = :d WHERE PER_ID=:id")
            ->execute([':d'=>$dnaiss, ':id'=>$perId]);

        // Upsert adresse utilitaire
        $upsert = function(PDO $pdo, int $perId, array $n, string $type){
            if ($n['rue']==='' && $n['num']==='' && $n['npa']==='' && $n['ville']==='' && $n['pays']==='') {
                return; // rien à faire
            }
            if ($n['id']) {
                // UPDATE
                $pdo->prepare("UPDATE ADRESSE
                               SET ADR_RUE=:r, ADR_NUMERO=:num, ADR_NPA=:npa, ADR_VILLE=:v, ADR_PAYS=:p
                               WHERE ADR_ID=:id AND ADR_TYPE=:t")
                    ->execute([':r'=>$n['rue'], ':num'=>$n['num'], ':npa'=>$n['npa'], ':v'=>$n['ville'], ':p'=>$n['pays'],
                        ':id'=>$n['id'], ':t'=>$type]);
                // s'assurer du lien
                $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:per, :adr)")
                    ->execute([':per'=>$perId, ':adr'=>$n['id']]);
            } else {
                // INSERT + lien
                $pdo->prepare("INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE)
                               VALUES (:r, :num, :npa, :v, :p, :t)")
                    ->execute([':r'=>$n['rue'], ':num'=>$n['num'], ':npa'=>$n['npa'], ':v'=>$n['ville'], ':p'=>$n['pays'], ':t'=>$type]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:per, :adr)")
                    ->execute([':per'=>$perId, ':adr'=>$newId]);
            }
        };

        $upsert($pdo, $perId, $nf, 'FACTURATION');
        $upsert($pdo, $perId, $nl, 'LIVRAISON');

        $pdo->commit();
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_msg']  = "Vos informations ont été mises à jour.";
    } catch (Throwable $ex) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = "Une erreur est survenue lors de la mise à jour.";
    }

    header('Location: '.$BASE.'info_perso.php'); exit;
}

// --------- 4) Commandes ----------
$orders = [];
$st = $pdo->prepare("SELECT COM_ID, COM_DATE, COM_STATUT
                     FROM COMMANDE
                     WHERE PER_ID = :id
                     ORDER BY COM_DATE DESC, COM_ID DESC");
$st->execute([':id'=>$perId]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Mes informations</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleInfoPerso.css">
    <style>
        /* Toast */
        .toast {
            position: fixed; left: 50%; top: 16px; transform: translateX(-50%);
            padding: 12px 16px; border-radius: 10px; box-shadow: 0 4px 18px rgba(0,0,0,.12);
            font-weight: 600; z-index: 9999; opacity: 0; pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            background: #f5fff5; color:#0b7a0b; border:1px solid #cce8cc;
        }
        .toast.error { background:#fff6f6; color:#a31010; border-color:#f1c8c8; }
        .toast.show { opacity: 1; pointer-events: auto; transform: translateX(-50%) translateY(0); }
        .flash { margin: 8px 0; }
        .section-title { color:#7a0000; }
        .btn-primary { background:#7a0000; }
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

<main class="container page-profile" aria-label="Mes informations">
    <h1 class="section-title" style="font-size:1.6rem">Mes informations</h1>

    <div class="grid">
        <!-- Formulaire -->
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

                <hr style="margin:16px 0;border:0;border-top:1px solid #eee">

                <h2 class="section-title">Adresse de FACTURATION</h2>
                <input type="hidden" name="facturation[adr_id]" value="<?= h($adrFac['ADR_ID']) ?>">
                <div class="form-row-3">
                    <div class="field">
                        <label>Rue</label>
                        <input name="facturation[rue]" value="<?= h($adrFac['ADR_RUE']) ?>">
                    </div>
                    <div class="field">
                        <label>N°</label>
                        <input name="facturation[numero]" value="<?= h($adrFac['ADR_NUMERO']) ?>">
                    </div>
                    <div class="field">
                        <label>NPA</label>
                        <input name="facturation[npa]" value="<?= h($adrFac['ADR_NPA']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>Ville</label>
                        <input name="facturation[ville]" value="<?= h($adrFac['ADR_VILLE']) ?>">
                    </div>
                    <div class="field">
                        <label>Pays</label>
                        <input name="facturation[pays]" value="<?= h($adrFac['ADR_PAYS']) ?>">
                    </div>
                </div>

                <div class="same-check" style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0 1rem">
                    <input type="checkbox" id="same_fac_to_liv" name="same_fac_to_liv" value="1">
                    <label for="same_fac_to_liv">Utiliser la même adresse pour la LIVRAISON</label>
                </div>

                <h2 class="section-title" style="margin-top:6px">Adresse de LIVRAISON</h2>
                <input type="hidden" name="livraison[adr_id]" value="<?= h($adrLiv['ADR_ID']) ?>">
                <div class="form-row-3">
                    <div class="field">
                        <label>Rue</label>
                        <input name="livraison[rue]" value="<?= h($adrLiv['ADR_RUE']) ?>">
                    </div>
                    <div class="field">
                        <label>N°</label>
                        <input name="livraison[numero]" value="<?= h($adrLiv['ADR_NUMERO']) ?>">
                    </div>
                    <div class="field">
                        <label>NPA</label>
                        <input name="livraison[npa]" value="<?= h($adrLiv['ADR_NPA']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>Ville</label>
                        <input name="livraison[ville]" value="<?= h($adrLiv['ADR_VILLE']) ?>">
                    </div>
                    <div class="field">
                        <label>Pays</label>
                        <input name="livraison[pays]" value="<?= h($adrLiv['ADR_PAYS']) ?>">
                    </div>
                </div>

                <div class="actions">
                    <a class="btn-secondary" href="<?= $BASE ?>commande.php">Aller au panier</a>
                    <button class="btn-primary" type="submit" name="update_profile" value="1">Appliquer</button>
                </div>
                <p style="margin-top:10px;font-size:.9rem">
                    Modifier le mot de passe ? <a href="<?= $BASE ?>interface_oubli_mdp.php">Cliquez ici</a>.
                </p>
            </form>
        </section>

        <!-- Commandes -->
        <aside class="card" aria-label="Mes commandes">
            <h2 class="section-title">Mes commandes</h2>
            <?php if (!$orders): ?>
                <p>Aucune commande pour l’instant.</p>
            <?php else: ?>
                <table class="orders">
                    <thead><tr><th>#</th><th>Date</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= (int)$o['COM_ID'] ?></td>
                            <td><?= h(date('d.m.Y', strtotime($o['COM_DATE']))) ?></td>
                            <td><?= h($o['COM_STATUT']) ?></td>
                            <td><a class="btn-secondary" href="<?= $BASE ?>detail_commande.php?com_id=<?= (int)$o['COM_ID'] ?>">Détails</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    /* ==== UI: “Même adresse que la facturation” ==== */
    (function(){
        const same = document.getElementById('same_fac_to_liv');
        const form = document.getElementById('form-profile');
        const fac  = form.querySelectorAll('[name^="facturation["]');
        const liv  = form.querySelectorAll('[name^="livraison["]:not([name$="[adr_id]"])');

        function syncAndToggle(disable){
            // copie des valeurs fac -> liv
            fac.forEach(f => {
                const key = f.name.match(/facturation\[(.+)\]/)?.[1];
                if (!key) return;
                const target = form.querySelector(`[name="livraison[${key}]"]`);
                if (target && target.tagName === 'INPUT') {
                    target.value = f.value;
                    if(disable){ target.setAttribute('disabled','disabled'); }
                    else { target.removeAttribute('disabled'); }
                }
            });
        }

        same.addEventListener('change', () => {
            if (same.checked) syncAndToggle(true);
            else syncAndToggle(false);
        });

        // Si utilisateur modifie facturation alors que la case est cochée, on resynchronise
        fac.forEach(f => f.addEventListener('input', () => { if (same.checked) syncAndToggle(true); }));
    })();

    /* ==== Toast ==== */
    (function(){
        const t = document.getElementById('toast');
        if (!t) return;
        // petite animation d’entrée + auto-hide
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => t.classList.remove('show'), 4000);
    })();
</script>
</body>
</html>
