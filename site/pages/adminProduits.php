<?php
// /site/pages/adminProduits.php
session_start();
// Prefixe URL qui marche depuis n'importe quelle page de /site/pages
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';

// Variables pour le layout
$pageTitle = 'Produits';
$topTitle  = 'Produits';
$active    = 'produits';

// Base URL (avec slash final) utilisée par le layout pour charger les CSS/IMG
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Démarre le layout (ex-« admin_header »)
include __DIR__ . '/includes/admin_header.php';

// Connexion PDO
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Exemple de SELECT (adapte aux vraies colonnes/tables)
$sql  = "SELECT PRO_ID, PRO_NOM, PRO_PRIX, PRO_QTE_STOCK
         FROM PRODUIT
         ORDER BY PRO_ID DESC
         LIMIT 100";
$rows = [];
try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // tu peux logger si besoin
}
?>
    <div class="card">
        <div class="card-head">
            <h2>Catalogue</h2>
            <a class="btn" href="<?= $BASE ?>adminProduit_form.php">+ Ajouter un produit</a>
        </div>
        <div class="table like">
            <div class="row head">
                <div>#</div><div>Nom</div><div>Prix</div><div>Stock</div><div>Actions</div>
            </div>
            <?php if ($rows): foreach ($rows as $r): ?>
                <div class="row">
                    <div><?= (int)$r['PRO_ID'] ?></div>
                    <div><?= htmlspecialchars($r['PRO_NOM']) ?></div>
                    <div><?= number_format((float)$r['PRO_PRIX'], 2, '.', ' ') ?> CHF</div>
                    <div><?= (int)$r['PRO_QTE_STOCK'] ?></div>
                    <div>
                        <a class="link" href="<?= $BASE ?>adminProduit_form.php?id=<?= (int)$r['PRO_ID'] ?>">Modifier</a> ·
                        <a class="link" href="<?= $BASE ?>adminProduit_delete.php?id=<?= (int)$r['PRO_ID'] ?>" onclick="return confirm('Supprimer ce produit ?')">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="row empty">Aucun produit.</div>
            <?php endif; ?>
        </div>
    </div>
<?php
// Fin du layout (ex-« admin_footer »)
require __DIR__ . '/includes/admin_footer.php';
