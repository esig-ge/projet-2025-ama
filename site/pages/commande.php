<?php
// /site/pages/commande.php
session_start();

/* ===== Anti-cache pour éviter le retour d’anciens paniers ===== */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ===== Base URL (robuste) ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* ===== Bloquer les administrateurs ===== */
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['adm_id']);
if ($isAdmin) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Les administrateurs ne peuvent pas passer de commande.";
    header('Location: '.$BASE.'adminAccueil.php');
    exit;
}

/* ===== Accès ===== */
if (empty($_SESSION['per_id'])) {
    $_SESSION['message'] = "Veuillez vous connecter pour voir votre commande.";
    header('Location: interface_connexion.php'); exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$perId = (int)($_SESSION['per_id'] ?? 0);

/* ============================== */
/* ========== HELPERS =========== */
/* ============================== */
function norm_name(string $s): string {
    $s = strtolower(trim($s));
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($converted !== false) $s = $converted;
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}
function getProductImage(string $name): string {
    $k = norm_name($name);

    // EMBALLAGES
    if (preg_match('/^(papier|emballage)s?\s+(blanc|gris|noir|violet)$/', $k, $m)) return 'emballage_' . $m[2] . '.PNG';
    if (preg_match('/^(papier|emballage)s?\s+rose(\s+pale|\s+pale)?$/', $k))     return 'emballage_rose.PNG';

    // SUPPLÉMENTS
    if (preg_match('/paillet+e?s?/', $k))   return 'paillette_argent.PNG';
    if (preg_match('/papillon/', $k))       return 'papillon_doree.PNG';
    if (preg_match('/baton\s*coeur|batt?on\s*coeur/', $k)) return 'baton_coeur.PNG';
    if (preg_match('/diamant/', $k))        return 'diamant.PNG';
    if (preg_match('/couronne/', $k))       return 'couronne.PNG';
    if (preg_match('/(lettre|initiale)/', $k)) return 'lettre.png';
    if (preg_match('/carte/', $k))          return 'carte.PNG';

    // ROSES UNITAIRES
    if (preg_match('/^rose.*clair$/', $k))  return 'rose_claire.png';
    static $simpleMap = [
        'rose rouge'   => 'rouge.png',
        'rose rose'    => 'rose.png',
        'rose blanche' => 'rosesBlanche.png',
        'rose bleue'   => 'bleu.png',
        'rose noire'   => 'noir.png',
        'panier vide'  => 'panier_vide.png',
        'panier rempli'=> 'panier_rempli.png',
    ];
    if (isset($simpleMap[$k])) return $simpleMap[$k];

    // BOUQUETS : on choisit l’image par le NOMBRE
    if (preg_match('/\bbouquet\b(?:\s+de)?\s+([0-9]{2,3})\b/', $k, $m)) {
        $nb = (int)$m[1];
        switch ($nb) {
            case 12:  return '12Roses.png';
            case 20:  return '20Roses.png';
            case 24:  return '20Roses.png';
            case 36:  return '36Roses.png';
            case 50:  return '50Roses.png';
            case 66:  return '66Roses.png';
            case 99:  return '100Roses.png';
            case 100: return '100Roses.png';
            case 101: return '100Roses.png';
        }
    }

    // COFFRETS
    if (strpos($k, 'coffret') === 0) return 'coffret.png';

    return 'placeholder.png';
}
function color_hex(?string $c): ?string {
    if (!$c) return null;
    $k = strtolower(trim($c));
    $map = ['rouge'=>'#b70f0f','rose'=>'#f29fb5','blanc'=>'#e7e7e7','bleu'=>'#3b6bd6','noir'=>'#222222','gris'=>'#9aa0a6'];
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $k)) return $k;
    return $map[$k] ?? null;
}

/* ======================================= */
/* ========== ACTIONS PANIER ============= */
/* ======================================= */

