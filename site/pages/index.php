<?php session_start();?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>index</title>
    <link rel="stylesheet" href="css/squeletteIndex.css">
</head>
</script>

<body class="corps">
<?php
include 'includes/header.php';
?>

<main>
    <section class="entete_accueil">
        <div class="image_texte">
               <div class="texte">
                   <h1>Bienvenue <span class="accent">élégance</span></h1>
                   <p class="paragraphe">L’art floral intemporel, au service d’une expérience unique et raffinée. La beauté qui ne fane jamais.</p>
                   <br>
                   <div class="btn_accueil">
                       <a class="btn_index" href="creations.php">Découvrir nos créations</a>
                       <a class="btn_index" href="interface_selection_produit.php">Créer la vôtre</a>
                   </div>
               </div>
                <div class="bouquet">
                    <img class="boxerouge" src="img/boxe_rouge_DK.png" alt="" >
                </div>
        </div>
    </section>

</main>
<?php
include 'includes/footer.php';
?>
</body>
</html>

