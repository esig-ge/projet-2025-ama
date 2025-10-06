<?php
// /site/pages/admin_produit_add.php
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ===== Accès admin strict ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if (!$isAdmin) { http_response_code(403); exit('Accès réservé à l’administrateur'); }

/* ===== Connexion DB ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Type pré-sélectionné via ?type= ===== */
$prefType   = $_GET['type'] ?? null; // 'bouquet' | 'fleur' | 'coffret' | 'supplement' | 'emballage' | 'autre'
$validTypes = ['bouquet','fleur','coffret','supplement','emballage','autre'];
if ($prefType && !in_array($prefType, $validTypes, true)) { $prefType = null; }

/* ===== Dossier upload ===== */
$UPLOAD_DIR = __DIR__ . '/uploads/products/';     // chemin serveur
$PUBLIC_DIR = $BASE . 'uploads/products/';        // URL publique
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0775, true); }

/* ===== Helpers ===== */
function clean_name(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('~[^a-z0-9._-]+~', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'image';
}
function rand_suffix(int $len=8): string { return bin2hex(random_bytes(intval($len/2))); }

/* ===== Variables d’état ===== */
$errors = [];
$okMsg  = '';

/* ===== Traitement POST ===== */
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $errors[] = "Session expirée (CSRF).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Champs communs
    $pro_nom  = trim($_POST['pro_nom']  ?? '');
    $pro_prix = (float)($_POST['pro_prix'] ?? 0);
    $pro_type = trim($_POST['pro_type'] ?? ($prefType ?: 'autre'));
    $pro_desc = trim($_POST['pro_desc'] ?? '');

    if ($pro_nom === '') $errors[] = "Le nom du produit est obligatoire.";
    if ($pro_prix <= 0)  $errors[] = "Le prix doit être positif.";
    if (!in_array($pro_type, $validTypes, true)) $errors[] = "Type de produit invalide.";

    // Champs spécifiques (bouquet)
    $bou_nb_roses = (int)($_POST['bou_nb_roses'] ?? 12);
    $bou_couleur  = trim($_POST['bou_couleur'] ?? 'rouge');
    $bou_stock    = (int)($_POST['bou_stock'] ?? 10);
    if ($pro_type === 'bouquet') {
        if ($bou_nb_roses <= 0) $errors[] = "Le nombre de roses doit être > 0.";
        if ($bou_stock   <  0)  $errors[] = "Le stock bouquet doit être ≥ 0.";
    }

    // Champs spécifiques (fleur)
    $fle_couleur = trim($_POST['fle_couleur'] ?? 'rouge');
    $fle_stock   = (int)($_POST['fle_stock'] ?? 50);
    if ($pro_type === 'fleur') {
        if ($fle_stock < 0) $errors[] = "Le stock fleur doit être ≥ 0.";
    }

    // Upload image (facultatif)
    $imgPath = null;
    if (!empty($_FILES['pro_img']['name'])) {
        $f = $_FILES['pro_img'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur d’upload (code {$f['error']}).";
        } else {
            if ($f['size'] > 5 * 1024 * 1024) {
                $errors[] = "L’image dépasse 5 Mo.";
            } else {
                // Vérif MIME: finfo si dispo, sinon getimagesize
                $mime = null;
                if (class_exists('finfo')) {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
                } else {
                    $gi = @getimagesize($f['tmp_name']);
                    $mime = $gi['mime'] ?? '';
                }
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif',
                ];
                if (!isset($allowed[$mime])) {
                    $errors[] = "Format d’image non supporté (JPEG/PNG/WebP/GIF uniquement).";
                } else {
                    $ext  = $allowed[$mime];
                    $base = clean_name(pathinfo($f['name'], PATHINFO_FILENAME));
                    $name = $base . '-' . rand_suffix() . '.' . $ext;
                    if (!move_uploaded_file($f['tmp_name'], $UPLOAD_DIR . $name)) {
                        $errors[] = "Impossible d’enregistrer le fichier uploadé.";
                    } else {
                        $imgPath = $PUBLIC_DIR . $name; // URL publique à stocker en BDD
                    }
                }
            }
        }
    }
