<?php
// site/api/cart.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database/config/connexionBDD.php'; // OK depuis /site/api
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ============================
   DEV MODE (à désactiver en prod)
   ============================ */
const DEV_FORCE_LOGIN = true;                               // <<< mets false en prod
const DEV_EMAIL       = 'dev.panier@dkbloom.local';         // mail unique de test

if (DEV_FORCE_LOGIN && empty($_SESSION['per_id'])) {
    // crée / réutilise un client de test et pose $_SESSION['per_id']
    $_SESSION['per_id'] = (function(PDO $pdo): int {
        // 1) déjà présent ?
        $q = $pdo->prepare("SELECT PER_ID FROM PERSONNE WHERE PER_EMAIL = :mail LIMIT 1");
        $q->execute(['mail' => DEV_EMAIL]);
        $perId = $q->fetchColumn();

        if (!$perId) {
            // 2) créer la PERSONNE (respecte les CHECKs)
            $insertP = $pdo->prepare("
                INSERT INTO PERSONNE (PER_NOM, PER_PRENOM, PER_EMAIL, PER_MDP, PER_NUM_TEL)
                VALUES ('Dev','Panier', :mail, 'DevTest@1234!', '0791111111')
            ");
            $insertP->execute(['mail' => DEV_EMAIL]);
            $perId = (int)$pdo->lastInsertId();
        }

        // 3) s'assurer qu'il est CLIENT
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
/* ===== fin DEV MODE ===== */

if (empty($_SESSION['per_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$perId  = (int) $_SESSION['per_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function getOrCreateOpenOrder(PDO $pdo, int $perId): int {
    if (!empty($_SESSION['com_id'])) return (int) $_SESSION['com_id'];

    $q = $pdo->prepare("SELECT COM_ID FROM COMMANDE
                        WHERE PER_ID=:per AND COM_STATUT='en préparation'
                        ORDER BY COM_ID DESC LIMIT 1");
    $q->execute(['per' => $perId]);
    $id = $q->fetchColumn();
    if ($id) { $_SESSION['com_id'] = (int)$id; return (int)$id; }

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

try {
    if ($action === 'add') {
        $proId = (int)($_POST['pro_id'] ?? 0);
        $qty   = max(1, (int)($_POST['qty'] ?? 1));
        if ($proId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_pro_id']); exit; }

        $chk = $pdo->prepare("SELECT 1 FROM PRODUIT WHERE PRO_ID=:id");
        $chk->execute(['id' => $proId]);
        if (!$chk->fetchColumn()) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'product_not_found']); exit; }

        $comId = getOrCreateOpenOrder($pdo, $perId);
        $type  = guessProductType($pdo, $proId);

        $sql = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                VALUES (:com,:pro,:q,:t)
                ON DUPLICATE KEY UPDATE CP_QTE_COMMANDEE = CP_QTE_COMMANDEE + VALUES(CP_QTE_COMMANDEE)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['com'=>$comId,'pro'=>$proId,'q'=>$qty,'t'=>$type]);

        $items = $pdo->prepare("SELECT cp.PRO_ID, p.PRO_NOM, p.PRO_PRIX, cp.CP_QTE_COMMANDEE
                                FROM COMMANDE_PRODUIT cp
                                JOIN PRODUIT p ON p.PRO_ID=cp.PRO_ID
                                WHERE cp.COM_ID=:com
                                ORDER BY p.PRO_NOM");
        $items->execute(['com'=>$comId]);

        echo json_encode(['ok'=>true,'com_id'=>$comId,'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'list') {
        $comId = $_SESSION['com_id'] ?? null;
        if (!$comId) { echo json_encode(['ok'=>true,'items'=>[]]); exit; }

        $items = $pdo->prepare("SELECT cp.PRO_ID, p.PRO_NOM, p.PRO_PRIX, cp.CP_QTE_COMMANDEE
                                FROM COMMANDE_PRODUIT cp
                                JOIN PRODUIT p ON p.PRO_ID=cp.PRO_ID
                                WHERE cp.COM_ID=:com
                                ORDER BY p.PRO_NOM");
        $items->execute(['com'=>$comId]);

        echo json_encode(['ok'=>true,'com_id'=>(int)$comId,'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_action']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}
