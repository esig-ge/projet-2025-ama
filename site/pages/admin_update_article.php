<?php

// /site/pages/admin_update_article.php
session_start();

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['save_id'])) {
        throw new RuntimeException('Requête invalide.');
    }

    $rowId = (int)$_POST['save_id'];
    $data = $_POST['rows'][$rowId] ?? null;
    if (!$data) throw new RuntimeException('Données manquantes.');
    $type = $data['type'] ?? '';
    if (!$type) throw new RuntimeException('Type manquant.');

    $pdo->beginTransaction();

    switch ($type) {
        case 'fleur':
        {
            $proId = (int)($data['PRO_ID'] ?? 0);
            $pdo->prepare("UPDATE PRODUIT SET PRO_NOM=:nom, PRO_PRIX=:prix WHERE PRO_ID=:id")
                ->execute([
                    ':nom' => trim($data['PRO_NOM'] ?? ''),
                    ':prix' => (float)($data['PRO_PRIX'] ?? 0),
                    ':id' => $proId
                ]);
            $pdo->prepare("UPDATE FLEUR SET FLE_COULEUR=:coul, FLE_QTE_STOCK=:stk WHERE PRO_ID=:id")
                ->execute([
                    ':coul' => trim($data['FLE_COULEUR'] ?? ''),
                    ':stk' => (int)($data['FLE_QTE_STOCK'] ?? 0),
                    ':id' => $proId
                ]);
            break;
        }
        case 'bouquet':
        {
            $proId = (int)($data['PRO_ID'] ?? 0);
            $pdo->prepare("UPDATE PRODUIT SET PRO_NOM=:nom, PRO_PRIX=:prix WHERE PRO_ID=:id")
                ->execute([
                    ':nom' => trim($data['PRO_NOM'] ?? ''),
                    ':prix' => (float)($data['PRO_PRIX'] ?? 0),
                    ':id' => $proId
                ]);
            $pdo->prepare("UPDATE BOUQUET SET BOU_NB_ROSES=:nb, BOU_COULEUR=:coul, BOU_QTE_STOCK=:stk WHERE PRO_ID=:id")
                ->execute([
                    ':nb' => (int)($data['BOU_NB_ROSES'] ?? 0),
                    ':coul' => trim($data['BOU_COULEUR'] ?? ''),
                    ':stk' => (int)($data['BOU_QTE_STOCK'] ?? 0),
                    ':id' => $proId
                ]);
            break;
        }
        case 'coffret':
        {
            $proId = (int)($data['PRO_ID'] ?? 0);
            $pdo->prepare("UPDATE PRODUIT SET PRO_NOM=:nom, PRO_PRIX=:prix WHERE PRO_ID=:id")
                ->execute([
                    ':nom' => trim($data['PRO_NOM'] ?? ''),
                    ':prix' => (float)($data['PRO_PRIX'] ?? 0),
                    ':id' => $proId
                ]);
            $pdo->prepare("UPDATE COFFRET SET COF_EVENEMENT=:ev, COF_QTE_STOCK=:stk WHERE PRO_ID=:id")
                ->execute([
                    ':ev' => trim($data['COF_EVENEMENT'] ?? ''),
                    ':stk' => (int)($data['COF_QTE_STOCK'] ?? 0),
                    ':id' => $proId
                ]);
            break;
        }
        case 'supplement':
        {
            $supId = (int)($data['SUP_ID'] ?? 0);
            $pdo->prepare("UPDATE SUPPLEMENT
                           SET SUP_NOM=:nom, SUP_DESCRIPTION=:descr, SUP_PRIX_UNITAIRE=:prix, SUP_QTE_STOCK=:stk
                           WHERE SUP_ID=:id")
                ->execute([
                    ':nom' => trim($data['SUP_NOM'] ?? ''),
                    ':descr' => trim($data['SUP_DESCRIPTION'] ?? ''),
                    ':prix' => (float)($data['SUP_PRIX_UNITAIRE'] ?? 0),
                    ':stk' => (int)($data['SUP_QTE_STOCK'] ?? 0),
                    ':id' => $supId
                ]);
            break;
        }
        case 'emballage':
        {
            $embId = (int)($data['EMB_ID'] ?? 0);
            $pdo->prepare("UPDATE EMBALLAGE
                           SET EMB_NOM=:nom, EMB_COULEUR=:coul, EMB_QTE_STOCK=:stk
                           WHERE EMB_ID=:id")
                ->execute([
                    ':nom' => trim($data['EMB_NOM'] ?? ''),
                    ':coul' => trim($data['EMB_COULEUR'] ?? ''),
                    ':stk' => (int)($data['EMB_QTE_STOCK'] ?? 0),
                    ':id' => $embId
                ]);
            break;
        }
        default:
            throw new RuntimeException('Type invalide.');
    }

    $pdo->commit();
    $_SESSION['message'] = 'Article mis à jour.';
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
}

$back = $_SERVER['HTTP_REFERER'] ?? ($BASE ?? '/');
header('Location: ' . $back);
exit;
