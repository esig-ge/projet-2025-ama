<?php
session_start();
if (empty($_SESSION['per_id'])) { $_SESSION['message']="Veuillez vous connecter."; header('Location: interface_connexion.php'); exit; }
$perId = (int)$_SESSION['per_id'];

$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$pdo  = require __DIR__ . '/../database/config/connexionBDD.php';

$kind = $_POST['kind'] ?? 'produit';                 // 'produit' | 'supp' | 'emb'
$type = $_POST['type'] ?? null;                      // 'bouquet' | 'fleur' | 'coffret' (si kind=produit)
$proId= isset($_POST['pro_id'])? (int)$_POST['pro_id'] : 0;
$supId= isset($_POST['sup_id'])? (int)$_POST['sup_id'] : 0;
$embId= isset($_POST['emb_id'])? (int)$_POST['emb_id'] : 0;
$qty  = max( (int)($_POST['qty'] ?? 0), 0 );

if ($qty < 1) { $_SESSION['message']="Quantité invalide."; header('Location: commande.php'); exit; }

function getOrCreateOpenOrder(PDO $pdo, int $perId): int {
    $st=$pdo->prepare("SELECT COM_ID FROM COMMANDE WHERE PER_ID=:p AND COM_STATUT='en préparation' ORDER BY COM_ID DESC LIMIT 1");
    $st->execute([':p'=>$perId]); $id=(int)$st->fetchColumn();
    if($id) return $id;
    $pdo->prepare("INSERT INTO COMMANDE (PER_ID, COM_DATE, COM_STATUT, COM_PTS_CUMULE) VALUES (:p, CURDATE(),'en préparation',0)")
        ->execute([':p'=>$perId]);
    return (int)$pdo->lastInsertId();
}

function getOrderType(PDO $pdo, int $comId): string {
    $st=$pdo->prepare("SELECT DISTINCT CP_TYPE_PRODUIT FROM COMMANDE_PRODUIT WHERE COM_ID=:c");
    $st->execute([':c'=>$comId]);
    $types=array_column($st->fetchAll(PDO::FETCH_NUM),0);
    if(!$types) return 'none';
    if(count($types)>1) return 'mixed';
    return $types[0];
}
function assertCanAdd(string $currentType, string $toAdd): void {
    if($currentType==='none') return;
    if($toAdd==='supp' || $toAdd==='emb'){
        if($currentType!=='bouquet') throw new RuntimeException("Suppléments/emballages: seulement avec une commande bouquet.");
        return;
    }
    if($currentType!==$toAdd) throw new RuntimeException("Cette commande est « $currentType ». Impossible d'ajouter « $toAdd ».");
}

