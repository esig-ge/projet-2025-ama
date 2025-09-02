<?php

// Les boutons ajouter pour chaque produit doivent être comme ça :
/*
<button class="add-to-cart"
        data-id="BQ-20"
        data-sku="BQ-20"
        data-name="20 roses"
        data-price="40"
        data-img="assets/img/20Roses.png">
            Ajouter
</button>

*/

// Comme ça, le lient est créé avec la page commande et c'est js qui gère


//Puis penser à intégrer ça niveau php :

/*
 * <body>
  <h1>Catalogue bouquets</h1>

  <div id="catalogue">
    <?php foreach ($produits as $produit): ?>
      <div class="produit">
        <img src="../assets/img/<?= htmlspecialchars($produit['image']) ?>"
             alt="<?= htmlspecialchars($produit['nom']) ?>">

        <h3><?= htmlspecialchars($produit['nom']) ?></h3>
        <p><?= number_format($produit['prix'], 2) ?> CHF</p>

        <!-- Bouton Ajouter avec les data-attributes -->
        <button class="add-to-cart"
          data-id="<?= $produit['id'] ?>"
          data-nom="<?= htmlspecialchars($produit['sku']) ?>"
          data-attribut3="<?= htmlspecialchars($produit['nom']) ?>"   // Noms des attributs tel qu'ils se trouvent dans la base de données
          data-attribut4="<?= $produit['prix'] ?>"
          data-img="../assets/img/<?= htmlspecialchars($produit['image']) ?>">
          Ajouter
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <a href="commande.php" class="button">Voir mon panier</a>

  <script src="../assets/js/cart.js"></script>
 */