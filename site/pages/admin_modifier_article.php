<?php
/* admin_modifier_article.php */
session_start();

/* ===== Connexion DB ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Debug temporaire ===== */
echo "<!-- DEBUG BASE={$BASE} FILE=".__FILE__." -->";
echo "<!-- AJT EXISTS? " . (is_file(__DIR__ . '/admin_produit_ajt.php') ? 'YES' : 'NO') . " -->";
/* ==============================
   Fonctions de récupération
   ============================== */
function recup_donnee_fleur(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_PRIX,
              f.FLE_COULEUR,
              f.FLE_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN FLEUR f ON f.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_bouquet(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_PRIX,
              b.BOU_NB_ROSES,
              b.BOU_COULEUR,
              b.BOU_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN BOUQUET b ON b.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_coffret(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_PRIX,      -- on sélectionne le prix produit (utile pour l’édition)
              c.COF_EVENEMENT,
              c.COF_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN COFFRET c ON c.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_supplement(PDO $pdo): array {
    $sql = "SELECT
              s.SUP_ID,
              s.SUP_NOM,
              s.SUP_DESCRIPTION,
              s.SUP_PRIX_UNITAIRE,
              s.SUP_QTE_STOCK
            FROM SUPPLEMENT s
            ORDER BY s.SUP_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_emballage(PDO $pdo): array {
    $sql = "SELECT
              e.EMB_ID,
              e.EMB_NOM,
              e.EMB_COULEUR,
              e.EMB_QTE_STOCK
            FROM EMBALLAGE e
            ORDER BY e.EMB_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Admin — Modifier les articles</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>/*  a ajouter sur le css plus tard*/
        .btn-add{
            display:inline-block;padding:8px 12px;border-radius:10px;
            background:var(--brand,#7a0000);color:#fff;text-decoration:none;
            margin:6px 0 14px; border:1px solid rgba(0,0,0,.06)
        }
        .btn-add:hover{filter:brightness(1.05)}
        body { font-family: system-ui, Arial, sans-serif; margin: 0; background:#fafafa; color:#111; }
        .admin-header { padding:16px 20px; background:#fff; border-bottom:1px solid #eee; }
        .breadcrumb a { color:#a00; text-decoration:none; }
        .breadcrumb a:hover { text-decoration:underline; }
        h1 { margin:8px 0 0 }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        section.product-details { background:#fff; border:1px solid #eee; border-radius:12px; padding:16px; margin-bottom:20px; }
        section.product-details h3 { margin-top:0 }
        table.kv { width:100%; border-collapse: collapse; }
        table.kv thead td, table.kv thead th { font-weight:700; padding:10px 8px; border-bottom:1px solid #eee; background:#faf7f7; }
        table.kv td { padding:10px 8px; border-bottom:1px solid #f1f1f1; vertical-align: middle; }
        table.kv td input[type="text"],
        table.kv td input[type="number"] { width:100%; box-sizing:border-box; padding:6px 8px; border:1px solid #ddd; border-radius:8px; }
        .actions { white-space: nowrap; }
        .btn { display:inline-block; padding:8px 12px; border:1px solid #990f2b; background:#990f2b; color:#fff; border-radius:10px; cursor:pointer; }
        .btn:hover { filter: brightness(0.95); }
        .msg { margin: 0 0 16px; padding:10px 12px; border-radius:10px; }
        .msg.ok { background:#e9f7ef; border:1px solid #b7e1c6; }
        .msg.err { background:#fdecea; border:1px solid #f5c2be; }
        .empty { color:#666; }
        .modal{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000}
        .modal[hidden]{display:none}
        .modal-card{background:#fff;border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,.15);padding:18px;max-width:520px;width:92%}
        .modal-card .close{float:right;font-size:22px;background:transparent;border:0;cursor:pointer}
        .modal-card label{display:block;margin-top:10px;font-weight:600}
        .modal-card input[type="text"],
        .modal-card input[type="number"],
        .modal-card textarea{width:100%;padding:8px;border:1px solid #e7e7ea;border-radius:8px}
        .modal-card .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}
        .btn-primary{background:var(--brand,#7a0000);color:#fff;border:0;border-radius:10px;padding:8px 14px}
        .btn-cancel{background:#fff;border:1px solid #e7e7ea;border-radius:10px;padding:8px 14px}

    </style>
</head>
<body>
<header class="admin-header">
    <nav class="breadcrumb">
        <a href="<?= htmlspecialchars($BASE) ?>adminAccueil.php">Dashboard</a> › <a href="<?= htmlspecialchars($BASE) ?>admin_catalogue.php">Produits</a>
    </nav>
    <h1>Modifier les articles</h1>
</header>

<div class="wrap">
    <?php if (!empty($_SESSION['message'])) {
        $class = (str_starts_with($_SESSION['message'], 'Erreur:')) ? 'err' : 'ok';
        echo '<p class="msg '.$class.'">'.htmlspecialchars($_SESSION['message']).'</p>';
        unset($_SESSION['message']);
    } ?>

    <!-- ===========================
         ROSES (FLEUR + PRODUIT)
         =========================== -->
    <h2>Fleurs</h2>
    <a class="btn-add" data-type="fleur" href="<?= $BASE ?>admin_produit_ajt.php?type=fleur">➕ Ajouter une fleur</a>
    <section class="product-details">
        <h3>Données de l’article des Roses</h3>
        <form method="post" action="<?= htmlspecialchars($BASE) ?>admin_update_article.php">
            <table class="kv">
                <thead>
                <tr>
                    <td><strong>Nom</strong></td>
                    <td><strong>Prix</strong></td>
                    <td><strong>Couleur</strong></td>
                    <td><strong>Stock</strong></td>
                    <td class="actions"><strong>Actions</strong></td>
                </tr>
                </thead>
                <tbody>
                <?php
                $fleurs = recup_donnee_fleur($pdo);
                if (empty($fleurs)) { ?>
                    <tr><td colspan="5" class="empty">Aucun article trouvé</td></tr>
                <?php } else {
                    foreach ($fleurs as $fleur) {
                        $id = (int)$fleur['PRO_ID']; ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $id ?>][PRO_NOM]" value="<?= htmlspecialchars($fleur['PRO_NOM']) ?>" required></td>
                            <td><input type="number" step="0.05" min="0" name="rows[<?= $id ?>][PRO_PRIX]" value="<?= htmlspecialchars($fleur['PRO_PRIX']) ?>" required></td>
                            <td><input type="text" name="rows[<?= $id ?>][FLE_COULEUR]" value="<?= htmlspecialchars($fleur['FLE_COULEUR'] ?? '') ?>"></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][FLE_QTE_STOCK]" value="<?= htmlspecialchars($fleur['FLE_QTE_STOCK'] ?? 0) ?>"></td>
                            <td class="actions">
                                <button class="btn" type="submit" name="save_id" value="<?= $id ?>">Enregistrer</button>
                                <input type="hidden" name="rows[<?= $id ?>][type]" value="fleur">
                                <input type="hidden" name="rows[<?= $id ?>][PRO_ID]" value="<?= $id ?>">
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </form>
    </section>

    <!-- ===========================
         BOUQUETS
         =========================== -->
    <h2>Bouquets</h2>
    <a class="btn-add" data-type="bouquet" href="<?= $BASE ?>admin_produit_ajt.php?type=bouquet">➕ Ajouter un bouquet</a>
    <section class="product-details">
        <h3>Données de l’article des Bouquets</h3>
        <form method="post" action="<?= htmlspecialchars($BASE) ?>admin_update_article.php">
            <table class="kv">
                <thead>
                <tr>
                    <td><strong>Nom</strong></td>
                    <td><strong>Prix</strong></td>
                    <td><strong>Nb Roses</strong></td>
                    <td><strong>Couleur</strong></td>
                    <td><strong>Stock</strong></td>
                    <td class="actions"><strong>Actions</strong></td>
                </tr>
                </thead>
                <tbody>
                <?php
                $bouquets = recup_donnee_bouquet($pdo);
                if (empty($bouquets)) { ?>
                    <tr><td colspan="6" class="empty">Aucun article trouvé</td></tr>
                <?php } else {
                    foreach ($bouquets as $b) {
                        $id = (int)$b['PRO_ID'];
                        $nomBouquet = htmlspecialchars($b['PRO_NOM']);
                        ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $id ?>][PRO_NOM]" value="<?= $nomBouquet ?>" required></td>
                            <td><input type="number" step="0.05" min="0" name="rows[<?= $id ?>][PRO_PRIX]" value="<?= htmlspecialchars($b['PRO_PRIX']) ?>" required></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][BOU_NB_ROSES]" value="<?= htmlspecialchars($b['BOU_NB_ROSES'] ?? 0) ?>"></td>
                            <td><input type="text" name="rows[<?= $id ?>][BOU_COULEUR]" value="<?= htmlspecialchars($b['BOU_COULEUR'] ?? '') ?>"></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][BOU_QTE_STOCK]" value="<?= htmlspecialchars($b['BOU_QTE_STOCK'] ?? 0) ?>"></td>
                            <td class="actions">
                                <button class="btn" type="submit" name="save_id" value="<?= $id ?>">Enregistrer</button>
                                <input type="hidden" name="rows[<?= $id ?>][type]" value="bouquet">
                                <input type="hidden" name="rows[<?= $id ?>][PRO_ID]" value="<?= $id ?>">
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </form>
    </section>

    <!-- ===========================
         COFFRETS
         =========================== -->
    <h2>Coffrets</h2>
    <a class="btn-add" data-type="coffret" href="<?= $BASE ?>admin_produit_ajt.php?type=coffret">➕ Ajouter un coffret</a>
    <section class="product-details">
        <h3>Données de l’article des Coffrets</h3>
        <form method="post" action="<?= htmlspecialchars($BASE) ?>admin_update_article.php">
            <table class="kv">
                <thead>
                <tr>
                    <td><strong>Nom</strong></td>
                    <td><strong>Prix</strong></td>
                    <td><strong>Événement</strong></td>
                    <td><strong>Stock</strong></td>
                    <td class="actions"><strong>Actions</strong></td>
                </tr>
                </thead>
                <tbody>
                <?php
                $coffrets = recup_donnee_coffret($pdo);
                if (empty($coffrets)) { ?>
                    <tr><td colspan="5" class="empty">Aucun article trouvé</td></tr>
                <?php } else {
                    foreach ($coffrets as $c) {
                        $id = (int)$c['PRO_ID']; ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $id ?>][PRO_NOM]" value="<?= htmlspecialchars($c['PRO_NOM']) ?>" required></td>
                            <td><input type="number" step="0.05" min="0" name="rows[<?= $id ?>][PRO_PRIX]" value="<?= htmlspecialchars($c['PRO_PRIX'] ?? 0) ?>"></td>
                            <td><input type="text" name="rows[<?= $id ?>][COF_EVENEMENT]" value="<?= htmlspecialchars($c['COF_EVENEMENT'] ?? '') ?>"></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][COF_QTE_STOCK]" value="<?= htmlspecialchars($c['COF_QTE_STOCK'] ?? 0) ?>"></td>
                            <td class="actions">
                                <button class="btn" type="submit" name="save_id" value="<?= $id ?>">Enregistrer</button>
                                <input type="hidden" name="rows[<?= $id ?>][type]" value="coffret">
                                <input type="hidden" name="rows[<?= $id ?>][PRO_ID]" value="<?= $id ?>">
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </form>
    </section>

    <!-- ===========================
         SUPPLÉMENTS
         =========================== -->

    <h2>Suppléments</h2>
    <a class="btn-add" data-type="supplement" href="<?= $BASE ?>admin_produit_ajt.php?type=supplement">➕ Ajouter un supplément</a>
    <section class="product-details">
        <h3>Données de l’article des Suppléments</h3>
        <form method="post" action="<?= htmlspecialchars($BASE) ?>admin_update_article.php">
            <table class="kv">
                <thead>
                <tr>
                    <td><strong>Nom</strong></td>
                    <td><strong>Description</strong></td>
                    <td><strong>Prix</strong></td>
                    <td><strong>Stock</strong></td>
                    <td class="actions"><strong>Actions</strong></td>
                </tr>
                </thead>
                <tbody>
                <?php
                $supps = recup_donnee_supplement($pdo);
                if (empty($supps)) { ?>
                    <tr><td colspan="5" class="empty">Aucun article trouvé</td></tr>
                <?php } else {
                    foreach ($supps as $s) {
                        $id = (int)$s['SUP_ID']; ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $id ?>][SUP_NOM]" value="<?= htmlspecialchars($s['SUP_NOM']) ?>" required></td>
                            <td><input type="text" name="rows[<?= $id ?>][SUP_DESCRIPTION]" value="<?= htmlspecialchars($s['SUP_DESCRIPTION'] ?? '') ?>"></td>
                            <td><input type="number" step="0.05" min="0" name="rows[<?= $id ?>][SUP_PRIX_UNITAIRE]" value="<?= htmlspecialchars($s['SUP_PRIX_UNITAIRE']) ?>" required></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][SUP_QTE_STOCK]" value="<?= htmlspecialchars($s['SUP_QTE_STOCK'] ?? 0) ?>"></td>
                            <td class="actions">
                                <button class="btn" type="submit" name="save_id" value="<?= $id ?>">Enregistrer</button>
                                <input type="hidden" name="rows[<?= $id ?>][type]" value="supplement">
                                <input type="hidden" name="rows[<?= $id ?>][SUP_ID]" value="<?= $id ?>">
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </form>
    </section>

    <!-- ===========================
         EMBALLAGES
         =========================== -->
    <h2>Emballages</h2>
    <a class="btn-add" data-type="emballage" href="<?= $BASE ?>admin_produit_ajt.php?type=emballage">➕ Ajouter un emballage</a>
    <section class="product-details">
        <h3>Données de l’article des Emballages</h3>
        <form method="post" action="<?= htmlspecialchars($BASE) ?>admin_update_article.php">
            <table class="kv">
                <thead>
                <tr>
                    <td><strong>Nom</strong></td>
                    <td><strong>Couleur</strong></td>
                    <td><strong>Stock</strong></td>
                    <td class="actions"><strong>Actions</strong></td>
                </tr>
                </thead>
                <tbody>
                <?php
                $embs = recup_donnee_emballage($pdo);
                if (empty($embs)) { ?>
                    <tr><td colspan="4" class="empty">Aucun article trouvé</td></tr>
                <?php } else {
                    foreach ($embs as $e) {
                        $id = (int)$e['EMB_ID']; ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $id ?>][EMB_NOM]" value="<?= htmlspecialchars($e['EMB_NOM']) ?>" required></td>
                            <td><input type="text" name="rows[<?= $id ?>][EMB_COULEUR]" value="<?= htmlspecialchars($e['EMB_COULEUR'] ?? '') ?>"></td>
                            <td><input type="number" step="1" min="0" name="rows[<?= $id ?>][EMB_QTE_STOCK]" value="<?= htmlspecialchars($e['EMB_QTE_STOCK'] ?? 0) ?>"></td>
                            <td class="actions">
                                <button class="btn" type="submit" name="save_id" value="<?= $id ?>">Enregistrer</button>
                                <input type="hidden" name="rows[<?= $id ?>][type]" value="emballage">
                                <input type="hidden" name="rows[<?= $id ?>][EMB_ID]" value="<?= $id ?>">
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </form>
    </section>
</div>
<!------------------------------------------------------------ Modale d'ajout produit ------------------------------------------------------------------>
<div id="modal-add" class="modal" hidden>
    <div class="modal-card">
        <button class="close" type="button" aria-label="Fermer">&times;</button>
        <h3 id="modal-title">Ajouter un produit</h3>

        <form id="form-add" method="post" action="<?= $BASE ?>admin_produit_ajt.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="pro_type" id="add-type" value="autre">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ??= bin2hex(random_bytes(16))) ?>">

            <!-- ===== Commun PRODUIT (pour FLEUR/BOUQUET/COFFRET) ===== -->
            <div class="common-produit" hidden>
                <label>Nom *</label>
                <input type="text" name="pro_nom" required>

                <label>Prix (CHF) *</label>
                <input type="number" name="pro_prix" step="0.05" min="0.00" required>
            </div>

            <!-- ===== FLEUR ===== -->
            <div class="type-fields" data-type="fleur" hidden>
                <!-- colonnes de ton tableau Fleurs -->
                <label>Couleur *</label>
                <input type="text" name="fle_couleur" required>

                <label>Stock *</label>
                <input type="number" name="fle_qte_stock" step="1" min="0" value="0" required>
            </div>

            <!-- ===== BOUQUET ===== -->
            <div class="type-fields" data-type="bouquet" hidden>
                <!-- colonnes de ton tableau Bouquets -->
                <label>Nb Roses *</label>
                <input type="number" name="bou_nb_roses" step="1" min="1" value="12" required>

                <label>Couleur *</label>
                <input type="text" name="bou_couleur" required>

                <label>Stock *</label>
                <input type="number" name="bou_qte_stock" step="1" min="0" value="0" required>
            </div>

            <!-- ===== COFFRET ===== -->
            <div class="type-fields" data-type="coffret" hidden>
                <!-- colonnes de ton tableau Coffrets -->
                <label>Événement</label>
                <input type="text" name="cof_evenement" placeholder="Anniversaire, Mariage…">

                <label>Stock *</label>
                <input type="number" name="cof_qte_stock" step="1" min="0" value="0" required>
            </div>

            <!-- ===== SUPPLÉMENT ===== -->
            <div class="type-fields" data-type="supplement" hidden>
                <!-- colonnes de ton tableau Suppléments (+ couleur que tu veux gérer) -->
                <label>Nom *</label>
                <input type="text" name="sup_nom" required>

                <label>Description</label>
                <input type="text" name="sup_description">

                <label>Prix unitaire (CHF) *</label>
                <input type="number" name="sup_prix_unitaire" step="0.05" min="0.00" required>

                <label>Couleur <!-- si tu as SUP_COULEUR --></label>
                <input type="text" name="sup_couleur" placeholder="(optionnel si non utilisé)">

                <label>Stock *</label>
                <input type="number" name="sup_qte_stock" step="1" min="0" value="0" required>
            </div>

            <!-- ===== EMBALLAGE ===== -->
            <div class="type-fields" data-type="emballage" hidden>
                <!-- colonnes de ton tableau Emballages -->
                <label>Nom *</label>
                <input type="text" name="emb_nom" required>

                <label>Couleur</label>
                <input type="text" name="emb_couleur">

                <label>Stock *</label>
                <input type="number" name="emb_qte_stock" step="1" min="0" value="0" required>
            </div>

            <!-- (facultatif) Image pour les types liés à PRODUIT -->
            <label>Image (JPEG/PNG/WebP/GIF, max 5 Mo)</label>
            <input type="file" name="pro_img" accept="image/jpeg,image/png,image/webp,image/gif">

            <div class="actions">
                <button type="button" class="btn-cancel">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>

    </div>
</div>
<script>
    document.querySelectorAll('.btn-add').forEach(btn=>{
        btn.addEventListener('click', e=>{
            e.preventDefault();
            const type = btn.dataset.type || 'autre';
            document.getElementById('add-type').value = type;
            document.getElementById('modal-title').textContent = 'Ajouter ' + type;
            document.getElementById('modal-add').hidden = false;
        });
    });

    document.querySelector('#modal-add .close')?.addEventListener('click', ()=> {
        document.getElementById('modal-add').hidden = true;
    });
    document.querySelector('#modal-add .btn-cancel')?.addEventListener('click', ()=> {
        document.getElementById('modal-add').hidden = true;
    });

        (function(){
        function showType(type){
            // Affiche le bloc commun PRODUIT seulement pour: fleur/bouquet/coffret
            const common = document.querySelector('#form-add .common-produit');
            if (common) common.hidden = !['fleur','bouquet','coffret'].includes(type);

            // Affiche les champs du type demandé
            document.querySelectorAll('#form-add .type-fields').forEach(div=>{
                div.hidden = (div.dataset.type !== type);
            });
        }

        // Ouverture modale + affichage champs
        document.querySelectorAll('.btn-add').forEach(btn=>{
        btn.addEventListener('click', e=>{
        e.preventDefault();
        const type = (btn.dataset.type || 'autre').toLowerCase();

        // fixe pro_type
        document.getElementById('add-type').value = type;
        document.getElementById('modal-title').textContent = 'Ajouter ' + type;

        // reset le form
        const f = document.getElementById('form-add');
        f?.reset();

        showType(type);
        document.getElementById('modal-add').hidden = false;
    });
    });
    })();
</script>


</body>
</html>
