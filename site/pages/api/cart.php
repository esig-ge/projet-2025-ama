<?php
// /site/pages/api/cart.php
declare(strict_types=1);                 // Active le typage strict (meilleure robustesse)
session_start();                         // Démarre/continue la session PHP (on lit per_id, com_id)
header('Content-Type: application/json; charset=utf-8');  // Toutes les réponses sont en JSON
error_reporting(E_ALL);                  // Remonte tous les niveaux d’erreur (utile en dev)
ini_set('display_errors', '0');          // Mais ne les affiche pas au client (on renvoie JSON propre)

/* =========================================================
   =============== 1) HELPERS DE RÉPONSES JSON =============
   ========================================================= */

/**
 * Réponse JSON standard pour succès.
 * - $data : tableau de paires clé/valeur ajouté au JSON
 * - $status : code HTTP (200 par défaut)
 * Forme finale : {"ok":true, ...$data}
 * Termine le script (exit).
 */
function ok(array $data = [], int $status = 200){
    http_response_code($status);
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Réponse JSON standard pour erreur.
 * - $code : code interne (ex: 'validation', 'auth', 'server_error', …)
 * - $msg : message détaillé optionnel
 * - $status : code HTTP (400 par défaut)
 * Forme finale : {"ok":false,"error":"code","msg":"..."}
 * Termine le script (exit).
 */
function err(string $code, string $msg = '', int $status = 400){
    http_response_code($status);
    $p = ['ok'=>false,'error'=>$code];
    if ($msg !== '') $p['msg'] = $msg;
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
   ================== 2) CONNEXION BDD (PDO) ===============
   ========================================================= */

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php'; // Fichier doit retourner un PDO
    if (!$pdo instanceof PDO) throw new RuntimeException('PDO manquant'); // Sécurité : s’assurer d’un PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);       // Exceptions sur erreurs SQL
} catch (Throwable $e){
    // En cas d’échec de connexion ou autre erreur serveur, renvoyer un JSON d’erreur (HTTP 500)
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}

/* =========================================================
   ==================== 3) AUTHENTIFICATION ================
   ========================================================= */

// On récupère l'identité du client depuis la session
$perId = (int)($_SESSION['per_id'] ?? 0);
// Si non connecté → 401 Unauthorized
if ($perId <= 0) err('auth', 'Connecte-toi pour ajouter au panier.', 401);

/* =========================================================
   ===================== 4) CONSTANTES =====================
   ========================================================= */

// Statut de commande "ouverte" (doit exister dans la BDD)
// Remarque : si changement de libellé côté BDD, le mettre à jour ici aussi.
const ORDER_STATUS_OPEN = 'en preparation'; // doit matcher la BDD sinon ça marche pas

/* =========================================================
   ======================= 5) UTILS ========================
   ========================================================= */

/**
 * Lit une quantité envoyée (POST/GET) avec différents noms possibles (qty/qte).
 * Normalise : min=1, max=999.
 */
function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_GET['qty'] ?? 1; // Valeur par défaut = 1
    $q = (int)$q;
    if ($q < 1) $q = 1;
    if ($q > 999) $q = 999;
    return $q;
}

/**
 * Retourne l'ID de la commande "ouverte" (COMMANDE.COM_STATUT = ORDER_STATUS_OPEN)
 * pour un client donné ($perId). Si aucune trouvée, la crée.
 * - Stocke aussi COM_ID en session pour réutilisation.
 */
function getOpenOrderId(PDO $pdo, int $perId): int {
    // 1) Cherche la commande ouverte la plus récente du client
    $q = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE PER_ID=:p AND COM_STATUT=:st ORDER BY COM_ID DESC LIMIT 1");
    $q->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $id = (int)($q->fetchColumn() ?: 0);
    if ($id) { $_SESSION['com_id'] = $id; return $id; }

    // 2) Sinon crée une nouvelle commande ouverte (avec date NOW)
    $ins = $pdo->prepare("INSERT INTO COMMANDE (PER_ID, COM_STATUT, COM_DATE) VALUES (:p, :st, NOW())");
    $ins->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $newId = (int)$pdo->lastInsertId();
    $_SESSION['com_id'] = $newId;
    return $newId;
}

