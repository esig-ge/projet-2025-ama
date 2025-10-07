<?php
// /site/pages/info_perso.php
// Page "Mon espace > Mes informations"
// - Récupère / met à jour: nom, prénom, email, tel, date de naissance (CLIENT)
// - Gère 2 adresses: FACTURATION et LIVRAISON (+ case "même adresse")
// - Affiche la liste des commandes
// NIVEAU: étudiant 2e année — code clair, robuste et commenté.

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

/** fetchOne: prépare + exécute + retourne 1 ligne (ou null) */
function fetchOne(PDO $pdo, string $sql, array $p){
    $st=$pdo->prepare($sql); $st->execute($p);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** email déjà pris par une autre personne ? (corrigé: 1 seul execute) */
function emailTakenByAnother(PDO $pdo, string $email, int $perId): bool {
    $st = $pdo->prepare("SELECT 1 FROM PERSONNE WHERE PER_EMAIL=:e AND PER_ID<>:id LIMIT 1");
    $st->execute([':e'=>$email, ':id'=>$perId]);
    return (bool)$st->fetchColumn();
}

/** recherche d’un doublon d’adresse (même texte, même type) */
function findDuplicateAddressId(
    PDO $pdo, int $perId, string $type,
    string $rue, string $num, string $npa, string $ville, string $pays,
    ?int $excludeAdrId = null
): ?int {
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

    $params = [
        ':per'=>$perId, ':type'=>$type, ':rue'=>$rue, ':num'=>$num,
        ':npa'=>$npa, ':ville'=>$ville, ':pays'=>$pays
    ];
    if ($excludeAdrId) $params[':exclude'] = $excludeAdrId;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

/* =========================
   2) LECTURE DES DONNÉES
   ========================= */
// PERSONNE
$personne = fetchOne(
    $pdo,
    "SELECT PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL FROM PERSONNE WHERE PER_ID=:id",
    [':id'=>$perId]
) ?: ['PER_NOM'=>'','PER_PRENOM'=>'','PER_EMAIL'=>'','PER_NUM_TEL'=>''];

// CLIENT (date de naissance)
$client = fetchOne(
    $pdo,
    "SELECT CLI_DATENAISSANCE FROM CLIENT WHERE PER_ID=:id",
    [':id'=>$perId]
);
if (!$client || !array_key_exists('CLI_DATENAISSANCE', $client)) {
    // Si l’entrée n’existe pas, on la crée (utile si anciens imports)
    $pdo->prepare(
        "INSERT IGNORE INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
         VALUES (:id, NULL, 0)"
    )->execute([':id'=>$perId]);
    $client = ['CLI_DATENAISSANCE'=>null];
}

// Adresses (on récupère la plus récente de chaque type)
$sqlAdr = "SELECT A.ADR_ID, A.ADR_RUE, A.ADR_NUMERO, A.ADR_NPA, A.ADR_VILLE, A.ADR_PAYS, A.ADR_TYPE
           FROM ADRESSE A
           JOIN ADRESSE_CLIENT AC ON AC.ADR_ID = A.ADR_ID
           WHERE AC.PER_ID = :id AND A.ADR_TYPE = :type
           ORDER BY A.ADR_ID DESC LIMIT 1";

$adrLiv = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'LIVRAISON']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'',
    'ADR_VILLE'=>'','ADR_PAYS'=>'Suisse','ADR_TYPE'=>'LIVRAISON'
];
$adrFac = fetchOne($pdo, $sqlAdr, [':id'=>$perId, ':type'=>'FACTURATION']) ?: [
    'ADR_ID'=>null,'ADR_RUE'=>'','ADR_NUMERO'=>'','ADR_NPA'=>'',
    'ADR_VILLE'=>'','ADR_PAYS'=>'Suisse','ADR_TYPE'=>'FACTURATION'
];

