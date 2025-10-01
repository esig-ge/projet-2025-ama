<?php
session_start();

// Base URL avec slash final (robuste depuis n'importe quel sous-dossier)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <title>Contact — DK Bloom</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- CSS global (header/footer + layout) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- CSS spécifique (tu l’utilises pour tes formulaires/cartes) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">
    <!-- <link rel="stylesheet" href="<?= $BASE ?>css/style.css"> -->
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 style="text-align:center; margin:0 0 16px; color:darkred;">Nous contacter</h1>

    <div class="grid">
             <!-- Coordonnées / Carte -->
        <aside class="card" aria-labelledby="titre-infos">
            <h2 id="titre-infos">Nos coordonnées</h2>
            <ul class="info-list">
                <li><strong>E-mail :</strong> contact@dkbloom.ch</li>
                <li><strong>Téléphone :</strong> +41 79 123 45 67</li>
                <li><strong>Adresse :</strong> Rue des Fleurs 12, 1200 Genève</li>
                <li><strong>Horaires :</strong> Mar–Sam 9:00–18:30</li>
            </ul>

            <div style="height:12px"></div>
            <iframe
                    class="map"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=Gen%C3%A8ve&output=embed"
                    title="Carte — DK Bloom"
                    style="width:100%; height:320px; border:0; border-radius:8px;">
            </iframe>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
