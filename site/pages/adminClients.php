<?php
$pageTitle='Clients'; $topTitle='Clients'; $active='clients';
require __DIR__ . '/../includes/admin_header.php';
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql="SELECT p.PER_ID, p.PER_NOM, p.PER_PRENOM, p.PER_EMAIL, p.PER_NUM_TEL
      FROM PERSONNE p
      JOIN CLIENT c ON c.PER_ID = p.PER_ID
      ORDER BY p.PER_NOM ASC
      LIMIT 200";
$rows=[]; try{$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
?>
<div class="card">
    <div class="card-head"><h2>Clients</h2></div>
    <div class="table like">
        <div class="row head"><div>#</div><div>Nom</div><div>Email</div><div>Téléphone</div></div>
        <?php if ($rows): foreach($rows as $r): ?>
            <div class="row">
                <div><?= (int)$r['PER_ID'] ?></div>
                <div><?= htmlspecialchars($r['PER_PRENOM'].' '.$r['PER_NOM']) ?></div>
                <div><?= htmlspecialchars($r['PER_EMAIL']) ?></div>
                <div><?= htmlspecialchars($r['PER_NUM_TEL']) ?></div>
            </div>
        <?php endforeach; else: ?>
            <div class="row empty">Aucun client.</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
