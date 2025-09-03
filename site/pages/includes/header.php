

<link rel="stylesheet" href="css/squeletteIndex.css">

<header class="site-header">
    <div class="header">

        <div class="h">
            <p class="titre">DkBloom</p>
            <nav>
                <ul class="menu">
                    <a href="index.php">Accueil</a>
                    <a href="">A propos</a>
                    <a href="interface_selection_produit.php">Catalogue</a>
                    <a href="">Contact</a>
                    <a href="inscription.php">S'inscrire</a>
                    <a  href="interface_connexion.php">Se connecter</a>
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

