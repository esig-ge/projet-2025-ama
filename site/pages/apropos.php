<?php
session_start();

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$isLogged = !empty($_SESSION['per_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — À propos</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_apropos.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container apropos">
    <h1 class="section-title">Notre histoire</h1>
    <div class="texte_propos">
        <p>
            Recevoir des fleurs fait toujours plaisir. Elles apportent de la joie, de la couleur et un instant d’émotion…
            C’est de ce constat qu’est née DK Bloom : l’envie de prolonger ces instants précieux en créant des fleurs qui durent pour l’éternité.
            Nos créations allient l’élégance de la nature et la durabilité, pour que chaque bouquet devienne un souvenir intemporel.
        </p>
    </div>

    <div class="slider">
        <div class="slide-track">
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet 1">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret 1">
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet 2">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret 2">
            <img src="<?= $BASE ?>img/bouquet-removebg-preview.png" alt="Bouquet 3">
            <img src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret 3">
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
