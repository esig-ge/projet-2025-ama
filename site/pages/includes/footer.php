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
            <a href="<?= $BASE ?>mentions.php"> Mentions légales </a>
            <a href="<?= $BASE ?>contact.php"> Contact </a>
            <a href="<?= $BASE ?>login.php"> Espace client </a>
        </nav>
    </div>
</footer>

<!-- JS principal -->
<script src="<?= $BASE ?>js/script.js" defer></script>