// après lecture de fleur/bouquet existants…
    $cof_evenement = trim($_POST['cof_evenement'] ?? '');
    $cof_stock     = (int)($_POST['cof_stock'] ?? 0);

    $sup_nom   = trim($_POST['sup_nom'] ?? '');
    $sup_desc  = trim($_POST['sup_description'] ?? '');
    $sup_pu_in = (string)($_POST['sup_prix_unitaire'] ?? '');
    $sup_stock = (int)($_POST['sup_qte_stock'] ?? 0);
    $sup_coul  = trim($_POST['sup_couleur'] ?? '');

    $emb_nom   = trim($_POST['emb_nom'] ?? '');
    $emb_coul  = trim($_POST['emb_couleur'] ?? '');
    $emb_stock = (int)($_POST['emb_qte_stock'] ?? 0);

// validations minimales
    if ($pro_type === 'coffret' && $cof_stock < 0) $errors[] = "Le stock coffret doit être ≥ 0.";
    if ($pro_type === 'supplement') {
        if ($sup_nom === '') $errors[] = "Nom du supplément requis.";
        if (!preg_match('~^\d+(?:\.\d{1,2})?$~', $sup_pu_in)) $errors[] = "Prix unitaire supplément invalide.";
        if ($sup_stock < 0) $errors[] = "Stock supplément ≥ 0.";
    }
    if ($pro_type === 'emballage') {
        if ($emb_nom === '') $errors[] = "Nom d’emballage requis.";
        if ($emb_stock < 0) $errors[] = "Stock emballage ≥ 0.";
    }

    // Insertion
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if (in_array($pro_type, ['bouquet','fleur','coffret'], true)) {
                // 1) PRODUIT (comme tu fais déjà)
                $sql = "INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX, PRO_TYPE, PRO_DESC, PRO_IMG)
                VALUES (:nom, :prix, :type, :descr, :img)";
                $st = $pdo->prepare($sql);
                $st->execute([
                    ':nom'  => $pro_nom,
                    ':prix' => $pro_prix,
                    ':type' => $pro_type,
                    ':descr'=> $pro_desc !== '' ? $pro_desc : null,
                    ':img'  => $imgPath
                ]);
                $newId = (int)$pdo->lastInsertId();

                // 2) Sous-table selon le type
                if ($pro_type === 'bouquet') {
                    $sqlB = "INSERT INTO BOUQUET (PRO_ID, BOU_NB_ROSES, BOU_COULEUR, BOU_QTE_STOCK, BOU_DESCRIPTION)
                     VALUES (:pid, :nb, :coul, :stk, :descr)";
                    $pdo->prepare($sqlB)->execute([
                        ':pid'=>$newId, ':nb'=>$bou_nb_roses, ':coul'=>$bou_couleur,
                        ':stk'=>$bou_stock, ':descr'=>($pro_desc !== '' ? $pro_desc : null)
                    ]);
                } elseif ($pro_type === 'fleur') {
                    $sqlF = "INSERT INTO FLEUR (PRO_ID, FLE_COULEUR, FLE_QTE_STOCK)
                     VALUES (:pid, :coul, :stk)";
                    $pdo->prepare($sqlF)->execute([
                        ':pid'=>$newId, ':coul'=>$fle_couleur, ':stk'=>$fle_stock
                    ]);
                } elseif ($pro_type === 'coffret') {
                    $sqlC = "INSERT INTO COFFRET (PRO_ID, COF_EVENEMENT, COF_QTE_STOCK)
                     VALUES (:pid, :evt, :stk)";
                    $pdo->prepare($sqlC)->execute([
                        ':pid'=>$newId, ':evt'=>($cof_evenement !== '' ? $cof_evenement : null), ':stk'=>$cof_stock
                    ]);
                }

            } elseif ($pro_type === 'supplement') {
                // Pas d'INSERT dans PRODUIT (ta liste lit directement SUPPLEMENT)
                $sup_pu = (float)str_replace(',', '.', $sup_pu_in);
                $sqlS = "INSERT INTO SUPPLEMENT (SUP_NOM, SUP_DESCRIPTION, SUP_PRIX_UNITAIRE, SUP_QTE_STOCK, SUP_COULEUR)
                 VALUES (:nom, :descr, :pu, :stk, :coul)";
                $pdo->prepare($sqlS)->execute([
                    ':nom'=>$sup_nom, ':descr'=>($sup_desc !== '' ? $sup_desc : null),
                    ':pu'=>$sup_pu, ':stk'=>$sup_stock, ':coul'=>($sup_coul !== '' ? $sup_coul : null)
                ]);

            } elseif ($pro_type === 'emballage') {
                // Pas d'INSERT dans PRODUIT (ta liste lit directement EMBALLAGE)
                $sqlE = "INSERT INTO EMBALLAGE (EMB_NOM, EMB_COULEUR, EMB_QTE_STOCK)
                 VALUES (:nom, :coul, :stk)";
                $pdo->prepare($sqlE)->execute([
                    ':nom'=>$emb_nom, ':coul'=>($emb_coul !== '' ? $emb_coul : null), ':stk'=>$emb_stock
                ]);
            }

            $pdo->commit();
            $_SESSION['message'] = "✅ Produit « {$pro_nom} » ajouté avec succès.";
            header('Location: '.$BASE.'admin_modifier_article.php?ok=1');
            exit;

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Erreur à l’enregistrement : ".$ex->getMessage();
        }
    }
}

