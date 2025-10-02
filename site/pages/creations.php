<?php
/* Page: creations.php*/

session_start();

/* Base URL robuste: permet de référencer /css, /img, /js depuis /site/pages/ */
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // ex: /.../site/pages/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Nos créations</title>

    <!-- CSS global (header/footer + styles généraux) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style.css">
    <!-- CSS spécifique à cette page (carrousel) -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_creations.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <!-- Titre de page -->
    <h1 class="reveal">Nos prestations déjà réalisées</h1>

    <!-- Section carrousel (scope visuel + sémantique) -->
    <section class="dkb-video-carousel" role="region" aria-label="Prestations DK Bloom">
        <!-- Composant carrousel -->
        <div class="dkb-carousel" aria-roledescription="carousel" aria-label="Carrousel de vidéos" tabindex="0">
            <!-- Piste qui coulisse horizontalement -->
            <div class="dkb-track" style="transform: translateX(0);">
                <!-- Slide 1 -->
                <div class="dkb-slide is-active" id="slide-1" role="group" aria-roledescription="slide" aria-label="1 sur 3">
                    <div class="dkb-frame">
                        <video class="dkb-video"
                               preload="metadata"
                               playsinline
                               muted
                               controls
                               poster="<?= $BASE ?>img/tiktok.png">
                            <source src="<?= $BASE ?>img/videofleur1.mp4" type="video/mp4">
                            Votre navigateur ne peut pas lire cette vidéo.
                        </video>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="dkb-slide" id="slide-2" role="group" aria-roledescription="slide" aria-label="2 sur 3">
                    <div class="dkb-frame">
                        <video class="dkb-video"
                               preload="metadata"
                               playsinline
                               muted
                               controls
                               poster="<?= $BASE ?>img/tiktok.png">
                            <source src="<?= $BASE ?>img/videofleur2.mp4" type="video/mp4">
                            Votre navigateur ne peut pas lire cette vidéo.
                        </video>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="dkb-slide" id="slide-3" role="group" aria-roledescription="slide" aria-label="3 sur 3">
                    <div class="dkb-frame">
                        <video class="dkb-video"
                               preload="metadata"
                               playsinline
                               muted
                               controls
                               poster="<?= $BASE ?>img/tiktok.png">
                            <source src="<?= $BASE ?>img/videofleur3.mp4" type="video/mp4">
                            Votre navigateur ne peut pas lire cette vidéo.
                        </video>
                    </div>
                </div>
            </div>

            <!-- Contrôles (flèches) -->
            <button class="dkb-nav dkb-prev" aria-label="Précédent" type="button">&#10094;</button>
            <button class="dkb-nav dkb-next" aria-label="Suivant"   type="button">&#10095;</button>

            <!-- Indicateurs (puces) -->
            <div class="dkb-dots" role="tablist" aria-label="Sélection de la vidéo">
                <button class="dkb-dot is-active" role="tab" aria-selected="true"  aria-controls="slide-1" type="button"></button>
                <button class="dkb-dot"          role="tab" aria-selected="false" aria-controls="slide-2" type="button"></button>
                <button class="dkb-dot"          role="tab" aria-selected="false" aria-controls="slide-3" type="button"></button>
            </div>
        </div>
    </section>

    <!-- JS du carrousel: simple et commenté -->
    <script>
        (function () {
            const root   = document.querySelector('.dkb-carousel');
            if (!root) return;

            const track  = root.querySelector('.dkb-track');
            const slides = Array.from(root.querySelectorAll('.dkb-slide'));
            const videos = slides.map(s => s.querySelector('video'));
            const prev   = root.querySelector('.dkb-prev');
            const next   = root.querySelector('.dkb-next');
            const dots   = Array.from(root.querySelectorAll('.dkb-dot'));

            let index = 0;
            let advanceTimer = null;

            function playActive() {
                const v = videos[index];
                if (!v) return;
                // Assure-toi que la vidéo est prête puis joue-la (silencieuse)
                const p = v.play?.();
                if (p && typeof p.then === 'function') p.catch(() => {});
            }

            function setIndex(i) {
                index = (i + slides.length) % slides.length;
                track.style.transform = `translateX(-${index * 100}%)`;

                slides.forEach((s, k) => s.classList.toggle('is-active', k === index));
                dots.forEach((d, k) => {
                    d.classList.toggle('is-active', k === index);
                    d.setAttribute('aria-selected', k === index ? 'true' : 'false');
                });

                // Pause toutes les autres vidéos
                videos.forEach((v, k) => { if (k !== index && v) try { v.pause(); } catch {} });

                // Joue celle qui devient visible
                playActive();
            }

            const goNext = () => setIndex(index + 1);
            const goPrev = () => setIndex(index - 1);

            // Boutons
            next.addEventListener('click', goNext);
            prev.addEventListener('click', goPrev);
            dots.forEach((d, k) => d.addEventListener('click', () => setIndex(k)));

            // Clavier
            root.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') goNext();
                if (e.key === 'ArrowLeft')  goPrev();
            });

            // Swipe simple
            let down = false, startX = 0, moved = 0;
            function start(x){ down = true; startX = x; moved = 0; }
            function move(x){ if(!down) return; moved = x - startX; }
            function end(){
                if(!down) return; down = false;
                const t = window.innerWidth * 0.12;
                if (moved >  t) goPrev();
                if (moved < -t) goNext();
            }
            root.addEventListener('pointerdown', e => { start(e.clientX); root.setPointerCapture(e.pointerId); });
            root.addEventListener('pointermove',  e => move(e.clientX));
            root.addEventListener('pointerup',    end);
            root.addEventListener('pointercancel',end);
            root.addEventListener('pointerleave', end);

            // Auto-advance quand une vidéo se termine
            videos.forEach((v) => {
                if (!v) return;
                v.addEventListener('ended', () => {
                    // petite latence pour une transition “smooth”
                    clearTimeout(advanceTimer);
                    advanceTimer = setTimeout(goNext, 200);
                });

                // Si l’utilisateur clique “play” manuellement au milieu, on annule
                // tout timer d’avance automatique pour éviter un saut involontaire.
                v.addEventListener('play', () => clearTimeout(advanceTimer));
            });

            // Met pause si onglet caché, relance la bonne vidéo au retour
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) videos.forEach(v => v && v.pause());
                else playActive();
            });

            // Démarrage
            setIndex(0);
        })();
    </script>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