/* =========================
   3) TRAITEMENT POST (UPDATE)
   ========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])) {
    $errors = [];

    // a) PERSONNE
    $nom   = trim($_POST['nom'] ?? '');
    $pre   = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel   = trim($_POST['tel'] ?? '');

    if ($email !== '' && emailTakenByAnother($pdo, $email, $perId)) {
        $errors[] = "Cet e-mail est déjà utilisé par un autre compte.";
    }

    // b) CLIENT — date de naissance
    // <input type="date"> renvoie "YYYY-MM-DD".
    // On tolère aussi "DD/MM/YYYY" si jamais (saisie manuelle).
    $dnaiss = trim($_POST['dnaiss'] ?? '');
    if ($dnaiss !== '') {
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $dnaiss)) {
            // ok, on garde tel quel
        } elseif (preg_match('#^\d{2}/\d{2}/\d{4}$#', $dnaiss)) {
            [$d,$m,$y] = explode('/',$dnaiss); $dnaiss = "$y-$m-$d";
        } else {
            $errors[] = "Format de date invalide (utilisez AAAA-MM-JJ).";
        }
    } else {
        $dnaiss = null;
    }

    // c) Adresses + éventuelle copie FAC -> LIV
    $sameFacToLiv = !empty($_POST['same_fac_to_liv']);

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

    // Normalisation minimale
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

    // Vérif doublons adresses (si au moins 1 champ rempli)
    $hasFacInput = ($nf['rue']!=='' || $nf['num']!=='' || $nf['npa']!=='' || $nf['ville']!=='' || $nf['pays']!=='');
    $hasLivInput = ($nl['rue']!=='' || $nl['num']!=='' || $nl['npa']!=='' || $nl['ville']!=='' || $nl['pays']!=='');

    if ($hasFacInput) {
        $dupFacId = findDuplicateAddressId($pdo, $perId, 'FACTURATION', $nf['rue'],$nf['num'],$nf['npa'],$nf['ville'],$nf['pays'], $nf['id']);
        if ($dupFacId) { $errors[] = "L’adresse de FACTURATION existe déjà."; }
    }
    if ($hasLivInput) {
        $dupLivId = findDuplicateAddressId($pdo, $perId, 'LIVRAISON', $nl['rue'],$nl['num'],$nl['npa'],$nl['ville'],$nl['pays'], $nl['id']);
        if ($dupLivId) { $errors[] = "L’adresse de LIVRAISON existe déjà."; }
    }

    // En cas d’erreurs: toast + redirection
    if ($errors) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = implode("<br>", $errors);
        header('Location: '.$BASE.'info_perso.php'); exit;
    }

    // Appliquer les mises à jour
    $pdo->beginTransaction();
    try {
        // PERSONNE
        $pdo->prepare(
            "UPDATE PERSONNE
             SET PER_NOM=:n, PER_PRENOM=:p, PER_EMAIL=:e, PER_NUM_TEL=:t
             WHERE PER_ID=:id"
        )->execute([':n'=>$nom, ':p'=>$pre, ':e'=>$email, ':t'=>$tel, ':id'=>$perId]);

        // CLIENT
        $pdo->prepare("UPDATE CLIENT SET CLI_DATENAISSANCE = :d WHERE PER_ID=:id")
            ->execute([':d'=>$dnaiss, ':id'=>$perId]);

        // petite fonction locale d'upsert adresse
        $upsert = function(PDO $pdo, int $perId, array $n, string $type){
            $empty = ($n['rue']==='' && $n['num']==='' && $n['npa']==='' && $n['ville']==='' && $n['pays']==='');
            if ($empty) return;

            if ($n['id']) {
                // UPDATE
                $pdo->prepare(
                    "UPDATE ADRESSE
                     SET ADR_RUE=:r, ADR_NUMERO=:num, ADR_NPA=:npa, ADR_VILLE=:v, ADR_PAYS=:p
                     WHERE ADR_ID=:id AND ADR_TYPE=:t"
                )->execute([
                    ':r'=>$n['rue'], ':num'=>$n['num'], ':npa'=>$n['npa'], ':v'=>$n['ville'], ':p'=>$n['pays'],
                    ':id'=>$n['id'], ':t'=>$type
                ]);
                // s’assurer du lien
                $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:per, :adr)")
                    ->execute([':per'=>$perId, ':adr'=>$n['id']]);
            } else {
                // INSERT + lien
                $pdo->prepare(
                    "INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE)
                     VALUES (:r, :num, :npa, :v, :p, :t)"
                )->execute([
                    ':r'=>$n['rue'], ':num'=>$n['num'], ':npa'=>$n['npa'], ':v'=>$n['ville'], ':p'=>$n['pays'], ':t'=>$type
                ]);
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

/* =========================
   4) LISTE DES COMMANDES
   ========================= */
