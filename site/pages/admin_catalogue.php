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
    $sql = "SELECT * FROM SUPPLEMENT";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_emballage($pdo)
{
    $sql = "SELECT *
        FROM EMBALLAGE";

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
    <title>DK Bloom</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/admin_catalogue.css">
</head>
<body>
<div>

    <h1>PRODUIT</h1>

<!---------------    tableau fleur ---------------->
    <h2 id="toggleFleur" style="cursor:pointer;">Fleur </h2>

    <div id="tableFleur" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Couleur</th>
                <th>Prix</th>
                <th>Qté max</th>
                <th>Quantité actuelle</th>
                <th> </th>
                <th> </th>
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
                echo "<td>" . htmlspecialchars($row['PRO_PRIX'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . "</td>";
                echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
                echo "<td><a href='supprimer.php?id=" . urlencode($row['PRO_ID']) . "'>Supprimer</a></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById("toggleFleur").addEventListener("click", function() {
            const table = document.getElementById("tableFleur");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";   // affiche
                this.textContent = "Fleur "; // change titre
            } else {
                table.style.display = "none";    // cache
                this.textContent = "Fleur "; // remet titre
            }
        });
    </script>
<br>

    <!---------------    tableau bouquet ---------------->
    <h2 id="toggleBouquet" style="cursor:pointer;">Bouquet</h2>
    <div id="tableBouquet" style="display:none;">
    <table>

    <thead>
    <tr>
        <th>Nom</th>
        <th>ID</th>
        <th>Couleur</th>
        <th>Prix</th>
        <th>Qté max</th>
        <th>Quantité actuelle</th>
        <th> </th>
        <th> </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $produits = recup_donnee_bouquet($pdo);
    foreach ($produits as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
        echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['BOU_COULEUR'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['PRO_PRIX'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['BOU_QTE_MAX'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['BOU_QTE_STOCK'] ?? '-') . "</td>";
        echo "<td><a href='admin_modifier_article.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
        echo "<td><a href='admin_supprimer_article.php?id=" . urlencode($row['PRO_ID']) . "'>Supprimer</a></td>";
        echo "</tr>";
    }
    ?>
    </tbody>
    </table>
</div>

<script>
    document.getElementById("toggleBouquet").addEventListener("click", function() {
        const table = document.getElementById("tableBouquet");
        if (table.style.display === "none" || table.style.display === "") {
            table.style.display = "block";   // affiche
            this.textContent = "Bouquet "; // change titre
        } else {
            table.style.display = "none";    // cache
            this.textContent = "Bouquet "; // remet titre
        }
    });
</script>
    <br>

    <!---------------    tableau coffret ---------------->
<h2 id="toggleCoffret" style="cursor:pointer;">Coffret</h2>
<div id="tableCoffret" style="display:none;">
    <table>

        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Evènement</th>
            <th>Prix</th>
            <th>Qté max</th>
            <th>Quantité actuelle</th>
            <th> </th>
            <th> </th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_coffret($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['PRO_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['COF_EVENEMENT'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_PRIX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['COF_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['COF_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['PRO_ID']) . "'>Modifier</a></td>";
            echo "<td><a href='supprimer.php?id=" . urlencode($row['PRO_ID']) . "'>Supprimer</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>

    <script>
        document.getElementById("toggleCoffret").addEventListener("click", function() {
            const table = document.getElementById("tableCoffret");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";   // affiche
                this.textContent = "Coffret "; // change titre
            } else {
                table.style.display = "none";    // cache
                this.textContent = "Coffret "; // remet titre
            }
        });
    </script>
    <br>

    <!---------------    tableau supplement ---------------->

    <h2 id="toggleSupplement" style="cursor:pointer;">Supplément</h2>
    <div id="tableSupplement" style="display:none;">
    <table>

        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Prix</th>
            <th>Qté max</th>
            <th>Quantité actuelle</th>
            <th> </th>
            <th> </th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_supplement($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['SUP_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['SUP_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['SUP_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['SUP_PRIX_UNITAIRE'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['SUP_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['SUP_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['SUP_ID']) . "'>Modifier</a></td>";
            echo "<td><a href='supprimer.php?id=" . urlencode($row['SUP_ID']) . "'>Supprimer</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    </div>

    <script>
        document.getElementById("toggleSupplement").addEventListener("click", function() {
            const table = document.getElementById("tableSupplement");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";   // affiche
                this.textContent = "Supplément "; // change titre
            } else {
                table.style.display = "none";    // cache
                this.textContent = "Supplément "; // remet titre
            }
        });
    </script>
    <br>

    <!---------------    tableau emballage ---------------->
    <h2 id="toggleEmballage" style="cursor:pointer;">Emballage</h2>
    <div id="tableEmballage" style="display:none;">
    <table>

        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Prix</th>
            <th>Qté max</th>
            <th>Quantité actuelle</th>
            <th> </th>
            <th> </th>
        </tr>
        </thead>

        <tbody>
        <?php
        $produits = recup_donnee_emballage($pdo);
        foreach ($produits as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['EMB_NOM']) . "</td>";
            echo "<td>" . htmlspecialchars($row['EMB_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['EMB_COULEUR'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_PRIX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRO_QTE_MAX'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['EMB_QTE_STOCK'] ?? '-') . "</td>";
            echo "<td><a href='modifier.php?id=" . urlencode($row['EMB_ID']) . "'>Modifier</a></td>";
            echo "<td><a href='supprimer.php?id=" . urlencode($row['EMB_ID']) . "'>Supprimer</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
    </div>

    <script>
        document.getElementById("toggleEmballage").addEventListener("click", function() {
            const table = document.getElementById("tableEmballage");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";   // affiche
                this.textContent = " Emballage "; // change titre
            } else {
                table.style.display = "none";    // cache
                this.textContent = "Emballage "; // remet titre
            }
        });
    </script>
    <br>




</div>
</body>
</html>
