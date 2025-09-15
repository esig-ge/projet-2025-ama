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

/* ===== A) SUPPRESSION D’UN ARTICLE ===== */
if (($_POST['action'] ?? '') === 'del') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    $delPro = (int)($_POST['pro_id'] ?? 0);

    if ($delCom > 0 && $delPro > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE
                              WHERE COM_ID = :c AND PER_ID = :p AND COM_STATUT = 'en préparation' LIMIT 1");
        $chk->execute([':c' => $delCom, ':p' => $perId]);
        if ($chk->fetchColumn()) {
            $del = $pdo->prepare("DELETE FROM COMMANDE_PRODUIT
                                  WHERE COM_ID = :c AND PRO_ID = :p LIMIT 1");
            $del->execute([':c' => $delCom, ':p' => $delPro]);
            $_SESSION['message'] = "Article supprimé de votre commande.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Requête invalide.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* ===== B) CHARGEMENT DE LA COMMANDE + LIGNES ===== */
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
$comId = 0;

if ($com) {
    $comId = (int)$com['COM_ID'];
    $sql = "SELECT cp.PRO_ID, cp.CP_QTE_COMMANDEE, cp.CP_TYPE_PRODUIT,
                   p.PRO_NOM, p.PRO_PRIX
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

$hasOrder  = (bool)$com;
$hasItems  = $hasOrder && !empty($lines);

// 2) Frais de livraison (placeholder)
$shipping = 0.00;
$total = $subtotal + $shipping;

// Helper image
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
    <style>
        /* Mise en page + états */
        .grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
        @media (max-width:980px){.grid{grid-template-columns:1fr}}
        .card{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.06);overflow:hidden}
        .card.disabled{opacity:.6;filter:grayscale(.5)}
        .muted{color:#777}
        .cart-row{display:grid;grid-template-columns:64px 1fr auto auto auto;gap:12px;align-items:center;padding:12px 16px;border-top:1px solid #eee}
        .cart-img{width:64px;height:64px;object-fit:cover;border-radius:8px}
        .cart-name{font-weight:600}
        .item-sub{font-weight:400;font-size:12px;color:#777}
        .trash-form{margin:0}
        .trash-btn{background:transparent;border:0;cursor:pointer;font-size:18px;line-height:1;color:#b70f0f;padding:6px;border-radius:8px}
        .trash-btn:hover{background:#b70f0f10}
        .flash{margin:12px auto;max-width:920px;background:#f6fff6;color:#0a6b0a;border:1px solid #bfe6bf;padding:10px 12px;border-radius:10px}
        .card.empty{text-align:center;padding:24px}
        .section-title{margin:18px 0 10px 0;font-size:18px;font-weight:700}
        .shipping-block{margin-top:20px}
        .shipping-block .inner{padding:16px}
        .btn-primary[aria-disabled="true"]{pointer-events:none;opacity:.6}
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="wrap">
    <h1 class="page-title">Récapitulatif de ma commande</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="flash" role="status"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="grid">
        <!-- Colonne gauche : Panier -->
        <section class="card">
            <div style="padding:16px 16px 0">
                <h2 class="sr-only">Articles</h2>
                <?php if ($hasOrder): ?>
                    <div class="muted" style="font-size:14px;">
                        Commande #<?= (int)$com['COM_ID'] ?> du <?= htmlspecialchars($com['COM_DATE']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$hasOrder || !$hasItems): ?>
                <div class="card empty" style="box-shadow:none;background:transparent">
                    <p><strong>Le panier est vide</strong><br><span class="muted">Aucun article dans le panier.</span></p>
                </div>
                <p style="text-align:center; padding:0 0 16px">
                    <a class="btn-primary" href="<?= $BASE ?>interface_catalogue_bouquet.php">Parcourir le catalogue</a>
                </p>
            <?php else: ?>
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
                            <span class="item-sub"><?= htmlspecialchars($L['CP_TYPE_PRODUIT']) ?> · Qté <?= $q ?></span>
                        </div>
                        <div class="cart-unit"><?= number_format($pu, 2, '.', ' ') ?> CHF</div>
                        <div class="cart-total"><?= number_format($lt, 2, '.', ' ') ?> CHF</div>

                        <form class="trash-form" method="post" action="<?= $BASE ?>commande.php" onsubmit="return confirm('Supprimer cet article ?');">
                            <input type="hidden" name="action" value="del">
                            <input type="hidden" name="com_id" value="<?= $comId ?>">
                            <input type="hidden" name="pro_id" value="<?= $id ?>">
                            <button class="trash-btn" aria-label="Supprimer cet article">🗑️</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Colonne droite : Récap -->
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

    <!-- ===== Bloc LIVRAISON séparé (sous panier + récap) ===== -->
    <?php
    $disableShipping = ($subtotal <= 0); // griser + désactiver si panier vide
    $disabledAttr = $disableShipping ? 'disabled' : '';
    $disabledClass = $disableShipping ? ' disabled' : '';
    ?>
    <section class="card shipping-block<?= $disabledClass ?>">
        <div class="inner">
            <div class="section-title">Type de livraison</div>
            <fieldset class="full group">
                <div class="options-row" style="display:flex; gap:16px; flex-wrap:wrap; margin-top:6px;">
                    <label class="opt"><input type="radio" name="livraison" value="standard" <?= $disabledAttr ?> checked> <span>Standard (48h)</span></label>
                    <label class="opt"><input type="radio" name="livraison" value="express"  <?= $disabledAttr ?>> <span>Express (24h)</span></label>
                    <label class="opt"><input type="radio" name="livraison" value="retrait"  <?= $disabledAttr ?>> <span>Retrait en boutique</span></label>
                </div>
            </fieldset>

            <div class="actions" style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                <a class="btn-ghost" href="<?= $BASE ?>interface_catalogue_bouquet.php">Continuer mes achats</a>
                <a class="btn-ghost" href="<?= $BASE ?>interface_supplement.php">Ajouter des suppléments</a>
            </div>

            <?php if ($disableShipping): ?>
                <p class="muted" style="margin-top:10px">Le panier est vide : choisissez des articles pour sélectionner un mode de livraison.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
