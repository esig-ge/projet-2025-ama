<?php
$pageTitle='Commandes'; $topTitle='Commandes'; $active='commandes';
require __DIR__ . '/../includes/admin_header.php';

$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Adapte noms de colonnes à ton MLD (com_id, com_date, com_total, com_statut)
$sql="SELECT com_id, com_date, com_total, com_statut FROM COMMANDE ORDER BY com_date DESC LIMIT 200";
$rows=[]; try{$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
?>
<div class="card">
    <div class="card-head"><h2>Commandes</h2></div>
    <div class="table like">
        <div class="row head"><div>#</div><div>Date</div><div>Client</div><div>Total</div><div>Statut</div><div></div></div>
        <?php if ($rows): foreach($rows as $r): ?>
            <div class="row">
                <div><?= (int)$r['com_id'] ?></div>
                <div><?= htmlspecialchars(substr($r['com_date'],0,16)) ?></div>
                <div>—</div>
                <div><?= number_format((float)$r['com_total'],2,'.',' ') ?> CHF</div>
                <div><?= htmlspecialchars($r['com_statut']) ?></div>
                <div><a class="link" href="adminCommande_view.php?id=<?= (int)$r['com_id'] ?>">Détails</a></div>
            </div>
        <?php endforeach; else: ?>
            <div class="row empty">Aucune commande.</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
