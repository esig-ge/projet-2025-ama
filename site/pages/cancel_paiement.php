<?php

// /site/pages/cancel.php
declare(strict_types=1);
session_start();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>DK Bloom — Paiement annulé</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            background: #fff7f7;
            font-family: system-ui, Segoe UI, Roboto, Arial, sans-serif
        }

        .wrap {
            max-width: 720px;
            margin: 40px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .07);
            padding: 28px
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff0f3;
            border: 1px solid #f2c9cf;
            color: #8a1b2e
        }

        a.btn {
            display: inline-block;
            margin-top: 16px;
            background: #5C0012;
            color: #fff;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Paiement annulé</h1>
    <p class="alert">Votre session de paiement a été annulée. Aucun montant n'a été débité.</p>
    <a class="btn" href="adresse_paiement.php">Revenir au paiement</a>
</div>
</body>
</html>