/* -- A) SUPPRESSION D’UN ARTICLE -- */
if (($_POST['action'] ?? '') === 'del') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $kind   = strtolower((string)($_POST['kind'] ?? 'produit'));

    if ($delCom > 0 && $itemId > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {

            if ($kind === 'produit') {
                $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id");
                $sel->execute([':c'=>$delCom, ':id'=>$itemId]);
                if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$r['CP_QTE_COMMANDEE'];
                    $type = strtolower((string)$r['CP_TYPE_PRODUIT']);
                    $map = [
                        'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                        'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                        'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                    ];
                    if (isset($map[$type]) && $q > 0) {
                        $sql = "UPDATE {$map[$type]['table']} SET {$map[$type]['col']} = {$map[$type]['col']} + :q WHERE {$map[$type]['id']} = :id";
                        $pdo->prepare($sql)->execute([':q'=>$q, ':id'=>$itemId]);
                    }
                }
                $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);

            } elseif ($kind === 'supplement') {
                $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id");
                $sel->execute([':c'=>$delCom, ':id'=>$itemId]);
                if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$r['CS_QTE_COMMANDEE'];
                    if ($q > 0) $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :q WHERE SUP_ID=:id")->execute([':q'=>$q, ':id'=>$itemId]);
                }
                $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);

            } else { // emballage
                $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id");
                $sel->execute([':c'=>$delCom, ':id'=>$itemId]);
                if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$r['CE_QTE'];
                    if ($q > 0) $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :q WHERE EMB_ID=:id")->execute([':q'=>$q, ':id'=>$itemId]);
                }
                $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id")->execute([':c'=>$delCom, ':id'=>$itemId]);
            }

            $_SESSION['message'] = "Article supprimé et stock rétabli.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Requête invalide.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* -- A2) SUPPRESSION MULTIPLE -- */
