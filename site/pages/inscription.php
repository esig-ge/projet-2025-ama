<?php ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription</title>
    <link rel="stylesheet" href="css/style_connexion_inscription.css">
</head>
<body>
<div class="conteneur_form">
    <h2>S'inscrire</h2>
    <form action="" method="POST">
        <label for="firstname">Prénom</label>
        <input type="text" id="firstname" name="firstname" required placeholder="Ton prénom"/>

        <label for="lastname">Nom</label>
        <input type="text" id="lastname" name="lastname" required placeholder="Ton nom"/>

        <label for="phone">Téléphone</label>
        <input type="tel" id="phone" name="phone" required placeholder="078 212 56 78" pattern="[0-9\s\-+]{6,15}"/>

        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" required placeholder="exemple@mail.com"/>

        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required placeholder="••••"/>

        <input type="submit" value="S'inscrire">
    </form>
</div>




