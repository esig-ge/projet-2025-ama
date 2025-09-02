<?php session_start();?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>index</title>
    <link rel="stylesheet" href="../../public/assets/css/style.css">
</head>

<?php include 'app/includes/header.php';
?>
<script>
    (function () {
        // Ciblage selon ta structure
        const track  = document.querySelector('[data-track]');
        const slide  = track.querySelector('.slide');
        const imgs   = Array.from(slide.querySelectorAll('img'));
        const prev   = slide.querySelector('[data-prev]');
        const next   = slide.querySelector('[data-next]');
        let index = 0;

        // Affiche l'image d'indice i
        function show(i) {
            imgs.forEach((img, k) => img.classList.toggle('is-active', k === i));
            prev.disabled = (i === 0);
            next.disabled = (i === imgs.length - 1);
        }

        // Navigation
        prev.addEventListener('click', () => { if (index > 0) { index--; show(index); } });
        next.addEventListener('click', () => { if (index < imgs.length - 1) { index++; show(index); } });

        // Clavier (facultatif)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft')  prev.click();
            if (e.key === 'ArrowRight') next.click();
        });

        // Init (active la 1ère image)
        show(0);
    })();
</script>

<body class="corps">


<main>
    <section class="entete_accueil">
        <div class="boutons">
            <div class="cta-row">
                <h1>Bienvenu <span class="accent">élégance</span>.</h1>
                <p>L’art floral intemporel, au service d’une expérience unique et raffinée. La beauté qui ne fane jamais.</p>
                <br>
                <a class="btn_catalogue" href="catalogue.php">Découvrir nos créations</a>
                <a class="btn_creer" href="personnalisation.php">Créer la vôtre</a>

            </div>
            <div class="bouquet">
                <img class="" src="../../public/assets/img/bouquet-removebg-preview.png" alt="" width="200px" height="auto">
            </div>
    </section>
</main>

</body>

<?php
include 'app/includes/footer.php';
?>