try {
    $pdo->beginTransaction();

    $comId = getOrCreateOpenOrder($pdo, $perId);
    $currentType = getOrderType($pdo, $comId);

    if ($kind === 'produit') {
        if (!in_array($type, ['bouquet','fleur','coffret'], true) || $proId<=0)
            throw new RuntimeException("Paramètres produit invalides.");

        assertCanAdd($currentType, $type);

        // Lock & check produit + stock selon type
        $prod = null;
        if ($type==='bouquet') {
            $st=$pdo->prepare("SELECT p.PRO_ID,p.PRO_NOM,p.PRO_PRIX,b.BOU_QTE_STOCK,b.BOU_COULEUR
                               FROM PRODUIT p JOIN BOUQUET b ON b.PRO_ID=p.PRO_ID
                               WHERE p.PRO_ID=:id FOR UPDATE");
            $st->execute([':id'=>$proId]); $prod=$st->fetch(PDO::FETCH_ASSOC);
            if(!$prod) throw new RuntimeException("Bouquet introuvable.");
            if((int)$prod['BOU_QTE_STOCK']<$qty) throw new RuntimeException("Stock bouquet insuffisant.");
        } elseif ($type==='fleur') {
            $st=$pdo->prepare("SELECT p.PRO_ID,p.PRO_NOM,p.PRO_PRIX,f.FLE_QTE_STOCK
                               FROM PRODUIT p JOIN FLEUR f ON f.PRO_ID=p.PRO_ID
                               WHERE p.PRO_ID=:id FOR UPDATE");
            $st->execute([':id'=>$proId]); $prod=$st->fetch(PDO::FETCH_ASSOC);
            if(!$prod) throw new RuntimeException("Fleur introuvable.");
            if((int)$prod['FLE_QTE_STOCK']<$qty) throw new RuntimeException("Stock fleur insuffisant.");
        } else { // coffret
            $st=$pdo->prepare("SELECT p.PRO_ID,p.PRO_NOM,p.PRO_PRIX,c.COF_QTE_STOCK
                               FROM PRODUIT p JOIN COFFRET c ON c.PRO_ID=p.PRO_ID
                               WHERE p.PRO_ID=:id FOR UPDATE");
            $st->execute([':id'=>$proId]); $prod=$st->fetch(PDO::FETCH_ASSOC);
            if(!$prod) throw new RuntimeException("Coffret introuvable.");
            if((int)$prod['COF_QTE_STOCK']<$qty) throw new RuntimeException("Stock coffret insuffisant.");
        }

        // Merge ligne
        $st=$pdo->prepare("SELECT CP_QTE_COMMANDEE FROM COMMANDE_PRODUIT WHERE COM_ID=:c AND PRO_ID=:p FOR UPDATE");
        $st->execute([':c'=>$comId, ':p'=>$proId]);
        if($row=$st->fetch(PDO::FETCH_ASSOC)){
            $newQty=(int)$row['CP_QTE_COMMANDEE']+$qty;
            $pdo->prepare("UPDATE COMMANDE_PRODUIT SET CP_QTE_COMMANDEE=:q, CP_TYPE_PRODUIT=:t WHERE COM_ID=:c AND PRO_ID=:p")
                ->execute([':q'=>$newQty, ':t'=>$type, ':c'=>$comId, ':p'=>$proId]);
        }else{
            $pdo->prepare("INSERT INTO COMMANDE_PRODUIT (COM_ID,PRO_ID,CP_QTE_COMMANDEE,CP_TYPE_PRODUIT)
                           VALUES (:c,:p,:q,:t)")
                ->execute([':c'=>$comId, ':p'=>$proId, ':q'=>$qty, ':t'=>$type]);
        }

        // Décrément stock
        if ($type==='bouquet') {
            $pdo->prepare("UPDATE BOUQUET SET BOU_QTE_STOCK=BOU_QTE_STOCK-:q WHERE PRO_ID=:p")
                ->execute([':q'=>$qty, ':p'=>$proId]);
        } elseif ($type==='fleur') {
            $pdo->prepare("UPDATE FLEUR SET FLE_QTE_STOCK=FLE_QTE_STOCK-:q WHERE PRO_ID=:p")
                ->execute([':q'=>$qty, ':p'=>$proId]);
        } else {
            $pdo->prepare("UPDATE COFFRET SET COF_QTE_STOCK=COF_QTE_STOCK-:q WHERE PRO_ID=:p")
                ->execute([':q'=>$qty, ':p'=>$proId]);
        }

        $_SESSION['message'] = "« {$prod['PRO_NOM']} » ajouté à la commande.";

    } elseif ($kind === 'supp') {
        if ($supId<=0) throw new RuntimeException("Supplément invalide.");
        assertCanAdd($currentType, 'supp');

        // Stock & merge
        $st=$pdo->prepare("SELECT SUP_NOM,SUP_QTE_STOCK FROM SUPPLEMENT WHERE SUP_ID=:s FOR UPDATE");
        $st->execute([':s'=>$supId]); $sup=$st->fetch(PDO::FETCH_ASSOC);
        if(!$sup) throw new RuntimeException("Supplément introuvable.");
        if((int)$sup['SUP_QTE_STOCK']<$qty) throw new RuntimeException("Stock supplément insuffisant.");

        $st=$pdo->prepare("SELECT CS_QTE_COMMANDEE FROM COMMANDE_SUPP WHERE SUP_ID=:s AND COM_ID=:c FOR UPDATE");
        $st->execute([':s'=>$supId, ':c'=>$comId]);
        if($x=$st->fetch(PDO::FETCH_ASSOC)){
            $newQty=(int)$x['CS_QTE_COMMANDEE']+$qty;
            $pdo->prepare("UPDATE COMMANDE_SUPP SET CS_QTE_COMMANDEE=:q WHERE SUP_ID=:s AND COM_ID=:c")
                ->execute([':q'=>$newQty, ':s'=>$supId, ':c'=>$comId]);
        }else{
            $pdo->prepare("INSERT INTO COMMANDE_SUPP (SUP_ID,COM_ID,CS_QTE_COMMANDEE) VALUES (:s,:c,:q)")
                ->execute([':s'=>$supId, ':c'=>$comId, ':q'=>$qty]);
        }

        $pdo->prepare("UPDATE SUPPLEMENT SET SUP_QTE_STOCK=SUP_QTE_STOCK-:q WHERE SUP_ID=:s")
            ->execute([':q'=>$qty, ':s'=>$supId]);

        $_SESSION['message'] = "Supplément « {$sup['SUP_NOM']} » ajouté.";

    } elseif ($kind === 'emb') {
        if ($embId<=0) throw new RuntimeException("Emballage invalide.");
        assertCanAdd($currentType, 'emb');

        $st=$pdo->prepare("SELECT EMB_NOM,EMB_QTE_STOCK FROM EMBALLAGE WHERE EMB_ID=:e FOR UPDATE");
        $st->execute([':e'=>$embId]); $emb=$st->fetch(PDO::FETCH_ASSOC);
        if(!$emb) throw new RuntimeException("Emballage introuvable.");
        if((int)$emb['EMB_QTE_STOCK']<$qty) throw new RuntimeException("Stock emballage insuffisant.");

        $st=$pdo->prepare("SELECT CE_QTE FROM COMMANDE_EMBALLAGE WHERE COM_ID=:c AND EMB_ID=:e FOR UPDATE");
        $st->execute([':c'=>$comId, ':e'=>$embId]);
        if($x=$st->fetch(PDO::FETCH_ASSOC)){
            $newQty=(int)$x['CE_QTE']+$qty;
            $pdo->prepare("UPDATE COMMANDE_EMBALLAGE SET CE_QTE=:q WHERE COM_ID=:c AND EMB_ID=:e")
                ->execute([':q'=>$newQty, ':c'=>$comId, ':e'=>$embId]);
        }else{
            $pdo->prepare("INSERT INTO COMMANDE_EMBALLAGE (COM_ID,EMB_ID,CE_QTE) VALUES (:c,:e,:q)")
                ->execute([':c'=>$comId, ':e'=>$embId, ':q'=>$qty]);
        }

        $pdo->prepare("UPDATE EMBALLAGE SET EMB_QTE_STOCK=EMB_QTE_STOCK-:q WHERE EMB_ID=:e")
            ->execute([':q'=>$qty, ':e'=>$embId]);

        $_SESSION['message'] = "Emballage « {$emb['EMB_NOM']} » ajouté.";
    } else {
        throw new RuntimeException("Action inconnue.");
    }

    $pdo->commit();
    header('Location: commande.php'); exit;

} catch (Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['message'] = "Erreur: ".$e->getMessage();
    header('Location: commande.php'); exit;
}
