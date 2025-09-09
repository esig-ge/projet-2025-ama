<?php
// Base URL relative au script courant (toujours avec un slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<footer class="site-footer" role="contentinfo">
    <div class="container footer-grid">
        <p class="footer-left">© <?= date('Y') ?> DK Bloom. Tous droits réservés.</p>

        <nav class="footer-nav" aria-label="Pied de page">
            <a href="<?= $BASE ?>mentions.php">Mentions légales</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>
            <a href="<?= $BASE ?>login.php">Espace client</a>
        </nav>

        <div class="reseau_sociaux">
            <a href="https://www.instagram.com/accounts/login/?next=%2F_dkbloom%2F&source=omni_redirect"
               target="_blank" rel="noopener" aria-label="Instagram">
                <!-- width/height fixent la taille pour ne pas “gonfler” le footer -->
                <img src="<?= $BASE ?>img/instagram_icon.png" alt="" width="24" height="24" loading="lazy">
            </a>

            <a href="https://www.tiktok.com/@_dkbloom" target="_blank" rel="noopener" aria-label="TikTok">
                <img src="<?= $BASE ?>img/tiktok.png" alt="" width="24" height="24" loading="lazy">
            </a>
        </div>
    </div>
</footer>

<script src="<?= $BASE ?>js/script.js" defer></script>
