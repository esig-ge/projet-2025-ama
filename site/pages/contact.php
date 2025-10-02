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

<main role="main">
    <!-- MAP HERO -->
    <section class="contact-hero">
        <div class="map-blob" id="mapBlob">
            <iframe
                    class="map-iframe"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="https://www.google.com/maps?q=Rue%20des%20Fleurs%2012%2C%201200%20Gen%C3%A8ve&output=embed"
                    title="Localisation DK Bloom"
            ></iframe>
        </div>
    </section>

    <!-- Informations de contact -->
    <section class="contact-talk container">
        <h1 class="talk-title"> INFOS ET <span> CONTACT </span></h1>

        <div class="talk-grid">
            <div class="talk-col">
                <h2>DK Bloom </h2>
                <ul class="talk-list">
                    <li><strong>Adresse</strong><br>Rue des Fleurs 12<br>1200 Genève, CH</li>
                    <li><strong>Téléphone</strong><br><a href="tel:+41791234567">+41 79 123 45 67</a></li>
                    <li><strong>E-mail</strong><br><a href="mailto:dk.bloom@gmail.com">dk.bloom@gmail.com</a></li>
                    <li><strong>Horaires</strong><br>Mar–Sam · 09:00–18:30</li>
                </ul>
            </div>

            <div class="talk-col">
                <h2>Suivez-nous dans nos réseaux-sociaux</h2>
                <div class="socials">
                    <!-- TikTok -->
                    <a class="social-btn" aria-label="TikTok"
                       href="https://www.tiktok.com/@_dkbloom"
                       target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                            <path fill="currentColor"
                                  d="M12 2c.5 0 1 .4 1 1v11.6c0 1.5-1.2 2.7-2.7 2.7S7.6 16.1 7.6 14.6c0-1.4 1-2.5 2.4-2.7V9.3c-3 .2-5.4 2.6-5.4 5.6 0 3.1 2.5 5.6 5.6 5.6 3 0 5.6-2.5 5.6-5.6V8.4c.9.6 2 1 3.1 1.1.6 0 1-.4 1-1V6.3c0-.6-.4-1-1-1-.9 0-1.8-.3-2.5-.9a3.6 3.6 0 0 1-1.1-2.6c0-.5-.4-1-1-1h-4c-.6 0-1 .5-1 1z"/>
                        </svg>
                    </a>

                    <!-- Instagram -->
                    <a class="social-btn" aria-label="Instagram"
                       href="https://www.instagram.com/_dkbloom/"
                       target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                            <path fill="currentColor"
                                  d="M12 7.3A4.7 4.7 0 1 0 12 16.7 4.7 4.7 0 1 0 12 7.3zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm6.1-8.3a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2zM12 2.5c-2.7 0-3 .01-4.1.06-1.1.05-1.9.23-2.6.5a5.2 5.2 0 0 0-1.9 1.2 5.2 5.2 0 0 0-1.2 1.9c-.27.7-.45 1.5-.5 2.6C1.1 10.4 1.1 10.7 1.1 13.4s.01 3 .06 4.1c.05 1.1.23 1.9.5 2.6.28.7.64 1.3 1.2 1.9a5.2 5.2 0 0 0 1.9 1.2c.7.27 1.5.45 2.6.5 1.1.05 1.4.06 4.1.06s3-.01 4.1-.06c1.1-.05 1.9-.23 2.6-.5a5.2 5.2 0 0 0 1.9-1.2 5.2 5.2 0 0 0 1.2-1.9c.27-.7.45-1.5.5-2.6.05-1.1.06-1.4.06-4.1s-.01-3-.06-4.1c-.05-1.1-.23-1.9-.5-2.6a5.2 5.2 0 0 0-1.2-1.9 5.2 5.2 0 0 0-1.9-1.2c-.7-.27-1.5-.45-2.6-.5C15 2.51 14.7 2.5 12 2.5z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- SEPARATOR + 3 ICONS -->
    <section class="contact-help container">
        <h2>Comment pouvons-nous <span> vous aider ?</span></h2>
        <ul class="help-cards">
            <li class="help-card">
                <div class="help-icon">?</div>
                <p>Questions fréquentes</p>
            </li>
            <li class="help-card">
                <div class="help-icon">🤝</div>
                <p>Événements & partenariats</p>
            </li>
            <li class="help-card">
                <div class="help-icon">📷</div>
                <p>Prestations & médias</p>
            </li>
        </ul>
    </section>
</main>

<script>
    const blob = document.getElementById('mapBlob');

    // Desktop : survol = mode interactif
    blob.addEventListener('mouseenter', () => blob.classList.add('is-interactive'));
    blob.addEventListener('mouseleave', () => blob.classList.remove('is-interactive'));

    // Clavier / accessibilité
    blob.addEventListener('focusin',  () => blob.classList.add('is-interactive'));
    blob.addEventListener('focusout', () => blob.classList.remove('is-interactive'));

    // Mobile : premier tap = interactif ; tap en dehors = revient
    document.addEventListener('touchstart', (e) => {
        if (blob.contains(e.target)) {
            blob.classList.add('is-interactive');
        } else {
            blob.classList.remove('is-interactive');
        }
    }, {passive:true});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
