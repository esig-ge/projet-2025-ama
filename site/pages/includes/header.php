

<link rel="stylesheet" href="../css/style_header_footer.css">

<header class="site-header">
    <div class="header">
        <!-- Bouton hamburger -->
        <button class="hamburger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="nav-menu">
            <span></span><span></span><span></span>
        </button>

        <p class="titre">DkBloom</p>

        <nav id="nav-menu" class="nav">
            <button class="close-menu" aria-label="Fermer le menu">&times;</button>
            <ul class="menu">
                <li><a href="../index.php">Accueil</a></li>
                <li><a href="">A propos</a></li>
                <li><a href="">Catalogue</a></li>
                <li><a href="">Contact</a></li>
                <li><a href="../inscription.php">S'inscrire</a></li>
                <li><a href="../interface_connexion.php">Se connecter</a></li>
            </ul>
        </nav>

        <div class="icon">
            <a href="index.php">
                <img src="../img/iconInscrire-removebg-preview.png" alt="inscrire" width="80">
                <img src="../img/iconLoupe-removebg-preview.png" alt="recherche" width="50">
            </a>
        </div>
    </div>
</header>
<script>
    const btn      = document.querySelector('.hamburger');
    const menu     = document.querySelector('.menu');
    const closeBtn = document.querySelector('.close-menu'); // si présent

    btn.addEventListener('click', () => {
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        menu.classList.toggle('active', !open);
        if (closeBtn) closeBtn.classList.toggle('active', !open);
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            btn.setAttribute('aria-expanded', 'false');
            menu.classList.remove('active');
            closeBtn.classList.remove('active');
        });
    }
</script>


</script>