/**
 * Récupère la liste des items du panier (produits, suppléments, emballages)
 * pour une commande donnée ($comId). UNION ALL de 3 sous-requêtes :
 * - COMMANDE_PRODUIT + PRODUIT
 * - COMMANDE_SUPP + SUPPLEMENT
 * - COMMANDE_EMBALLAGE + EMBALLAGE
 * Retourne un tableau associatif homogène :
 *   item_type ('produit'|'supplement'|'emballage'), id, PRO_NOM/..., PRO_PRIX, qty, subtype
 */
function listItems(PDO $pdo, int $comId): array {
    $sql = "
        SELECT 'produit' AS item_type, p.PRO_ID AS id, p.PRO_NOM, p.PRO_PRIX,
               cp.CP_QTE_COMMANDEE AS qty, cp.CP_TYPE_PRODUIT AS subtype
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :c1

        UNION ALL
        SELECT 'supplement', s.SUP_ID, s.SUP_NOM, s.SUP_PRIX_UNITAIRE,
               cs.CS_QTE_COMMANDEE, 'supplement'
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :c2

        UNION ALL
        SELECT 'emballage', e.EMB_ID, e.EMB_NOM, 0.00,
               ce.CE_QTE, 'emballage'
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :c3
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['c1'=>$comId, 'c2'=>$comId, 'c3'=>$comId]); // 3 binds pour les 3 sous-requêtes
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcule le sous-total (somme prix * quantité) basé sur la liste renvoyée par listItems().
 * - On arrondit à 2 décimales (float).
 */
function subtotal(array $items): float {
    $s = 0.0;
    foreach ($items as $r) $s += (float)$r['PRO_PRIX'] * (int)$r['qty'];
    return round($s, 2);
}

/* =========================================================
   ===================== 6) ROUTAGE ACTION =================
   ========================================================= */

// L’action peut venir de POST ou GET. Valeur par défaut : 'list'
$action = $_REQUEST['action'] ?? 'list';

