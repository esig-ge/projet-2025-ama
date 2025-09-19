<?php

session_start();

// Connexion DB
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* Bases de chemins */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
function recup_donnee_fleur($pdo)
{
    $sql = "SELECT p.*, f.*
        FROM PRODUIT p
        INNER JOIN FLEUR f ON p.PRO_ID = f.PRO_ID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_bouquet($pdo)
{
    $sql = "SELECT p.*, b.*
        FROM PRODUIT p
        INNER JOIN BOUQUET b ON p.PRO_ID = b.PRO_ID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_supplement($pdo)
{
    $sql = "SELECT p.*, s.*
        FROM PRODUIT p
        INNER JOIN SUPPLEMENT s ON p.PRO_ID = s.PRO_ID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_emballage($pdo)
{
    $sql = "SELECT p.*, e.*
        FROM PRODUIT p
        INNER JOIN EMBALLAGE e ON p.PRO_ID = e.PRO_ID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_coffret($pdo)
{
    $sql = "SELECT p.*, c.*
        FROM PRODUIT p
        INNER JOIN COFFRET c ON p.PRO_ID = c.PRO_ID";

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
    <title>DK Bloom — Accueil</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/admin_catalogue.css">
</head>
<body>
<div>

    <h1>PRODUIT</h1>

<!---------------    tableau fleur ---------------->
    <table>
        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Qté min</th>
            <th>Qté max</th>
            <th>Qté actuelle</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_fleur($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MIN'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
            echo "</tr>";
        }
?>
        </tbody>
    </table>
<br>

    <!---------------    tableau bouquet ---------------->
    <table>
    <thead>
    <tr>
        <th>Nom</th>
        <th>ID</th>
        <th>Couleur</th>
        <th>Qté min</th>
        <th>Qté max</th>
        <th>Qté actuelle</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $produits = recup_donnee_bouquet($pdo);
    foreach ($produits as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['PRO_QTE_MIN'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
        echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
        echo "</tr>";
    }
    ?>
    </tbody>
    </table>
    <br>

    <!---------------    tableau coffret ---------------->
    <table>
        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Qté min</th>
            <th>Qté max</th>
            <th>Qté actuelle</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_fleur($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MIN'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    <br>
    <table>
        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Qté min</th>
            <th>Qté max</th>
            <th>Qté actuelle</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_fleur($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MIN'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    <br>
    <table>
        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Qté min</th>
            <th>Qté max</th>
            <th>Qté actuelle</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_fleur($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MIN'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    <br>





</div>
</body>
</html>
