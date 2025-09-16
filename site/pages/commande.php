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

/* ====== Utils: normalisation + image ====== */
function norm_name(string $s): string {
    $s = strtolower(trim($s));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}
function getProductImage(string $name): string {
    $k = norm_name($name);
    if (preg_match('/^(papier|emballage)s?\s+(blanc|gris|noir|violet)$/', $k, $m)) return 'emballage_'.$m[2].'.PNG';
    if (preg_match('/^(papier|emballage)s?\s+rose(\s+pale|\s+pâle)?$/', $k)) return 'emballage_rose.PNG';
    if (preg_match('/paillet+e?s?/', $k)) return 'paillette_argent.PNG';
    if (preg_match('/papillon/', $k)) return 'papillon_doree.PNG';
    if (preg_match('/^rose.*clair$/', $k)) return 'rose_claire.png';
    static $map = [
        '12 roses'=>'12Roses.png','bouquet 12'=>'12Roses.png',
        '20 roses'=>'20Roses.png','bouquet 20'=>'20Roses.png',
        '24 roses'=>'20Roses.png','bouquet 24'=>'20Roses.png',
        '36 roses'=>'36Roses.png','bouquet 36'=>'36Roses.png',
        '50 roses'=>'50Roses.png','bouquet 50'=>'50Roses.png',
        '66 roses'=>'66Roses.png','bouquet 66'=>'66Roses.png',
        '99 roses'=>'100Roses.png','bouquet 99'=>'100Roses.png',
        '100 roses'=>'100Roses.png','bouquet 100'=>'100Roses.png',
        '101 roses'=>'100Roses.png','bouquet 101'=>'100Roses.png',
        'rose rouge'=>'rouge.png','rose rose'=>'rose.png','rose blanche'=>'rosesBlanche.png',
        'rose bleue'=>'bleu.png','rose noire'=>'noir.png',
        'mini ourson'=>'ours_blanc.PNG','deco anniv'=>'happybirthday.PNG','decoration anniversaire'=>'happybirthday.PNG',
        'baton coeur'=>'baton_coeur.PNG','diamant'=>'diamant.PNG','couronne'=>'couronne.PNG',
        'lettre'=>'lettre.png','initiale'=>'lettre.png','carte pour mot'=>'carte.PNG','carte'=>'carte.PNG',
        'panier vide'=>'panier_vide.png','panier rempli'=>'panier_rempli.png',
    ];
    if (isset($map[$k])) return $map[$k];
    if (strpos($k, 'coffret') === 0) return 'coffret.png';
    return 'placeholder.png';
}

/* ====== Couleurs (clé -> hex) ====== */
function color_hex(?string $c): ?string {
    if (!$c) return null;
    $k = strtolower(trim($c));
    // Adapte les clés à celles que tu envoies depuis le produit (ex: rouge, rose, blanc, bleu, noir)
    $map = [
        'rouge' => '#b70f0f',
        'rose'  => '#f29fb5',
        'blanc' => '#e7e7e7',
        'bleu'  => '#3b6bd6',
        'noir'  => '#222222',
        'gris'  => '#9aa0a6',
        // tu peux ajouter d’autres couleurs ici
    ];
    // si on reçoit déjà un hex (#xxxxxx), le laisser passer
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $k)) return $k;
    return $map[$k] ?? null;
}

/* ========= A) SUPPRESSION D’UN ARTICLE (existant) ========= */
if (($_POST['action'] ?? '') === 'del') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $kind   = $_POST['kind'] ?? 'produit';

    if ($delCom > 0 && $itemId > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en préparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {
            if ($kind === 'produit') {
                $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);
            } elseif ($kind === 'supplement') {
                $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);
            } else {
                $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);
            }
            $_SESSION['message'] = "Article supprimé de votre commande.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Requête invalide.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* ========= A2) SUPPRESSION MULTIPLE (NOUVEAU) ========= */
