<?php
// /site/pages/adresse_paiement.php VF?
session_start();

/* ===== 0) Accès ===== */
if (empty($_SESSION['per_id'])) {
    $_SESSION['message'] = "Veuillez vous connecter pour continuer.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

/* ===== 1) Bases de chemins (identiques à commande.php) ===== */
$dir       = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$PAGE_BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
$SITE_BASE = preg_replace('#pages/$#', '', $PAGE_BASE);

/* Détection checkout.php (à la racine du site ou dans /pages) */
$co_fs_main  = __DIR__ . '/../create_checkout.php';   // /site/checkout.php
$co_fs_pages = __DIR__ . '/create_checkout.php';      // /site/pages/checkout.php
if (is_file($co_fs_main)) {
    $CHECKOUT_URL = $SITE_BASE . 'create_checkout.php';
} elseif (is_file($co_fs_pages)) {
    $CHECKOUT_URL = $PAGE_BASE . 'create_heckout.php';
} else {
    $CHECKOUT_URL = $SITE_BASE . 'create_checkout.php'; // fallback
}

/* Détection API cart.php pour afficher le récap (si tu gardes l'API) */
$api_fs_main  = __DIR__ . '/../api/cart.php';
$api_fs_pages = __DIR__ . '/api/cart.php';
if (is_file($api_fs_main)) {
    $API_URL = $SITE_BASE . 'api/cart.php';
} elseif (is_file($api_fs_pages)) {
    $API_URL = $PAGE_BASE . 'api/cart.php';
} else {
    $API_URL = $SITE_BASE . 'api/cart.php';
}

$PLACEHOLDER = is_file(__DIR__ . '/img/placeholder.png')
    ? $PAGE_BASE . 'img/placeholder.png'
    : $SITE_BASE . 'img/placeholder.png';

/* ===== 2) DB + commande ouverte ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

/** Récupère la commande "en préparation" **/
$st = $pdo->prepare("SELECT COM_ID FROM COMMANDE
                     WHERE PER_ID=:p AND COM_STATUT='en préparation'
                     ORDER BY COM_ID DESC LIMIT 1");
$st->execute([':p' => $perId]);
$comId = (int)$st->fetchColumn();
if (!$comId) {
    $_SESSION['message'] = "Votre panier est vide.";
    header('Location: commande.php'); exit;
}

/* (Conservées si tu veux côté PHP) */
function norm_name(string $s): string {
    $s = strtolower(trim($s));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s); // accents -> ascii
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s); // retire ponctuation
    $s = preg_replace('/\s+/', ' ', $s);         // espaces multiples -> 1
    return trim($s);
}
function getProductImage(string $name): string {
    $k = norm_name($name);
    if (preg_match('/^(papier|emballage)s?\s+(blanc|gris|noir|violet)$/', $k, $m)) return 'emballage_' . $m[2] . '.PNG';
    if (preg_match('/^(papier|emballage)s?\s+rose(\s+pale|\s+pale)?$/', $k)) return 'emballage_rose.PNG';
    if (preg_match('/paillet+e?s?/', $k)) return 'paillette_argent.PNG';
    if (preg_match('/papillon/', $k)) return 'papillon_doree.PNG';
    if (preg_match('/^rose.*clair$/', $k)) return 'rose_claire.png';
    static $map = [
        '12 roses'=>'12Roses.png','bouquet 12'=>'12Roses.png',
        '20 roses'=>'20Roses.png','bouquet 20'=>'20Roses.png',
        '36 roses'=>'36Roses.png','bouquet 36'=>'36Roses.png',
        '50 roses'=>'50Roses.png','bouquet 50'=>'50Roses.png',
        '66 roses'=>'66Roses.png','bouquet 66'=>'66Roses.png',
        '100 roses'=>'100Roses.png','bouquet 100'=>'100Roses.png',
        'rose rouge'=>'rouge.png','rose rose'=>'rose.png','rose blanche'=>'rosesBlanche.png',
        'rose bleue'=>'bleu.png','rose noire'=>'noir.png',
        'mini ourson'=>'ours_blanc.PNG','deco anniv'=>'happybirthday.PNG',
        'decoration anniversaire'=>'happybirthday.PNG','baton coeur'=>'baton_coeur.PNG',
        'diamant'=>'diamant.PNG','couronne'=>'couronne.PNG',
        'lettre'=>'lettre.png','initiale'=>'lettre.png',
        'carte pour mot'=>'carte.PNG','carte'=>'carte.PNG',
        'panier vide'=>'panier_vide.png','panier rempli'=>'panier_rempli.png',
    ];
    if (isset($map[$k])) return $map[$k];
    if (strpos($k,'coffret') === 0) return 'coffret.png';
    return 'placeholder.png';
}

