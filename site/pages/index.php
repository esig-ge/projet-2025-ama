<?php
session_start();
// Prefixe URL qui marche depuis n'importe quelle page de /site/pages
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Accueil</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/squeletteIndex.css">
</head>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Ouvrir/fermer au clic
        document.querySelectorAll('.menu .dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const dd = btn.closest('.dropdown');
                const isOpen = dd.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });

        // Fermer si clic à l'extérieur
        document.addEventListener('click', (e) => {
            document.querySelectorAll('.menu .dropdown.open').forEach(dd => {
                if (!dd.contains(e.target)) {
                    dd.classList.remove('open');
                    const btn = dd.querySelector('.dropdown-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });
        });

        // Fermer avec Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.menu .dropdown.open').forEach(dd => {
                    dd.classList.remove('open');
                    const btn = dd.querySelector('.dropdown-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            }
        });
    });
</script>

<body class="corps">

<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <section class="entete_accueil">
        <div class="image_texte">
            <div class="texte">
                <h1>
                    Bienvenu<?php if (!empty($_SESSION['per_prenom'])): ?>e <?= htmlspecialchars($_SESSION['per_prenom']) ?>
                    <?php else: ?>
                        <span class="accent">élégance</span>
                    <?php endif; ?>
                </h1>
                <p class="paragraphe">
                    L’art floral intemporel, au service d’une expérience unique et raffinée.
                    La beauté qui ne fane jamais.
                </p>
                <br>
                <div class="btn_accueil">
                    <a class="btn_index" href="<?= $BASE ?>creations.php">Découvrir nos créations</a>
                    <a class="btn_index" href="<?= $BASE ?>interface_selection_produit.php">Créer la vôtre</a>
                </div>
            </div>

            <div class="bouquet">
                <img class="boxerouge" src="<?= $BASE ?>img/boxe_rouge_DK.png" alt="Coffret de roses DK">
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
