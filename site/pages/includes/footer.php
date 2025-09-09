<?php
// Base URL relative au script courant (toujours avec un slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
        <p class="legal">© <?= date('Y') ?> DK Bloom. Tous droits réservés.</p>

        <nav class="footer-links" aria-label="Pied de page">
            <a href="<?= $BASE ?>mentions.php">Mentions légales</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>
            <a href="<?= $BASE ?>login.php">Espace client</a>
        </nav>

        <div class="footer-social">
            <a href="https://www.instagram.com/_dkbloom/"
               target="_blank" rel="noopener" aria-label="Instagram">
                <img src="<?= $BASE ?>img/Instagram_icon.png"
                     alt="Instagram" width="24" height="24" loading="lazy">
            </a>
<<<<<<< HEAD
            <a href="https://www.tiktok.com/@_dkbloom" target="_blank">
                <img src="../img/tiktok-removebg-preview.png" alt="tiktok" width="150">
=======
            <a href="https://www.tiktok.com/@_dkbloom"
               target="_blank" rel="noopener" aria-label="TikTok">
                <img src="<?= $BASE ?>img/tiktok.png"
                     alt="TikTok" width="24" height="24" loading="lazy">
>>>>>>> 8b357fac546ca0a61551585d0190b03a143838a1
            </a>
        </div>
    </div>
</footer>

<!-- JS principal -->
<script src="<?= $BASE ?>js/script.js" defer></script>