/* ===== 3) Règle métier : bouquet => emballage obligatoire ===== */
function getOrderType(PDO $pdo, int $comId): string {
    $st = $pdo->prepare("SELECT DISTINCT CP_TYPE_PRODUIT
                         FROM COMMANDE_PRODUIT WHERE COM_ID=:c");
    $st->execute([':c' => $comId]);
    $types = array_column($st->fetchAll(PDO::FETCH_NUM), 0);
    if (!$types) return 'none';
    if (count($types) > 1) return 'mixed';
    return $types[0];
}
function mustHavePackagingIfBouquet(PDO $pdo, int $comId): void {
    $type = getOrderType($pdo, $comId);
    if ($type !== 'bouquet') return;
    $st = $pdo->prepare("SELECT COUNT(*) FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c");
    $st->execute([':c' => $comId]);
    if ((int)$st->fetchColumn() < 1) {
        throw new RuntimeException("Votre bouquet nécessite un emballage. Merci d'en ajouter un.");
    }
}
try {
    mustHavePackagingIfBouquet($pdo, $comId);
} catch (RuntimeException $e) {
    $_SESSION['message'] = $e->getMessage();
    header('Location: commande.php'); exit;
}

/* ===== 4) CSRF pour POST → checkout.php ===== */
$_SESSION['csrf_checkout'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf_checkout'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DK Bloom — Adresses & paiement</title>

    <!-- tes CSS -->
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $PAGE_BASE ?>css/commande.css">

    <style>
        /* ===== mini styles layout ===== */
        .wrap { max-width: 1150px; margin-inline:auto; }
        .grid-pay { display:grid; gap:18px; grid-template-columns: 1fr; }
        @media (min-width: 980px){ .grid-pay { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 1180px){ .grid-pay { grid-template-columns: 1fr 1fr .85fr; } }

        .card { background:#fff; border-radius:10px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,.06); }
        form#checkout-form { display:grid; gap:18px; grid-template-columns: 1fr; }
        @media (min-width: 980px){ form#checkout-form { grid-template-columns: 1fr 1fr; } }

        .group { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
        .field label { font-weight:600; font-size:.95rem; }
        .field input, .field select { padding:10px; border:1px solid #ddd; border-radius:8px; font-size:1rem; }
        .muted { color:#666; font-size:.92rem; }
        .hr { height:1px; background:#eee; margin:12px 0; }
        .pay-group { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
        .pay-option { display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #ddd; border-radius:10px; }
        .btn-primary { display:inline-flex; align-items:center; justify-content:center; gap:10px;
            background:#8b0000; color:#fff; padding:12px 18px; border-radius:10px; text-decoration:none; font-weight:700; }
        .btn-primary[aria-disabled="true"] { opacity:.6; pointer-events:none; }
        .note { background:#fff8e5; border:1px solid #ffecb3; padding:10px 12px; border-radius:8px; }

        /* ===== mini-cart ===== */
        .mini-title { font-size:1.15rem; font-weight:800; margin-bottom:8px; }
        .mini-cart-list { display:flex; flex-direction:column; gap:10px; }
        .mini-row { display:grid; grid-template-columns: 56px 1fr auto; gap:10px; align-items:center; }
        .mini-thumb { width:56px; height:56px; border-radius:8px; object-fit:cover; background:#f7f7f7; }
        .mini-name { font-weight:600; }
        .mini-meta { font-size:.9rem; color:#666; }
        .mini-total { display:flex; justify-content:space-between; padding-top:10px; border-top:1px solid #eee; margin-top:10px; font-weight:700; }
        .mini-empty { color:#666; font-style:italic; }
    </style>

    <script>
        // Expose au JS
        window.CHECKOUT_URL = <?= json_encode($CHECKOUT_URL) ?>;
        window.API_URL      = <?= json_encode($API_URL) ?>;
        window.CSRF_CHECKOUT= <?= json_encode($CSRF) ?>;
        window.PLACEHOLDER  = <?= json_encode($PLACEHOLDER) ?>;

        // Dossier des images (pour le fallback)
        window.IMG_BASE     = <?= json_encode($SITE_BASE . 'img/') ?>;

        // ===== Helpers: normalisation + mapping nom → fichier image (fallback si l’API n’envoie pas d’URL) =====
        function normName(s){
            s = (s||'').toLowerCase().trim();
            const map = {'à':'a','â':'a','ä':'a','é':'e','è':'e','ê':'e','ë':'e','î':'i','ï':'i','ô':'o','ö':'o','ù':'u','û':'u','ü':'u','ç':'c'};
            s = s.replace(/[àâäéèêëîïôöùûüç]/g, m => map[m] || m);
            s = s.replace(/[^a-z0-9 ]+/g,' ').replace(/\s+/g,' ').trim();
            return s;
        }
        function getProductImageFile(name){
            const k = normName(name);

            // 1) Emballages
            if (/^(papier|emballage)s?\s+(blanc|gris|noir|violet)$/.test(k)) {
                const color = k.replace(/^(papier|emballage)s?\s+/, '');
                return `emballage_${color}.PNG`;
            }
            if (/^(papier|emballage)s?\s+rose(\s+pale|\s+pale)?$/.test(k)) {
                return 'emballage_rose.PNG';
            }

            // 2) Paillettes
            if (/paillet+e?s?/.test(k)) return 'paillette_argent.PNG';

            // 3) Papillon(s)
            if (/papillon/.test(k)) return 'papillon_doree.PNG';

            // 4) Cas particuliers roses
            if (/^rose.*clair$/.test(k)) return 'rose_claire.png';

            // 5) Table standard
            const map = {
                // Bouquets
                '12 roses':'12Roses.png','bouquet 12':'12Roses.png',
                '20 roses':'20Roses.png','bouquet 20':'20Roses.png',
                '36 roses':'36Roses.png','bouquet 36':'36Roses.png',
                '50 roses':'50Roses.png','bouquet 50':'50Roses.png',
                '66 roses':'66Roses.png','bouquet 66':'66Roses.png',
                '100 roses':'100Roses.png','bouquet 100':'100Roses.png',

                // Roses unitaires
                'rose rouge':'rouge.png',
                'rose rose':'rose.png',
                'rose blanche':'rosesBlanche.png',
                'rose bleue':'bleu.png',
                'rose noire':'noir.png',

                // Suppléments
                'mini ourson':'ours_blanc.PNG',
                'deco anniv':'happybirthday.PNG',
                'decoration anniversaire':'happybirthday.PNG',
                'baton coeur':'baton_coeur.PNG',
                'diamant':'diamant.PNG',
                'couronne':'couronne.PNG',
                'lettre':'lettre.png',
                'initiale':'lettre.png',
                'carte pour mot':'carte.PNG',
                'carte':'carte.PNG',

                // Paniers
                'panier vide':'panier_vide.png',
                'panier rempli':'panier_rempli.png',
            };
            if (map[k]) return map[k];

            // 6) Coffrets
            if (k.startsWith('coffret')) return 'coffret.png';

            return 'placeholder.png';
        }
        function imageUrlFromName(name){
            return window.IMG_BASE + getProductImageFile(name);
        }

        /* === Fallback robuste: variations d’extensions et sous-dossiers === */
        const IMG_SUBFOLDERS = ["", "roses/", "bouquets/", "emballages/", "supplements/", "coffrets/"];
        function buildImageCandidates(fileBase){
            const base = fileBase.replace(/\.(png|jpg|jpeg)$/i, '');
            const exts = ["png","PNG","jpg","JPG","jpeg","JPEG"];
            const files = new Set();

            files.add(fileBase);
            files.add(fileBase.toLowerCase());
            exts.forEach(ext => files.add(`${base}.${ext}`));

            const urls = [];
            files.forEach(fname => {
                IMG_SUBFOLDERS.forEach(sub => urls.push(window.IMG_BASE + sub + fname));
            });
            urls.push(window.PLACEHOLDER);
            return urls;
        }
        function tryNextImage(imgEl){
            try{
                const rest = JSON.parse(imgEl.dataset.srcs || "[]");
                if (rest.length){
                    const next = rest.shift();
                    imgEl.dataset.srcs = JSON.stringify(rest);
                    imgEl.src = next;
                } else {
                    imgEl.src = window.PLACEHOLDER;
                }
            }catch(e){
                imgEl.src = window.PLACEHOLDER;
            }
        }
    </script>
</head>

<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap" style="padding-top:var(--header-h,80px)">
    <h1 class="page-title">Adresses & paiement</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="grid-pay">
        <!-- ===== FORMULAIRE ===== -->
        <form id="checkout-form" autocomplete="on">
            <!-- Colonne 1 : FACTURATION -->
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

                <input type="hidden" id="bill_country" name="bill_country" value="CH">

                <div class="hr"></div>

                <div class="field">
                    <label class="pay-option" style="cursor:pointer">
                        <input type="checkbox" id="same_as_billing" checked>
                        Utiliser cette adresse aussi pour la livraison
                    </label>
                </div>
            </section>

            <!-- Colonne 2 : LIVRAISON + PAIEMENT -->
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
                </div>

                <input type="hidden" id="ship_country" name="ship_country" value="CH">

                <div class="hr"></div>

                <h2>Moyen de paiement</h2>
                <div class="pay-group" role="radiogroup" aria-label="Moyen de paiement">
                    <label class="pay-option">
                        <input type="radio" name="pay_method" value="card" checked>
                        Carte
                    </label>
                    <label class="pay-option" title="Bientôt">
                        <input type="radio" name="pay_method" value="twint" disabled>
                        TWINT
                    </label>
                    <label class="pay-option" title="Bientôt">
                        <input type="radio" name="pay_method" value="bank" disabled>
                        Revolut
                    </label>
                </div>

                <p class="muted">Le paiement est sécurisé. Vous serez redirigé(e) vers Stripe pour finaliser la transaction.</p>

                <div class="hr"></div>

                <button type="submit" id="btn-pay" class="btn-primary">Payer maintenant</button>
                <p id="form-msg" class="muted" role="status" style="margin-top:10px"></p>
            </section>
        </form>

        <!-- Colonne 3 : RÉCAP PANIER -->
        <aside class="card" id="mini-cart" aria-live="polite">
            <div class="mini-title">Récapitulatif</div>
            <div id="mini-cart-list" class="mini-cart-list">
                <div class="mini-empty">Chargement du panier…</div>
            </div>
            <div class="mini-total" id="mini-total" style="display:none">
                <span>Total</span><span id="mini-total-amount">0.00 CHF</span>
            </div>
        </aside>
    </div>

    <div class="note" style="margin-top:18px">
        Astuce : tu peux revenir au panier pour modifier les articles avant de payer.
        <br> Les commandes sont effectuées uniquement en Suisse.
        <a href="<?= $PAGE_BASE ?>commande.php">Retour au panier</a>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    /* ==========================
       1) Copier facturation → livraison
       ========================== */
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
        .forEach(id => document.getElementById(id)?.addEventListener('input', () => { if (same.checked) copyBillingToShipping(); }));
    setShippingDisabled(same.checked);

    /* ==========================
       2) Soumission → checkout.php
       ========================== */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        msg.textContent = 'Création du paiement en cours…';

        const fd = new FormData(form);
        fd.append('action', 'create_checkout');
        fd.append('same_as_billing', same.checked ? '1' : '0');
        fd.append('csrf', window.CSRF_CHECKOUT);

        try {
            const res  = await fetch(window.CHECKOUT_URL, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const text = await res.text();
            let data = null;
            try { data = JSON.parse(text); } catch (_) {}

            if (res.ok && data && data.ok && data.url) {
                window.location.href = data.url; return;
            }
            if (res.redirected) { window.location.href = res.url; return; }
            if (!res.ok) throw new Error(`HTTP ${res.status} — ${text.slice(0,200)}`);

            const m = text.match(/https:\/\/checkout\.stripe\.com\/pay\/[A-Za-z0-9_%\-]+/);
            if (m) { window.location.href = m[0]; return; }

            throw new Error('Réponse inattendue de create_checkout.php');
        } catch (err) {
            console.error(err);
            msg.textContent = "Oups, impossible de démarrer le paiement. Réessaie ou contacte-nous.";
        }
    });

    /* ==========================
       3) Mini-récap du panier (lecture seule)
          → récupère via API cart.php ; si pas d’URL d’image, on déduit depuis le nom,
            puis on teste plusieurs variantes (extensions & sous-dossiers).
       ========================== */
    async function renderMiniCart(){
        const box = document.getElementById('mini-cart-list');
        const totalBox = document.getElementById('mini-total');
        const totalAmt = document.getElementById('mini-total-amount');

        try {
            const url = window.API_URL + '?action=list';
            const res = await fetch(url, { credentials:'same-origin' });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch(e) { throw new Error('Réponse non JSON du panier'); }

            if (!res.ok || data.ok === false) throw new Error(data.error || data.msg || `HTTP ${res.status}`);

            const items = data.items || data.lines || [];
            if (!items.length) {
                box.innerHTML = '<div class="mini-empty">Votre panier est vide.</div>';
                totalBox.style.display = 'none';
                return;
            }

            let html = '';
            let subtotal = 0;

            items.forEach(it => {
                const name  = it.name || it.pro_nom || 'Article';
                const qty   = Number(it.qty ?? it.quantite ?? 1);
                const price = Number(it.price ?? it.prix ?? it.unit_price ?? 0);
                const line  = qty * price;
                subtotal += line;

                // 1) URL de l'API si fournie
                let apiUrl = it.img || it.image || '';

                // 2) Fichier deviné depuis le nom
                const guessFile = getProductImageFile(name);

                // 3) Pile de candidates: API (si existe) puis variantes locales puis placeholder
                const candidates = [];
                if (apiUrl) candidates.push(apiUrl);
                buildImageCandidates(guessFile).forEach(u => candidates.push(u));

                const initialSrc = candidates.shift();
                const restJson   = JSON.stringify(candidates).replace(/'/g, '&#39;');

                html += `
        <div class="mini-row">
            <img
              src="${initialSrc}"
              alt=""
              class="mini-thumb"
              onerror="tryNextImage(this)"
              data-srcs='${restJson}'
            >
            <div>
                <div class="mini-name">${name}</div>
                <div class="mini-meta">x ${qty} · ${price.toFixed(2)} CHF</div>
            </div>
            <div class="mini-meta">${line.toFixed(2)} CHF</div>
        </div>`;
            });

            box.innerHTML = html;
            totalAmt.textContent = subtotal.toFixed(2) + ' CHF';
            totalBox.style.display = '';
        } catch (err) {
            console.error(err);
            document.getElementById('mini-cart-list').innerHTML =
                '<div class="mini-empty">Impossible de charger le récap pour le moment.</div>';
            document.getElementById('mini-total').style.display = 'none';
        }
    }
    renderMiniCart();
</script>
</body>
</html>
