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
              p.PRO_PRIX,
              f.FLE_COULEUR,
              f.FLE_QTE_STOCK
            FROM PRODUIT p
            INNER JOIN FLEUR f ON f.PRO_ID = p.PRO_ID
            ORDER BY p.PRO_NOM ASC";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
/*
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
}*/
/*---------- bouquet-----------*//*
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
}*/

/*---------- coffret-----------*//*
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
}*/
/*---------- supplement-----------*//*
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
}*/

/*---------- emballage-----------*/
/*
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
}*/

?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Admin — Modifier les articles </title>
</head>
<body class="adm">

<!-- En-tête / fil d’Ariane -->
<header class="admin-header">
    <nav class="breadcrumb">
        <a href="#">Dashboard</a> › <a href="#">Produits</a> › <span>Modifier</span>
    </nav>
    <h1>Modifier l’article</h1>
</header>

<!-- Carte “aperçu” -->
<section class="product-hero">
    <figure class="product-cover">
        <!-- Remplacer src par l’URL de l’image de l’article -->
        <img data-field="image_url" src="/img/placeholder.png" alt="Image de l’article" />
    </figure>
</section>


<!-- Détails (toutes les données) -->
<section class="product-details">
    <h3>Données de l’article</h3>

    <table class="kv">
        <tbody>
        <?php
        $fleurs = recup_donnee_fleur($pdo);
        foreach ($fleurs as $fleur) { ?>
            <tr>
                <td>Nom</td>
                <td><?= htmlspecialchars($fleur['PRO_NOM']) ?></td>
            </tr>
            <tr>
                <td>Prix </td>
                <td><?= htmlspecialchars($fleur['PRO_PRIX']) ?> CHF</td>
            </tr>
            <tr>
                <td>Couleur</td>
                <td><?= htmlspecialchars($fleur['FLE_COULEUR'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Stock</td>
                <td><?= htmlspecialchars($fleur['FLE_QTE_STOCK'] ?? 0) ?></td>
            </tr>
            <tr>
                <td>Quantité max</td>
                <td><?= htmlspecialchars($fleur['PRO_QTE_MAX'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Image</strong></td>
                <td><?= htmlspecialchars($fleur['PRO_IMAGE'] ?? '-') ?></td>
            </tr>
            <li><strong>Statut :</strong> <span data-field="actif">Actif / Inactif</span></li>

            <br>
        <?php } ?>
        </tbody>
    </table>

</section>

<!-- Actions (affichage seulement pour l’instant) -->
<section class="product-actio