if (($_POST['action'] ?? '') === 'bulk_del') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    $selected = $_POST['sel'] ?? []; // tableau de "kind:id"
    if ($delCom > 0 && is_array($selected) && count($selected)) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en préparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {
            $pdo->beginTransaction();
            try {
                $stmtP = $pdo->prepare("DELETE FROM COMMANDE_PRODUIT   WHERE COM_ID=:c AND PRO_ID=:id");
                $stmtS = $pdo->prepare("DELETE FROM COMMANDE_SUPP     WHERE COM_ID=:c AND SUP_ID=:id");
                $stmtE = $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id");
                foreach ($selected as $token) {
                    if (!preg_match('/^(produit|supplement|emballage):(\d+)$/', $token, $m)) continue;
                    [$all,$k,$id] = $m;
                    $id = (int)$id;
                    if ($id <= 0) continue;
                    if ($k === 'produit')   $stmtP->execute([':c'=>$delCom, ':id'=>$id]);
                    elseif ($k === 'supplement') $stmtS->execute([':c'=>$delCom, ':id'=>$id]);
                    else $stmtE->execute([':c'=>$delCom, ':id'=>$id]);
                }
                $pdo->commit();
                $_SESSION['message'] = "Sélection supprimée.";
            } catch (Throwable $e) {
                $pdo->rollBack();
                $_SESSION['message'] = "Erreur lors de la suppression multiple.";
            }
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Aucun article sélectionné.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* ========= A3) VIDER TOUT LE PANIER (NOUVEAU) ========= */
if (($_POST['action'] ?? '') === 'clear_all') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    if ($delCom > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en préparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c")->execute([':c'=>$delCom]);
                $pdo->prepare("DELETE FROM COMMANDE_SUPP      WHERE COM_ID=:c")->execute([':c'=>$delCom]);
                $pdo->prepare("DELETE FROM COMMANDE_PRODUIT   WHERE COM_ID=:c")->execute([':c'=>$delCom]);
                $pdo->commit();
                $_SESSION['message'] = "Panier vide";
            } catch (Throwable $e) {
                $pdo->rollBack();
                $_SESSION['message'] = "Erreur lors du vidage du panier.";
            }
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* ========= B) CHARGEMENT DE LA COMMANDE + LIGNES ========= */
$sql = "SELECT COM_ID, COM_DATE
        FROM COMMANDE
        WHERE PER_ID = :per AND COM_STATUT = 'en préparation'
        ORDER BY COM_ID DESC
        LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute([':per'=>$perId]);
$com = $st->fetch(PDO::FETCH_ASSOC);

$lines = [];
$subtotal = 0.0;
$comId = 0;

if ($com) {
    $comId = (int)$com['COM_ID'];
    $sql = "
        SELECT 'produit' AS KIND, p.PRO_ID AS ITEM_ID, p.PRO_NOM AS NAME,
               p.PRO_PRIX AS UNIT_PRICE, cp.CP_QTE_COMMANDEE AS QTE, cp.CP_TYPE_PRODUIT AS SUBTYPE, 
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :com1

        UNION ALL
        SELECT 'supplement', s.SUP_ID, s.SUP_NOM, s.SUP_PRIX_UNITAIRE, cs.CS_QTE_COMMANDEE, 'supplement'
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :com2

        UNION ALL
        SELECT 'emballage', e.EMB_ID, e.EMB_NOM, 0.00, ce.CE_QTE, 'emballage'
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :com3

        ORDER BY NAME
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':com1'=>$comId, ':com2'=>$comId, ':com3'=>$comId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $L) {
        $subtotal += (float)$L['UNIT_PRICE'] * (int)$L['QTE'];
    }
}

$hasOrder = (bool)$com;
$hasItems = $hasOrder && !empty($lines);

