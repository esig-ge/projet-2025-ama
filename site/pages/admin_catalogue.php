<?php
/* ============================================================
   SESSION + CONNEXION BDD + BASE D’URL
   ------------------------------------------------------------
   - session_start() : nécessaire pour l’admin.
   - $pdo : connexion PDO (fichier centralisé).
   - $BASE : préfixe d’URL robuste pour pointer CSS/liens
     depuis n’importe quel script dans /site/pages.
   ============================================================ */
session_start();

// Connexion DB
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* Bases de chemins :
   - dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']) -> dossier logique de la page
   - rtrim(..., '/\\') + ajout d’un "/" final -> forme normalisée
   - Cas racine ('' ou '.') → '/'  */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ============================================================
   HELPERS D’ACCÈS DONNÉES (lecture simple)
   ------------------------------------------------------------
   Convention :
   - Chaque fonction retourne un tableau associatif (fetchAll).
   - Jointure PRODUIT ↔ (FLEUR/BOUQUET/COFFRET) quand il y a un PRO_ID.
   - Remarque perf : en cas de volumétrie, ajouter LIMIT/ORDER BY
     et une pagination côté UI.
   ============================================================ */

function recup_donnee_fleur(PDO $pdo): array
{
    $sql = "SELECT p.*, f.*
              FROM PRODUIT p
              INNER JOIN FLEUR f ON p.PRO_ID = f.PRO_ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_bouquet(PDO $pdo): array
{
    $sql = "SELECT p.*, b.*
              FROM PRODUIT p
              INNER JOIN BOUQUET b ON p.PRO_ID = b.PRO_ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_supplement(PDO $pdo): array
{
    $sql = "SELECT * FROM SUPPLEMENT";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_emballage(PDO $pdo): array
{
    $sql = "SELECT *
              FROM EMBALLAGE
              JOIN PRODUIT p ON p.PRO_ID = e.PRO_ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_donnee_coffret(PDO $pdo): array
{
    $sql = "SELECT p.*, c.*
              FROM PRODUIT p
              INNER JOIN COFFRET c ON p.PRO_ID = c.PRO_ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function recup_produit(PDO $pdo): array
{
    $sql = "SELECT p.PRO_ID, p.PRO_NOM, p.PRO_DESCRIPTION, p.PRO_PRIX, p.PRO_QTE_MAX, p.PRO_IMAGE,
                   f.FLE_COULEUR, f.FLE_QTE_STOCK
              FROM PRODUIT p
              JOIN FLEUR f ON f.PRO_ID = p.PRO_ID
             WHERE f.FLE_QTE_STOCK > 0
          ORDER BY p.PRO_NOM ASC";
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
    <h1>PRODUITS</h1>
    <a href='admin_modifier_article.php?type=fleur&id='>Modifier les produits</a>
    <!-- =======================================================
         TABLEAU FLEUR (repliable)
         -------------------------------------------------------
         - Titre <h2> cliquable qui toggle l’affichage du tableau.
         - Données issues de recup_donnee_fleur($pdo).
         ======================================================= -->
    <h2 id="toggleFleur" style="cursor:pointer;">Fleur </h2>

    <div id="tableFleur" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Couleur</th>
                <th>Prix</th>
                <th>Quantité actuelle</th>
                <th> </th>
            </tr>
            </thead>
            <tbody>
            <?php
            $produits = recup_donnee_fleur($pdo);
            foreach ($produits as $row) {
                echo "<tr>";
                echo '<td>' . htmlspecialchars($row['PRO_NOM']) . '</td>';
                echo '<td>' . htmlspecialchars($row['PRO_ID']) . '</td>';
                echo '<td>' . htmlspecialchars($row['FLE_COULEUR'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['PRO_PRIX'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['FLE_QTE_STOCK'] ?? '-') . '</td>';
                echo '<td><a href="admin_supprimer_article.php?type=fleur&id=">Supprimer</a></td>';
                echo "<tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle Fleur : bascule l’affichage du tableau et ajuste le titre
        document.getElementById("toggleFleur").addEventListener("click", function() {
            const table = document.getElementById("tableFleur");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";
                this.textContent = "Fleur ";
            } else {
                table.style.display = "none";
                this.textContent = "Fleur ";
            }
        });
    </script>

    <br>

    <!-- =======================================================
         TABLEAU BOUQUET (repliable)
         ======================================================= -->
    <h2 id="toggleBouquet" style="cursor:pointer;">Bouquet</h2>
    <div id="tableBouquet" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Couleur</th>
                <th>Prix</th>
                <th>Quantité actuelle</th>
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
                echo "<td>" . htmlspecialchars($row['BOU_QTE_STOCK'] ?? '-') . "</td>";
                echo "<td><a href='admin_supprimer_article.php?id=" . urlencode($row['PRO_ID']) . "'>Supprimer</a></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle Bouquet
        document.getElementById("toggleBouquet").addEventListener("click", function() {
            const table = document.getElementById("tableBouquet");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";
                this.textContent = "Bouquet ";
            } else {
                table.style.display = "none";
                this.textContent = "Bouquet ";
            }
        });
    </script>

    <br>

    <!-- =======================================================
         TABLEAU COFFRET (repliable)
         ======================================================= -->
    <h2 id="toggleCoffret" style="cursor:pointer;">Coffret</h2>
    <div id="tableCoffret" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Evènement</th>
                <th>Prix</th>
                <th>Quantité actuelle</th>
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
                echo "<td>" . htmlspecialchars($row['COF_QTE_STOCK'] ?? '-') . "</td>";
                echo "<td><a href='admin_supprimer_article.php?id=" . urlencode($row['PRO_ID']) . "'>Supprimer</a></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle Coffret
        document.getElementById("toggleCoffret").addEventListener("click", function() {
            const table = document.getElementById("tableCoffret");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";
                this.textContent = "Coffret ";
            } else {
                table.style.display = "none";
                this.textContent = "Coffret ";
            }
        });
    </script>

    <br>

    <!-- =======================================================
         TABLEAU SUPPLÉMENT (repliable)
         ======================================================= -->
    <h2 id="toggleSupplement" style="cursor:pointer;">Supplément</h2>
    <div id="tableSupplement" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Couleur</th>
                <th>Prix</th>
                <th>Quantité actuelle</th>
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
                echo "<td>" . htmlspecialchars($row['SUP_QTE_STOCK'] ?? '-') . "</td>";
                echo "<td><a href='admin_supprimer_article.php?id=" . urlencode($row['SUP_ID']) . "'>Supprimer</a></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle Supplément
        document.getElementById("toggleSupplement").addEventListener("click", function() {
            const table = document.getElementById("tableSupplement");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";
                this.textContent = "Supplément ";
            } else {
                table.style.display = "none";
                this.textContent = "Supplément ";
            }
        });
    </script>

    <br>

    <!-- =======================================================
         TABLEAU EMBALLAGE (repliable)
         ======================================================= -->
    <h2 id="toggleEmballage" style="cursor:pointer;">Emballage</h2>
    <div id="tableEmballage" style="display:none;">
        <table>
            <thead>
            <tr>
                <th>Nom</th>
                <th>ID</th>
                <th>Couleur</th>
                <th>Prix</th>
                <th>Quantité actuelle</th>
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
                echo "<td>" . htmlspecialchars($row['EMB_QTE_STOCK'] ?? '-') . "</td>";
                echo "<td><a href='admin_modifier_article.php?id=" . urlencode($row['EMB_ID']) . "'>Supprimer</a></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle Emballage
        document.getElementById("toggleEmballage").addEventListener("click", function() {
            const table = document.getElementById("tableEmballage");
            if (table.style.display === "none" || table.style.display === "") {
                table.style.display = "block";
                this.textContent = " Emballage ";
            } else {
                table.style.display = "none";
                this.textContent = "Emballage ";
            }
        });
    </script>

    <br>

    <!-- Retour dashboard -->
    <p style="margin-top:16px">
        <a class="btn ghost" href="<?= $BASE ?>adminAccueil.php">← Retour au dashboard</a>
    </p>
</div>
</body>
</html>
