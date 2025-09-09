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
    <div class="main_slide">
        <div class="slider">
            <div class="slides">
                <!-- Slide 1 -->
                <video controls>
                    <source src="img/videofleur2.mp4" type="video/mp4" >
                </video>

                <!-- Slide 2 -->
                <video controls>
                    <source src="img/videofleur3.mp4" type="video/mp4" >
                </video>
                <button class="btn prev">&#10094;</button>
                <button class="btn next">&#10095;</button>
            </div>
        </div>
    </div>

</main>

<?php include 'includes/footer.php'; ?>
</body>