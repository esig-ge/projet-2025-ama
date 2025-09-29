<?php
// /site/pages/api/upsert_from_cart.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== 1) Auth ===== */
$perId = isset($_SESSION['per_id']) ? (int)$_SESSION['per_id'] : 0;
if ($perId <= 0) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'not_authenticated']);
    exit;
}

/* ===== 2) Panier en session (si tu l’utilises encore) ===== */
$cartProducts   = $_SESSION['cart_products']   ?? []; // [{pro_id, type:'bouquet'|'fleur'|'coffret', qty}]
$cartSupps      = $_SESSION['cart_supps']      ?? []; // [{sup_id, qty}]
$cartEmballages = $_SESSION['cart_emballages'] ?? []; // [{emb_id, qty}]

/* ===== 2bis) Si session vide, réutiliser la COMMANDE BDD existante avec des lignes ===== */
if (!$cartProducts && !$cartSupps && !$cartEmballages) {
    // Cherche la dernière COMMANDE en preparation du client
    $st = $pdo->prepare("
        SELECT COM_ID
          FROM COMMANDE
         WHERE PER_ID = :per
           AND COM_STATUT = 'en preparation'
         ORDER BY COM_ID DESC
         LIMIT 1
    ");
    $st->execute([':per'=>$perId]);
    $existingComId = (int)($st->fetchColumn() ?: 0);

    if ($existingComId > 0) {
        // Vérifie qu’il y a au moins UNE ligne rattachée
        $hasLines = false;
        foreach ([
                     "SELECT 1 FROM COMMANDE_PRODUIT   WHERE COM_ID=:id LIMIT 1",
                     "SELECT 1 FROM COMMANDE_SUPP      WHERE COM_ID=:id LIMIT 1",
                     "SELECT 1 FROM COMMANDE_EMBALLAGE WHERE COM_ID=:id LIMIT 1",
                 ] as $q) {
            $chk = $pdo->prepare($q);
            $chk->execute([':id'=>$existingComId]);
            if ($chk->fetchColumn()) { $hasLines = true; break; }
        }

        if ($hasLines) {
            $_SESSION['current_com_id'] = $existingComId;
            echo json_encode(['ok'=>true,'order_id'=>$existingComId]);
            exit;
        }
    }

    // Rien en BDD non plus → panier réellement vide
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'empty_cart']);
    exit;
}

/* ===== 3) Upsert COMMANDE + lignes ===== */
$orderId = isset($_SESSION['current_com_id']) ? (int)$_SESSION['current_com_id'] : 0;

$pdo->beginTransaction();
try {
    // Vérifie l’existante “en preparation”
    if ($orderId > 0) {
        $st = $pdo->prepare("
            SELECT COM_ID
              FROM COMMANDE
             WHERE COM_ID=:id AND PER_ID=:per AND COM_STATUT='en preparation'
             FOR UPDATE
        ");
        $st->execute([':id'=>$orderId, ':per'=>$perId]);
        if (!$st->fetchColumn()) {
            $orderId = 0;
        }
    }

    // Créer si besoin
    if ($orderId <= 0) {
        $ins = $pdo->prepare("
            INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, PAI_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
            VALUES (:per, NULL, NULL, NULL, 'en preparation', CURDATE(), NULL, 0)
        ");
        $ins->execute([':per'=>$perId]);
        $orderId = (int)$pdo->lastInsertId();
        $_SESSION['current_com_id'] = $orderId;
    } else {
        // Purge des anciennes lignes pour resynchro
        $pdo->prepare("DELETE FROM COMMANDE_PRODUIT   WHERE COM_ID=:id")->execute([':id'=>$orderId]);
        $pdo->prepare("DELETE FROM COMMANDE_SUPP      WHERE COM_ID=:id")->execute([':id'=>$orderId]);
        $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:id")->execute([':id'=>$orderId]);
    }

    // PRODUITS
    if ($cartProducts) {
        $stP = $pdo->prepare("
            INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
            VALUES (:com, :pro, :q, :t)
        ");
        foreach ($cartProducts as $it) {
            $stP->execute([
                ':com'=>$orderId,
                ':pro'=>(int)($it['pro_id'] ?? 0),
                ':q'  =>max(1, (int)($it['qty'] ?? 1)),
                ':t'  =>in_array(($it['type'] ?? ''), ['bouquet','fleur','coffret'], true) ? $it['type'] : 'fleur',
            ]);
        }
    }

    // SUPPLÉMENTS
    if ($cartSupps) {
        $stS = $pdo->prepare("
            INSERT INTO COMMANDE_SUPP (SUP_ID, COM_ID, CS_QTE_COMMANDEE)
            VALUES (:sup, :com, :q)
        ");
        foreach ($cartSupps as $it) {
            $stS->execute([
                ':sup'=>(int)($it['sup_id'] ?? 0),
                ':com'=>$orderId,
                ':q'  =>max(1, (int)($it['qty'] ?? 1)),
            ]);
        }
    }

    // EMBALLAGES
    if ($cartEmballages) {
        $stE = $pdo->prepare("
            INSERT INTO COMMANDE_EMBALLAGE (COM_ID, EMB_ID, CE_QTE)
            VALUES (:com, :emb, :q)
        ");
        foreach ($cartEmballages as $it) {
            $stE->execute([
                ':com'=>$orderId,
                ':emb'=>(int)($it['emb_id'] ?? 0),
                ':q'  =>max(1, (int)($it['qty'] ?? 1)),
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['ok'=>true,'order_id'=>$orderId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db_error','details'=>$e->getMessage()]);
}
