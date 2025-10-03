<?php
session_start();



/* ===== Connexion DB ===== */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Bases de chemins ===== */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

$prefType = $_GET['type'] ?? null; // 'bouquet' | 'fleur' | 'coffret' | 'supplement' | 'emballage'
$validTypes = ['bouquet','fleur','coffret','supplement','emballage','autre'];
if ($prefType && !in_array($prefType, $validTypes, true)) { $prefType = null; }


// juste après $okMsg = ""; (ou en haut de fichier)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// ...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // … (tes validations + upload) …

        if (!$errors) {
            $pdo->beginTransaction();

            // 1) Insert PRODUIT
            $sql = "INSERT INTO PRODUIT (PRO_NOM, PRO_PRIX, PRO_TYPE, PRO_DESC, PRO_IMG)
                VALUES (:nom, :prix, :type, :descr, :img)";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':nom'  => $pro_nom,
                ':prix' => $pro_prix,
                ':type' => $pro_type,
                ':descr'=> $pro_desc !== '' ? $pro_desc : null,
                ':img'  => $imgPath
            ]);
            $newId = (int)$pdo->lastInsertId();

            // 2) Selon le type, créer la ligne “détail” nécessaire au CATALOGUE
            if ($pro_type === 'bouquet') {
                // ⚠ adapte les valeurs par défaut ici ou prends celles du POST si tu as un formulaire complet
                $bou_nb_roses = (int)($_POST['bou_nb_roses'] ?? 12);
                $bou_couleur  = trim($_POST['bou_couleur'] ?? 'rouge');
                $bou_stock    = (int)($_POST['bou_stock'] ?? 10);
                $bou_desc     = $pro_desc !== '' ? $pro_desc : null;

                $sqlB = "INSERT INTO BOUQUET (PRO_ID, BOU_NB_ROSES, BOU_COULEUR, BOU_QTE_STOCK, BOU_DESCRIPTION)
                     VALUES (:pid, :nb, :coul, :stk, :descr)";
                $pdo->prepare($sqlB)->execute([
                    ':pid'=>$newId, ':nb'=>$bou_nb_roses, ':coul'=>$bou_couleur,
                    ':stk'=>$bou_stock, ':descr'=>$bou_desc
                ]);
            }
            elseif ($pro_type === 'fleur') {
                // idem: insère dans FLEUR les colonnes minimales pour qu’elle apparaisse dans son catalogue
                $fle_couleur = trim($_POST['fle_couleur'] ?? 'rouge');
                $fle_stock   = (int)($_POST['fle_stock'] ?? 50);

                $sqlF = "INSERT INTO FLEUR (PRO_ID, FLE_COULEUR, FLE_QTE_STOCK)
                     VALUES (:pid, :coul, :stk)";
                $pdo->prepare($sqlF)->execute([':pid'=>$newId, ':coul'=>$fle_couleur, ':stk'=>$fle_stock]);
            }
            // idem pour coffret / supplement / emballage si tu as des tables dédiées

            $pdo->commit();

            // Flash + redirection PRG
            $_SESSION['message'] = "✅ Produit « {$pro_nom} » ajouté avec succès.";
            header('Location: '.$BASE.'admin_produits.php?ok=1'); // <- mets le nom de TA page liste/modif
            exit;
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = "Erreur à l’enregistrement : ".$ex->getMessage();
    }
}