$st = $pdo->prepare(
    "SELECT COM_ID, COM_DATE, COM_STATUT
     FROM COMMANDE
     WHERE PER_ID = :id
     ORDER BY COM_DATE DESC, COM_ID DESC"
);
$st->execute([':id'=>$perId]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Mes informations</title>

    <!-- Feuilles de style globales + page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleInfoPerso.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Toast (message flash) -->
<?php if (!empty($_SESSION['toast_msg'])): ?>
    <div id="toast" class="toast <?= ($_SESSION['toast_type'] ?? 'success') === 'error' ? 'error' : '' ?>">
        <?= $_SESSION['toast_msg']; ?>
    </div>
    <?php unset($_SESSION['toast_msg'], $_SESSION['toast_type']); ?>
<?php endif; ?>

<main class="container page-profile variant-white" aria-label="Mes informations">
       <div class="grid">
        <!-- ======= Formulaire profil ======= -->
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
                        <!-- pattern simple: 10 chiffres commençant par 0 -->
                        <input id="tel" name="tel" pattern="0[0-9]{9}" value="<?= h($personne['PER_NUM_TEL']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="dnaiss">Date de naissance (client)</label>
                        <input type="date" id="dnaiss" name="dnaiss"
                               value="<?= h($client['CLI_DATENAISSANCE'] ? substr($client['CLI_DATENAISSANCE'],0,10) : '') ?>">
                    </div>
                    <div class="field"><!-- espace --></div>
                </div>

                <hr class="sep">

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

                <div class="same-check">
                    <input type="checkbox" id="same_fac_to_liv" name="same_fac_to_liv" value="1">
                    <label for="same_fac_to_liv">Utiliser la même adresse pour la LIVRAISON</label>
                </div>

                <h2 class="section-title">Adresse de LIVRAISON</h2>
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

                <p class="muted">
                    Modifier le mot de passe ? <a href="<?= $BASE ?>interface_oubli_mdp.php">Cliquez ici</a>.
                </p>
                <a href="<?= $BASE ?>supprimer_compte.php">Supprimer le compte</a>
            </form>
        </section>

        <!-- ======= Commandes ======= -->
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
    /* =========================================
       UI: “Même adresse que la facturation”
       - Copie FAC -> LIV
       - Désactive les champs LIV si coché
       ========================================= */
    (function(){
        const same = document.getElementById('same_fac_to_liv');
        const form = document.getElementById('form-profile');
        const fac  = form.querySelectorAll('[name^="facturation["]');
        const liv  = form.querySelectorAll('[name^="livraison["]:not([name$="[adr_id]"])');

        function syncAndToggle(disable){
            fac.forEach(f => {
                const key = f.name.match(/facturation\[(.+)\]/)?.[1];
                if (!key) return;
                const target = form.querySelector(`[name="livraison[${key}]"]`);
                if (!target) return;
                target.value = f.value;
                if (disable) target.setAttribute('disabled','disabled');
                else target.removeAttribute('disabled');
            });
        }

        same.addEventListener('change', () => {
            if (same.checked) syncAndToggle(true);
            else syncAndToggle(false);
        });
        // resynchro si l’utilisateur modifie FAC pendant que c’est coché
        fac.forEach(f => f.addEventListener('input', () => { if (same.checked) syncAndToggle(true); }));
    })();

    /* =========================================
       Toast: animation simple + auto-hide
       ========================================= */
    (function(){
        const t = document.getElementById('toast');
        if (!t) return;
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => t.classList.remove('show'), 4000);
    })();
</script>
</body>
</html>
