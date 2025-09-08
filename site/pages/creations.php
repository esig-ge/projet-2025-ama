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
                <source src="img/videofleur2.mov" type="video/mp4" >
                Votre navigateur ne supporte pas la vidéo.
            </video>

            <!-- Slide 2 -->
            <video controls>
                <source src="img/videofleur3.mov" type="video/mp4" >
                <source src="img/videofleur2.mov" type="video/mp4" >
            </video>
        </div>
        <button class="btn prev">&#10094;</button>
        <button class="btn next">&#10095;</button>
    </div>
    <!--
    .slider {
      position: relative;
      max-width: 800px;
      margin: 0 auto;
      overflow: hidden;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .slides {
      display: flex;
      transition: transform 0.5s ease-in-out;
    }

    .slides video,
    .slides img {
      min-width: 100%;
      height: 450px;
      object-fit: cover;
    }

    /* Boutons */
    .btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      font-size: 2rem;
      color: white;
      background: rgba(0,0,0,0.4);
      border: none;
      cursor: pointer;
      padding: 10px;
      border-radius: 50%;
    }
    .btn.prev { left: 10px; }
    .btn.next { right: 10px; }
  </style>
</head>
<body>

  <header> -->
</main>

<?php include 'includes/footer.php.php'; ?>
</body>