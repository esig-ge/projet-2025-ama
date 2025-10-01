<?php
session_start();
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // ex: /.../site/pages/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Coffrets</title>

    <!-- CSS global + page -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCreations.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <h1>Nos prestations déjà réalisées</h1>

    <section class="dkb-video-carousel" role="region" aria-label="Prestations DK Bloom">
        <!-- rôle et description de carrousel + focus clavier -->
        <div class="dkb-carousel" data-active="0"
             role="region" aria-roledescription="carousel" aria-label="Carrousel de vidéos" tabindex="0">
            <div class="dkb-track">
                <!-- Slide 1 -->
                <div class="dkb-slide is-active" id="slide-1">
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
                <div class="dkb-slide" id="slide-2">
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
                <div class="dkb-slide" id="slide-3">
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

            <!-- Flèches -->
            <button class="dkb-nav dkb-prev" aria-label="Précédent" type="button">&#10094;</button>
            <button class="dkb-nav dkb-next" aria-label="Suivant" type="button">&#10095;</button>

            <!-- Puces -->
            <div class="dkb-dots" role="tablist" aria-label="Sélection de la vidéo">
                <button class="dkb-dot is-active" role="tab" aria-selected="true" aria-controls="slide-1" type="button"></button>
                <button class="dkb-dot" role="tab" aria-selected="false" aria-controls="slide-2" type="button"></button>
                <button class="dkb-dot" role="tab" aria-selected="false" aria-controls="slide-3" type="button"></button>
            </div>
        </div>
        <div>
            M D R
            <!--<div><img src="<?php /*= $BASE */?>img/bouquet_rouge_insta.jpg" alt="Bouquet rouge"></div>
            <div><img src="<?php /*= $BASE */?>img/bouquet_B.jpg" alt="Bouquet B"></div>
            <div><img src="<?php /*= $BASE */?>img/bouquet_rouge_coeur.jpg" alt="Bouquet cœur rouge"></div>-->
        </div>
    </section>




    <script>
        (() => {
            const root  = document.querySelector('.dkb-carousel');
            if (!root) return;

            const track = root.querySelector('.dkb-track');
            const slides = Array.from(root.querySelectorAll('.dkb-slide'));
            const videos = slides.map(s => s.querySelector('video'));
            const prevBtn = root.querySelector('.dkb-prev');
            const nextBtn = root.querySelector('.dkb-next');
            const dots    = Array.from(root.querySelectorAll('.dkb-dot'));

            let index = 0;
            const setIndex = (i) => {
                index = (i + slides.length) % slides.length;
                track.style.transform = `translateX(-${index * 100}%)`;
                slides.forEach((s, k) => s.classList.toggle('is-active', k === index));
                dots.forEach((d, k) => d.classList.toggle('is-active', k === index));
                dots.forEach((d, k) => d.setAttribute('aria-selected', k === index ? 'true' : 'false'));

                // Lecture uniquement de la vidéo active
                videos.forEach((v, k) => {
                    if (!v) return;
                    if (k === index) {
                        // Autoplay silencieux si possible
                        const play = v.play?.();
                        if (play && typeof play.then === 'function') {
                            play.catch(() => {/* ignore */});
                        }
                    } else {
                        try { v.pause(); } catch(e){}
                    }
                });
            };

            const next = () => setIndex(index + 1);
            const prev = () => setIndex(index - 1);

            nextBtn.addEventListener('click', next);
            prevBtn.addEventListener('click', prev);
            dots.forEach((d, k) => d.addEventListener('click', () => setIndex(k)));

            // clavier
            root.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') next();
                if (e.key === 'ArrowLeft')  prev();
            });
            root.tabIndex = 0; // pour capter le clavier

            // Swipe (drag) simple
            let startX = 0, isDown = false, moved = 0;
            const onStart = (x) => { isDown = true; startX = x; moved = 0; };
            const onMove  = (x) => { if(!isDown) return; moved = x - startX; };
            const onEnd   = () => {
                if(!isDown) return;
                isDown = false;
                const threshold = window.innerWidth * 0.12;
                if (moved >  threshold) prev();
                else if (moved < -threshold) next();
            };

            root.addEventListener('pointerdown', e => { onStart(e.clientX); root.setPointerCapture(e.pointerId); });
            root.addEventListener('pointermove',  e => onMove(e.clientX));
            root.addEventListener('pointerup',    onEnd);
            root.addEventListener('pointercancel',onEnd);
            root.addEventListener('pointerleave', onEnd);

            // Mise en pause quand la page n’est pas visible
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) videos.forEach(v => v && v.pause());
                else setIndex(index);
            });

            // Démarrage
            setIndex(0);
        })();
    </script>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- JS éventuel du slider -->
<script src="<?= $BASE ?>js/script.js" defer></script>
<!-- <script src="<?= $BASE ?>js/slider.js" defer></script> -->
</body>
</html>
