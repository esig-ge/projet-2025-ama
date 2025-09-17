<?php
$pageTitle='Produit'; $topTitle='Produit'; $active='produits';
require __DIR__ . '/../includes/admin_header.php';

$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id   = (int)($_GET['id'] ?? 0);
$mode = $id ? 'edit' : 'create';

$prod = ['PRO_NOM'=>'','PRO_PRIX'=>'','PRO_QTE_STOCK'=>''];
if ($id) {
    $st=$pdo->prepare("SELECT PRO_NOM, PRO_PRIX, PRO_QTE_STOCK FROM PRODUIT WHERE PRO_ID=?");
    $st->execute([$id]);
    $prod = $st->fetch(PDO::FETCH_ASSOC) ?: $prod;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nom  = trim($_POST['nom'] ?? '');
    $prix = (float)($_POST['prix'] ?? 0);
    $stk  = (int)($_POST['stock'] ?? 0);

    if ($mode==='create') {
        $st=$pdo->prepare("INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX, PRO_QTE_STOCK) VALUES (?,?,?)");
        $st->execute([$nom,$prix,$stk]);
    } else {
        $st=$pdo->prepare("UPDATE PRODUIT SET PRO_NOM=?, PRO_PRIX=?, PRO_QTE_STOCK=? WHERE PRO_ID=?");
        $st->execute([$nom,$prix,$stk,$id]);
    }
    header('Location: adminProduits.php'); exit;
}
?>
<div class="card">
    <div class="card-head"><h2><?= $mode==='create'?'Ajouter':'Modifier' ?> un produit</h2></div>
    <form method="post" class="form" style="padding:16px">
        <label>Nom<br><input name="nom" required value="<?= htmlspecialchars($prod['PRO_NOM']) ?>"></label><br><br>
        <label>Prix (CHF)<br><input type="number" step="0.01" name="prix" required value="<?= htmlspecialchars($prod['PRO_PRIX']) ?>"></label><br><br>
        <label>Stock<br><input type="number" name="stock" required value="<?= htmlspecialchars($prod['PRO_QTE_STOCK']) ?>"></label><br><br>
        <button class="btn"><?= $mode==='create'?'Créer':'Enregistrer' ?></button>
        <a class="btn ghost" href="adminProduits.php">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
