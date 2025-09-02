<?php
session_start();

// Connexion BDD (même si on ne l'utilise pas encore sur cette page)
//require_once __DIR__ . '/config/connexionBdd.php';

// Pour identifier un client connecté:
// $userId = $_SESSION['user']['id'] ?? null;
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Mon panier</title>

    <!-- CSS du panier -->
    <link rel="stylesheet" href="css/commande.css">
</head>
<body>
<header class="dkb-header">
    <div class="wrap headbar">
        <a class="brand" href="index.php">
            <img src="/public/assets/img/logo.jpg" alt="DK Bloom" class="logo">
            <span class="brand-text">DK Bloom</span>
        </a>
        <nav class="head-actions">
            <a href="catalogue.php" class="link">Continuer mes achats</a>
        </nav>
    </div>
</header>

<main class="wrap">
    <h1 class="page-title">Récapitulatif de mon panier</h1>

    <div class="grid">
        <!-- Liste des lignes du panier -->
        <section class="card" id="cart-list" aria-live="polite">
            <!-- Les lignes seront injectées par JS -->
        </section>

        <!-- Résumé -->
        <aside class="card summary">
            <div class="sum-row">
                <span>Produits</span><span id="sum-subtotal">0.00 CHF</span>
            </div>
            <div class="sum-row">
                <span>Livraison</span><span id="sum-shipping">—</span>
            </div>
            <div class="sum-total">
                <span>Total</span><span id="sum-total">0.00 CHF</span>
            </div>

            <a href="checkout.php" class="btn-primary" id="btn-checkout">Valider ma commande</a>

            <div class="coupon">
                <input type="text" id="coupon-input" placeholder="Mon code de réduction" disabled>
                <button class="btn-ghost" disabled>Ajouter</button>
            </div>

            <div class="help">
                <p>Une question ? Contactez-nous au <a href="tel:+4122XXXXXXX">+41 76 569 85 41</a></p>
                <ul>
                    <li>Expédition sous 24–48h (si disponible)</li>
                    <li>Frais offerts dès 80 CHF</li>
                    <li>Paiement sécurisé</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<footer class="dkb-footer">
    <div class="wrap">© DK Bloom</div>
</footer>

<!-- JS du panier -->
<script src="/public/assets/js/commande.js"></script>
</body>
</html>
