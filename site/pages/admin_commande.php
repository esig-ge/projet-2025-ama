
<?php

session_start();

// Connexion DB
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* Bases de chemins */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
function recup_donnee_commande($pdo)
{
    $sql = " SELECT 
            c.COM_ID AS commande_id,
            c.COM_DATE AS date_commande,

            per.PER_NOM AS nom_client,
            per.PER_PRENOM AS prenom_client,

            CONCAT_WS(' ', a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS) AS adresse_complete,

            GROUP_CONCAT(DISTINCT CONCAT(p.PRO_NOM, ' x', COALESCE(cp.CP_QTE_COMMANDEE,1))
                         ORDER BY p.PRO_NOM SEPARATOR ', ') AS produits,

            COALESCE(l.LIV_STATUT,'—')  AS statut_livraison,
            COALESCE(pa.PAI_MONTANT,0)  AS montant_commande,
            COALESCE(pa.PAI_MODE,'—')   AS mode_paiement,
            COALESCE(pa.PAI_STATUT,'—') AS statut_paiement

        FROM COMMANDE c
        JOIN CLIENT cli         ON c.PER_ID = cli.PER_ID
        JOIN PERSONNE per       ON cli.PER_ID = per.PER_ID  

        LEFT JOIN ADRESSE_CLIENT ac  ON cli.PER_ID = ac.PER_ID
        LEFT JOIN ADRESSE a          ON ac.ADR_ID = a.ADR_ID
        LEFT JOIN COMMANDE_PRODUIT cp ON c.COM_ID = cp.COM_ID
        LEFT JOIN PRODUIT p           ON cp.PRO_ID = p.PRO_ID
        LEFT JOIN LIVRAISON l         ON c.LIV_ID  = l.LIV_ID
        LEFT JOIN PAIEMENT pa         ON c.PAI_ID  = pa.PAI_ID

        GROUP BY c.COM_ID, c.COM_DATE, per.PER_NOM, per.PER_PRENOM,
                 a.ADR_RUE, a.ADR_NUMERO, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS,
                 l.LIV_STATUT, pa.PAI_MONTANT, pa.PAI_MODE, pa.PAI_STATUT
        ORDER BY c.COM_DATE DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>



<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/admin_catalogue.css">
</head>
<body>
<div>

    <h1>COMMANDES</h1>



    <div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Nom client</th>
                <th>Prenom client</th>
                <th>Adresse</th>
                <th>Produit </th>
                <th>Statut de livraison</th>
                <th>Montant de la commande</th>
                <th>Mode de paiement</th>
                <th>Statut du paiement</th>



            </tr>
            </thead>

            <tbody>
            <?php
            $commandes = recup_donnee_commande($pdo);

            foreach ($commandes as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['commande_id'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['date_commande'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['nom_client'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['prenom_client'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['adresse_complete'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['produits'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['statut_livraison'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['montant_commande'] ?? '0') . "</td>";
                echo "<td>" . htmlspecialchars($row['mode_paiement'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['statut_paiement'] ?? '-') . "</td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
