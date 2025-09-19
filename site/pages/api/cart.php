<?php
// /site/pages/api/cart.php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

// ===== Connexion BDD
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('DB connection not returned');
    }
    // Important : exceptions pour éviter les échecs silencieux
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   DEV / PROD SWITCH
   ========================= */
const DEV_FORCE_LOGIN = false;
const DEV_EMAIL       = 'dev.panier@dkbloom.local';

$IS_DEV = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || isset($_GET['dev']);

if ($IS_DEV && isset($_GET['reset'])) {
    unset($_SESSION['com_id']);
}

/* ====== login forcé DEV ====== */
if (DEV_FORCE_LOGIN && empty($_SESSION['per_id'])) {
    $_SESSION['per_id'] = (function (PDO $pdo): int {
        $q = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :mail LIMIT 1");
        $q->execute(['mail' => DEV_EMAIL]);
        $perId = (int)($q->fetchColumn() ?: 0);

        if (!$perId) {
            $pdo->prepare("
                INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
                VALUES ('Dev','Panier', :mail, 'DevTest@1234!', '0791111111')
            ")->execute(['mail' => DEV_EMAIL]);
            $perId = (int)$pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
                VALUES (:id, '2000-01-01', 0)
            ")->execute(['id' => $perId]);
        } else {
            $q = $pdo->prepare("SELECT 1 FROM CLIENT WHERE PER_ID = :id");
            $q->execute(['id' => $perId]);
            if (!$q->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO CLIENT (PER_ID, CLI_DATENAISSANCE, CLI_NB_POINTS_FIDELITE)
                    VALUES (:id, '2000-01-01', 0)
                ")->execute(['id' => $perId]);
            }
        }
        return $perId;
    })($pdo);
}

if (empty($_SESSION['per_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'auth_required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$perId  = (int)$_SESSION['per_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/* =========================
   HELPERS
   ========================= */

// --- Mets ici la valeur EXACTE existant dans COMMANDE.COM_STATUT
// Exemples possibles: 'PANIER' | 'en préparation' | 'en_preparation'
const ORDER_STATUS_OPEN = 'PANIER';

function json_ok(array $data=[]): void {
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $msg, int $code=400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Teste si une colonne existe (pour construire l'INSERT dynamiquement) */
function db_has_col(PDO $pdo, string $table, string $col): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
              AND COLUMN_NAME = :c
            LIMIT 1";
    $s = $pdo->prepare($sql);
    $s->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$s->fetchColumn();
}

function getOrCreateOpenOrder(PDO $pdo, int $perId, bool $isDev): int {
    if (!empty($_SESSION['com_id'])) return (int)$_SESSION['com_id'];

    // 1) Réutiliser une commande ouverte existante
    if (!$isDev) {
        $q = $pdo->prepare("
            SELECT COM_ID
            FROM COMMANDE
            WHERE PER_ID = :per AND COM_STATUT = :st
            ORDER BY COM_ID DESC
            LIMIT 1
        ");
        $q->execute(['per' => $perId, 'st' => ORDER_STATUS_OPEN]);
        $id = (int)($q->fetchColumn() ?: 0);
        if ($id) {
            $_SESSION['com_id'] = $id;
            return $id;
        }
    }

    // 2) Construire un INSERT qui s'adapte aux colonnes présentes
    $cols = ['PER_ID', 'COM_STATUT', 'COM_DATE', 'COM_DESCRIPTION', 'COM_PTS_CUMULE'];
    $vals = [':per', ':st', 'NOW()', ':desc', '0'];

    foreach (['LIV_ID','RAB_ID','PAI_ID','STRIPE_SESSION_ID','TOTAL_PAYER_CHF'] as $opt) {
        if (db_has_col($pdo, 'COMMANDE', $opt)) {
            $cols[] = $opt;
            $vals[] = 'NULL';
        }
    }

    $sql = "INSERT INTO COMMANDE (" . implode(',', $cols) . ")
            VALUES (" . implode(',', $vals) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':per'  => $perId,
        ':st'   => ORDER_STATUS_OPEN,
        ':desc' => 'Panier en cours',
    ]);

    $newId = (int)$pdo->lastInsertId();
    $_SESSION['com_id'] = $newId;
    return $newId;
}

/** Normalise la quantité reçue (qty|qte|quantity) → int [1..999] */
function read_qty(): int {
    $q = $_POST['qty'] ?? $_POST['qte'] ?? $_POST['quantity'] ?? $_GET['qty'] ?? 1;
    $q = (int)$q;
    if ($q < 1)   $q = 1;
    if ($q > 999) $q = 999;
    return $q;
}

/** Retourne 'Bouquet' | 'Fleur' | 'Coffret' (Majuscule initiale) pour coller aux ENUM fréquents */
function guessProductType(PDO $pdo, int $proId): string {
    foreach (['FLEUR'=>'Fleur', 'BOUQUET'=>'Bouquet', 'COFFRET'=>'Coffret'] as $table => $type) {
        $s = $pdo->prepare("SELECT 1 FROM {$table} WHERE PRO_ID=:id");
        $s->execute(['id' => $proId]);
        if ($s->fetchColumn()) return $type;
    }
    return 'Bouquet';
}

function addEmballage(PDO $pdo, int $comId, int $embId, int $qty): void {
    $chk = $pdo->prepare("SELECT 1 FROM EMBALLAGE WHERE EMB_ID=:id");
    $chk->execute(['id' => $embId]);
    if (!$chk->fetchColumn()) json_err('emballage_not_found', 404);

    $sql = "INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
            VALUES (:c,:e,:q)
            ON DUPLICATE KEY UPDATE CE_QTE = CE_QTE + VALUES(CE_QTE)";
    $pdo->prepare($sql)->execute(['c'=>$comId,'e'=>$embId,'q'=>$qty]);
}

function addSupplement(PDO $pdo, int $comId, int $supId, int $qty): void {
    $chk = $pdo->prepare("SELECT 1 FROM SUPPLEMENT WHERE SUP_ID=:id");
    $chk->execute(['id' => $supId]);
    if (!$chk->fetchColumn()) json_err('supplement_not_found', 404);

    $sql = "INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE)
            VALUES (:s,:c,:q)
            ON DUPLICATE KEY UPDATE CS_QTE_COMMANDEE = CS_QTE_COMMANDEE + VALUES(CS_QTE_COMMANDEE)";
    $pdo->prepare($sql)->execute(['s'=>$supId,'c'=>$comId,'q'=>$qty]);
}

function listOrder(PDO $pdo, int $comId): array {
    // PRODUITS
    $p = $pdo->prepare("
        SELECT
            'produit'             AS item_type,
            cp.PRO_ID             AS id,

            p.PRO_NOM             AS PRO_NOM,
            p.PRO_PRIX            AS PRO_PRIX,
            cp.CP_QTE_COMMANDEE   AS CP_QTE_COMMANDEE,

            p.PRO_NOM             AS name,
            p.PRO_PRIX            AS price,
            cp.CP_QTE_COMMANDEE   AS qty,
            ''                    AS img
        FROM COMMANDE_PRODUIT cp
        JOIN PRODUIT p ON p.PRO_ID = cp.PRO_ID
        WHERE cp.COM_ID = :c
        ORDER BY p.PRO_NOM
    ");
    $p->execute(['c'=>$comId]);
    $items = $p->fetchAll(PDO::FETCH_ASSOC);

    // EMBALLAGES
    $e = $pdo->prepare("
        SELECT
            'emballage'           AS item_type,
            ce.EMB_ID             AS id,

            e.EMB_NOM             AS PRO_NOM,
            0.00                  AS PRO_PRIX,
            ce.CE_QTE             AS CP_QTE_COMMANDEE,

            e.EMB_NOM             AS name,
            0.00                  AS price,
            ce.CE_QTE             AS qty,
            ''                    AS img
        FROM COMMANDE_EMBALLAGE ce
        JOIN EMBALLAGE e ON e.EMB_ID = ce.EMB_ID
        WHERE ce.COM_ID = :c
        ORDER BY e.EMB_NOM
    ");
    $e->execute(['c'=>$comId]);
    $items = array_merge($items, $e->fetchAll(PDO::FETCH_ASSOC));

    // SUPPLÉMENTS
    $s = $pdo->prepare("
        SELECT
            'supplement'          AS item_type,
            cs.SUP_ID             AS id,

            s.SUP_NOM             AS PRO_NOM,
            s.SUP_PRIX_UNITAIRE   AS PRO_PRIX,
            cs.CS_QTE_COMMANDEE   AS CP_QTE_COMMANDEE,

            s.SUP_NOM             AS name,
            s.SUP_PRIX_UNITAIRE   AS price,
            cs.CS_QTE_COMMANDEE   AS qty,
            ''                    AS img
        FROM COMMANDE_SUPP cs
        JOIN SUPPLEMENT s ON s.SUP_ID = cs.SUP_ID
        WHERE cs.COM_ID = :c
        ORDER BY s.SUP_NOM
    ");
    $s->execute(['c'=>$comId]);
    $items = array_merge($items, $s->fetchAll(PDO::FETCH_ASSOC));

    return $items;
}

function subtotalFromItems(array $items): float {
    $sum = 0.0;
    foreach ($items as $it) {
        $unit = isset($it['price']) ? (float)$it['price']
            : (isset($it['PRO_PRIX']) ? (float)$it['PRO_PRIX'] : 0.0);
        $qte  = isset($it['qty']) ? (int)$it['qty']
            : (isset($it['CP_QTE_COMMANDEE']) ? (int)$it['CP_QTE_COMMANDEE'] : 1);
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
        $comId = getOrCreateOpenOrder($pdo, $perId, $IS_DEV);

        $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
        $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
        $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

        $pdo->beginTransaction();
        try {
            if ($proId > 0) {
                // check produit
                $chk = $pdo->prepare("SELECT 1 FROM PRODUIT WHERE PRO_ID=:id");
                $chk->execute(['id' => $proId]);
                if (!$chk->fetchColumn()) json_err('product_not_found', 404);

                $type = guessProductType($pdo, $proId); // 'Bouquet' | 'Fleur' | 'Coffret'

                // IMPORTANT : clé unique (COM_ID, PRO_ID) attendue pour ON DUPLICATE
                $sql = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                        VALUES (:c,:p,:q,:t)
                        ON DUPLICATE KEY UPDATE CP_QTE_COMMANDEE = CP_QTE_COMMANDEE + VALUES(CP_QTE_COMMANDEE)";
                $pdo->prepare($sql)->execute(['c'=>$comId,'p'=>$proId,'q'=>$qty,'t'=>$type]);

            } elseif ($embId > 0) {
                addEmballage($pdo, $comId, $embId, $qty);

            } elseif ($supId > 0) {
                addSupplement($pdo, $comId, $supId, $qty);

            } else {
                json_err('missing_pro_or_emb_or_sup_id', 400);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $items    = listOrder($pdo, $comId);
        $subtotal = subtotalFromItems($items);
        json_ok(['com_id'=>$comId,'items'=>$items,'subtotal'=>$subtotal]);
    }

    if ($action === 'list') {
        $comId = (int)($_SESSION['com_id'] ?? 0);
        if (!$comId) json_ok(['items'=>[],'subtotal'=>0.0]);
        $items    = listOrder($pdo, $comId);
        $subtotal = subtotalFromItems($items);
        json_ok(['com_id'=>$comId,'items'=>$items,'subtotal'=>$subtotal]);
    }

    if ($action === 'remove') {
        $comId = (int)($_SESSION['com_id'] ?? 0);
        if (!$comId) json_err('no_order');

        $proId = (int)($_POST['pro_id'] ?? $_GET['pro_id'] ?? 0);
        $embId = (int)($_POST['emb_id'] ?? $_GET['emb_id'] ?? 0);
        $supId = (int)($_POST['sup_id'] ?? $_GET['sup_id'] ?? 0);

        if ($proId > 0) {
            $pdo->prepare("DELETE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p")
                ->execute(['c'=>$comId,'p'=>$proId]);
        } elseif ($embId > 0) {
            $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e")
                ->execute(['c'=>$comId,'e'=>$embId]);
        } elseif ($supId > 0) {
            $pdo->prepare("DELETE FROM COMMANDE_SUPP WHERE COM_ID=:c AND SUP_ID=:s")
                ->execute(['c'=>$comId,'s'=>$supId]);
        } else {
            json_err('missing_id');
        }

        $items    = listOrder($pdo, $comId);
        $subtotal = subtotalFromItems($items);
        json_ok(['items'=>$items,'subtotal'=>$subtotal]);
    }

    json_err('bad_action', 400);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
