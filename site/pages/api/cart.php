<?php
// /site/pages/api/cart.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* ===== Helpers sortie ===== */
function ok(array $data = [], int $status = 200) {
    http_response_code($status);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $error, string $msg = '', int $status = 400) {
    http_response_code($status);
    $payload = ['ok' => false, 'error' => $error];
    if ($msg !== '') $payload['msg'] = $msg;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== DB ===== */
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    if (!$pdo instanceof PDO) throw new RuntimeException('PDO manquant');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    err('server_error', $e->getMessage(), 500);
}

/* ===== Auth requise (commande.js envoie des requêtes pour un client connecté) ===== */
$perId = (int)($_SESSION['per_id'] ?? 0);
if ($perId <= 0) err('auth', 'Connecte-toi pour ajouter au panier.', 401);

/* ===== Constantes (aligne avec commande.php) ===== */
const ORDER_STATUS_OPEN = 'en preparation'; // DOIT matcher ta BDD

/* ===== Utilitaires ===== */
function getOpenOrderId(PDO $pdo, int $perId): int {
    // Récupère une COMMANDE ouverte sinon la crée
    $q = $pdo->prepare("
        SELECT COM_ID
        FROM COMMANDE
        WHERE PER_ID = :p AND COM_STATUT = :st
        ORDER BY COM_ID DESC
        LIMIT 1
    ");
    $q->execute([':p' => $perId, ':st' => ORDER_STATUS_OPEN]);
    $id = (int)($q->fetchColumn() ?: 0);
    if ($id > 0) {
        $_SESSION['com_id'] = $id;
        return $id;
    }

    $ins = $pdo->prepare("
        INSERT INTO COMMANDE (PER_ID, COM_STATUT, COM_DATE)
        VALUES (:p, :st, NOW())
    ");
    $ins->execute([':p' => $perId, ':st' => ORDER_STATUS_OPEN]);
    $newId = (int)$pdo->lastInsertId();
    $_SESSION['com_id'] = $newId;
    return $newId;
}

function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_GET['qty'] ?? 1;
    $q = (int)$q;
    if ($q < 1) $q = 1;
    if ($q > 999) $q = 999;
    return $q;
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
    $st->execute([':c' => $comId, ':c2' => $comId, ':c3' => $comId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function subtotal(array $items): float {
    $sum = 0.0;
    foreach ($items as $r) {
        $p = (float)$r['PRO_PRIX'];
        $q = (int)$r['qty'];
        $sum += $p * $q;
    }
    return round($sum, 2);
}

/* ===== Router ===== */
$action = $_REQUEST['action'] ?? 'list';

try {
    switch ($action) {

        /* ---------- ADD produit (fleur/bouquet/coffret) ---------- */
        case 'add': {
            $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
            $qty   = read_qty();
            if ($proId <= 0) err('validation', 'Produit invalide', 422);

            // Vérifier l’existence du produit
            $chk = $pdo->prepare("SELECT PRO_ID FROM PRODUIT WHERE PRO_ID = :id");
            $chk->execute([':id' => $proId]);
            if (!$chk->fetchColumn()) err('not_found', 'Produit introuvable', 404);

            $comId = getOpenOrderId($pdo, $perId);

            // Upsert sans décrémenter le stock (simple et robuste)
            // Assure une contrainte UNIQUE (COM_ID, PRO_ID) dans COMMANDE_PRODUIT
            $sql = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                    VALUES (:c, :p, :q, 'fleur')
                    ON DUPLICATE KEY UPDATE CP_QTE_COMMANDEE = CP_QTE_COMMANDEE + VALUES(CP_QTE_COMMANDEE)";
            $pdo->prepare($sql)->execute([':c' => $comId, ':p' => $proId, ':q' => $qty]);

            $items = listItems($pdo, $comId);
            ok(['com_id' => $comId, 'items' => $items, 'subtotal' => subtotal($items)]);
        }

        /* ---------- ADD supplément ---------- */
        case 'add_supplement': {
            $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);
            $qty   = read_qty();
            if ($supId <= 0) err('validation', 'Supplément invalide', 422);

            $chk = $pdo->prepare("SELECT SUP_ID FROM SUPPLEMENT WHERE SUP_ID=:id");
            $chk->execute([':id' => $supId]);
            if (!$chk->fetchColumn()) err('not_found', 'Supplément introuvable', 404);

            $comId = getOpenOrderId($pdo, $perId);

            // Assure UNIQUE (COM_ID, SUP_ID)
            $sql = "INSERT INTO COMMANDE_SUPP (COM_ID, SUP_ID, CS_QTE_COMMANDEE)
                    VALUES (:c, :s, :q)
                    ON DUPLICATE KEY UPDATE CS_QTE_COMMANDEE = CS_QTE_COMMANDEE + VALUES(CS_QTE_COMMANDEE)";
            $pdo->prepare($sql)->execute([':c' => $comId, ':s' => $supId, ':q' => $qty]);

            $items = listItems($pdo, $comId);
            ok(['com_id' => $comId, 'items' => $items, 'subtotal' => subtotal($items)]);
        }

        /* ---------- ADD emballage ---------- */
        case 'add_emballage': {
            $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
            $qty   = read_qty();
            if ($embId <= 0) err('validation', 'Emballage invalide', 422);

            $chk = $pdo->prepare("SELECT EMB_ID FROM EMBALLAGE WHERE EMB_ID=:id");
            $chk->execute([':id' => $embId]);
            if (!$chk->fetchColumn()) err('not_found', 'Emballage introuvable', 404);

            $comId = getOpenOrderId($pdo, $perId);

            // Assure UNIQUE (COM_ID, EMB_ID)
            $sql = "INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
                    VALUES (:c, :e, :q)
                    ON DUPLICATE KEY UPDATE CE_QTE = CE_QTE + VALUES(CE_QTE)";
            $pdo->prepare($sql)->execute([':c' => $comId, ':e' => $embId, ':q' => $qty]);

            $items = listItems($pdo, $comId);
            ok(['com_id' => $comId, 'items' => $items, 'subtotal' => subtotal($items)]);
        }

        /* ---------- LIST ---------- */
        case 'list': {
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) ok(['items' => [], 'subtotal' => 0.0]);
            $items = listItems($pdo, $comId);
            ok(['com_id' => $comId, 'items' => $items, 'subtotal' => subtotal($items)]);
        }

        /* ---------- REMOVE ---------- */
        case 'remove': {
            $comId = (int)($_SESSION['com_id'] ?? 0);
            if ($comId <= 0) err('no_order', 'Aucune commande ouverte');

            if (isset($_POST['pro_id']) || isset($_GET['pro_id'])) {
                $id = (int)($_POST['pro_id'] ?? $_GET['pro_id']);
                if ($id > 0) {
                    $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p")
                        ->execute([':c' => $comId, ':p' => $id]);
                }
            } elseif (isset($_POST['sup_id']) || isset($_GET['sup_id'])) {
                $id = (int)($_POST['sup_id'] ?? $_GET['sup_id']);
                if ($id > 0) {
                    $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s")
                        ->execute([':c' => $comId, ':s' => $id]);
                }
            } elseif (isset($_POST['emb_id']) || isset($_GET['emb_id'])) {
                $id = (int)($_POST['emb_id'] ?? $_GET['emb_id']);
                if ($id > 0) {
                    $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e")
                        ->execute([':c' => $comId, ':e' => $id]);
                }
            } else {
                err('missing_id', 'Aucun identifiant fourni');
            }

            $items = listItems($pdo, $comId);
            ok(['com_id' => $comId, 'items' => $items, 'subtotal' => subtotal($items)]);
        }

        default:
            err('bad_request', 'Action inconnue', 400);
    }
} catch (Throwable $e) {
    // En dev, tu peux temporairement exposer le message
    err('server_error', $e->getMessage(), 500);
}