try {
    switch ($action) {

        /* =================================================
           ============ A) ADD — Ajouter un PRODUIT =========
           =================================================
           - Gère FLEUR / BOUQUET / COFFRET
           - Vérifie le stock et décrémente dans la table dédiée
           - Ajoute/Met à jour COMMANDE_PRODUIT
           - Renvoie JSON avec items + subtotal + stockLeft
        */
        case 'add': {
            // 1) Lire identifiant produit + quantité
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $qty   = read_qty();
            if ($proId <= 0) err('validation', 'Produit invalide', 422);

            // 2) Déterminer le sous-type (fleur/bouquet/coffret) en testant existe + lecture stock
            $cands = [
                ['tbl'=>'FLEUR',   'stock'=>'FLE_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'fleur'],
                ['tbl'=>'BOUQUET', 'stock'=>'BOU_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'bouquet'],
                ['tbl'=>'COFFRET', 'stock'=>'COF_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'coffret'],
            ];
            $found = null; $stock = 0;

            foreach ($cands as $c) {
                $q = $pdo->prepare("SELECT {$c['stock']} AS s FROM {$c['tbl']} WHERE {$c['idcol']}=:id LIMIT 1");
                $q->execute(['id'=>$proId]);
                $val = $q->fetchColumn();
                if ($val !== false) {
                    $found = $c;                 // On sait maintenant dans quelle table se trouve l'article
                    $stock = (int)$val;          // Stock disponible
                    break;
                }
            }
            if (!$found) err('not_found','Produit introuvable dans FLEUR/BOUQUET/COFFRET',404);
            if ($stock < $qty) err('stock','Stock insuffisant',409); // Pas assez de stock

            // 3) Lire le nom du produit (optionnel mais utile pour des toasts)
            $name = (function(PDO $pdo, int $id){
                $s = $pdo->prepare("SELECT PRO_NOM FROM PRODUIT WHERE PRO_ID=:id");
                $s->execute(['id'=>$id]);
                return (string)($s->fetchColumn() ?: ("Produit #".$id));
            })($pdo, $proId);

            // 4) Récupérer/Créer la commande ouverte du client
            $comId = getOpenOrderId($pdo, $perId);

            // 5) Décrément du stock dans la table identifiée (contrainte >= :qmin pour éviter les courses)
            $sql = "UPDATE {$found['tbl']}
            SET {$found['stock']} = {$found['stock']} - :qdec
            WHERE {$found['idcol']} = :id AND {$found['stock']} >= :qmin";
            $u = $pdo->prepare($sql);
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$proId]);
            if ($u->rowCount() === 0) err('stock','Stock insuffisant (conflit)',409); // Conflit de stock

            // 6) Upsert sur COMMANDE_PRODUIT (sans ON DUPLICATE KEY)
            $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p");
            $sel->execute(['c'=>$comId, 'p'=>$proId]);
            $cur = $sel->fetchColumn();

            if ($cur === false) {
                // Insertion si la ligne n’existe pas encore
                $ins = $pdo->prepare("
            INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
            VALUES (:c, :p, :q, :t)
        ");
                $ins->execute(['c'=>$comId, 'p'=>$proId, 'q'=>$qty, 't'=>$found['label']]);
            } else {
                // Sinon on additionne la quantité
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
            UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE=:q
            WHERE COM_ID=:c AND PRO_ID=:p
        ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 'p'=>$proId]);
            }

            // 7) Réponse homogène : items actualisés + sous-total + stock restant côté table
            $items  = listItems($pdo, $comId);
            $left   = max(0, $stock - $qty);
            ok([
                'com_id'    => $comId,
                'type'      => $found['label'],   // 'bouquet' | 'fleur' | 'coffret'
                'proId'     => $proId,
                'name'      => $name,
                'stockLeft' => $left,
                'items'     => $items,
                'subtotal'  => subtotal($items),
            ]);
        }

        /* =================================================
           ====== B) ADD_SUPPLEMENT — Ajouter SUPPLÉMENT ====
           =================================================
           - Vérifie existence + stock SUPPLEMENT
           - Décrémente stock SUPPLEMENT
           - Upsert COMMANDE_SUPP
           - Renvoie JSON avec items + subtotal + stockLeft
        */
        case 'add_supplement': {
            // 1) Lire identifiant supplément + quantité
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
            $qty   = read_qty();
            if ($supId <= 0) err('validation','Supplément invalide',422);

            // 2) Vérifier existence + lire nom et stock
            $ck = $pdo->prepare("SELECT SUP_NOM, COALESCE(SUP_QTE_STOCK,0) AS stock FROM SUPPLEMENT WHERE SUP_ID=:id");
            $ck->execute(['id'=>$supId]);
            $row = $ck->fetch(PDO::FETCH_ASSOC);
            if (!$row) err('not_found','Supplément introuvable',404);

            $name  = (string)$row['SUP_NOM'];
            $stock = (int)$row['stock'];
            if ($stock < $qty) err('insufficient_stock','Stock insuffisant',409);

            // 3) Commande ouverte
            $comId = getOpenOrderId($pdo, $perId);

            // 4) Décrément stock SUPPLEMENT (contrainte >= :qmin)
            $u = $pdo->prepare("
        UPDATE SUPPLEMENT
        SET SUP_QTE_STOCK = SUP_QTE_STOCK - :qdec
        WHERE SUP_ID = :id AND SUP_QTE_STOCK >= :qmin
    ");
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$supId]);
            if ($u->rowCount() === 0) err('insufficient_stock','Stock insuffisant (conflit)',409);

            // 5) Upsert COMMANDE_SUPP
            $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s");
            $sel->execute(['c'=>$comId, 's'=>$supId]);
            $cur = $sel->fetchColumn();

            if ($cur === false) {
                $ins = $pdo->prepare("
            INSERT INTO COMMANDE_SUPP (COM_ID, SUP_ID, CS_QTE_COMMANDEE)
            VALUES (:c, :s, :q)
        ");
                $ins->execute(['c'=>$comId, 's'=>$supId, 'q'=>$qty]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
            UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE=:q
            WHERE COM_ID=:c AND SUP_ID=:s
        ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 's'=>$supId]);
            }

            // 6) Réponse JSON avec items + sous-total + stock restant
            $left   = max(0, $stock - $qty);
            $items  = listItems($pdo, $comId);
            $subtot = subtotal($items);

            ok([
                'com_id'    => $comId,
                'type'      => 'supplement',
                'supId'     => $supId,
                'name'      => $name,
                'stockLeft' => $left,
                'items'     => $items,
                'subtotal'  => $subtot,
            ]);
        }

        /* =================================================
           ===== C) ADD_EMBALLAGE — Ajouter EMBALLAGE =======
           =================================================
           - Vérifie existence + stock EMBALLAGE
           - Décrémente stock EMBALLAGE
           - Upsert COMMANDE_EMBALLAGE
           - Renvoie JSON avec items + subtotal + stockLeft
        */
        case 'add_emballage': {
            // 1) Lire identifiant emballage + quantité
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $qty   = read_qty();
            if ($embId <= 0) err('validation','Emballage invalide',422);

            // 2) Existence + lire nom/stock
            $ck = $pdo->prepare("SELECT EMB_NOM, COALESCE(EMB_QTE_STOCK,0) AS stock FROM EMBALLAGE WHERE EMB_ID=:id");
            $ck->execute(['id'=>$embId]);
            $row = $ck->fetch(PDO::FETCH_ASSOC);
            if (!$row) err('not_found','Emballage introuvable',404);

            $name  = (string)$row['EMB_NOM'];
            $stock = (int)$row['stock'];
            if ($stock < $qty) err('insufficient_stock','Stock insuffisant',409);

            // 3) Commande ouverte
            $comId = getOpenOrderId($pdo, $perId);

            // 4) Décrément stock (contrainte >= :qmin)
            $u = $pdo->prepare("
        UPDATE EMBALLAGE
        SET EMB_QTE_STOCK = EMB_QTE_STOCK - :qdec
        WHERE EMB_ID = :id AND EMB_QTE_STOCK >= :qmin
    ");
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$embId]);
            if ($u->rowCount() === 0) err('insufficient_stock','Stock insuffisant (conflit)',409);

            // 5) Upsert COMMANDE_EMBALLAGE
            $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e");
            $sel->execute(['c'=>$comId, 'e'=>$embId]);
            $cur = $sel->fetchColumn();

            if ($cur === false) {
                $ins = $pdo->prepare("
            INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
            VALUES (:c, :e, :q)
        ");
                $ins->execute(['c'=>$comId, 'e'=>$embId, 'q'=>$qty]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
            UPDATE COMMANDE_EMBALLAGE SET CE_QTE=:q
            WHERE COM_ID=:c AND EMB_ID=:e
        ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 'e'=>$embId]);
            }

            // 6) Réponse JSON homogène
            $left   = max(0, $stock - $qty);
            $items  = listItems($pdo, $comId);
            $subtot = subtotal($items);

            ok([
                'com_id'    => $comId,
                'type'      => 'emballage',
                'embId'     => $embId,
                'name'      => $name,
                'stockLeft' => $left,
                'items'     => $items,
                'subtotal'  => $subtot,
            ]);
        }

        /* =================================================
           ================= D) LIST — Lister =================
           =================================================
           - Renvoie les items de la commande ouverte (si existe)
           - Sinon items=[] et subtotal=0.0
        */
        case 'list': {
            $comId = (int)($_SESSION['com_id'] ?? 0);          // On réutilise le COM_ID en session
            if ($comId <= 0) ok(['items'=>[], 'subtotal'=>0.0]); // Aucun panier encore
            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* =================================================
           ========= E) REMOVE — Retirer un article =========
           =================================================
           - Supprime un produit / emballage / supplément du panier
           - Récrédite le stock dans la table correspondante
           - Renvoie les items restants + subtotal
        */
        case 'remove': {
            // 1) Besoin d’une commande ouverte
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) err('no_order','Aucune commande ouverte');

            // 2) On accepte pro_id OU emb_id OU sup_id
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

            if ($proId > 0) {
                // a) Lire la ligne COMMANDE_PRODUIT pour connaître la quantité et le type (fleur/bouquet/coffret)
                $sel = $pdo->prepare("
            SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT
            FROM COMMANDE_PRODUIT
            WHERE COM_ID=:c AND PRO_ID=:p
        ");
                $sel->execute(['c'=>$comId, 'p'=>$proId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$row['CP_QTE_COMMANDEE'];                // quantité à récréditer
                    $type = strtolower(trim((string)$row['CP_TYPE_PRODUIT'])); // type d’origine
                    // Mapping type → table/colonne de stock → clé id
                    $map = [
                        'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                        'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                        'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                    ];
                    // b) Si type reconnu, récréditer le stock
                    if (isset($map[$type]) && $q > 0) {
                        $sql = "UPDATE {$map[$type]['table']}
                        SET {$map[$type]['col']} = {$map[$type]['col']} + :qadd
                        WHERE {$map[$type]['id']} = :id";
                        $pdo->prepare($sql)->execute(['qadd'=>$q, 'id'=>$proId]);
                    }
                }
                // c) Supprimer la ligne du panier
                $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p")
                    ->execute(['c'=>$comId, 'p'=>$proId]);
            }
            elseif ($embId > 0) {
                // a) Lire quantité à récréditer
                $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e");
                $sel->execute(['c'=>$comId, 'e'=>$embId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$row['CE_QTE'];
                    if ($q > 0) {
                        // b) Récréditer stock EMBALLAGE
                        $pdo->prepare("
                    UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :qadd WHERE EMB_ID=:id
                ")->execute(['qadd'=>$q, 'id'=>$embId]);
                    }
                }
                // c) Supprimer du panier
                $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e")
                    ->execute(['c'=>$comId, 'e'=>$embId]);
            }
            elseif ($supId > 0) {
                // a) Lire quantité à récréditer
                $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s");
                $sel->execute(['c'=>$comId, 's'=>$supId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$row['CS_QTE_COMMANDEE'];
                    if ($q > 0) {
                        // b) Récréditer stock SUPPLEMENT
                        $pdo->prepare("
                    UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :qadd WHERE SUP_ID=:id
                ")->execute(['qadd'=>$q, 'id'=>$supId]);
                    }
                }
                // c) Supprimer du panier
                $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s")
                    ->execute(['c'=>$comId, 's'=>$supId]);
            }
            else {
                // Aucun ID fourni → erreur de validation
                err('missing_id','Aucun identifiant fourni');
            }

            // 3) Renvoie le panier mis à jour + sous-total recalculé
            $items    = listItems($pdo, $comId);
            $subtotal = subtotal($items);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>$subtotal]);
        }

        // ==================================================
        // ============ F) ACTION PAR DÉFAUT : erreur =======
        // ==================================================
        default:
            err('bad_request','Action inconnue',400);
    }
} catch (Throwable $e){
    // Catch global : on renvoie une erreur serveur JSON propre
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}
