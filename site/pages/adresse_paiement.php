<?php
// /site/pages/adresse_paiement.php
session_start();

/* ===== 1) Bases de chemins (identiques à commande.php) ===== */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);

/* Détection checkout.php (à la racine du site ou dans /pages) */
$co_fs_main  = __DIR__ . '/../checkout.php';   // /site/checkout.php
$co_fs_pages = __DIR__ . '/checkout.php';      // /site/pages/checkout.php
if (is_file($co_fs_main)) {
    $CHECKOUT_URL = $SITE_BASE . 'checkout.php';
} elseif (is_file($co_fs_pages)) {
    $CHECKOUT_URL = $PAGE_BASE . 'checkout.php';
} else {
    $CHECKOUT_URL = $SITE_BASE . 'checkout.php'; // fallback
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Adresses & paiement</title>

    <!-- tes CSS globales -->
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/commande.css">

    <style>
        /* mini styles pour un layout propre */
        .wrap { max-width: 1100px; margin-inline:auto; }
        .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:18px; }
        .card { background:#fff; border-radius:10px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,.06); }
        .group { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
        .field label { font-weight:600; font-size:.95rem; }
        .field input, .field select { padding:10px; border:1px solid #ddd; border-radius:8px; font-size:1rem; }
        .muted { color:#666; font-size:.92rem; }
        .hr { height:1px; background:#eee; margin:12px 0; }
        .pay-group { display:flex; gap:14px; align-items:center; }
        .pay-option { display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #ddd; border-radius:10px; }
        .btn-primary { display:inline-flex; align-items:center; justify-content:center; gap:10px;
            background:#8b0000; color:#fff; padding:12px 18px; border-radius:10px; text-decoration:none; font-weight:700; }
        .btn-primary[aria-disabled="true"] { opacity:.6; pointer-events:none; }
        .note { background:#fff8e5; border:1px solid #ffecb3; padding:10px 12px; border-radius:8px; }
    </style>

    <script>
        // Expose l’URL de checkout au JS
        window.CHECKOUT_URL = <?= json_encode($CHECKOUT_URL) ?>;
    </script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap" style="padding-top:var(--header-h,80px)">
    <h1 class="page-title">Adresses & paiement</h1>

    <form id="checkout-form" class="grid-2" autocomplete="on">
        <!-- ============ Colonne 1 : FACTURATION ============ -->
        <section class="card" aria-labelledby="bill-title">
            <h2 id="bill-title">Adresse de facturation</h2>
            <div class="group">
                <div class="field">
                    <label for="bill_lastname">Nom</label>
                    <input id="bill_lastname" name="bill_lastname" required>
                </div>
                <div class="field">
                    <label for="bill_firstname">Prénom</label>
                    <input id="bill_firstname" name="bill_firstname" required>
                </div>
            </div>

            <div class="field">
                <label for="bill_email">Email</label>
                <input id="bill_email" name="bill_email" type="email" required>
            </div>

            <div class="field">
                <label for="bill_phone">Téléphone</label>
                <input id="bill_phone" name="bill_phone" type="tel" required>
            </div>

            <div class="field">
                <label for="bill_address">Adresse</label>
                <input id="bill_address" name="bill_address" required placeholder="Rue et n°">
            </div>

            <div class="group">
                <div class="field">
                    <label for="bill_postal">NPA</label>
                    <input id="bill_postal" name="bill_postal" required>
                </div>
                <div class="field">
                    <label for="bill_city">Ville</label>
                    <input id="bill_city" name="bill_city" required>
                </div>
            </div>

            <div class="field">
                <label for="bill_country">Pays</label>
                <select id="bill_country" name="bill_country" required>
                    <option value="CH" selected>Suisse</option>
                    <option value="FR">France</option>
                    <option value="IT">Italie</option>
                    <option value="DE">Allemagne</option>
                </select>
            </div>

            <div class="hr"></div>

            <div class="field">
                <label class="pay-option" style="cursor:pointer">
                    <input type="checkbox" id="same_as_billing" checked>
                    Utiliser cette adresse aussi pour la livraison
                </label>
            </div>
        </section>

        <!-- ============ Colonne 2 : LIVRAISON + PAIEMENT ============ -->
        <section class="card" aria-labelledby="ship-title">
            <h2 id="ship-title">Adresse de livraison</h2>

            <div id="ship-fields">
                <div class="group">
                    <div class="field">
                        <label for="ship_lastname">Nom</label>
                        <input id="ship_lastname" name="ship_lastname" required>
                    </div>
                    <div class="field">
                        <label for="ship_firstname">Prénom</label>
                        <input id="ship_firstname" name="ship_firstname" required>
                    </div>
                </div>

                <div class="field">
                    <label for="ship_phone">Téléphone</label>
                    <input id="ship_phone" name="ship_phone" type="tel" required>
                </div>

                <div class="field">
                    <label for="ship_address">Adresse</label>
                    <input id="ship_address" name="ship_address" required placeholder="Rue et n°">
                </div>

                <div class="group">
                    <div class="field">
                        <label for="ship_postal">NPA</label>
                        <input id="ship_postal" name="ship_postal" required>
                    </div>
                    <div class="field">
                        <label for="ship_city">Ville</label>
                        <input id="ship_city" name="ship_city" required>
                    </div>
                </div>

                <div class="field">
                    <label for="ship_country">Pays</label>
                    <select id="ship_country" name="ship_country" required>
                        <option value="CH" selected>Suisse</option>
                        <option value="FR">France</option>
                        <option value="IT">Italie</option>
                        <option value="DE">Allemagne</option>
                    </select>
                </div>
            </div>

            <div class="hr"></div>

            <h2>Moyen de paiement</h2>
            <div class="pay-group" role="radiogroup" aria-label="Moyen de paiement">
                <label class="pay-option">
                    <input type="radio" name="pay_method" value="card" checked>
                    Carte (Stripe)
                </label>
                <label class="pay-option" title="Bientôt">
                    <input type="radio" name="pay_method" value="twint" disabled>
                    TWINT (bientôt)
                </label>
                <label class="pay-option" title="Bientôt">
                    <input type="radio" name="pay_method" value="bank" disabled>
                    Virement (bientôt)
                </label>
            </div>

            <p class="muted">Le paiement est sécurisé. Vous serez redirigé(e) vers Stripe pour finaliser la transaction.</p>

            <div class="hr"></div>

            <button type="submit" id="btn-pay" class="btn-primary">
                Payer maintenant
            </button>

            <p id="form-msg" class="muted" role="status" style="margin-top:10px"></p>
        </section>
    </form>

    <div class="note" style="margin-top:18px">
        Astuce : si tu veux, tu peux revenir au panier pour modifier les articles avant de payer.
        <a href="<?= $PAGE_BASE ?>commande.php">Retour au panier</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    /* ===== 2) Logique JS : copier la facturation -> livraison, validation min, et POST vers checkout.php ===== */

    const form = document.getElementById('checkout-form');
    const same = document.getElementById('same_as_billing');
    const shipFields = document.querySelectorAll('#ship-fields input, #ship-fields select');
    const msg  = document.getElementById('form-msg');

    function copyBillingToShipping() {
        const map = [
            ['bill_lastname','ship_lastname'],
            ['bill_firstname','ship_firstname'],
            ['bill_phone','ship_phone'],
            ['bill_address','ship_address'],
            ['bill_postal','ship_postal'],
            ['bill_city','ship_city'],
            ['bill_country','ship_country'],
        ];
        map.forEach(([b,s]) => {
            const bv = document.getElementById(b)?.value ?? '';
            const sf = document.getElementById(s);
            if (sf) sf.value = bv;
        });
    }

    function setShippingDisabled(disabled) {
        shipFields.forEach(el => {
            el.disabled = disabled;
            if (el.required) el.dataset.wasRequired = '1';
            if (disabled) el.required = false; else if (el.dataset.wasRequired) el.required = true;
        });
        if (disabled) copyBillingToShipping();
    }

    same.addEventListener('change', () => setShippingDisabled(same.checked));
    ['bill_lastname','bill_firstname','bill_phone','bill_address','bill_postal','bill_city','bill_country']
        .forEach(id => document.getElementById(id).addEventListener('input', () => { if (same.checked) copyBillingToShipping(); }));

    // état initial
    setShippingDisabled(same.checked);

    // Soumission -> appelle checkout.php (POST) et redirige vers l’URL Stripe renvoyée
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        msg.textContent = 'Création du paiement en cours…';

        // Construit notre charge utile (x-www-form-urlencoded)
        const fd = new FormData(form);
        fd.append('action', 'create_checkout'); // lis ceci dans checkout.php
        fd.append('same_as_billing', same.checked ? '1' : '0');

        try {
            const res  = await fetch(window.CHECKOUT_URL, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const text = await res.text();
            // Deux comportements supportés:
            // 1) checkout.php renvoie {ok:true,url:"https://checkout.stripe.com/..."}
            // 2) checkout.php fait un header('Location: …') et renvoie la page (on suit)
            let data = null;
            try { data = JSON.parse(text); } catch (_) { /* pas JSON -> on laisse le navigateur suivre */ }

            if (res.ok && data && data.ok && data.url) {
                window.location.href = data.url; // redirection Stripe
                return;
            }
            if (res.redirected) {
                window.location.href = res.url;  // si le PHP a déjà redirigé
                return;
            }
            if (!res.ok) throw new Error(`HTTP ${res.status} — ${text.slice(0,200)}`);

            // Si on arrive ici et qu’il n’y a pas eu de redirect, on tente quand même:
            const m = text.match(/https:\/\/checkout\.stripe\.com\/pay\/[A-Za-z0-9_%\-]+/);
            if (m) { window.location.href = m[0]; return; }

            throw new Error('Réponse inattendue de checkout.php');
        } catch (err) {
            console.error(err);
            msg.textContent = "Oups, impossible de démarrer le paiement. Réessaie ou contacte-nous.";
        }
    });
</script>
</body>
</html>
