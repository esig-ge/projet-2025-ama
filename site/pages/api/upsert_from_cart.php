<?php
// /site/pages/api/upsert_from_cart.php
declare(strict_types=1);                 // Mode strict : limite les conversions implicites.
session_start();                         // On utilise la session pour lire per_id et le contenu du panier.
header('Content-Type: application/json'); // Toutes les réponses sont en JSON.

/* =========================================================
   0) CONNEXION BDD (PDO)
   =========================================================
   - Le fichier de config doit retourner une instance PDO.
   - On active les exceptions sur erreurs SQL.
*/
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================================================
   1) AUTHENTIFICATION (client connecté requis)
   =========================================================
   - On exige un per_id en session sinon 401.
*/
$perId = isset($_SESSION['per_id']) ? (int)$_SESSION['per_id'] : 0;
if ($perId <= 0) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'not_authenticated']);
    exit;
}

/* =========================================================
   2) RÉCUPÉRATION DU PANIER EN SESSION (si encore utilisé)
   =========================================================
   - cart_products   : [{pro_id, type:'bouquet'|'fleur'|'coffret', qty}]
   - cart_supps      : [{sup_id, qty}]
   - cart_emballages : [{emb_id, qty}]
   - Ces tableaux sont supposés déjà remplis côté front.
*/
$cartProducts   = $_SESSION['cart_products']   ?? []; // produits principaux
$cartSupps      = $_SESSION['cart_supps']      ?? []; // suppléments
$cartEmballages = $_SESSION['cart_emballages'] ?? []; // emballages

/* =========================================================
   2bis) SI LE PANIER EN SESSION EST VIDE :
         - On tente de réutiliser la dernière COMMANDE "en preparation"
           qui possède AU MOINS une ligne en BDD.
   =========================================================
   - Si trouvée → on renvoie son COM_ID (et on le met en session).
   - Sinon → on considère le panier vide (400).
*/
if (!$cartProducts && !$cartSupps && !$cartEmballages) {
    // Cherche la dernière COMMANDE "en preparation" pour cet utilisateur
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
        // Vérifie qu’il existe au moins UNE ligne rattachée (produit / supp / emballage)
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
            // Panier/Bdd cohérents → on renvoie l’ID de commande existant
            $_SESSION['current_com_id'] = $existingComId;
            echo json_encode(['ok'=>true,'order_id'=>$existingComId]);
            exit;
        }
    }

    // Aucun contenu ni en session ni en BDD → panier vide
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'empty_cart']);
    exit;
}

/* =========================================================
   3) UPSERT COMMANDE + LIGNES (depuis le panier session)
   =========================================================
   Stratégie :
   - On récupère un éventuel COM_ID déjà en session (current_com_id).
   - En transaction :
       a) Si COM_ID valide (appartient à perId et "en preparation") → on purge
          ses anciennes lignes pour resynchroniser proprement.
       b) Sinon, on crée une nouvelle COMMANDE "en preparation".
       c) On insère les lignes depuis le panier session
          (COMMANDE_PRODUIT / COMMANDE_SUPP / COMMANDE_EMBALLAGE).
   - Commit puis réponse { ok:true, order_id }.
*/
$orderId = isset($_SESSION['current_com_id']) ? (int)$_SESSION['current_com_id'] : 0;

$pdo->beginTransaction();
try {
    /* a) Vérifie l’existence d’une commande "en preparation" verrouillée (FOR UPDATE)
          et appartenant au bon utilisateur. Si pas valide → on forcera la création. */
    if ($orderId > 0) {
        $st = $pdo->prepare("
            SELECT COM_ID
              FROM COMMANDE
             WHERE COM_ID=:id AND PER_ID=:per AND COM_STATUT='en preparation'
             FOR UPDATE
        ");
        $st->execute([':id'=>$orderId, ':per'=>$perId]);
        if (!$st->fetchColumn()) {
            $orderId = 0; // pas de commande valide → on créera plus bas
        }
    }

    /* b) Créer la commande si nécessaire, sinon purger les anciennes lignes
          (on repart d’un état propre pour resynchroniser les quantités/types). */
    if ($orderId <= 0) {
        // Création d’une nouvelle COMMANDE "en preparation"
        $ins = $pdo->prepare("
            INSERT INTO COMMANDE (PER_ID, LIV_ID, RAB_ID, PAI_ID, COM_STATUT, COM_DATE, COM_DESCRIPTION, COM_PTS_CUMULE)
            VALUES (:per, NULL, NULL, NULL, 'en preparation', CURDATE(), NULL, 0)
        ");
        $ins->execute([':per'=>$perId]);
        $orderId = (int)$pdo->lastInsertId();
        $_SESSION['current_com_id'] = $orderId; // mémorise la commande ouverte
    } else {
        // Purge des anciennes lignes pour éviter doubles/écarts
        $pdo->prepare("DELETE FROM COMMANDE_PRODUIT   WHERE COM_ID=:id")->execute([':id'=>$orderId]);
        $pdo->prepare("DELETE FROM COMMANDE_SUPP      WHERE COM_ID=:id")->execute([':id'=>$orderId]);
        $pdo->prepare("DELETE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:id")->execute([':id'=>$orderId]);
    }

    /* c) (Ré)insertion des lignes à partir du contenu du panier session */
    // PRODUITS (bouquet / fleur / coffret)
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
                // Sécurise le type : si valeur inconnue → 'fleur' par défaut
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

    // Tout s’est bien passé → commit
    $pdo->commit();
    echo json_encode(['ok'=>true,'order_id'=>$orderId]);
} catch (Throwable $e) {
    // Erreur durant la transaction → rollback et réponse 500
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db_error','details'=>$e->getMessage()]);
}
