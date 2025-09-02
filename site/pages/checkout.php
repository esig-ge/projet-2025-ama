<?php
session_start();
require_once __DIR__ . '/../../config/connexionBDD.php';
require_once __DIR__ . '/../../config/stripe.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Checkout</title>
    <link rel="stylesheet" href="css/checkout.css?v=1">

    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<main class="wrap">
    <h1>Finaliser ma commande</h1>

    <div class="grid">
        <section class="card">
            <form id="checkout-form">
                <!-- (tes champs d’adresses ici si besoin) -->
                <h2>Paiement</h2>
                <div id="payment-element"></div>
                <button id="pay-btn" class="btn-primary" type="submit">Payer</button>
                <p id="form-message" class="hint"></p>
            </form>
        </section>

        <aside class="card summary">
            <h2>Récapitulatif</h2>
            <div id="cart-lines"></div>
            <div class="sum-row"><span>Sous-total</span><span id="sum-subtotal">0.00 CHF</span></div>
            <div class="sum-row"><span>Livraison</span><span id="sum-shipping">—</span></div>
            <div class="sum-row"><span>TVA</span><span id="sum-tva">—</span></div>
            <div class="sum-total"><span>Total</span><span id="sum-total">0.00 CHF</span></div>
        </aside>
    </div>
</main>

<script>window.__STRIPE_PK__ = "<?= STRIPE_PUBLISHABLE_KEY ?>";</script>
<script src="js/checkout.js?v=1" defer></script>