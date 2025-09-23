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

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Admin — Modifier l’article</title>
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

    <div class="product-summary">
        <h2 class="product-name"><span data-field="nom">Nom du produit</span></h2>
        <ul class="product-meta">
            <?php
            $fleurs = recup_donnee_fleur($pdo);
            foreach ($fleurs as $fleur) {
                echo '<td>' . htmlspecialchars($fleur['PRO_NOM']) . '</td>';
                echo '<td>' . htmlspecialchars($fleur['PRO_ID']) . '</td>';
                echo '<td>' . htmlspecialchars($fleur['FLE_COULEUR'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($fleur['PRO_PRIX'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($fleur['PRO_QTE_MAX'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($fleur['FLE_QTE_STOCK'] ?? '-') . '</td>';
                echo '<td><a href="admin_modifier_article.php?type=fleur&id=">Modifier</a></td>';
            }
            ?>
            <li><strong>ID :</strong> <span data-field="id">—</span></li>
            <li><strong>Type :</strong> <span data-field="type">fleur | bouquet | coffret | supplement | emballage</span></li>
            <li><strong>Statut :</strong> <span data-field="actif">Actif / Inactif</span></li>
        </ul>
        <p class="product-desc" data-field="description">Description de l’article…</p>
    </div>
</section>

<!-- Détails (toutes les données) -->
<section class="product-details">
    <h3>Données de l’article</h3>

    <table class="kv">
        <tbody>
        <!-- Champs génériques -->
        <tr>
            <th>Nom</th>
            <td><span data-field="nom">—</span></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><span data-field="description">—</span></td>
        </tr>
        <tr>
            <th>Prix (CHF)</th>
            <td><span data-field="prix">—</span></td>
        </tr>
        <tr>
            <th>Prix unitaire (CHF)</th>
            <td><span data-field="prix_unitaire">—</span></td>
        </tr>
        <tr>
            <th>Stock</th>
            <td><span data-field="stock">—</span></td>
        </tr>
        <tr>
            <th>Quantité max</th>
            <td><span data-field="qte_max">—</span></td>
        </tr>
        <tr>
            <th>Visible</th>
            <td><span data-field="actif">—</span></td>
        </tr>
        <tr>
            <th>Image (URL)</th>
            <td><span data-field="image_url_text">—</span></td>
        </tr>

        <!-- Champs spécifiques par TYPE (remplir si applicable, sinon laisser “—”) -->
        <tr>
            <th>Couleur (FLEUR/EMBALLAGE)</th>
            <td><span data-field="couleur">—</span></td>
        </tr>
        <tr>
            <th>Nombre de roses (BOUQUET)</th>
            <td><span data-field="nb_roses">—</span></td>
        </tr>
        <tr>
            <th>Taille (COFFRET)</th>
            <td><span data-field="taille">—</span></td>
        </tr>

        <!-- Métadonnées optionnelles -->
        <tr>
            <th>Tags</th>
            <td><span data-field="tags">—</span></td>
        </tr>
        <tr>
            <th>Créé le</th>
            <td><span data-field="created_at">—</span></td>
        </tr>
        <tr>
            <th>Mis à jour le</th>
            <td><span data-field="updated_at">—</span></td>
        </tr>
        </tbody>
    </table>
</section>

<!-- Actions (affichage seulement pour l’instant) -->
<section class="product-actio
