<?php
// /site/pages/api/cart.php
// ============================================================================
// BUT DU FICHIER (API JSON - Panier)
// ----------------------------------------------------------------------------
// On expose une petite API REST-like en JSON pour gérer le panier d’un client :
// - Ajouter un produit / supplément / emballage
// - Lister les items du panier
// - Supprimer un item (avec ré-crédit du stock)
// On renvoie toujours du JSON standardisé : { ok: true|false, ... }.
//
// On suppose que la session connait déjà le client connecté (per_id).
// On suppose que la base respecte la structure COMMANDE / COMMANDE_* / PRODUIT…
// ============================================================================

declare(strict_types=1); // On active le typage strict → moins d'erreurs silencieuses
session_start();         // On démarre/continue la session (accès à $_SESSION['per_id'])

/* On force les entêtes HTTP :
   - on communique toujours en JSON (UTF-8),
   - on évite d’afficher les notices PHP à l’écran (on loggue côté serveur).
*/
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // On n’affiche pas les erreurs au client (API propre)

/* =============================================================================
   =============== 1) HELPERS DE RÉPONSES JSON (format standard) ===============
   =============================================================================
   Idée : centraliser la façon de répondre pour rester cohérent partout.
============================================================================= */

/**
 * ok() → réponse JSON "succès".
 * - $data : données supplémentaires (tableau associatif).
 * - $status : code HTTP (200 par défaut).
 * On termine le script immédiatement (exit) après l’envoi.
 */
function ok(array $data = [], int $status = 200){
    http_response_code($status);
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * err() → réponse JSON "erreur".
 * - $code : identifiant bref de l’erreur (ex: 'auth', 'validation', 'stock', 'server_error').
 * - $msg : message lisible (facultatif → utile côté front pour afficher une notif).
 * - $status : code HTTP (400 par défaut, on adapte selon le cas).
 * On termine le script immédiatement (exit) après l’envoi.
 */
function err(string $code, string $msg = '', int $status = 400){
    http_response_code($status);
    $p = ['ok'=>false,'error'=>$code];
    if ($msg !== '') $p['msg'] = $msg;
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =============================================================================
   ====================== 2) CONNEXION BDD (via PDO) ===========================
   =============================================================================
   On inclut notre fichier de connexion qui retourne un objet PDO prêt à l’emploi.
   On force le mode exception pour capter proprement les erreurs SQL.
============================================================================= */

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    if (!$pdo instanceof PDO) throw new RuntimeException('PDO manquant'); // garde-fou
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e){
    // Si la BDD est KO ou autre souci technique → on renvoie un JSON d’erreur 500.
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}

/* =============================================================================
   =========================== 3) AUTHENTIFICATION =============================
   =============================================================================
   On récupère l’identifiant du client connecté depuis la session.
   S’il n’est pas connecté → 401 Unauthorized.
============================================================================= */

$perId = (int)($_SESSION['per_id'] ?? 0);
if ($perId <= 0) err('auth', 'Connecte-toi pour ajouter au panier.', 401);

/* =============================================================================
   ============================== 4) CONSTANTES ================================
   =============================================================================
   On centralise les libellés "stables" utilisés par la logique métier.
   Ici : libellé du statut d’une commande “ouverte” (encore modifiable).
   Il doit correspondre STRICTEMENT à ce qui est en base.
============================================================================= */

const ORDER_STATUS_OPEN = 'en preparation'; // doit matcher COMMANDE.COM_STATUT

/* =============================================================================
   ================================ 5) UTILS ===================================
   =============================================================================
   Petites fonctions utilitaires réutilisées plus bas.
============================================================================= */

/**
 * read_qty() → lit la quantité depuis POST/GET (qty ou qte).
 * On normalise la valeur : entre 1 et 999 inclus.
 */
function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_GET['qty'] ?? 1;
    $q = (int)$q;
    if ($q < 1) $q = 1;
    if ($q > 999) $q = 999;
    return $q;
}

/**
 * getOpenOrderId(PDO $pdo, int $perId) → retourne l’ID de la commande “ouverte”.
 * Si aucune commande ouverte n’existe pour ce client, on en crée une (INSERT).
 * On mémorise aussi COM_ID en session pour accélérer les appels suivants.
 */
