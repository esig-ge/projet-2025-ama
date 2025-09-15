<?php
// Base URL (avec slash final)
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Fleurs</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleFleurUnique.css">

    <!-- Expose BASE + API_URL au JS -->
    <script>
        window.DKBASE  = <?= json_encode($BASE) ?>;
        window.API_URL = <?= json_encode($BASE . 'api/cart.php') ?>;
    </script>

    <!-- JS panier (doit exposer window.addToCart) -->
    <script src="<?= $BASE ?>js/commande.js" defer></script>

    <!-- Pont fleur -> addToCart -->
    <script>
        // Appelé au clic sur "Sélectionner"
        function selectRose(btn){
            const box = btn.closest('.produit-info');
            if(!box){ return; }

            // Couleur (radio)
            const selected = box.querySelector('input.color-radio:checked');
            if(!selected){
                alert("Choisis une couleur de rose.");
                return;
            }
            const proId = selected.dataset.proId;
            if(!proId){
                alert("Produit introuvable pour cette couleur.");
                return;
            }

            // Quantité
            const qtyInput = box.querySelector('.qty');
            const qty = parseInt((qtyInput && qtyInput.value) ? qtyInput.value : "1", 10);
            if(!qty || qty < 1){
                alert("Quantité invalide.");
                return;
            }

            // (1) pro_id (hidden)
            let hidPro = box.querySelector('input[name="pro_id"]');
            if(!hidPro){
                hidPro = document.createElement('input');
                hidPro.type = 'hidden';
                hidPro.name = 'pro_id';
                box.appendChild(hidPro);
            }
            hidPro.value = proId;

            // (2) type=fleur (hidden)
            let hidType = box.querySelector('input[name="type"]');
            if(!hidType){
                hidType = document.createElement('input');
                hidType.type = 'hidden';
                hidType.name = 'type';
                box.appendChild(hidType);
            }
            hidType.value = 'fleur';

            // (3) couleur (hidden) — utile pour log/affichage
            let hidColor = box.querySelector('input[name="couleur"]');
            if(!hidColor){
                hidColor = document.createElement('input');
                hidColor.type = 'hidden';
                hidColor.name = 'couleur';
                box.appendChild(hidColor);
            }
            // valeurs possibles: rouge, roseC, rose, blanc, bleu, noir (selon tes IDs)
            hidColor.value = (selected.id || '').replace('c-','');

            // (4) transmettre la quantité via data-* si commande.js l'utilise
            btn.dataset.qty = String(qty);

            // Ajout au panier
            if (typeof window.addToCart === 'function') {
                window.addToCart(proId, btn);
            } else {
                console.error('addToCart() introuvable. Vérifie js/commande.js');
                alert('Impossible d’ajouter au panier (script panier manquant).');
            }
        }
    </script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <section class="catalogue">
        <div class="produit">
            <div class="produit-info">
                <h3 class="product-title">La rose</h3>
                <p class="product-desc">
                    Elle est le symbole d’un amour né au premier regard et incarne l’unicité.
                </p>

                <!-- Radios (portent l'ID produit + nom + image) -->
                <input type="radio" id="c-rouge"  name="rose-color" class="color-radio"
                       data-pro-id="1" data-name="Rose rouge"       data-img="<?= $BASE ?>img/rouge.png" checked>

                <input type="radio" id="c-roseC" name="rose-color" class="color-radio"
                       data-pro-id="2" data-name="Rose claire"      data-img="<?= $BASE ?>img/rose_claire.png">

                <input type="radio" id="c-rose"  name="rose-color" class="color-radio"
                       data-pro-id="3" data-name="Rose rose"        data-img="<?= $BASE ?>img/rose.png">

                <input type="radio" id="c-blanc" name="rose-color" class="color-radio"
                       data-pro-id="4" data-name="Rose blanche"     data-img="<?= $BASE ?>img/rosesBlanche.png">

                <input type="radio" id="c-bleu"  name="rose-color" class="color-radio"
                       data-pro-id="5" data-name="Rose bleue"       data-img="<?= $BASE ?>img/bleu.png">

                <input type="radio" id="c-noir"  name="rose-color" class="color-radio"
                       data-pro-id="6" data-name="Rose noire"       data-img="<?= $BASE ?>img/noir.png">

                <!-- Zone image -->
                <div class="rose">
                    <img src="<?= $BASE ?>img/rouge.png"        class="img-rose rouge"    alt="Rose rouge"    width="500">
                    <img src="<?= $BASE ?>img/rose_claire.png"  class="img-rose roseC"    alt="Rose claire"   width="500">
                    <img src="<?= $BASE ?>img/rose.png"         class="img-rose rose"     alt="Rose rose"     width="500">
                    <img src="<?= $BASE ?>img/rosesBlanche.png" class="img-rose blanche"  alt="Rose blanche"  width="500">
                    <img src="<?= $BASE ?>img/bleu.png"         class="img-rose bleue"    alt="Rose bleue"    width="500">
                    <img src="<?= $BASE ?>img/noir.png"         class="img-rose noire"    alt="Rose noire"    width="500">
                </div>

                <!-- Pastilles (labels) -->
                <fieldset class="swatches" aria-label="Couleur de la rose">
                    <label class="swatch" for="c-rouge" title="Rouge">
                        <span style="--swatch:red"></span>
                    </label>
                    <label class="swatch" for="c-roseC" title="Rose claire">
                        <span style="--swatch:#ffa0c4"></span>
                    </label>
                    <label class="swatch" for="c-rose" title="Rose">
                        <span style="--swatch:pink"></span>
                    </label>
                    <label class="swatch" for="c-blanc" title="Blanc">
                        <span style="--swatch:#e9e9e9"></span>
                    </label>
                    <label class="swatch" for="c-bleu" title="Bleu">
                        <span style="--swatch:#0418a5"></span>
                    </label>
                    <label class="swatch" for="c-noir" title="Noir">
                        <span style="--swatch:#111"></span>
                    </label>
                </fieldset>

                <input type="number" class="qty" name="qty" min="1" max="99" step="1" value="1" inputmode="numeric">

                <!-- Sélectionner = ajoute au panier la radio cochée -->
                <button class="btn" type="button" onclick="selectRose(this)">Sélectionner</button>
            </div>
        </div>

        <div class="btn_accueil">
            <a href="<?= $BASE ?>interface_selection_produit.php" class="button">Retour</a>
            <a href="<?= $BASE ?>interface_supplement.php" class="button">Suivant</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
