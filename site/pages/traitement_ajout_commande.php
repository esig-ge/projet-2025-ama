<?php
// /site/pages/traitement_commande_add.php
session_start();

// 1) Sécurité : besoin d'un client connecté
if (empty($_SESSION['per_id'])) {
    $_SESSION['message'] = "Veuillez vous connecter pour ajouter au panier.";
    header('Location: interface_connexion.php'); exit;
}
$perId = (int)$_SESSION['per_id'];

// 2) Base URL pour redirections
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// 3) DB
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';

// 4) Inputs
$proId = (int)($_POST['pro_id'] ?? 0);
$qty   = (int)($_POST['qty']    ?? 0);
$type  = ($_POST['type'] ?? 'bouquet'); // 'bouquet' | 'fleur' | 'coffret'

if ($proId <= 0 || $qty <= 0) {
    $_SESSION['message'] = "Produit/quantité invalide(s).";
    header('Location: interface_catalogue_bouquet.php'); exit;
}

// 5) Helpers
function getOrCreateOpenOrder(PDO $pdo, int $perId): int {
    // On réutilise la commande ouverte si elle existe
    $sql = "SELECT COM_ID FROM COMMANDE
            WHERE PER_ID = :per AND COM_STATUT = 'en préparation'
            ORDER BY COM_ID DESC LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':per' => $perId]);
    $comId = (int)$st->fetchColumn();
    if ($comId) return $comId;

    // Sinon, on la crée
    $sql = "INSERT INTO COMMANDE (PER_ID, COM_DATE, COM_STATUT, COM_DESCRIPTION, COM_PTS_CUMULE)
            VALUES (:per, CURDATE(), 'en préparation', NULL, 0)";
    $pdo->prepare($sql)->execute([':per' => $perId]);
    return (int)$pdo->lastInsertId();
}

// 6) Transaction : contrôle produit + stock + insertion/merge de la ligne + décrément stock
try {
    $pdo->beginTransaction();

    // Valider que le produit existe et récupérer prix + si c'est bien un bouquet (ici on vérifie l'existence en BOUQUET)
    $sql = "SELECT p.PRO_ID, p.PRO_NOM, p.PRO_PRIX, b.BOU_QTE_STOCK
            FROM PRODUIT p
            LEFT JOIN BOUQUET b ON b.PRO_ID = p.PRO_ID
            WHERE p.PRO_ID = :id
            FOR UPDATE"; // on verrouille la ligne pour éviter les conflits de stock
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $proId]);
    $prod = $st->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        throw new RuntimeException("Produit introuvable.");
    }

    // Si type déclaré 'bouquet', on exige l'entrée côté BOUQUET
    if ($type === 'bouquet' && $prod['BOU_QTE_STOCK'] === null) {
        throw new RuntimeException("Ce produit n'est pas un bouquet.");
    }

    // Contrôle stock (uniquement pour bouquet ici)
    if ($type === 'bouquet') {
        $stock = (int)$prod['BOU_QTE_STOCK'];
        if ($stock < $qty) {
            throw new RuntimeException("Stock insuffisant pour « {$prod['PRO_NOM']} » (stock: {$stock}).");
        }
    }

    // COMMANDE courante
    $comId = getOrCreateOpenOrder($pdo, $perId);

    // Merge (si ligne existe, on incrémente)
    $sql = "SELECT CP_QTE_COMMANDEE FROM COMMANDE_PRODUIT
            WHERE COM_ID = :com AND PRO_ID = :pro
            FOR UPDATE";
    $st = $pdo->prepare($sql);
    $st->execute([':com' => $comId, ':pro' => $proId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $newQty = (int)$row['CP_QTE_COMMANDEE'] + $qty;
        $sql = "UPDATE COMMANDE_PRODUIT
                SET CP_QTE_COMMANDEE = :qte, CP_TYPE_PRODUIT = :type
                WHERE COM_ID = :com AND PRO_ID = :pro";
        $pdo->prepare($sql)->execute([
            ':qte'  => $newQty,
            ':type' => $type,
            ':com'  => $comId,
            ':pro'  => $proId,
        ]);
    } else {
        $sql = "INSERT INTO COMMANDE_PRODUIT (COM_ID, PRO_ID, CP_QTE_COMMANDEE, CP_TYPE_PRODUIT)
                VALUES (:com, :pro, :qte, :type)";
        $pdo->prepare($sql)->execute([
            ':com'  => $comId,
            ':pro'  => $proId,
            ':qte'  => $qty,
            ':type' => $type,
        ]);
    }

    // Décrément stock bouquet
    if ($type === 'bouquet') {
        $sql = "UPDATE BOUQUET SET BOU_QTE_STOCK = BOU_QTE_STOCK - :qte WHERE PRO_ID = :id";
        $pdo->prepare($sql)->execute([':qte' => $qty, ':id' => $proId]);
    }

    $pdo->commit();

    $_SESSION['message'] = "« {$prod['PRO_NOM']} » a été ajouté à votre commande.";
    header('Location: commande.php'); exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['message'] = "Erreur : " . $e->getMessage();
    header('Location: interface_catalogue_bouquet.php'); exit;
}