function getOpenOrderId(PDO $pdo, int $perId): int {
    // 1) On cherche la plus récente commande ouverte de ce client
    $q = $pdo->prepare("
        SELECT COM_ID
          FROM COMMANDE
         WHERE PER_ID = :p AND COM_STATUT = :st
         ORDER BY COM_ID DESC
         LIMIT 1
    ");
    $q->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $id = (int)($q->fetchColumn() ?: 0);
    if ($id) { $_SESSION['com_id'] = $id; return $id; }

    // 2) Sinon on crée une nouvelle commande “ouverte”
    $ins = $pdo->prepare("
        INSERT INTO COMMANDE (PER_ID, COM_STATUT, COM_DATE)
        VALUES (:p, :st, NOW())
    ");
    $ins->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $newId = (int)$pdo->lastInsertId();

    $_SESSION['com_id'] = $newId;
    return $newId;
}

/**
 * listItems(PDO $pdo, int $comId) → renvoie tous les items d’une commande
 * sous une forme homogène, en UNION ALL de 3 tables de lignes :
 * - COMMANDE_PRODUIT  (liée à PRODUIT)
 * - COMMANDE_SUPP     (liée à SUPPLEMENT)
 * - COMMANDE_EMBALLAGE(liée à EMBALLAGE)
 *
 * On standardise les colonnes : item_type, id, PRO_NOM/..., PRO_PRIX, qty, subtype.
 * Cela simplifie le front (on itère sur un seul tableau).
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
    $st->execute(['c1'=>$comId, 'c2'=>$comId, 'c3'=>$comId]); // même id lié 3x
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * subtotal(array $items) → calcule le sous-total = Σ (prix * quantité).
 * On arrondit à 2 décimales (format CHF).
 */
function subtotal(array $items): float {
    $s = 0.0;
    foreach ($items as $r) {
        $s += (float)$r['PRO_PRIX'] * (int)$r['qty'];
    }
    return round($s, 2);
}

/* =============================================================================
   ============================ 6) ROUTAGE D’ACTION ============================
   =============================================================================
   On récupère "action" depuis POST/GET. Valeur par défaut : 'list'.
   Chaque case du switch gère une action API.
============================================================================= */

$action = $_REQUEST['action'] ?? 'list';

try {
    switch ($action) {

        /* --------------------------------------------------------------------
           A) action=add → Ajouter un PRODUIT (fleur/bouquet/coffret)
           --------------------------------------------------------------------
           Étapes :
           1) Lire pro_id + qty (validation).
           2) Déterminer dans quelle table se trouve l’article (FLEUR/BOUQUET/COFFRET).
           3) Vérifier le stock disponible → si OK, décrémenter (UPDATE ... WHERE stock >= qty).
           4) Upsert dans COMMANDE_PRODUIT (insert ou update la quantité).
           5) Renvoyer la snapshot du panier + subtotal + stockLeft.
        -------------------------------------------------------------------- */
        case 'add': {
            // (1) Lecture & validation
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $qty   = read_qty();
            if ($proId <= 0) err('validation', 'Produit invalide', 422);

            // (2) On détecte le “sous-type” en testant l’existence en base
            $cands = [
                ['tbl'=>'FLEUR',   'stock'=>'FLE_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'fleur'],
                ['tbl'=>'BOUQUET', 'stock'=>'BOU_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'bouquet'],
                ['tbl'=>'COFFRET', 'stock'=>'COF_QTE_STOCK', 'idcol'=>'PRO_ID', 'label'=>'coffret'],
            ];
            $found = null;
            $stock = 0;

            foreach ($cands as $c) {
                $q = $pdo->prepare("SELECT {$c['stock']} AS s FROM {$c['tbl']} WHERE {$c['idcol']} = :id LIMIT 1");
                $q->execute(['id'=>$proId]);
                $val = $q->fetchColumn();
                if ($val !== false) {
                    $found = $c;          // on connait la table/colonne à mettre à jour
                    $stock = (int)$val;   // stock courant
                    break;
                }
            }
            if (!$found)              err('not_found', 'Produit introuvable', 404);
            if ($stock < $qty)        err('stock', 'Stock insuffisant', 409);

            // (3) On lit le nom pour un retour plus “humain”
            $name = (function(PDO $pdo, int $id){
                $s = $pdo->prepare("SELECT PRO_NOM FROM PRODUIT WHERE PRO_ID = :id");
                $s->execute(['id'=>$id]);
                return (string)($s->fetchColumn() ?: ("Produit #".$id));
            })($pdo, $proId);

            // (4) On garantit une commande “ouverte”
            $comId = getOpenOrderId($pdo, $perId);

            // (5) Décrément du stock (optimiste, avec garde WHERE stock >= qty)
            $sql = "
                UPDATE {$found['tbl']}
                   SET {$found['stock']} = {$found['stock']} - :qdec
                 WHERE {$found['idcol']} = :id
                   AND {$found['stock']} >= :qmin
            ";
            $u = $pdo->prepare($sql);
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$proId]);
            if ($u->rowCount() === 0) err('stock', 'Stock insuffisant (conflit)', 409);

            // (6) Upsert COMMANDE_PRODUIT (sans ON DUPLICATE KEY → compatible MariaDB)
            $sel = $pdo->prepare("
                SELECT CP_QTE_COMMANDEE
                  FROM COMMANDE_PRODUIT
                 WHERE COM_ID = :c AND PRO_ID = :p
            ");
            $sel->execute(['c'=>$comId, 'p'=>$proId]);
            $cur = $sel->fetchColumn();

            if ($cur === false) {
                $ins = $pdo->prepare("
                    INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                    VALUES (:c, :p, :q, :t)
                ");
                $ins->execute(['c'=>$comId, 'p'=>$proId, 'q'=>$qty, 't'=>$found['label']]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
                    UPDATE COMMANDE_PRODUIT
                       SET CP_QTE_COMMANDEE = :q
                     WHERE COM_ID = :c AND PRO_ID = :p
                ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 'p'=>$proId]);
            }

            // (7) On renvoie l’état du panier à jour
            $items  = listItems($pdo, $comId);
            $left   = max(0, $stock - $qty);

            ok([
                'com_id'    => $comId,
                'type'      => $found['label'],     // 'fleur' | 'bouquet' | 'coffret'
                'proId'     => $proId,
                'name'      => $name,
                'stockLeft' => $left,
                'items'     => $items,
                'subtotal'  => subtotal($items),
            ]);
        }

        /* --------------------------------------------------------------------
           B) action=add_supplement → Ajouter un SUPPLÉMENT
           --------------------------------------------------------------------
           Étapes proches de add (produit) mais sur la table SUPPLEMENT.
        -------------------------------------------------------------------- */
        case 'add_supplement': {
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
            $qty   = read_qty();
            if ($supId <= 0) err('validation','Supplément invalide', 422);

            // Existence + stock
            $ck = $pdo->prepare("SELECT SUP_NOM, COALESCE(SUP_QTE_STOCK,0) AS stock FROM SUPPLEMENT WHERE SUP_ID = :id");
            $ck->execute(['id'=>$supId]);
            $row = $ck->fetch(PDO::FETCH_ASSOC);
            if (!$row) err('not_found','Supplément introuvable', 404);

            $name  = (string)$row['SUP_NOM'];
            $stock = (int)$row['stock'];
            if ($stock < $qty) err('insufficient_stock','Stock insuffisant', 409);

            $comId = getOpenOrderId($pdo, $perId);

            // Décrément stock
            $u = $pdo->prepare("
                UPDATE SUPPLEMENT
                   SET SUP_QTE_STOCK = SUP_QTE_STOCK - :qdec
                 WHERE SUP_ID = :id AND SUP_QTE_STOCK >= :qmin
            ");
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$supId]);
            if ($u->rowCount() === 0) err('insufficient_stock','Stock insuffisant (conflit)', 409);

            // Upsert COMMANDE_SUPP
            $sel = $pdo->prepare("
                SELECT CS_QTE_COMMANDEE
                  FROM COMMANDE_SUPP
                 WHERE COM_ID = :c AND SUP_ID = :s
            ");
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
                    UPDATE COMMANDE_SUPP
                       SET CS_QTE_COMMANDEE = :q
                     WHERE COM_ID = :c AND SUP_ID = :s
                ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 's'=>$supId]);
            }

            $items  = listItems($pdo, $comId);
            $subtot = subtotal($items);

            ok([
                'com_id'    => $comId,
                'type'      => 'supplement',
                'supId'     => $supId,
                'name'      => $name,
                'stockLeft' => max(0, $stock - $qty),
                'items'     => $items,
                'subtotal'  => $subtot,
            ]);
        }

        /* --------------------------------------------------------------------
           C) action=add_emballage → Ajouter un EMBALLAGE
           --------------------------------------------------------------------
           Même principe, mais sur la table EMBALLAGE.
        -------------------------------------------------------------------- */
        case 'add_emballage': {
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $qty   = read_qty();
            if ($embId <= 0) err('validation','Emballage invalide', 422);

            $ck = $pdo->prepare("
                SELECT EMB_NOM, COALESCE(EMB_QTE_STOCK,0) AS stock
                  FROM EMBALLAGE
                 WHERE EMB_ID = :id
            ");
            $ck->execute(['id'=>$embId]);
            $row = $ck->fetch(PDO::FETCH_ASSOC);
            if (!$row) err('not_found','Emballage introuvable', 404);

            $name  = (string)$row['EMB_NOM'];
            $stock = (int)$row['stock'];
            if ($stock < $qty) err('insufficient_stock','Stock insuffisant', 409);

            $comId = getOpenOrderId($pdo, $perId);

            // Décrément stock
            $u = $pdo->prepare("
                UPDATE EMBALLAGE
                   SET EMB_QTE_STOCK = EMB_QTE_STOCK - :qdec
                 WHERE EMB_ID = :id AND EMB_QTE_STOCK >= :qmin
            ");
            $u->execute(['qdec'=>$qty, 'qmin'=>$qty, 'id'=>$embId]);
            if ($u->rowCount() === 0) err('insufficient_stock','Stock insuffisant (conflit)', 409);

            // Upsert COMMANDE_EMBALLAGE
            $sel = $pdo->prepare("
                SELECT CE_QTE
                  FROM COMMANDE_EMBALLAGE
                 WHERE COM_ID = :c AND EMB_ID = :e
            ");
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
                    UPDATE COMMANDE_EMBALLAGE
                       SET CE_QTE = :q
                     WHERE COM_ID = :c AND EMB_ID = :e
                ");
                $upd->execute(['q'=>$newQ, 'c'=>$comId, 'e'=>$embId]);
            }

            $items  = listItems($pdo, $comId);
            $subtot = subtotal($items);

            ok([
                'com_id'    => $comId,
                'type'      => 'emballage',
                'embId'     => $embId,
                'name'      => $name,
                'stockLeft' => max(0, $stock - $qty),
                'items'     => $items,
                'subtotal'  => $subtot,
            ]);
        }

        /* --------------------------------------------------------------------
           D) action=list → Lister le contenu du panier
           --------------------------------------------------------------------
           On renvoie la liste des items + le sous-total.
           Si aucune commande encore ouverte en session → items=[] & subtotal=0.0
        -------------------------------------------------------------------- */
        case 'list': {
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) ok(['items'=>[], 'subtotal'=>0.0]);
            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* --------------------------------------------------------------------
           E) action=remove → Retirer un item du panier
           --------------------------------------------------------------------
           On prend au choix : pro_id OU emb_id OU sup_id.
           On lit la quantité associée dans la ligne COMMANDE_*,
           on ré-crédite le stock dans la table correspondante,
           puis on supprime la ligne de la commande.
        -------------------------------------------------------------------- */
        case 'remove': {
            // (1) On doit avoir une commande ouverte
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) err('no_order','Aucune commande ouverte');

            // (2) Un seul identifiant ciblé (produit, emballage ou supplément)
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

            if ($proId > 0) {
                // a) Lire la ligne produit pour connaitre quantité + type (fleur/bouquet/coffret)
                $sel = $pdo->prepare("
                    SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT
                      FROM COMMANDE_PRODUIT
                     WHERE COM_ID = :c AND PRO_ID = :p
                ");
                $sel->execute(['c'=>$comId, 'p'=>$proId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q    = (int)$row['CP_QTE_COMMANDEE'];
                    $type = strtolower(trim((string)$row['CP_TYPE_PRODUIT']));
                    // b) Mapping type → table/colonne stock
                    $map = [
                        'fleur'   => ['table'=>'FLEUR',   'col'=>'FLE_QTE_STOCK', 'id'=>'PRO_ID'],
                        'bouquet' => ['table'=>'BOUQUET', 'col'=>'BOU_QTE_STOCK', 'id'=>'PRO_ID'],
                        'coffret' => ['table'=>'COFFRET', 'col'=>'COF_QTE_STOCK', 'id'=>'PRO_ID'],
                    ];
                    // c) Ré-crédit du stock si on reconnait le type
                    if (isset($map[$type]) && $q > 0) {
                        $sql = "
                            UPDATE {$map[$type]['table']}
                               SET {$map[$type]['col']} = {$map[$type]['col']} + :qadd
                             WHERE {$map[$type]['id']} = :id
                        ";
                        $pdo->prepare($sql)->execute(['qadd'=>$q, 'id'=>$proId]);
                    }
                }
                // d) Suppression de la ligne du panier
                $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID = :c AND PRO_ID = :p")
                    ->execute(['c'=>$comId, 'p'=>$proId]);
            }
            elseif ($embId > 0) {
                // a) Lire la quantité
                $sel = $pdo->prepare("
                    SELECT CE_QTE
                      FROM COMMANDE_EMBALLAGE
                     WHERE COM_ID = :c AND EMB_ID = :e
                ");
                $sel->execute(['c'=>$comId, 'e'=>$embId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$row['CE_QTE'];
                    if ($q > 0) {
                        // b) Ré-crédit stock EMBALLAGE
                        $pdo->prepare("
                            UPDATE EMBALLAGE
                               SET EMB_QTE_STOCK = EMB_QTE_STOCK + :qadd
                             WHERE EMB_ID = :id
                        ")->execute(['qadd'=>$q, 'id'=>$embId]);
                    }
                }
                // c) Suppression de la ligne
                $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID = :c AND EMB_ID = :e")
                    ->execute(['c'=>$comId, 'e'=>$embId]);
            }
            elseif ($supId > 0) {
                // a) Lire la quantité
                $sel = $pdo->prepare("
                    SELECT CS_QTE_COMMANDEE
                      FROM COMMANDE_SUPP
                     WHERE COM_ID = :c AND SUP_ID = :s
                ");
                $sel->execute(['c'=>$comId, 's'=>$supId]);
                if ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                    $q = (int)$row['CS_QTE_COMMANDEE'];
                    if ($q > 0) {
                        // b) Ré-crédit stock SUPPLEMENT
                        $pdo->prepare("
                            UPDATE SUPPLEMENT
                               SET SUP_QTE_STOCK = SUP_QTE_STOCK + :qadd
                             WHERE SUP_ID = :id
                        ")->execute(['qadd'=>$q, 'id'=>$supId]);
                    }
                }
                // c) Suppression de la ligne
                $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID = :c AND SUP_ID = :s")
                    ->execute(['c'=>$comId, 's'=>$supId]);
            }
            else {
                // Aucun identifiant reçu → on ne sait pas quoi supprimer
                err('missing_id','Aucun identifiant fourni');
            }

            // (3) Snapshot du panier après suppression
            $items    = listItems($pdo, $comId);
            $subtotal = subtotal($items);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>$subtotal]);
        }

        // ---------------------------------------------------------------------
        // Action inconnue → on renvoie une erreur 400.
        // ---------------------------------------------------------------------
        default:
            err('bad_request','Action inconnue', 400);
    }
} catch (Throwable $e){
    // Filet de sécurité global : on évite de “casser” l’API côté client.
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}
