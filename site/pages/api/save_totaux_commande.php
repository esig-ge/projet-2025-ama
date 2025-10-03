<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Méthode interdite');
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide');
    }
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié');

    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $comId       = (int)($_POST['com_id'] ?? 0);
    $tvaTaux     = (float)($_POST['tva_taux'] ?? 0);      // ex 7.7
    $tvaMontant  = (float)($_POST['tva_chf']  ?? 0);      // ex 1.18
    $livMontant  = (float)($_POST['liv_chf']  ?? 0);      // ex 5
    $livMode     = trim((string)($_POST['liv_mode'] ?? 'standard')); // "standard", "boutique", etc.

    // garde-fous
    if ($comId <= 0) throw new RuntimeException('COM_ID manquant');

    // Vérifie la commande
    $ok = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
    $ok->execute([':c'=>$comId, ':p'=>$perId]);
    if (!$ok->fetchColumn()) throw new RuntimeException('Commande invalide');

    $pdo->beginTransaction();

    // 1) Crée/Met à jour la LIVRAISON si nécessaire
    $livId = null;
    if ($livMontant > 0 || $livMode !== '') {
        // S'il existe déjà une livraison reliée on la met à jour, sinon on l'insère
        $getLiv = $pdo->prepare("SELECT LIV_ID FROM COMMANDE WHERE COM_ID=:c FOR UPDATE");
        $getLiv->execute([':c'=>$comId]);
        $livId = $getLiv->fetchColumn();

        if ($livId) {
            $st = $pdo->prepare("
                UPDATE LIVRAISON
                SET LIV_MODE = :m, LIV_MONTANT_FRAIS = :f
                WHERE LIV_ID = :id
            ");
            $st->execute([':m'=>$livMode, ':f'=>$livMontant, ':id'=>$livId]);
        } else {
            $st = $pdo->prepare("
                INSERT INTO LIVRAISON (LIV_STATUT, LIV_MODE, LIV_MONTANT_FRAIS, LIV_DATE)
                VALUES ('en preparation', :m, :f, NOW())
            ");
            $st->execute([':m'=>$livMode, ':f'=>$livMontant]);
            $livId = (int)$pdo->lastInsertId();

            $up = $pdo->prepare("UPDATE COMMANDE SET LIV_ID=:liv WHERE COM_ID=:c");
            $up->execute([':liv'=>$livId, ':c'=>$comId]);
        }
    }

    // 2) Met à jour la TVA de la commande
    $upTva = $pdo->prepare("
        UPDATE COMMANDE
        SET COM_TVA_TAUX = :taux, COM_TVA_MONTANT = :montant
        WHERE COM_ID = :c
    ");
    $upTva->execute([
        ':taux'    => $tvaTaux,
        ':montant' => $tvaMontant,
        ':c'       => $comId
    ]);

    $pdo->commit();
    echo json_encode(['ok'=>true, 'liv_id'=>$livId]);
} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