$shipping = 0.00;
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Ma commande</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/styleCatalogue.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/commande.css">
    <style>
        .grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
        @media (max-width:980px){.grid{grid-template-columns:1fr}}
        .card{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.06);overflow:hidden}
        .card.disabled{opacity:.6;filter:grayscale(.5)}
        .muted{color:#777}
        /* Ajout d’une colonne checkbox (32px) */
        .cart-row{display:grid;grid-template-columns:32px 64px 1fr auto auto auto;gap:12px;align-items:center;padding:12px 16px;border-top:1px solid #eee}
        .cart-img{width:64px;height:64px;object-fit:cover;border-radius:8px}
        .cart-name{font-weight:600}
        .item-sub{font-weight:400;font-size:12px;color:#777}
        .trash-form{margin:0}
        .trash-btn{background:transparent;border:0;cursor:pointer;font-size:18px;line-height:1;color:#b70f0f;padding:6px;border-radius:8px}
        .trash-btn:hover{background:#b70f0f10}
        .flash{margin:12px auto;max-width:920px;background:#f6fff6;color:#0a6b0a;border:1px solid #bfe6bf;padding:10px 12px;border-radius:10px}
        .card.empty{text-align:center;padding:24px}
        .section-title{margin:18px 0 10px 0;font-size:18px;font-weight:700}
        .bulk-bar{display:flex;gap:8px;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee}
        .left-actions{display:flex;gap:10px;align-items:center}
        .btn-ghost.small{font-size:.9rem;padding:.35rem .7rem}
        .sum-row,.sum-total{display:flex;justify-content:space-between;padding:10px 16px}
        .sum-total{font-weight:700;border-top:1px solid #eee}
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
        <!-- Panier -->
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

                <!-- Barre de sélection multiple (NOUVEAU) -->
                <div class="bulk-bar">
                    <div class="left-actions">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input id="checkAll" type="checkbox"> <span>Tout sélectionner</span>
                        </label>
                        <form method="post" action="<?= $BASE ?>commande.php" id="bulkDeleteForm" onsubmit="return confirm('Supprimer tous les articles sélectionnés ?');">
                            <input type="hidden" name="action" value="bulk_del">
                            <input type="hidden" name="com_id" value="<?= (int)$com['COM_ID'] ?>">
                            <!-- Les <input name="sel[]"> sont ajoutés par les lignes du panier -->
                            <button class="btn-ghost small" type="submit">Supprimer la sélection</button>
                        </form>
                    </div>
                    <form method="post" action="<?= $BASE ?>commande.php" onsubmit="return confirm('Vider tout le panier ?');">
                        <input type="hidden" name="action" value="clear_all">
                        <input type="hidden" name="com_id" value="<?= (int)$com['COM_ID'] ?>">
                        <button class="btn-ghost small" type="submit">Vider tout le panier</button>
                    </form>
                </div>

                <?php foreach ($lines as $L):
                    $kind = $L['KIND'];
                    $id   = (int)$L['ITEM_ID'];
                    $q    = (int)$L['QTE'];
                    $pu   = (float)$L['UNIT_PRICE'];
                    $lt   = $pu * $q;
                    $sub  = $L['SUBTYPE'];
                    $img  = $BASE . 'img/' . getProductImage($L['NAME']);
                    ?>
                    <div class="cart-row">
                        <!-- Case à cocher liée au formulaire bulk (NOUVEAU) -->
                        <input form="bulkDeleteForm" type="checkbox" name="sel[]" value="<?= htmlspecialchars($kind) . ':' . $id ?>">
                        <img class="cart-img" src="<?= htmlspecialchars($img) ?>" alt="">
                        <div class="cart-name">
                            <?= htmlspecialchars($L['NAME']) ?><br>
                            <span class="item-sub"><?= htmlspecialchars($sub) ?> · Qté <?= $q ?></span>
                        </div>
                        <div class="cart-unit"><?= number_format($pu, 2, '.', ' ') ?> CHF</div>
                        <div class="cart-total"><?= number_format($lt, 2, '.', ' ') ?> CHF</div>

                        <!-- Suppression unitaire (existant) -->
                        <form class="trash-form" method="post" action="<?= $BASE ?>commande.php" onsubmit="return confirm('Supprimer cet article ?');">
                            <input type="hidden" name="action" value="del">
                            <input type="hidden" name="com_id"  value="<?= $comId ?>">
                            <input type="hidden" name="item_id" value="<?= $id ?>">
                            <input type="hidden" name="kind"    value="<?= htmlspecialchars($kind, ENT_QUOTES) ?>">
                            <button class="trash-btn" aria-label="Supprimer cet article">🗑️</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Récap -->
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
                    <li>Expédition en 1 semaine</li>
                    <li>Paiement sécurisé via Stripe</li>
                </ul>
            </div>
        </aside>
    </div>

    <?php
    $disableShipping = ($subtotal <= 0);
    $disabledAttr  = $disableShipping ? 'disabled' : '';
    $disabledClass = $disableShipping ? ' disabled' : '';
    ?>
    <br>
    <section class="card shipping-block<?= $disabledClass ?>">
        <div class="inner">
            <div class="section-title">Type de livraison</div>
            <fieldset class="full group shipping-options">
                <label class="opt">
                    <input type="radio" name="livraison" value="standard" <?= $disabledAttr ?> checked>
                    <span>🚚 Standard (48h)</span>
                </label>
                <label class="opt">
                    <input type="radio" name="livraison" value="express" <?= $disabledAttr ?>>
                    <span>⚡ Express (24h)</span>
                </label>
                <label class="opt">
                    <input type="radio" name="livraison" value="retrait" <?= $disabledAttr ?>>
                    <span>🏬 Retrait en boutique</span>
                </label>
            </fieldset>
            <br>
            <div class="actions">
                <a class="btn-ghost" href="<?= $BASE ?>interface_catalogue_bouquet.php">Continuer mes achats</a>
                <a class="btn-ghost" href="<?= $BASE ?>interface_supplement.php">Ajouter des suppléments</a>
            </div>
            <br>
            <?php if ($disableShipping): ?>
                <p class="muted">Le panier est vide : choisissez des articles pour sélectionner un mode de livraison.</p>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Petit JS pour cocher/décocher tout (facultatif, pas nécessaire au « Vider tout ») -->
<script>
    document.addEventListener('DOMContentLoaded', function(){
        var checkAll = document.getElementById('checkAll');
        if (!checkAll) return;
        checkAll.addEventListener('change', function(){
            document.querySelectorAll('input[name="sel[]"]').forEach(function(cb){ cb.checked = checkAll.checked; });
        });
    });
</script>
</body>
</html>
