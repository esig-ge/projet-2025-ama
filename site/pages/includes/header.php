

<link rel="stylesheet" href="../css/style_header_footer.css">

<header class="site-header">
    <div class="header">

        <div class="h">
            <p class="titre">DkBloom</p>
            <nav>
                <ul class="menu">
                    <li><a href="../index.php">Accueil</a></li>
                    <li><a href="">A propos</a></li>
                    <li><a href="">Catalogue</a></li>
                    <li><a href="">Contact</a></li>
                    <li><a href="../inscription.php">S'inscrire</a></li>
                    <li><a  href="../interface_connexion.php">Se connecter</a></li>
                </ul>
            </nav>
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

<!--<a href="../inscription.php">S'inscrire</a>
                <a href="../interface_connexion.php">Se connecter</a>-->

