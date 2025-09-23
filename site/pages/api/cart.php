<?php
// /site/pages/api/cart.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

/* ---------- Helpers ---------- */
function ok(array $data = [], int $status = 200){
    http_response_code($status);
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $code, string $msg = '', int $status = 400){
    http_response_code($status);
    $p = ['ok'=>false,'error'=>$code];
    if ($msg !== '') $p['msg'] = $msg;
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- DB ---------- */
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    if (!$pdo instanceof PDO) throw new RuntimeException('PDO manquant');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e){
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}

/* ---------- Auth ---------- */
$perId = (int)($_SESSION['per_id'] ?? 0);
if ($perId <= 0) err('auth', 'Connecte-toi pour ajouter au panier.', 401);

/* ---------- Const ---------- */
const ORDER_STATUS_OPEN = 'en preparation'; // doit matcher ta BDD

/* ---------- Utils ---------- */
function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_GET['qty'] ?? 1;
    $q = (int)$q;
    if ($q < 1) $q = 1;
    if ($q > 999) $q = 999;
    return $q;
}
function getOpenOrderId(PDO $pdo, int $perId): int {
    $q = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE PER_ID=:p AND COM_STATUT=:st ORDER BY COM_ID DESC LIMIT 1");
    $q->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $id = (int)($q->fetchColumn() ?: 0);
    if ($id) { $_SESSION['com_id'] = $id; return $id; }

    $ins = $pdo->prepare("INSERT INTO COMMANDE (PER_ID, COM_STATUT, COM_DATE) VALUES (:p, :st, NOW())");
    $ins->execute([':p'=>$perId, ':st'=>ORDER_STATUS_OPEN]);
    $newId = (int)$pdo->lastInsertId();
    $_SESSION['com_id'] = $newId;
    return $newId;
}
function listItems(PDO $pdo, int $comId): array {
    $sql = "
        SELECT 'produit' AS item_type, p.PRO_ID AS id, p.PRO_NOM, p.PRO_PRIX,
               cp.CP_QTE_COMMANDEE AS qty, cp.CP_TYPE_PRODUIT AS subtype
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :c

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
    $st->execute([':c'=>$comId, ':c2'=>$comId, ':c3'=>$comId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function subtotal(array $items): float {
    $s = 0.0;
    foreach ($items as $r) $s += (float)$r['PRO_PRIX'] * (int)$r['qty'];
    return round($s, 2);
}

/* ---------- Router ---------- */
$action = $_REQUEST['action'] ?? 'list';

try {
    switch ($action) {

        /* ===== ADD (FLEUR: décrément stock) ===== */
        case 'add': {
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $qty   = read_qty();
            if ($proId <= 0) err('validation', 'Produit invalide', 422);

            // produit existe ?
            $ck = $pdo->prepare("SELECT PRO_ID FROM PRODUIT WHERE PRO_ID=:id");
            $ck->execute([':id'=>$proId]);
            if (!$ck->fetchColumn()) err('not_found', 'Produit introuvable', 404);

            // vérifier qu'on est bien une fleur et lire le stock
            $sf = $pdo->prepare("SELECT FLE_QTE_STOCK FROM FLEUR WHERE PRO_ID=:id");
            $sf->execute([':id'=>$proId]);
            $stock = $sf->fetchColumn();
            if ($stock === false) err('not_fleur', 'Produit non présent dans FLEUR', 400);

            $stock = (int)$stock;
            if ($stock < $qty) err('stock', 'Stock insuffisant', 409);

            $comId = getOpenOrderId($pdo, $perId);

            // 1) décrément stock (simple)
            $u = $pdo->prepare("UPDATE FLEUR SET FLE_QTE_STOCK = FLE_QTE_STOCK - :q WHERE PRO_ID=:id");
            $u->execute([':q'=>$qty, ':id'=>$proId]);

            // 2) upsert ligne -> version *sans* ON DUPLICATE KEY (compat schémas sans index unique)
            $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p");
            $sel->execute([':c'=>$comId, ':p'=>$proId]);
            $cur = $sel->fetchColumn();
            if ($cur === false) {
                $ins = $pdo->prepare("
                    INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                    VALUES (:c, :p, :q, 'fleur')
                ");
                $ins->execute([':c'=>$comId, ':p'=>$proId, ':q'=>$qty]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
                    UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE=:q
                    WHERE COM_ID=:c AND PRO_ID=:p
                ");
                $upd->execute([':q'=>$newQ, ':c'=>$comId, ':p'=>$proId]);
            }

            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* ===== ADD_SUPPLEMENT ===== */
        case 'add_supplement': {
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
            $qty   = read_qty();
            if ($supId <= 0) err('validation','Supplément invalide',422);

            $ck = $pdo->prepare("SELECT SUP_ID FROM SUPPLEMENT WHERE SUP_ID=:id");
            $ck->execute([':id'=>$supId]);
            if (!$ck->fetchColumn()) err('not_found','Supplément introuvable',404);

            $comId = getOpenOrderId($pdo, $perId);

            $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s");
            $sel->execute([':c'=>$comId, ':s'=>$supId]);
            $cur = $sel->fetchColumn();
            if ($cur === false) {
                $ins = $pdo->prepare("
                    INSERT INTO COMMANDE_SUPP (COM_ID, SUP_ID, CS_QTE_COMMANDEE)
                    VALUES (:c, :s, :q)
                ");
                $ins->execute([':c'=>$comId, ':s'=>$supId, ':q'=>$qty]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
                    UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE=:q
                    WHERE COM_ID=:c AND SUP_ID=:s
                ");
                $upd->execute([':q'=>$newQ, ':c'=>$comId, ':s'=>$supId]);
            }

            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* ===== ADD_EMBALLAGE ===== */
        case 'add_emballage': {
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $qty   = read_qty();
            if ($embId <= 0) err('validation','Emballage invalide',422);

            $ck = $pdo->prepare("SELECT EMB_ID FROM EMBALLAGE WHERE EMB_ID=:id");
            $ck->execute([':id'=>$embId]);
            if (!$ck->fetchColumn()) err('not_found','Emballage introuvable',404);

            $comId = getOpenOrderId($pdo, $perId);

            $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e");
            $sel->execute([':c'=>$comId, ':e'=>$embId]);
            $cur = $sel->fetchColumn();
            if ($cur === false) {
                $ins = $pdo->prepare("
                    INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
                    VALUES (:c, :e, :q)
                ");
                $ins->execute([':c'=>$comId, ':e'=>$embId, ':q'=>$qty]);
            } else {
                $newQ = (int)$cur + $qty;
                $upd = $pdo->prepare("
                    UPDATE COMMANDE_EMBALLAGE SET CE_QTE=:q
                    WHERE COM_ID=:c AND EMB_ID=:e
                ");
                $upd->execute([':q'=>$newQ, ':c'=>$comId, ':e'=>$embId]);
            }

            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* ===== LIST ===== */
        case 'list': {
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) ok(['items'=>[], 'subtotal'=>0.0]);
            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        /* ===== REMOVE (récrédit stock) ===== */
        case 'remove': {
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) err('no_order','Aucune commande ouverte');

            // PRODUIT (fleur)
            if (isset($_POST['pro_id']) || isset($_GET['pro_id'])) {
                $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
                if ($proId > 0) {
                    $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p");
                    $sel->execute([':c'=>$comId, ':p'=>$proId]);
                    $q = (int)($sel->fetchColumn() ?: 0);

                    if ($q > 0) {
                        $pdo->prepare("UPDATE FLEUR SET FLE_QTE_STOCK = FLE_QTE_STOCK + :q WHERE PRO_ID=:p")
                            ->execute([':q'=>$q, ':p'=>$proId]);
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p")
                        ->execute([':c'=>$comId, ':p'=>$proId]);
                }
            }
            // EMBALLAGE
            elseif (isset($_POST['emb_id']) || isset($_GET['emb_id'])) {
                $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
                if ($embId > 0) {
                    $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e");
                    $sel->execute([':c'=>$comId, ':e'=>$embId]);
                    $q = (int)($sel->fetchColumn() ?: 0);
                    if ($q > 0) {
                        $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :q WHERE EMB_ID=:e")
                            ->execute([':q'=>$q, ':e'=>$embId]);
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e")
                        ->execute([':c'=>$comId, ':e'=>$embId]);
                }
            }
            // SUPPLÉMENT
            elseif (isset($_POST['sup_id']) || isset($_GET['sup_id'])) {
                $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
                if ($supId > 0) {
                    $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s");
                    $sel->execute([':c'=>$comId, ':s'=>$supId]);
                    $q = (int)($sel->fetchColumn() ?: 0);
                    if ($q > 0) {
                        $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :q WHERE SUP_ID=:s")
                            ->execute([':q'=>$q, ':s'=>$supId]);
                    }
                    $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s")
                        ->execute([':c'=>$comId, ':s'=>$supId]);
                }
            }
            else {
                err('missing_id','Aucun identifiant fourni');
            }

            $items = listItems($pdo, $comId);
            ok(['com_id'=>$comId, 'items'=>$items, 'subtotal'=>subtotal($items)]);
        }

        default:
            err('bad_request','Action inconnue',400);
    }
} catch (Throwable $e){
    err('server_error', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
}
