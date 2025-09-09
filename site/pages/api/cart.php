<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/config/connexionBDD.php';
/** @var PDO $pdo */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
/* =========================
   DEV / PROD SWITCH
   ========================= */
const DEV_FORCE_LOGIN = true;
const DEV_EMAIL       = 'dev.panier@dkbloom.local';

$IS_DEV = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false)
    || (isset($_GET['dev']));

// ⚠️ ne plus reset à chaque requête : reset volontaire seulement
if ($IS_DEV && isset($_GET['reset'])) {
    unset($_SESSION['com_id']);
}

/* ====== login forcé DEV ====== */
if (DEV_FORCE_LOGIN && empty($_SESSION['per_id'])) {
    $_SESSION['per_id'] = (function(PDO $pdo): int {
        $q = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :mail LIMIT 1");
        $q->execute(['mail' => DEV_EMAIL]);
        $perId = $q->fetchColumn();

        if (!$perId) {
            $insertP = $pdo->prepare("
                INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
                VALUES ('Dev','Panier', :mail, 'DevTest@1234!', '0791111111')
            ");
            $insertP->execute(['mail' => DEV_EMAIL]);
            $perId = (int)$pdo->lastInsertId();
        }
        $q = $pdo->prepare("SELECT 1 FROM CLIENT WHERE PER_ID = :id");
        $q->execute(['id' => $perId]);
        if (!$q->fetchColumn()) {
            $insC = $pdo->prepare("
                INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
                VALUES (:id, '2000-01-01', 0)
            ");
            $insC->execute(['id' => $perId]);
        }
        return (int)$perId;
    })($pdo);
}

if (empty($_SESSION['per_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$perId  = (int) $_SESSION['per_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/* ====== utilitaires ====== */
function getOrCreateOpenOrder(PDO $pdo, int $perId, bool $isDev): int {
    if (!empty($_SESSION['com_id'])) return (int) $_SESSION['com_id'];

    if (!$isDev) {
        $q = $pdo->prepare("SELECT COM_ID FROM COMMANDE
                            WHERE PER_ID=:per AND COM_STATUT='en préparation'
                            ORDER BY COM_ID DESC LIMIT 1");
        $q->execute(['per' => $perId]);
        $id = $q->fetchColumn();
        if ($id) { $_SESSION['com_id'] = (int)$id; return (int)$id; }
    }

    $q = $pdo->prepare("INSERT INTO COMMANDE
        (PER_ID, LIV_ID, RAB_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
        VALUES (:per, NULL, NULL, 'en préparation', CURRENT_DATE, 'Panier en cours', 0)");
    $q->execute(['per' => $perId]);
    $newId = (int)$pdo->lastInsertId();
    $_SESSION['com_id'] = $newId;
    return $newId;
}

function guessProductType(PDO $pdo, int $proId): string {
    foreach (['FLEUR' => 'fleur', 'BOUQUET' => 'bouquet', 'COFFRET' => 'coffret'] as $table => $type) {
        $s = $pdo->prepare("SELECT 1 FROM {$table} WHERE PRO_ID=:id");
        $s->execute(['id' => $proId]);
        if ($s->fetchColumn()) return $type;
    }
    return 'bouquet';
}

function addEmballage(PDO $pdo, int $comId, int $embId, int $qty): void {
    $chk = $pdo->prepare("SELECT 1 FROM EMBALLAGE WHERE EMB_ID=:id");
    $chk->execute(['id'=>$embId]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'emballage_not_found']);
        exit;
    }
    $sql = "INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
            VALUES (:com,:emb,:q)
            ON DUPLICATE KEY UPDATE CE_QTE = CE_QTE + VALUES(CE_QTE)";
    $pdo->prepare($sql)->execute(['com'=>$comId,'emb'=>$embId,'q'=>$qty]);
}

function addSupplement(PDO $pdo, int $comId, int $supId, int $qty): void {
    $chk = $pdo->prepare("SELECT 1 FROM SUPPLEMENT WHERE SUP_ID=:id");
    $chk->execute(['id'=>$supId]);
    if (!$chk->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'supplement_not_found']);
        exit;
    }
    $sql = "INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE)
            VALUES (:sup,:com,:q)
            ON DUPLICATE KEY UPDATE CS_QTE_COMMANDEE = CS_QTE_COMMANDEE + VALUES(CS_QTE_COMMANDEE)";
    $pdo->prepare($sql)->execute(['sup'=>$supId,'com'=>$comId,'q'=>$qty]);
}

function listOrder(PDO $pdo, int $comId): array {
    $p = $pdo->prepare("SELECT 'produit' AS item_type, cp.PRO_ID AS id, p.PRO_NOM AS nom,
                               p.PRO_PRIX AS prix_unitaire, cp.CP_QTE_COMMANDEE AS qte
                        FROM COMMANDE_PRODUIT cp
                        JOIN PRODUIT p ON p.PRO_ID=cp.PRO_ID
                        WHERE cp.COM_ID=:com
                        ORDER BY p.PRO_NOM");
    $p->execute(['com'=>$comId]);
    $items = $p->fetchAll(PDO::FETCH_ASSOC);

    $e = $pdo->prepare("SELECT 'emballage' AS item_type, ce.EMB_ID AS id, e.EMB_NOM AS nom,
                               0.00 AS prix_unitaire, ce.CE_QTE AS qte
                        FROM COMMANDE_EMBALLAGE ce
                        JOIN EMBALLAGE e ON e.EMB_ID=ce.EMB_ID
                        WHERE ce.COM_ID=:com
                        ORDER BY e.EMB_NOM");
    $e->execute(['com'=>$comId]);
    $items = array_merge($items, $e->fetchAll(PDO::FETCH_ASSOC));

    $s = $pdo->prepare("SELECT 'supplement' AS item_type, cs.SUP_ID AS id, s.SUP_NOM AS nom,
                               s.SUP_PRIX_UNITAIRE AS prix_unitaire, cs.CS_QTE_COMMANDEE AS qte
                        FROM COMMANDE_SUPP cs
                        JOIN SUPPLEMENT s ON s.SUP_ID=cs.SUP_ID
                        WHERE cs.COM_ID=:com
                        ORDER BY s.SUP_NOM");
    $s->execute(['com'=>$comId]);
    return array_merge($items, $s->fetchAll(PDO::FETCH_ASSOC));
}

/* =========================
   ROUTES
   ========================= */
try {
    if ($action === 'add') {
        $qty   = max(1, (int)($_POST['qty'] ?? $_GET['qty'] ?? 1));
        $comId = getOrCreateOpenOrder($pdo, $perId, $IS_DEV);

        $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
        $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
        $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

        if ($proId > 0) {
            $chk = $pdo->prepare("SELECT 1 FROM PRODUIT WHERE PRO_ID=:id");
            $chk->execute(['id' => $proId]);
            if (!$chk->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['ok'=>false,'error'=>'product_not_found']);
                exit;
            }
            $type = guessProductType($pdo, $proId);
            $sql  = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                     VALUES (:com,:pro,:q,:t)
                     ON DUPLICATE KEY UPDATE CP_QTE_COMMANDEE = CP_QTE_COMMANDEE + VALUES(CP_QTE_COMMANDEE)";
            $pdo->prepare($sql)->execute(['com'=>$comId,'pro'=>$proId,'q'=>$qty,'t'=>$type]);

        } elseif ($embId > 0) {
            addEmballage($pdo, $comId, $embId, $qty);

        } elseif ($supId > 0) {
            addSupplement($pdo, $comId, $supId, $qty);

        } else {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'missing_pro_or_emb_or_sup_id']);
            exit;
        }

        echo json_encode(['ok'=>true,'com_id'=>$comId,'items'=>listOrder($pdo,$comId)]);
        exit;
    }

    if ($action === 'list') {
        $comId = $_SESSION['com_id'] ?? null;
        if (!$comId) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }
        echo json_encode(['ok'=>true,'com_id'=>(int)$comId,'items'=>listOrder($pdo,(int)$comId)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_action']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}
