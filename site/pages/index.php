<?php
session_start();

// Vérifie si l'utilisateur a déjà accepté les cookies
$showCookieBanner = !isset($_COOKIE['accept_cookies']);

// Prefixe URL qui marche depuis n'importe quelle page de /site/pages
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Accueil</title>
    <style>
        .cookie-banner {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            font-family: sans-serif;
            z-index: 1000;
        }
        .cookie-banner button {
            background: #333;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
    <link rel="stylesheet" href="<?= $BASE ?>css/squeletteIndex.css">
</head>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const accountMenu = document.getElementById('account-menu');
        if (!accountMenu) return;

        const trigger = accountMenu.querySelector('.menu-link');

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            accountMenu.classList.toggle('open');
        });

        // Ferme si on clique ailleurs
        document.addEventListener('click', (e) => {
            if (!accountMenu.contains(e.target)) {
                accountMenu.classList.remove('open');
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
                        <span class="accent">cher client</span>
                    <?php endif; ?>
                </h1>

                <?php if ($showCookieBanner): ?>
                    <div class="cookie-banner" id="cookieBanner">
                        <span>🍪 Nous utilisons des cookies pour améliorer votre expérience.</span>
                        <button onclick="acceptCookies()">Accepter</button>
                    </div>
                    <script>
                        function acceptCookies() {
                            // Définit un cookie qui expire dans 1 an
                            document.cookie = "accept_cookies=true; path=/; max-age=" + 60*60*24*365;
                            document.getElementById('cookieBanner').style.display = 'none';
                        }
                    </script>
                <?php endif; ?>
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

<script>window.DKBASE = <?= json_encode($BASE) ?>;</script>
<script src="<?= $BASE ?>js/commande.js"></script>
<?php if (!empty($_SESSION['toast'])):
    $t = $_SESSION['toast'];
    unset($_SESSION['toast']); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const msg  = <?= json_encode($t['text'], JSON_UNESCAPED_UNICODE) ?>;
            const type = <?= json_encode($t['type']) ?>;
            if (typeof window.toast === 'function') { window.toast(msg, type); }
            else if (typeof window.showToast === 'function') { window.showToast(msg, type); }
            else { alert(msg); }
        });
    </script>
<?php endif; ?>

</body>
</html>
