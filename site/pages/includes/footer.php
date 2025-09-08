<?php if (!isset($BASE)) { $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; } ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <p class="footer-left">© <?= date('Y') ?> DK Bloom. Tous droits réservés.</p>

        <nav class="footer-nav">
            <a href="<?= $BASE ?>mentions.php">Mentions légales</a>
            <a href="<?= $BASE ?>contact.php">Contact</a>
            <a href="<?= $BASE ?>login.php">Espace client</a>
        </nav>

        <div class="reseau_sociaux">
            <a href="https://www.instagram.com/accounts/login/?next=%2F_dkbloom%2F&source=omni_redirect"
               target="_blank" rel="noopener" aria-label="Instagram">
                <!-- ⚠️ si ton fichier s'appelle 'instagram.png', change le nom ici -->
                <img src="<?= $BASE ?>img/instagram_icon.png" alt="">
            </a>
            <a href="https://www.tiktok.com/@_dkbloom" target="_blank" rel="noopener" aria-label="TikTok">
                <img src="<?= $BASE ?>img/tiktok.png" alt="">
            </a>
        </div>
    </div>
</footer>
<script src="<?= $BASE ?>js/script.js" defer></script>
