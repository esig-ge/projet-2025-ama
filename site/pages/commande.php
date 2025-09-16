<?php
// /site/pages/commande.php
session_start();

// Base URL (robuste)
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

if (empty($_SESSION['per_id'])) {
    $_SESSION['message'] = "Veuillez vous connecter pour voir votre commande.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)($_SESSION['per_id'] ?? 0);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// 1) Dernière commande "en préparation"
$sql = "SELECT COM_ID, COM_DATE
        FROM COMMANDE
        WHERE PER_ID = :per AND COM_STATUT = 'en préparation'
        ORDER BY COM_ID DESC
        LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute([':per' => $perId]);
$com = $st->fetch(PDO::FETCH_ASSOC);

$lines = [];
$subtotal = 0.0;

if ($com) {
    $comId = (int)$com['COM_ID'];
    $sql = "SELECT cp.PRO_ID,
                   cp.CP_QTE_COMMANDEE,
                   cp.CP_TYPE_PRODUIT,
                   p.PRO_NOM,
                   p.PRO_PRIX
            FROM COMMANDE_PRODUIT cp
            JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
            WHERE cp.COM_ID = :com
            ORDER BY p.PRO_NOM";
    $st = $pdo->prepare($sql);
    $st->execute([':com' => $comId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $L) {
        $subtotal += (float)$L['PRO_PRIX'] * (int)$L['CP_QTE_COMMANDEE'];
    }
}

// 2) Frais de livraison (placeholder — ajuste selon ta logique)
$shipping = 0.00; // p.ex. 4.90 si Standard, 9.90 si Express — à relier au choix radio + POST
$total = $subtotal + $shipping;

// Helper image (cherche /pages/img/produits/ID.jpg sinon fallback)
function productImg(string $base, int $id): string {
    $rel = "img/produits/{$id}.jpg";
    $fs  = __DIR__ . "/{$rel}";
    if (is_file($fs)) return $base . $rel;
    return $base . "img/placeholder.jpg";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Ma commande</title>

    <!-- CSS global -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">

    <!-- CSS spécifique commande -->
    <link rel="stylesheet" href="<?= $BASE ?>css/commande.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap">
    <h1 class="page-title">Récapitulatif de ma commande</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (!$com): ?>
        <div class="card empty">
            <p><strong>Votre panier est vide</strong><br>Ajoutez des bouquets pour commencer.</p>
        </div>
        <p style="text-align:center">
            <a class="btn-primary" href="<?= $BASE ?>interface_selection_produit.php">Parcourir le catalogue</a>
        </p>
    <?php else: ?>
        <div class="grid">
            <!-- Colonne gauche : liste des articles -->
            <section class="card">
                <div style="padding:16px 16px 0">
                    <h2 class="sr-only">Articles</h2>
                    <div style="color:#6b6b6b; font-size:14px;">
                        Commande #<?= (int)$com['COM_ID'] ?> du <?= htmlspecialchars($com['COM_DATE']) ?>
                    </div>
                </div>

                <?php foreach ($lines as $L):
                    $id = (int)$L['PRO_ID'];
                    $q  = (int)$L['CP_QTE_COMMANDEE'];
                    $pu = (float)$L['PRO_PRIX'];
                    $lt = $pu * $q;
                    $img = productImg($BASE, $id);
                    ?>
                    <div class="cart-row">
                        <img class="cart-img" src="<?= htmlspecialchars($img) ?>" alt="">
                        <div class="cart-name">
                            <?= htmlspecialchars($L['PRO_NOM']) ?><br>
                            <span class="item-sub"><?= htmlspecialchars($L['CP_TYPE_PRODUIT']) ?> &middot; Qté <?= $q ?></span>
                        </div>
                        <div class="cart-unit"><?= number_format($pu, 2, '.', ' ') ?> CHF</div>
                        <div class="cart-total"><?= number_format($lt, 2, '.', ' ') ?> CHF</div>
                    </div>
                <?php endforeach; ?>

                <!-- Choix livraison -->
                <div style="padding:16px">
                    <fieldset class="full group">
                        <legend>Type de livraison <span class="req">*</span></legend>
                        <div class="options-row" style="display:flex; gap:16px; flex-wrap:wrap; margin-top:6px;">
                            <label class="opt"><input type="radio" name="livraison" value="standard" checked> <span>Standard (48h)</span></label>
                            <label class="opt"><input type="radio" name="livraison" value="express"> <span>Express (24h)</span></label>
                            <label class="opt"><input type="radio" name="livraison" value="retrait"> <span>Retrait en boutique</span></label>
                        </div>
                    </fieldset>

                    <div class="actions" style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                        <a class="btn-ghost" href="<?= $BASE ?>interface_selection_produit.php">Continuer mes achats</a>
                        <a class="btn-ghost" href="<?= $BASE ?>interface_supplement.php">Ajouter des suppléments</a>
                    </div>
                </div>
            </section>

            <!-- Colonne droite : récap -->
            <aside class="card summary">
                <h2 class="sr-only">Récapitulatif</h2>

                <div class="sum-row">
                    <span>Produits</span>
                    <span><?= number_format($subtotal, 2, '.', ' ') ?> CHF</span>
                </div>
                <div class="sum-row">
                    <span>Livraison</span>
                    <span><?= number_format($shipping, 2, '.', ' ') ?> CHF</span>
                </div>

                <div class="sum-total">
                    <span>Total</span>
                    <span><?= number_format($total, 2, '.', ' ') ?> CHF</span>
                </div>

                <a id="btn-checkout"
                   class="btn-primary"
                   href="<?= $BASE ?>adresse_paiement.php"
                   aria-disabled="<?= ($subtotal <= 0 ? 'true' : 'false') ?>">
                    Valider ma commande
                </a>

                <div class="coupon">
                    <input type="text" placeholder="Code promo (optionnel)" disabled>
                    <button class="btn-ghost" disabled>Appliquer</button>
                </div>

                <div class="help">
                    <ul>
                        <li>Expédition en 24–48h</li>
                        <li>Frais de port offerts dès 50 CHF</li>
                        <li>Paiement sécurisé</li>
                    </ul>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
