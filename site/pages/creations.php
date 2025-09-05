<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>coffret</title>
    <link rel="stylesheet" href="css/styleCatalogue.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<h1>Mes prestations déjà réalisées</h1>
<main>
    <div class="slider">
        <div class="slides">
            <!-- Slide 1 -->
            <video controls>
                <source src="videos/presta1.mp4" type="video/mp4">
                Votre navigateur ne supporte pas la vidéo.
            </video>

            <!-- Slide 2 -->
            <video controls>
                <source src="videos/presta2.mp4" type="video/mp4">
            </video>

            <!-- Slide 3 -->
            <img src="images/presta3.jpg" alt="Photo prestation">
        </div>
        <button class="btn prev">&#10094;</button>
        <button class="btn next">&#10095;</button>
    </div>
</main>

<?php include 'includes/footer.php.php'; ?>
</body>