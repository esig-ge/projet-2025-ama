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

/*---------- bouquet-----------*/
function recup_donnee_bouquet(PDO $pdo): array {
    $sql = "SELECT
              p.PRO_ID,
              p.PRO_NOM,
              p.PRO_PRIX,
              b.BOU_NB_ROSES,
              b.BOU_COULEUR,
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
              c.COF_EVENEMENT,
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
              s.SUP_QTE_STOCK
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
              e.EMB_COULEUR,
              e.EMB_QTE_STOCK
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
    <title>Admin — Modifier les articles</title>



</head>
<body class="adm">

<!-- En-tête / fil d’Ariane -->
<header class="admin-header">
    <nav class="breadcrumb">
        <a href="adminAccueil.php">Dashboard</a> › <a href="admin_catalogue.php">Produits</a>
    </nav>
    <h1>Modifier les articles</h1>
</header>


<section class="product-details">
    <h3>Données de l’article des Roses</h3>
    <thead>
    <td><strong>Image</strong></td>
    <td><strong>Nom</strong></td>
    <td><strong>Prix</strong> </td>
    <td><strong>Couleur</strong></td>
    <td><strong>Stock</strong></td>
    <td><strong>Quantité max</strong></td>
    </thead>
    <table class="kv">
        <tbody>
        <?php
        $fleurs = recup_donnee_fleur($pdo);
        foreach ($fleurs as $fleur) { ?>
            <!--                <img data-field="image_url" src="/img/placeholder.png" alt="Image de l’article" />-->
            <tr>
                <td><?= htmlspecialchars($fleur['PRO_IMAGE'] ?? '---') ?></td>
                <td><?= htmlspecialchars($fleur['PRO_NOM']) ?></td>
                <td><?= htmlspecialchars($fleur['PRO_PRIX']) ?> CHF</td>
                <td><?= htmlspecialchars($fleur['FLE_COULEUR'] ?? '---') ?></td>
                <td><?= htmlspecialchars($fleur['FLE_QTE_STOCK'] ?? '---') ?></td>
                <td><?= htmlspecialchars($fleur['PRO_QTE_MAX'] ?? '---') ?></td>
            </tr>

        <?php } ?>
        </tbody>
    </table>
</section>

<br><section class="product-details">
    <h3>Données de l’article des Roses</h3>
    <table class="kv">
        <thead>
        <tr>
            <td><strong>Image</strong></td>
            <td><strong>Nom</strong></td>
            <td><strong>Prix</strong></td>
            <td><strong>Couleur</strong></td>
            <td><strong>Stock</strong></td>
            <td><strong>Quantité max</strong></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $fleurs = recup_donnee_fleur($pdo);
        if (empty($fleurs)) { ?>
            <tr><td colspan="6">Aucun article trouvé</td></tr>
        <?php } else {
            foreach ($fleurs as $fleur) { ?>
                <tr>
                    <td><?= htmlspecialchars($fleur['PRO_IMAGE'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($fleur['PRO_NOM']) ?></td>
                    <td><?= htmlspecialchars($fleur['PRO_PRIX']) ?> CHF</td>
                    <td><?= htmlspecialchars($fleur['FLE_COULEUR'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($fleur['FLE_QTE_STOCK'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($fleur['PRO_QTE_MAX'] ?? '---') ?></td>
                </tr>
            <?php } } ?>
        </tbody>
    </table>
</section>

<section class="product-details">
    <h3>Données de l’article des Bouquets</h3>
    <table class="kv">
        <thead>
        <tr>
            <td><strong>Nom</strong></td>
            <td><strong>Prix</strong></td>
            <td><strong>Nb Roses</strong></td>
            <td><strong>Couleur</strong></td>
            <td><strong>Stock</strong></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $bouquets = recup_donnee_bouquet($pdo);
        if (empty($bouquets)) { ?>
            <tr><td colspan="5">Aucun article trouvé</td></tr>
        <?php } else {
            foreach ($bouquets as $b) { ?>
                <tr>
                    <td><?= htmlspecialchars($b['PRO_NOM']) ?></td>
                    <td><?= htmlspecialchars($b['PRO_PRIX']) ?> CHF</td>
                    <td><?= htmlspecialchars($b['BOU_NB_ROSES'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($b['BOU_COULEUR'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($b['BOU_QTE_STOCK'] ?? '—') ?></td>
                </tr>
            <?php } } ?>
        </tbody>
    </table>
</section>

<section class="product-details">
    <h3>Données de l’article des Coffrets</h3>
    <table class="kv">
        <thead>
        <tr>
            <td><strong>Nom</strong></td>
            <td><strong>Prix</strong></td>
            <td><strong>Événement</strong></td>
            <td><strong>Stock</strong></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $coffrets = recup_donnee_coffret($pdo);
        if (empty($coffrets)) { ?>
            <tr><td colspan="4">Aucun article trouvé</td></tr>
        <?php } else {
            foreach ($coffrets as $c) { ?>
                <tr>
                    <td><?= htmlspecialchars($c['PRO_NOM']) ?></td>
                    <td><?= isset($c['PRO_PRIX']) ? htmlspecialchars($c['PRO_PRIX']).' CHF' : '—' ?></td>
                    <td><?= htmlspecialchars($c['COF_EVENEMENT'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['COF_QTE_STOCK'] ?? '—') ?></td>
                </tr>
            <?php } } ?>
        </tbody>
    </table>
</section>

<section class="product-details">
    <h3>Données de l’article des Suppléments</h3>
    <table class="kv">
        <thead>
        <tr>
            <td><strong>Nom</strong></td>
            <td><strong>Description</strong></td>
            <td><strong>Prix</strong></td>
            <td><strong>Stock</strong></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $supps = recup_donnee_supplement($pdo);
        if (empty($supps)) { ?>
            <tr><td colspan="4">Aucun article trouvé</td></tr>
        <?php } else {
            foreach ($supps as $s) { ?>
                <tr>
                    <td><?= htmlspecialchars($s['SUP_NOM']) ?></td>
                    <td><?= htmlspecialchars($s['SUP_DESCRIPTION'] ?? '—') ?></td>
                    <td><?= isset($s['SUP_PRIX_UNITAIRE']) ? htmlspecialchars($s['SUP_PRIX_UNITAIRE']).' CHF' : '—' ?></td>
                    <td><?= htmlspecialchars($s['SUP_QTE_STOCK'] ?? '—') ?></td>
                </tr>
            <?php } } ?>
        </tbody>
    </table>
</section>

<section class="product-details">
    <h3>Données de l’article des Emballages</h3>
    <table class="kv">
        <thead>
        <tr>
            <td><strong>Nom</strong></td>
            <td><strong>Couleur</strong></td>
            <td><strong>Stock</strong></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $embs = recup_donnee_emballage($pdo);
        if (empty($embs)) { ?>
            <tr><td colspan="3">Aucun article trouvé</td></tr>
        <?php } else {
            foreach ($embs as $e) { ?>
                <tr>
                    <td><?= htmlspecialchars($e['EMB_NOM']) ?></td>
                    <td><?= htmlspecialchars($e['EMB_COULEUR'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($e['EMB_QTE_STOCK'] ?? '—') ?></td>
                </tr>
            <?php } } ?>
        </tbody>
    </table>
</section>

<section class="product-actio
