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
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <h1 class="section-title" style="text-align:center; margin:0 0 16px; color:darkred;">Nous contacter</h1>

    <div class="grid">
        <!-- Formulaire -->
        <section class="card" aria-labelledby="titre-contact" style="max-width:760px; margin:auto;">
            <h2 id="titre-contact">Écris-nous</h2>
            <p class="muted">Une question, un événement ? Remplis le formulaire et nous te répondons rapidement.</p>

            <form id="contactForm" method="post" action="<?= $BASE ?>infos_contact.php" novalidate>
                <div>
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input id="nom" name="nom" required autocomplete="family-name" />
                </div>

                <div>
                    <label for="prenom">Prénom</label>
                    <input id="prenom" name="prenom" autocomplete="given-name" />
                </div>

                <div class="full">
                    <label for="email">E-mail <span class="req">*</span></label>
                    <input id="email" name="email" type="email" required autocomplete="email" inputmode="email" />
                </div>

                <div>
                    <label for="tel">Téléphone</label>
                    <input id="tel" name="tel" type="tel" inputmode="tel" autocomplete="tel"
                           placeholder="+41 79 123 45 67"
                           pattern="^(\+?\d{2}\s?)?0?\d{2}[\s.]?\d{3}[\s.]?\d{2}[\s.]?\d{2}$" />
                    <div class="help">Format CH accepté (ex. +41 79 123 45 67)</div>
                </div>

                <div>
                    <label for="sujet">Sujet <span class="req">*</span></label>
                    <select id="sujet" name="sujet" required>
                        <option value="">— choisir —</option>
                        <option>Service après-vente</option>
                        <option>Autre</option>
                    </select>
                </div>

                <div class="full">
                    <label for="message">Message <span class="req">*</span></label>
                    <textarea id="message" name="message" required maxlength="2000"
                              placeholder="Décris ton besoin, la date souhaitée, le budget, etc."></textarea>
                </div>

                <div class="full row-inline">
                    <input type="checkbox" id="consent" name="consent" required>
                    <label for="consent">J’accepte que mes données soient utilisées pour traiter ma demande (RGPD).</label>
                </div>

                <div class="full actions">
                    <button id="sendBtn" type="submit">Envoyer la demande</button>
                    <span class="note" id="formNote" aria-live="polite"></span>
                </div>
            </form>

            <div id="formMsg" class="alert ok" style="display:none;margin-top:12px;"></div>
            <div id="formErr" class="alert err" style="display:none;margin-top:12px;"></div>
        </section>

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