/* ===== Valeur par défaut du select type ===== */
$defaultType = $_POST['pro_type'] ?? ($prefType ?: 'autre');

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Ajouter un produit</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <style>
        .admin-wrap{max-width:900px;margin:24px auto;padding:16px}
        .form-card{background:#fff;border:1px solid #e7e7ea;border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,.05);padding:20px}
        .form-row{display:grid;grid-template-columns:180px 1fr;gap:12px;align-items:center;margin-bottom:14px}
        .form-row textarea{min-height:120px}
        .form-row input[type="file"]{padding:6px;background:#fafafa;border:1px dashed #ddd;border-radius:8px}
        .actions{display:flex;gap:12px;justify-content:flex-end;margin-top:16px}
        .button{display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid var(--stroke,#e7e7ea);background:var(--brand,#7a0000);color:#fff;text-decoration:none}
        .button.secondary{background:#fff;color:#1a1a1a}
        .flash.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;padding:10px 12px;border-radius:8px;margin-bottom:12px}
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="admin-wrap">
    <h1>Ajouter un produit</h1>

    <?php foreach ($errors as $e): ?>
        <div class="flash err">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="form-card">
        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <!--permet de cacher le champ--->
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <div class="form-row">
                <label for="pro_nom">Nom *</label>
                <input type="text" id="pro_nom" name="pro_nom" required
                       value="<?= htmlspecialchars($_POST['pro_nom'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label for="pro_prix">Prix (CHF) *</label>
                <input type="number" id="pro_prix" name="pro_prix" step="0.05" min="0.05" required
                       value="<?= htmlspecialchars($_POST['pro_prix'] ?? '') ?>">
            </div>

            <div class="form-row">
                <label for="pro_type">Type</label>
                <select id="pro_type" name="pro_type">
                    <?php
                    $types = ['bouquet'=>'Bouquet','fleur'=>'Fleur','coffret'=>'Coffret','supplement'=>'Supplément','emballage'=>'Emballage','autre'=>'Autre'];
                    foreach ($types as $val=>$label) {
                        $sel = ($defaultType === $val) ? 'selected' : '';
                        echo "<option value=\"$val\" $sel>$label</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-row">
                <label for="pro_desc">Description</label>
                <textarea id="pro_desc" name="pro_desc" placeholder="Facultatif…"><?= htmlspecialchars($_POST['pro_desc'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <label for="pro_img">Image (JPEG/PNG/WebP/GIF, max 5 Mo)</label>
                <input type="file" id="pro_img" name="pro_img" accept="image/*">
            </div>
            <!-- Champs spécifiques FLEUR -->
            <div class="form-row fleur-row">
                <label for="fle_couleur">Fleur — Couleur</label>
                <input type="text" id="fle_couleur" name="fle_couleur"
                       value="<?= htmlspecialchars($_POST['fle_couleur'] ?? 'rouge') ?>">
            </div>
            <div class="form-row fleur-row">
                <label for="fle_stock">Fleur — Stock</label>
                <input type="number" id="fle_stock" name="fle_stock" min="0"
                       value="<?= htmlspecialchars($_POST['fle_stock'] ?? 50) ?>">
            </div>

            <div class="actions">
                <a class="button secondary" href="<?= $BASE ?>admin_produits.php">Annuler</a>
                <button class="button" type="submit">Enregistrer</button>
            </div>

            <!-- Champs spécifiques BOUQUET -->
            <div class="form-row bouquet-row">
                <label for="bou_nb_roses">Bouquet — Nb roses</label>
                <input type="number" id="bou_nb_roses" name="bou_nb_roses" min="1"
                       value="<?= htmlspecialchars($_POST['bou_nb_roses'] ?? 12) ?>">
            </div>
            <div class="form-row bouquet-row">
                <label for="bou_couleur">Bouquet — Couleur</label>
                <input type="text" id="bou_couleur" name="bou_couleur"
                       value="<?= htmlspecialchars($_POST['bou_couleur'] ?? 'rouge') ?>">
            </div>
            <div class="form-row bouquet-row">
                <label for="bou_stock">Bouquet — Stock</label>
                <input type="number" id="bou_stock" name="bou_stock" min="0"
                       value="<?= htmlspecialchars($_POST['bou_stock'] ?? 10) ?>">
            </div>

            <!-- COFFRET -->
            <div class="form-row coffret-row" style="display:none">
                <label for="cof_evenement">Coffret — Événement</label>
                <input type="text" id="cof_evenement" name="cof_evenement" value="<?= htmlspecialchars($_POST['cof_evenement'] ?? '') ?>">
            </div>
            <div class="form-row coffret-row" style="display:none">
                <label for="cof_stock">Coffret — Stock</label>
                <input type="number" id="cof_stock" name="cof_stock" min="0" value="<?= htmlspecialchars($_POST['cof_stock'] ?? 0) ?>">
            </div>

            <!-- SUPPLÉMENT -->
            <div class="form-row supplement-row" style="display:none">
                <label for="sup_nom">Supplément — Nom *</label>
                <input type="text" id="sup_nom" name="sup_nom" value="<?= htmlspecialchars($_POST['sup_nom'] ?? '') ?>">
            </div>
            <div class="form-row supplement-row" style="display:none">
                <label for="sup_description">Supplément — Description</label>
                <input type="text" id="sup_description" name="sup_description" value="<?= htmlspecialchars($_POST['sup_description'] ?? '') ?>">
            </div>
            <div class="form-row supplement-row" style="display:none">
                <label for="sup_prix_unitaire">Supplément — Prix unitaire (CHF) *</label>
                <input type="number" id="sup_prix_unitaire" name="sup_prix_unitaire" step="0.05" min="0.00" value="<?= htmlspecialchars($_POST['sup_prix_unitaire'] ?? '') ?>">
            </div>
            <div class="form-row supplement-row" style="display:none">
                <label for="sup_couleur">Supplément — Couleur</label>
                <input type="text" id="sup_couleur" name="sup_couleur" value="<?= htmlspecialchars($_POST['sup_couleur'] ?? '') ?>">
            </div>
            <div class="form-row supplement-row" style="display:none">
                <label for="sup_qte_stock">Supplément — Stock *</label>
                <input type="number" id="sup_qte_stock" name="sup_qte_stock" min="0" value="<?= htmlspecialchars($_POST['sup_qte_stock'] ?? 0) ?>">
            </div>

            <!-- EMBALLAGE -->
            <div class="form-row emballage-row" style="display:none">
                <label for="emb_nom">Emballage — Nom *</label>
                <input type="text" id="emb_nom" name="emb_nom" value="<?= htmlspecialchars($_POST['emb_nom'] ?? '') ?>">
            </div>
            <div class="form-row emballage-row" style="display:none">
                <label for="emb_couleur">Emballage — Couleur</label>
                <input type="text" id="emb_couleur" name="emb_couleur" value="<?= htmlspecialchars($_POST['emb_couleur'] ?? '') ?>">
            </div>
            <div class="form-row emballage-row" style="display:none">
                <label for="emb_qte_stock">Emballage — Stock *</label>
                <input type="number" id="emb_qte_stock" name="emb_qte_stock" min="0" value="<?= htmlspecialchars($_POST['emb_qte_stock'] ?? 0) ?>">
            </div>

        </form>
    </div>
</main>

<script>
    /* Affiche/masque les champs spécifiques selon le type */

        function toggleTypeFields(type){
        // afficher Nom/Prix (PRODUIT) seulement pour fleur/bouquet/coffret
        document.querySelectorAll('.prod-row').forEach(el =>
            el.style.display = (['fleur','bouquet','coffret'].includes(type) ? 'grid' : 'none')
        );

        // groupes spécifiques
        const groups = {
        'fleur': '.fleur-row',
        'bouquet': '.bouquet-row',
        'coffret': '.coffret-row',
        'supplement': '.supplement-row',
        'emballage': '.emballage-row'
    };

        // tout cacher d'abord
        Object.values(groups).forEach(sel =>
        document.querySelectorAll(sel).forEach(el => el.style.display = 'none')
        );

        // montrer le groupe du type choisi
        if (groups[type]) {
        document.querySelectorAll(groups[type]).forEach(el => el.style.display = 'grid');
    }
    }

        const sel = document.getElementById('pro_type');
        if (sel) {
        toggleTypeFields(sel.value);
        sel.addEventListener('change', ()=> toggleTypeFields(sel.value));
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
