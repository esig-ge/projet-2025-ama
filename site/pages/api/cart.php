<?php
// /site/pages/api/cart.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* =========================
   Connexion BDD
   ========================= */
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    if (!$pdo instanceof PDO) throw new RuntimeException('DB connection not returned');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   Auth (requis)
   ========================= */
if (empty($_SESSION['per_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'auth_required'], JSON_UNESCAPED_UNICODE);
    exit;
}
$perId  = (int)$_SESSION['per_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/* =========================
   Helpers / Constantes
   ========================= */

const ORDER_STATUS_OPEN = 'en preparation';

function json_ok(array $data=[]): void { echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE); exit; }
function json_err(string $msg, int $code=400): void { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

function db_has_col(PDO $pdo, string $table, string $col): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1";
    $s = $pdo->prepare($sql); $s->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$s->fetchColumn();
}

function getOrCreateOpenOrder(PDO $pdo, int $perId): int {
    if (!empty($_SESSION['com_id'])) return (int)$_SESSION['com_id'];

    $q = $pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE PER_ID=:per AND COM_STATUT=:st ORDER BY COM_ID DESC LIMIT 1");
    $q->execute(['per'=>$perId,'st'=>ORDER_STATUS_OPEN]);
    $id = (int)($q->fetchColumn() ?: 0);
    if ($id) { $_SESSION['com_id'] = $id; return $id; }

    $cols = ['PER_ID','COM_STATUT','COM_DATE','COM_DESCRIPTION','COM_PTS_CUMULE'];
    $vals = [':per',':st','NOW()',':desc','0'];
    foreach (['LIV_ID','RAB_ID','PAI_ID','STRIPE_SESSION_ID','TOTAL_PAYER_CHF'] as $opt) {
        if (db_has_col($pdo,'COMMANDE',$opt)) { $cols[]=$opt; $vals[]='NULL'; }
    }
    $sql = "INSERT INTO COMMANDE (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    $pdo->prepare($sql)->execute([':per'=>$perId,':st'=>ORDER_STATUS_OPEN,':desc'=>'Panier en cours']);
    $_SESSION['com_id'] = (int)$pdo->lastInsertId();
    return (int)$_SESSION['com_id'];
}

function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_POST['quantity'] ?? $_GET['qty'] ?? 1;
    $q = (int)$q; if ($q<1) $q=1; if ($q>999) $q=999; return $q;
}

function resolveSubtype(PDO $pdo, int $proId, ?string $typeParam = null): array {
    $types = [
        'fleur'   => ['table'=>'FLEUR',   'stock_col'=>'FLE_QTE_STOCK'],
        'bouquet' => ['table'=>'BOUQUET', 'stock_col'=>'BOU_QTE_STOCK'],
        'coffret' => ['table'=>'COFFRET', 'stock_col'=>'COF_QTE_STOCK'],
    ];
    $tp = strtolower(trim((string)$typeParam));
    if (isset($types[$tp])) {
        $chk = $pdo->prepare("SELECT 1 FROM {$types[$tp]['table']} WHERE PRO_ID=:id");
        $chk->execute([':id'=>$proId]);
        if ($chk->fetchColumn()) return ['type'=>$tp] + $types[$tp];
    }
    foreach ($types as $k=>$meta) {
        $chk = $pdo->prepare("SELECT 1 FROM {$meta['table']} WHERE PRO_ID=:id");
        $chk->execute([':id'=>$proId]);
        if ($chk->fetchColumn()) return ['type'=>$k] + $meta;
    }
    json_err('unknown_product_type', 400);
    return [];
}

function addSupplementLine(PDO $pdo, int $comId, int $supId, int $qty): void {
    $sql = "INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE)
            VALUES (:s,:c,:q)
            ON DUPLICATE KEY UPDATE CS_QTE_COMMANDEE = CS_QTE_COMMANDEE + VALUES(CS_QTE_COMMANDEE)";
    $pdo->prepare($sql)->execute(['s'=>$supId,'c'=>$comId,'q'=>$qty]);
}

function listOrder(PDO $pdo, int $comId): array {
    $items = [];

    $p = $pdo->prepare("SELECT 'produit' AS item_type, cp.PRO_ID AS id, p.PRO_NOM, p.PRO_PRIX, cp.CP_QTE_COMMANDEE,
                               p.PRO_NOM AS name, p.PRO_PRIX AS price, cp.CP_QTE_COMMANDEE AS qty, '' AS img
                        FROM COMMANDE_PRODUIT cp
                        JOIN PRODUIT p ON p.PRO_ID=cp.PRO_ID
                        WHERE cp.COM_ID=:c
                        ORDER BY p.PRO_NOM");
    $p->execute(['c'=>$comId]); $items = array_merge($items, $p->fetchAll(PDO::FETCH_ASSOC));

    $e = $pdo->prepare("SELECT 'emballage' AS item_type, ce.EMB_ID AS id, e.EMB_NOM AS PRO_NOM, 0.00 AS PRO_PRIX, ce.CE_QTE AS CP_QTE_COMMANDEE,
                               e.EMB_NOM AS name, 0.00 AS price, ce.CE_QTE AS qty, '' AS img
                        FROM COMMANDE_EMBALLAGE ce
                        JOIN EMBALLAGE e ON e.EMB_ID=ce.EMB_ID
                        WHERE ce.COM_ID=:c
                        ORDER BY e.EMB_NOM");
    $e->execute(['c'=>$comId]); $items = array_merge($items, $e->fetchAll(PDO::FETCH_ASSOC));

    $s = $pdo->prepare("SELECT 'supplement' AS item_type, cs.SUP_ID AS id, s.SUP_NOM AS PRO_NOM, s.SUP_PRIX_UNITAIRE AS PRO_PRIX, cs.CS_QTE_COMMANDEE AS CP_QTE_COMMANDEE,
                               s.SUP_NOM AS name, s.SUP_PRIX_UNITAIRE AS price, cs.CS_QTE_COMMANDEE AS qty, '' AS img
                        FROM COMMANDE_SUPP cs
                        JOIN SUPPLEMENT s ON s.SUP_ID=cs.SUP_ID
                        WHERE cs.COM_ID=:c
                        ORDER BY s.SUP_NOM");
    $s->execute(['c'=>$comId]); $items = array_merge($items, $s->fetchAll(PDO::FETCH_ASSOC));

    return $items;
}

function subtotalFromItems(array $items): float {
    $sum = 0.0;
    foreach ($items as $it) {
        $unit = isset($it['price']) ? (float)$it['price'] : ((isset($it['PRO_PRIX'])?(float)$it['PRO_PRIX']:0.0));
        $qte  = isset($it['qty'])   ? (int)$it['qty']   : ((isset($it['CP_QTE_COMMANDEE'])?(int)$it['CP_QTE_COMMANDEE']:1));
        $sum += $unit * $qte;
    }
    return round($sum, 2);
}

/* =========================
   ROUTES
   ========================= */
try {
    if (in_array($action, ['add','add_emballage','add_supplement'], true)) {
        $qty   = read_qty();
        $comId = getOrCreateOpenOrder($pdo, $perId);

        $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
        $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
        $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

        // === PRODUIT (fleur/bouquet/coffret) avec décrément du stock ===
        if ($proId > 0 && $action === 'add') {
            $typeParam = $_POST['type'] ?? $_GET['type'] ?? null;
            $sub = resolveSubtype($pdo, $proId, $typeParam);

            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare("UPDATE {$sub['table']} SET {$sub['stock_col']} = {$sub['stock_col']} - :q
                                      WHERE PRO_ID=:id AND {$sub['stock_col']} >= :q");
                $upd->execute([':q'=>$qty, ':id'=>$proId]);
                if ($upd->rowCount() === 0) { $pdo->rollBack(); json_err('insufficient_stock', 409); }

                $sql = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                        VALUES (:c,:p,:q,:t)
                        ON DUPLICATE KEY UPDATE CP_QTE_COMMANDEE = CP_QTE_COMMANDEE + VALUES(CP_QTE_COMMANDEE)";
                $pdo->prepare($sql)->execute(['c'=>$comId,'p'=>$proId,'q'=>$qty,'t'=>$sub['type']]);

                $sel = $pdo->prepare("SELECT p.PRO_NOM, t.{$sub['stock_col']} AS stock_left
                                      FROM PRODUIT p JOIN {$sub['table']} t ON t.PRO_ID=p.PRO_ID
                                      WHERE p.PRO_ID=:id LIMIT 1");
                $sel->execute([':id'=>$proId]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('product_not_found',404); }

                $name = $row['PRO_NOM']; $stockLeft = (int)$row['stock_left'];
                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok(['com_id'=>$comId,'type'=>$sub['type'],'proId'=>$proId,'name'=>$name,'stockLeft'=>$stockLeft,'items'=>$items,'subtotal'=>$subtotal]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        // === EMBALLAGE avec décrément du stock ===
        if ($embId > 0 && $action === 'add_emballage') {
            $pdo->beginTransaction();
            try {
                // décrément atomique
                $u = $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK - :q
                                    WHERE EMB_ID=:id AND EMB_QTE_STOCK >= :q");
                $u->execute([':q'=>$qty, ':id'=>$embId]);
                if ($u->rowCount() === 0) { $pdo->rollBack(); json_err('insufficient_stock', 409); }

                // upsert ligne commande
                $sql = "INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
                        VALUES (:c,:e,:q)
                        ON DUPLICATE KEY UPDATE CE_QTE = CE_QTE + VALUES(CE_QTE)";
                $pdo->prepare($sql)->execute(['c'=>$comId,'e'=>$embId,'q'=>$qty]);

                // info retour
                $s = $pdo->prepare("SELECT EMB_NOM, EMB_QTE_STOCK AS stock_left FROM EMBALLAGE WHERE EMB_ID=:id LIMIT 1");
                $s->execute([':id'=>$embId]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('emballage_not_found',404); }

                $name = $row['EMB_NOM']; $stockLeft = (int)$row['stock_left'];
                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok(['com_id'=>$comId,'type'=>'emballage','embId'=>$embId,'name'=>$name,'stockLeft'=>$stockLeft,'items'=>$items,'subtotal'=>$subtotal]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        // === SUPPLÉMENT avec décrément du stock ===
        if ($supId > 0 && $action === 'add_supplement') {
            $pdo->beginTransaction();
            try {
                $u = $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK - :q
                                    WHERE SUP_ID=:id AND SUP_QTE_STOCK >= :q");
                $u->execute([':q'=>$qty, ':id'=>$supId]);
                if ($u->rowCount() === 0) { $pdo->rollBack(); json_err('insufficient_stock', 409); }

                addSupplementLine($pdo, $comId, $supId, $qty);

                $s = $pdo->prepare("SELECT SUP_NOM, SUP_QTE_STOCK AS stock_left FROM SUPPLEMENT WHERE SUP_ID=:id LIMIT 1");
                $s->execute([':id'=>$supId]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('supplement_not_found',404); }

                $name = $row['SUP_NOM']; $stockLeft = (int)$row['stock_left'];
                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok(['com_id'=>$comId,'type'=>'supplement','supId'=>$supId,'name'=>$name,'stockLeft'=>$stockLeft,'items'=>$items,'subtotal'=>$subtotal]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        json_err('missing_pro_or_emb_or_sup_id', 400);
    }

    // === LISTER ===
    if ($action === 'list') {
        $comId = (int)($_SESSION['com_id'] ?? 0);
        if (!$comId) json_ok(['items'=>[],'subtotal'=>0.0]);
        $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
        json_ok(['com_id'=>$comId,'items'=>$items,'subtotal'=>$subtotal]);
    }

    // === REMOVE (produit / emballage / supplément) ===
    if ($action === 'remove') {
        $comId = (int)($_SESSION['com_id'] ?? 0);
        if (!$comId) json_err('no_order');

        $proId   = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
        $embId   = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
        $supId   = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
        $qtyParam = (int)($_POST['qty'] ?? $_GET['qty'] ?? 0);

        // PRODUIT : restock
        if ($proId > 0) {
            $pdo->beginTransaction();
            try {
                $sel = $pdo->prepare("SELECT CP_QTE_COMMANDEE, CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p FOR UPDATE");
                $sel->execute([':c'=>$comId, ':p'=>$proId]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('not_in_cart',404); }

                $lineQty = (int)$row['CP_QTE_COMMANDEE'];
                $type    = strtolower((string)$row['CP_TYPE_PRODUIT']);
                $map = [
                    'fleur'   => ['table'=>'FLEUR',   'stock_col'=>'FLE_QTE_STOCK'],
                    'bouquet' => ['table'=>'BOUQUET', 'stock_col'=>'BOU_QTE_STOCK'],
                    'coffret' => ['table'=>'COFFRET', 'stock_col'=>'COF_QTE_STOCK'],
                ];
                if (!isset($map[$type])) { $pdo->rollBack(); json_err('bad_type_in_cart', 500); }

                $toRemove = $qtyParam > 0 ? min($qtyParam, $lineQty) : $lineQty;

                if ($toRemove < $lineQty) {
                    $pdo->prepare("UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE = CP_QTE_COMMANDEE - :q WHERE COM_ID=:c AND PRO_ID=:p")
                        ->execute([':q'=>$toRemove, ':c'=>$comId, ':p'=>$proId]);
                } else {
                    $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p")
                        ->execute([':c'=>$comId, ':p'=>$proId]);
                }

                $pdo->prepare("UPDATE {$map[$type]['table']} SET {$map[$type]['stock_col']} = {$map[$type]['stock_col']} + :q WHERE PRO_ID=:id")
                    ->execute([':q'=>$toRemove, ':id'=>$proId]);

                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok(['com_id'=>$comId,'restocked'=>$toRemove,'proId'=>$proId,'type'=>$type,'items'=>$items,'subtotal'=>$subtotal]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        // EMBALLAGE : restock immédiat (partiel ou total)
        if ($embId > 0) {
            $pdo->beginTransaction();
            try {
                $sel = $pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e FOR UPDATE");
                $sel->execute([':c'=>$comId, ':e'=>$embId]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('not_in_cart',404); }

                $lineQty  = (int)$row['CE_QTE'];
                $toRemove = $qtyParam > 0 ? min($qtyParam, $lineQty) : $lineQty;

                if ($toRemove < $lineQty) {
                    $pdo->prepare("UPDATE COMMANDE_EMBALLAGE SET CE_QTE = CE_QTE - :q WHERE COM_ID=:c AND EMB_ID=:e")
                        ->execute([':q'=>$toRemove, ':c'=>$comId, ':e'=>$embId]);
                } else {
                    $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e")
                        ->execute([':c'=>$comId, ':e'=>$embId]);
                }

                $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK = EMB_QTE_STOCK + :q WHERE EMB_ID=:id")
                    ->execute([':q'=>$toRemove, ':id'=>$embId]);

                // retourner stockLeft + nom
                $cur = $pdo->prepare("SELECT EMB_QTE_STOCK AS stock_left, EMB_NOM FROM EMBALLAGE WHERE EMB_ID=:id");
                $cur->execute([':id'=>$embId]);
                $info = $cur->fetch(PDO::FETCH_ASSOC) ?: ['stock_left'=>null,'EMB_NOM'=>null];

                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok([
                    'com_id'    => $comId,
                    'restocked' => $toRemove,
                    'embId'     => $embId,
                    'name'      => $info['EMB_NOM'] ?? null,
                    'stockLeft' => isset($info['stock_left']) ? (int)$info['stock_left'] : null,
                    'items'     => $items,
                    'subtotal'  => $subtotal,
                ]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        // SUPPLÉMENT : restock immédiat
        if ($supId > 0) {
            $pdo->beginTransaction();
            try {
                $sel = $pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s FOR UPDATE");
                $sel->execute([':c'=>$comId, ':s'=>$supId]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $pdo->rollBack(); json_err('not_in_cart',404); }

                $lineQty  = (int)$row['CS_QTE_COMMANDEE'];
                $toRemove = $qtyParam > 0 ? min($qtyParam, $lineQty) : $lineQty;

                if ($toRemove < $lineQty) {
                    $pdo->prepare("UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE = CS_QTE_COMMANDEE - :q WHERE COM_ID=:c AND SUP_ID=:s")
                        ->execute([':q'=>$toRemove, ':c'=>$comId, ':s'=>$supId]);
                } else {
                    $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s")
                        ->execute([':c'=>$comId, ':s'=>$supId]);
                }

                $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK = SUP_QTE_STOCK + :q WHERE SUP_ID=:id")
                    ->execute([':q'=>$toRemove, ':id'=>$supId]);

                $cur = $pdo->prepare("SELECT SUP_QTE_STOCK AS stock_left, SUP_NOM FROM SUPPLEMENT WHERE SUP_ID=:id");
                $cur->execute([':id'=>$supId]);
                $info = $cur->fetch(PDO::FETCH_ASSOC) ?: ['stock_left'=>null,'SUP_NOM'=>null];

                $pdo->commit();

                $items = listOrder($pdo, $comId); $subtotal = subtotalFromItems($items);
                json_ok([
                    'com_id'    => $comId,
                    'restocked' => $toRemove,
                    'supId'     => $supId,
                    'name'      => $info['SUP_NOM'] ?? null,
                    'stockLeft' => isset($info['stock_left']) ? (int)$info['stock_left'] : null,
                    'items'     => $items,
                    'subtotal'  => $subtotal,
                ]);
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }

        json_err('missing_id');
    }

    json_err('bad_action', 400);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