if (($_POST['action'] ?? '') === 'bulk_del') {
    $delCom   = (int)($_POST['com_id'] ?? 0);
    $selected = $_POST['sel'] ?? [];
    if ($delCom > 0 && is_array($selected) && count($selected)) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {
            foreach ($selected as $token) {
                if (!preg_match('/^(produit|supplement|emballage):(\d+)$/', $token, $m)) continue;
                $kind = $m[1]; $id = (int)$m[2];

                if ($kind === 'produit') {
                    $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id");
                    $sel->execute([':c'=>$delCom, ':id'=>$id]);
                    if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                        $q = (int)$r['CP_QTE_COMMANDEE'];
                        $type = strtolower((string)$r['CP_TYPE_PRODUIT']);
                        $map = [
                            'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                            'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                            'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                        ];
                        if (isset($map[$type]) && $q > 0) {
                            $sql = "UPDATE {$map[$type]['table']} SET {$map[$type]['col']} = {$map[$type]['col']} + :q WHERE {$map[$type]['id']} = :id";
                            $pdo->prepare($sql)->execute([':q'=>$q, ':id'=>$id]);
                        }
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id")->execute([':c'=>$delCom, ':id'=>$id]);

                } elseif ($kind === 'supplement') {
                    $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id");
                    $sel->execute([':c'=>$delCom, ':id'=>$id]);
                    if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                        $q = (int)$r['CS_QTE_COMMANDEE'];
                        if ($q > 0) $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :q WHERE SUP_ID=:id")->execute([':q'=>$q, ':id'=>$id]);
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id")->execute([':c'=>$delCom, ':id'=>$id]);

                } else { // emballage
                    $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id");
                    $sel->execute([':c'=>$delCom, ':id'=>$id]);
                    if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
                        $q = (int)$r['CE_QTE'];
                        if ($q > 0) $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :q WHERE EMB_ID=:id")->execute([':q'=>$q, ':id'=>$id]);
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id")->execute([':c'=>$delCom, ':id'=>$id]);
                }
            }
            $_SESSION['message'] = "Sélection supprimée et stocks rétablis.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Aucun article sélectionné.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* -- A3) VIDER TOUT LE PANIER -- */
if (($_POST['action'] ?? '') === 'clear_all') {
    $delCom = (int)($_POST['com_id'] ?? 0);
    if ($delCom > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
        $chk->execute([':c'=>$delCom, ':p'=>$perId]);
        if ($chk->fetchColumn()) {

            // produits -> restock
            $st = $pdo->prepare("SELECT PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c");
            $st->execute([':c'=>$delCom]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $q = (int)$r['CP_QTE_COMMANDEE'];
                $type = strtolower((string)$r['CP_TYPE_PRODUIT']);
                $pid  = (int)$r['PRO_ID'];
                $map = [
                    'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                    'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                    'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                ];
                if (isset($map[$type]) && $q > 0) {
                    $sql = "UPDATE {$map[$type]['table']} SET {$map[$type]['col']} = {$map[$type]['col']} + :q WHERE {$map[$type]['id']} = :id";
                    $pdo->prepare($sql)->execute([':q'=>$q, ':id'=>$pid]);
                }
            }

            // suppléments -> restock
            $st = $pdo->prepare("SELECT SUP_ID, CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c");
            $st->execute([':c'=>$delCom]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $q = (int)$r['CS_QTE_COMMANDEE'];
                if ($q > 0) $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :q WHERE SUP_ID=:id")->execute([':q'=>$q, ':id'=>$r['SUP_ID']]);
            }

            // emballages -> restock
            $st = $pdo->prepare("SELECT EMB_ID, CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c");
            $st->execute([':c'=>$delCom]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $q = (int)$r['CE_QTE'];
                if ($q > 0) $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :q WHERE EMB_ID=:id")->execute([':q'=>$q, ':id'=>$r['EMB_ID']]);
            }

            // purge
            $pdo->prepare("DELETE FROM COMMANDE_PRODUIT   WHERE COM_ID=:c")->execute([':c'=>$delCom]);
            $pdo->prepare("DELETE FROM COMMANDE_SUPP     WHERE COM_ID=:c")->execute([':c'=>$delCom]);
            $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c")->execute([':c'=>$delCom]);

            $_SESSION['message'] = "Panier vidé et stocks rétablis.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* -- A4) CHOIX LIVRAISON (standard / retrait + zone Genève/Suisse) -- */
if (($_POST['action'] ?? '') === 'set_shipping') {
    $_SESSION['ship_mode'] = ($_POST['ship_mode'] ?? 'standard') === 'retrait' ? 'retrait' : 'standard';
    $_SESSION['ship_zone'] = ($_POST['ship_zone'] ?? 'geneve') === 'suisse' ? 'suisse' : 'geneve';
    header("Location: ".$BASE."commande.php"); exit;
}

/* ========= A1) MISE À JOUR QUANTITÉ ========= */
if (($_POST['action'] ?? '') === 'set_qty') {
    $comId  = (int)($_POST['com_id']  ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $kind   = strtolower((string)($_POST['kind'] ?? 'produit'));
    $newQ   = max(1, (int)($_POST['qty'] ?? 1)); // au moins 1

    if ($comId > 0 && $itemId > 0) {
        // Vérifier que la commande appartient bien à l'utilisateur et est modifiable
        $chk = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
        $chk->execute([':c'=>$comId, ':p'=>$perId]);
        if ($chk->fetchColumn()) {

            if ($kind === 'produit') {
                // Récupérer qté actuelle + type produit (fleur/bouquet/coffret)
                $st = $pdo->prepare("SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:id");
                $st->execute([':c'=>$comId, ':id'=>$itemId]);
                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $oldQ = (int)$row['CP_QTE_COMMANDEE'];
                    $type = strtolower((string)$row['CP_TYPE_PRODUIT']);

                    // où débiter/re-créditer le stock
                    $map = [
                        'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                        'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                        'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                    ];
                    if (!isset($map[$type])) { $_SESSION['message'] = "Type de produit invalide."; header("Location: ".$BASE."commande.php"); exit; }

                    $t  = $map[$type]['table'];
                    $c  = $map[$type]['col'];
                    $id = $map[$type]['id'];

                    if ($newQ > $oldQ) {
                        $delta = $newQ - $oldQ;
                        // Vérifier le stock disponible
                        $qStock = (int)$pdo->query("SELECT {$c} FROM {$t} WHERE {$id}=".(int)$itemId)->fetchColumn();
                        if ($qStock < $delta) {
                            $_SESSION['message'] = "Stock insuffisant (disponible: {$qStock}).";
                            header("Location: ".$BASE."commande.php"); exit;
                        }
                        // Débiter le stock et mettre à jour la ligne
                        $pdo->prepare("UPDATE {$t} SET {$c}={$c}-:d WHERE {$id}=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE=:q WHERE COM_ID=:c AND PRO_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    } elseif ($newQ < $oldQ) {
                        $delta = $oldQ - $newQ;
                        // Re-créditer le stock et mettre à jour la ligne
                        $pdo->prepare("UPDATE {$t} SET {$c}={$c}+:d WHERE {$id}=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE=:q WHERE COM_ID=:c AND PRO_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    }
                }

            } elseif ($kind === 'supplement') {
                // Suppléments
                $st = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:id");
                $st->execute([':c'=>$comId, ':id'=>$itemId]);
                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $oldQ = (int)$row['CS_QTE_COMMANDEE'];
                    if ($newQ > $oldQ) {
                        $delta = $newQ - $oldQ;
                        $qStock = (int)$pdo->query("SELECT SUP_QTE_STOCK FROM SUPPLEMENT WHERE SUP_ID=".(int)$itemId)->fetchColumn();
                        if ($qStock < $delta) {
                            $_SESSION['message'] = "Stock insuffisant de supplément (disponible: {$qStock}).";
                            header("Location: ".$BASE."commande.php"); exit;
                        }
                        $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK=SUP_QTE_STOCK-:d WHERE SUP_ID=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE=:q WHERE COM_ID=:c ET SUP_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    } elseif ($newQ < $oldQ) {
                        $delta = $oldQ - $newQ;
                        $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK=SUP_QTE_STOCK+:d WHERE SUP_ID=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE=:q WHERE COM_ID=:c AND SUP_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    }
                }

            } else { // emballage
                $st = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:id");
                $st->execute([':c'=>$comId, ':id'=>$itemId]);
                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $oldQ = (int)$row['CE_QTE'];
                    if ($newQ > $oldQ) {
                        $delta = $newQ - $oldQ;
                        $qStock = (int)$pdo->query("SELECT EMB_QTE_STOCK FROM EMBALLAGE WHERE EMB_ID=".(int)$itemId)->fetchColumn();
                        if ($qStock < $delta) {
                            $_SESSION['message'] = "Stock emballage insuffisant (disponible: {$qStock}).";
                            header("Location: ".$BASE."commande.php"); exit;
                        }
                        $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK=EMB_QTE_STOCK-:d WHERE EMB_ID=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_EMBALLAGE SET CE_QTE=:q WHERE COM_ID=:c AND EMB_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    } elseif ($newQ < $oldQ) {
                        $delta = $oldQ - $newQ;
                        $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK=EMB_QTE_STOCK+:d WHERE EMB_ID=:id")->execute([':d'=>$delta, ':id'=>$itemId]);
                        $pdo->prepare("UPDATE COMMANDE_EMBALLAGE SET CE_QTE=:q WHERE COM_ID=:c AND EMB_ID=:id")->execute([':q'=>$newQ, ':c'=>$comId, ':id'=>$itemId]);
                    }
                }
            }

            $_SESSION['message'] = "Quantité mise à jour.";
        } else {
            $_SESSION['message'] = "Action non autorisée.";
        }
    } else {
        $_SESSION['message'] = "Requête invalide.";
    }
    header("Location: ".$BASE."commande.php"); exit;
}

/* =========================================== */
/* ========== CHARGEMENT DU PANIER =========== */
/* =========================================== */
/* On charge la COMMANDE du client en 'en preparation' (le panier actif) */
$st  = $pdo->prepare("
    SELECT COM_ID, COM_DATE
      FROM COMMANDE
     WHERE PER_ID = :per AND COM_STATUT = 'en preparation'
     ORDER BY COM_ID DESC
     LIMIT 1
");
$st->execute([':per'=>$perId]);
$com = $st->fetch(PDO::FETCH_ASSOC);

$lines = [];
$subtotal = 0.0;
$comId = 0;

if ($com) {
    $comId = (int)$com['COM_ID'];

    $sqlLines = "
        SELECT 'produit' AS KIND, p.PRO_ID AS ITEM_ID, p.PRO_NOM AS NAME,
               p.PRO_PRIX AS UNIT_PRICE, cp.CP_QTE_COMMANDEE AS QTE, cp.CP_TYPE_PRODUIT AS SUBTYPE
          FROM COMMANDE_PRODUIT cp
          JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
         WHERE cp.COM_ID = :c1

        UNION ALL
        SELECT 'supplement', s.SUP_ID, s.SUP_NOM, s.SUP_PRIX_UNITAIRE, cs.CS_QTE_COMMANDEE, 'supplement'
          FROM COMMANDE_SUPP cs
          JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
         WHERE cs.COM_ID = :c2

        UNION ALL
        SELECT 'emballage', e.EMB_ID, e.EMB_NOM, 0.00, ce.CE_QTE, 'emballage'
          FROM COMMANDE_EMBALLAGE ce
          JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
         WHERE ce.COM_ID = :c3

        ORDER BY NAME
    ";
    $st = $pdo->prepare($sqlLines);
    $st->execute([':c1'=>$comId, ':c2'=>$comId, ':c3'=>$comId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lines as $L) {
        $subtotal += (float)$L['UNIT_PRICE'] * (int)$L['QTE'];
    }
}

$hasOrder = (bool)$com;
$hasItems = $hasOrder && !empty($lines);

/* ===== Livraison (5 CHF Genève, 10 CHF Suisse), 0 si Retrait ===== */
$ship_mode = $_SESSION['ship_mode'] ?? 'standard'; // 'standard' | 'retrait'
$ship_zone = $_SESSION['ship_zone'] ?? 'geneve';   // 'geneve' | 'suisse'

$shipping = 0.00;
if ($hasItems) {
    if ($ship_mode === 'standard') {
        $shipping = ($ship_zone === 'geneve') ? 5.00 : 10.00;
    } else { // retrait en boutique
        $shipping = 0.00;
    }
}

/* ===== TVA Suisse (2024) ===== */
$RATE_REDUCED = 0.026; // 2,6% (fleurs/bouquets)
$RATE_NORMAL  = 0.081; // 8,1% (suppléments/emballages/coffrets)

$base_reduced = 0.0;
$base_normal  = 0.0;

foreach ($lines as $L) {
    $kind = strtolower((string)$L['KIND']);      // 'produit' | 'supplement' | 'emballage'
    $sub  = strtolower((string)$L['SUBTYPE']);   // 'fleur' | 'bouquet' | 'coffret' | 'supplement' | 'emballage'
    $qte  = (int)$L['QTE'];
    $pu   = (float)$L['UNIT_PRICE'];
    $lt   = $qte * $pu;

    if ($kind === 'produit' && ($sub === 'fleur' || $sub === 'bouquet')) {
        $base_reduced += $lt;      // 2,6%
    } else {
        $base_normal  += $lt;      // 8,1%
    }
}

/* Ventilation de la livraison au prorata des bases */
$ship_red = 0.0; $ship_norm = 0.0;
$goods_total = $base_reduced + $base_normal;
if ($shipping > 0 && $goods_total > 0) {
    $ship_red  = $shipping * ($base_reduced / $goods_total);
    $ship_norm = $shipping - $ship_red;
}

$tax_reduced = round(($base_reduced + $ship_red)  * $RATE_REDUCED, 2);
$tax_normal  = round(($base_normal  + $ship_norm) * $RATE_NORMAL,  2);
$tax_total   = $tax_reduced + $tax_normal;

/* Total (présentation comme avant: produits + livraison) */
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Ma commande</title>

    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <!-- <link rel="stylesheet" href="<?= $BASE ?>css/style.css"> -->
    <link rel="stylesheet" href="<?= $BASE ?>css/style_catalogue.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/commande.css">

    <style>
        /* Petit style pour la bulle TVA */
        .tva-pill{
            margin-left:6px;border:none;border-radius:999px;padding:0 8px;height:20px;line-height:20px;
            font-size:12px;cursor:pointer;background:#eee
        }
        .summary .sum-row{position:relative}
        .tva-pop{
            position:absolute; top:32px; left:8px; max-width:320px; background:#fff; border:1px solid #e6e6e9;
            box-shadow:0 8px 20px rgba(0,0,0,.08); border-radius:10px; padding:10px 12px; font-size:12px; color:#333;
            z-index:100; display:none
        }
        .tva-pop[aria-hidden="false"]{display:block}
        .small{font-size:12px}
        .muted.small{color:#666}
        .shipping-block .zone { display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        .shipping-block .note { font-size:12px; color:#666; margin-top:4px;}
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
                    <a class="btn-primary" href="<?= $BASE ?>interface_selection_produit.php">Parcourir le catalogue</a>
                </p>
            <?php else: ?>

                <div class="bulk-bar">
                    <div class="left-actions">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input id="checkAll" type="checkbox"> <span>Tout sélectionner</span>
                        </label>
                        <form method="post" action="<?= $BASE ?>commande.php" id="bulkDeleteForm" onsubmit="return confirm('Supprimer tous les articles sélectionnés ?');">
                            <input type="hidden" name="action" value="bulk_del">
                            <input type="hidden" name="com_id" value="<?= (int)$com['COM_ID'] ?>">
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
                        <input form="bulkDeleteForm" type="checkbox" name="sel[]" value="<?= htmlspecialchars($kind) . ':' . $id ?>">
                        <img class="cart-img" src="<?= htmlspecialchars($img) ?>" alt="">
                        <div class="cart-name">
                            <?= htmlspecialchars($L['NAME']) ?><br>
                            <span class="item-sub"><?= htmlspecialchars($sub) ?></span>
                        </div>

                        <!-- QTÉ -->
                        <form class="qty-form" method="post" action="<?= $BASE ?>commande.php">
                            <input type="hidden" name="action" value="set_qty">
                            <input type="hidden" name="com_id"  value="<?= $comId ?>">
                            <input type="hidden" name="item_id" value="<?= $id ?>">
                            <input type="hidden" name="kind"    value="<?= htmlspecialchars($kind, ENT_QUOTES) ?>">
                            <input class="qty-input" type="number" name="qty" min="1" value="<?= $q ?>">
                        </form>

                        <div class="cart-unit"><?= number_format($pu, 2, '.', ' ') ?> CHF</div>
                        <div class="cart-total"><?= number_format($lt, 2, '.', ' ') ?> CHF</div>

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
            <div class="sum-row">
                <span>
                    TVA
                    <button type="button" class="tva-pill" id="tva-pill" aria-haspopup="true" aria-expanded="false">?</button>
                    <span class="tva-pop" id="tva-pop" role="tooltip" aria-hidden="true">
                        <strong>TVA Suisse</strong><br>
                        Fleurs / bouquets : 2,6 %<br>
                        Suppléments / emballages / coffrets : 8,1 %<br>
                        Livraison ventilée au prorata (2,6 % / 8,1 %).
                    </span>
                </span>
                <span><?= number_format($tax_total, 2, '.', ' ') ?> CHF</span>
            </div>
            <div class="sum-row muted small">
                <span>Détail : 2,6 % <?= number_format($tax_reduced,2,'.',' ') ?> • 8,1 % <?= number_format($tax_normal,2,'.',' ') ?></span>
                <span></span>
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



            <div class="help">
                <ul>
                    <li>Expédition en 1 semaine</li>
                    <li>Paiement sécurisé via Stripe</li>
                </ul>
            </div>
            <br>
            <label>Informations supplémentaires :</label>
            <textarea placeholder="Veuillez ajouter des détails précis..." class="comment-box"></textarea>
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

            <!-- Formulaire des options de livraison -->
            <form method="post" action="<?= $BASE ?>commande.php" id="shipForm">
                <input type="hidden" name="action" value="set_shipping">

                <fieldset class="full group shipping-options">
                    <label class="opt">
                        <input type="radio" name="ship_mode" value="standard" <?= $disabledAttr ?> <?= ($ship_mode==='standard'?'checked':'') ?>>
                        <span>🚚 Standard (48h)</span>
                    </label>
                    <label class="opt">
                        <input type="radio" name="ship_mode" value="retrait" <?= $disabledAttr ?> <?= ($ship_mode==='retrait'?'checked':'') ?>>
                        <span>🏬 Retrait en boutique</span>
                    </label>
                </fieldset>

                <div id="zoneWrap" style="margin-top:8px;<?= ($ship_mode==='retrait'?'display:none;':'') ?>">
                    <div class="zone">
                        <label class="opt">
                            <input type="radio" name="ship_zone" value="geneve" <?= $disabledAttr ?> <?= ($ship_zone==='geneve'?'checked':'') ?>>
                            <span>Genève — 5.00 CHF</span>
                        </label>
                        <label class="opt">
                            <input type="radio" name="ship_zone" value="suisse" <?= $disabledAttr ?> <?= ($ship_zone==='suisse'?'checked':'') ?>>
                            <span>Reste de la Suisse — 10.00 CHF</span>
                        </label>
                    </div>
                    <div class="note">La TVA sur la livraison est ventilée entre 2,6 % et 8,1 % selon le contenu.</div>
                </div>
            </form>

            <br>
            <div class="actions">
                <a class="btn-ghost" href="<?= $BASE ?>interface_selection_produit.php">Continuer mes achats</a>
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

<script>
    /* Tout sélectionner */
    document.addEventListener('DOMContentLoaded', function(){
        var checkAll = document.getElementById('checkAll');
        if (!checkAll) return;
        checkAll.addEventListener('change', function(){
            document.querySelectorAll('input[name="sel[]"]').forEach(function(cb){ cb.checked = checkAll.checked; });
        });
    });
</script>

<script>
    /* Préparer la commande côté serveur avant de passer à l’adresse/paiement */
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-checkout');
        if (!btn) return;

        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            if (btn.getAttribute('aria-disabled') === 'true') return;

            btn.setAttribute('aria-disabled', 'true');
            const oldText = btn.textContent;
            btn.textContent = 'Préparation de la commande…';

            try {
                const res = await fetch('<?= $BASE ?>api/upsert_from_cart.php', { method: 'POST' });
                if (res.status === 401) { window.location.href = '<?= $BASE ?>interface_connexion.php'; return; }

                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data?.error || 'Échec de la création de la commande');

                const orderId = encodeURIComponent(data.order_id);
                window.location.href = '<?= $BASE ?>adresse_paiement.php?order_id=' + orderId;

            } catch (err) {
                alert('Désolé, impossible de préparer la commande.\n' + (err.message || 'Erreur inconnue'));
                btn.setAttribute('aria-disabled', 'false');
                btn.textContent = oldText;
            }
        });
    });
</script>

<script>
    /* Autosubmit qty + livraison + popover TVA */
    document.addEventListener('DOMContentLoaded', () => {
        // Quantités
        document.querySelectorAll('.qty-input').forEach(inp => {
            let t;
            inp.addEventListener('change', () => {
                const form = inp.closest('form.qty-form');
                if (form) form.submit();
            });
            inp.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => {
                    const form = inp.closest('form.qty-form');
                    if (form) form.submit();
                }, 500);
            });
        });

        // Livraison (autosubmit)
        const shipForm = document.getElementById('shipForm');
        if (shipForm){
            const zoneWrap = document.getElementById('zoneWrap');
            shipForm.addEventListener('change', (e)=>{
                if (e.target.name === 'ship_mode') {
                    // Afficher/masquer le choix de zone
                    if (e.target.value === 'retrait') {
                        zoneWrap && (zoneWrap.style.display = 'none');
                    } else {
                        zoneWrap && (zoneWrap.style.display = '');
                    }
                }
                shipForm.submit();
            });
        }

        // Popover TVA
        const pill = document.getElementById('tva-pill');
        const pop  = document.getElementById('tva-pop');
        if(pill && pop){
            const hide = ()=>{ pop.setAttribute('aria-hidden','true'); pill.setAttribute('aria-expanded','false'); };
            pill.addEventListener('click', ()=>{
                const open = pop.getAttribute('aria-hidden') === 'false';
                pop.setAttribute('aria-hidden', open ? 'true' : 'false');
                pill.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
            pop.addEventListener('mouseleave', hide);
            pill.addEventListener('mouseleave', ()=>{
                setTimeout(()=>{
                    if(!pill.matches(':hover') && !pop.matches(':hover')) hide();
                },120);
            });
            document.addEventListener('click', (e)=>{
                if(!pill.contains(e.target) && !pop.contains(e.target)) hide();
            });
        }
    });
</script>

<?php if (!empty($_SESSION['just_paid'])): ?>
    <script>
        try{
            localStorage.removeItem('DK_CART');
            localStorage.removeItem('DK_CART_ITEMS');
            window.history.replaceState({}, document.title, window.location.pathname);
        }catch(e){}
    </script>
    <?php unset($_SESSION['just_paid']); endif; ?>

</body>
</html>
