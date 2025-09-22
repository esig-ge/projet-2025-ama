<?php

session_start();

// Connexion DB
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/* Bases de chemins */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

function recup_donnee_fleur(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_DESCRIPTION,
              p.PRO_PRIX,
              p.PRO_QTE_MAX,
              p.PRO_IMAGE,
              p.PRO_ACTIF,
              f.FLE_COULEUR,
              f.FLE_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN FLEUR f ON f.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function get_fleur_by_id(PDO $pdo, int $id): ?array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_DESCRIPTION,
              p.PRO_PRIX,
              p.PRO_QTE_MAX,
              p.PRO_IMAGE,
              p.PRO_ACTIF,
              f.FLE_COULEUR,
              f.FLE_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN FLEUR f ON f.PRO_ID = p.PRO_ID
            WHERE p.PRO_ID = :id
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
/*---------- bouquet-----------*/
function recup_donnee_bouquet(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_DESCRIPTION,
              p.PRO_PRIX,
              p.PRO_QTE_MAX,
              p.PRO_IMAGE,
              p.PRO_ACTIF,
              b.BOU_NB_ROSES,
              b.BOU_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN BOUQUET b ON b.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/*---------- coffret-----------*/
function recup_donnee_coffret(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_DESCRIPTION,
              p.PRO_PRIX,
              p.PRO_QTE_MAX,
              p.PRO_IMAGE,
              p.PRO_ACTIF,
              c.COF_TAILLE,
              c.COF_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN COFFRET c ON c.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
/*---------- supplement-----------*/
function recup_donnee_supplement(PDO $pdo): array {
    $sql = "SELECT
              s.SUP_ID,
              s.SUP_NOM,
              s.SUP_DESCRIPTION,
              s.SUP_PRIX_UNITAIRE,
              s.SUP_QTE_STOCK,
              s.SUP_ACTIF
            FROM SUPPLEMENT s
            ORDER BY s.SUP_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/*---------- emballage-----------*/

function recup_donnee_emballage(PDO $pdo): array {
    $sql = "SELECT
              e.EMB_ID,
              e.EMB_NOM,
              e.EMB_DESCRIPTION,
              e.EMB_COULEUR,
              e.EMB_PRIX_UNITAIRE,
              e.EMB_QTE_STOCK,
              e.EMB_IMAGE,
              e.EMB_ACTIF
            FROM EMBALLAGE e
            ORDER BY e.EMB_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
$rows = recup_donnee_fleur($pdo);

foreach ($rows as $r) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($r['PRO_NOM']) . "</td>";
    echo "<td>#". (int)$r['PRO_ID'] . "</td>";
    echo "<td>" . htmlspecialchars($r['FLE_COULEUR'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars(number_format((float)$r['PRO_PRIX'], 2, '.', '')) . "</td>";
    echo "<td>" . htmlspecialchars((string)($r['PRO_QTE_MAX'] ?? '-')) . "</td>";
    echo "<td>" . (int)$r['FLE_QTE_STOCK'] . "</td>";
    echo '<td><a class="btn" href="'.$BASE.'admin_modifier_article.php?type=fleur&id='.(int)$r['PRO_ID'].'">Modifier</a></td>';
    echo "</tr>";
}
