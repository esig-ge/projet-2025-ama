<?php
// /site/pages/commande.php
session_start();

// Base URL
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if (empty($_SESSION['per_id'])) {
    $_SESSION['message'] = "Veuillez vous connecter pour voir votre commande.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// Récupérer commande ouverte
$sql = "SELECT COM_ID, COM_DATE FROM COMMANDE
        WHERE PER_ID = :per AND COM_STATUT = 'en préparation'
        ORDER BY COM_ID DESC LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute([':per' => $perId]);
$com = $st->fetch(PDO::FETCH_ASSOC);

$lines = [];
$total = 0.0;

if ($com) {
    $comId = (int)$com['COM_ID'];
    $sql = "SELECT cp.PRO_ID, cp.CP_QTE_COMMANDEE, p.PRO_NOM, p.PRO_PRIX, cp.CP_TYPE_PRODUIT
            FROM COMMANDE_PRODUIT cp
            JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
            WHERE cp.COM_ID = :com
            ORDER BY p.PRO_NOM";
    $st = $pdo->prepare($sql);
    $st->execute([':com' => $comId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $L) {
        $total += (float)$L['PRO_PRIX'] * (int)$L['CP_QTE_COMMANDEE'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Ma commande</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title">Ma commande</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (!$com): ?>
        <p>Votre panier est vide.</p>
        <p><a class="button" href="<?= $BASE ?>interface_catalogue_bouquet.php">Ajouter des bouquets</a></p>
    <?php else: ?>
        <div class="card">
            <h2>Commande #<?= (int)$com['COM_ID'] ?> du <?= htmlspecialchars($com['COM_DATE']) ?></h2>
            <div class="table-responsive">
                <table class="table-panier">
                    <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Type</th>
                        <th>Prix unité</th>
                        <th>Qté</th>
                        <th>Sous-total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $L):
                        $pu = (float)$L['PRO_PRIX'];
                        $q  = (int)$L['CP_QTE_COMMANDEE'];
                        $st = $pu * $q;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($L['PRO_NOM']) ?></td>
                            <td><?= htmlspecialchars($L['CP_TYPE_PRODUIT']) ?></td>/*
                            <td><?= number_format($pu, 2, '.', ' ') ?> CHF</td>
                            <td><?= $q ?></td>
                            <td><?= number_format($st, 2, '.', ' ') ?> CHF</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="4" style="text-align:right">Total</th>
                        <th><?= number_format($total, 2, '.', ' ') ?> CHF</th>
                    </tr>
                    </tfoot>
                </table>

            </div>

            <div class="actions" style="display:flex; gap:12px; flex-wrap:wrap">
                <a class="button" href="<?= $BASE ?>interface_catalogue_bouquet.php">Continuer mes achats</a>
                <a class="button" href="<?= $BASE ?>interface_supplement.php">Ajouter des suppléments</a>
                <a class="button" href="<?= $BASE ?>adresse_paiement.php">Passer au paiement</a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
