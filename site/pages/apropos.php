<?php
session_start();

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Utilisateur connecté ?
$isLogged = !empty($_SESSION['per_id']); // on teste la clé correcte
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Catalogue bouquet</title>
    <link rel="stylesheet" href="css/style_connexion_inscription.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <h1>Notre histoire</h1>
   <div class="texte_propos">
       <p>
           Recevoir des fleurs fait toujours plaisir. Elles apportent de la joie, de la couleur et un instant d’émotion. Mais ce bonheur est souvent éphémère, car les fleurs finissent par faner.
           C’est de ce constat qu’est née DK Bloom : l’envie de prolonger ces instants précieux en créant des fleurs qui durent pour l’éternité.
           Nos créations allient l’élégance de la nature et la durabilité, pour que chaque bouquet devienne un souvenir intemporel, à offrir ou à s’offrir.
       </p>
   </div>

    <div class="slider">
        <div class="slide-track">
            <!-- Répète les images pour boucle continue -->
            <img src="img/bouquet-removebg-preview.png" alt="1"/>
            <img src="img/boxe_rouge_DK.png" alt="2"/>
            <img src="img/bouquet-removebg-preview.png" alt="3"/>
            <img src="img/boxe_rouge_DK.png" alt="4"/>


            <img src="img/bouquet-removebg-preview.png" alt="1"/>
            <img src="img/boxe_rouge_DK.png" alt="2"/>
            <img src="img/bouquet-removebg-preview.png" alt="3"/>
            <img src="img/boxe_rouge_DK.png" alt="4"/>
            <img src="img/bouquet-removebg-preview.png" alt="5"/>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
</body>