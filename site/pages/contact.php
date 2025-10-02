<?php
session_start();

/**
 * Base URL avec slash final — fonctionne depuis n'importe quel sous-dossier.
 * Si $BASE est déjà défini par un layout parent, on ne le recalcul pas.
 */
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
    <meta name="description" content="Coordonnées, horaires et localisation de DK Bloom à Genève." />

    <!-- CSS global (variables, grilles, typographies, etc.) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- Composants formulaires/cartes (si .card/.container/.grid sont dedans). Supprime si inutile. -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <!-- Petits styles locaux pour cette page (évite le inline style) -->
    <style>
        /* Titre de page sobre, centré ; couleur via var si dispo sinon fallback */
        .page-title{
            text-align:center;
            margin:0 0 16px;
            color: var(--brand, #8d0e0e);
        }
        /* Liste d'infos compacte et propre */
        .info-list{
            margin: 0;
            padding-left: 1.2rem;
            line-height: 1.6;
        }
        /* Carte Google responsive avec angles doux (si pas déjà défini globalement) */
        .map{
            width: 100%;
            height: 320px;
            border: 0;
            border-radius: 8px;
        }
        /* Optionnel : petit espace vertical discret */
        .spacer-xs{ height: 12px; }
        /* Si ta grille ne définit qu'une seule colonne ici, c'est ok :
           tu peux retirer .grid et garder juste .container si tu veux encore simplifier. */
    </style>
</head>

<body>
<?php
/* Le header utilise souvent $BASE pour ses liens, on l'inclut après le calcul ci-dessus. */
include __DIR__ . '/includes/header.php';
?>

<main class="container" role="main">
    <h1 class="page-title">Nous contacter</h1>

    <div class="grid">
        <!-- Bloc coordonnées + carte -->
        <aside class="card" aria-labelledby="titre-infos">
            <h2 id="titre-infos">Nos coordonnées</h2>

            <ul class="info-list">
                <li><strong>E-mail&nbsp;:</strong> <a href="mailto:contact@dkbloom.ch">contact@dkbloom.ch</a></li>
                <li><strong>Téléphone&nbsp;:</strong> <a href="tel:+41791234567">+41&nbsp;79&nbsp;123&nbsp;45&nbsp;67</a></li>
                <li><strong>Adresse&nbsp;:</strong> Rue des Fleurs&nbsp;12, 1200&nbsp;Genève</li>
                <li><strong>Horaires&nbsp;:</strong> Mar–Sam&nbsp;09:00–18:30</li>
            </ul>

            <div class="spacer-xs"></div>

            <!--
              Google Maps sans clé API (recherche). Tu peux cibler précisément l’adresse
              en remplaçant la requête q=… par ton adresse encodée URL.
            -->
            <iframe
                    class="map"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=Rue%20des%20Fleurs%2012%2C%201200%20Gen%C3%A8ve&output=embed"
                    title="Localisation DK Bloom"
            ></iframe>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
