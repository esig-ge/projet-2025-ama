<?php
// /site/pages/api/save_addresses.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

try{
    /** @var PDO $pdo */
    $pdo = require __DIR__.'/../../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($_SESSION['per_id'])) throw new RuntimeException('Auth requise');
    $perId  = (int)$_SESSION['per_id'];
    $comId  = (int)($_POST['com_id'] ?? 0);
    if ($comId<=0) throw new RuntimeException('COM_ID manquant');

    // helpers
    $norm = fn($s)=>trim((string)$s);

    // --- FACTURATION ---
    $bf = [
        'rue'   => $norm($_POST['bill_address'] ?? ''),
        'npa'   => $norm($_POST['bill_postal']  ?? ''),
        'ville' => $norm($_POST['bill_city']    ?? ''),
        'pays'  => 'Suisse',
        'tel'   => $norm($_POST['bill_phone']   ?? ''),
        'nom'   => $norm($_POST['bill_lastname']?? ''),
        'pre'   => $norm($_POST['bill_firstname']??''),
        'email' => $norm($_POST['bill_email']   ?? ''),
    ];
    if (!$bf['rue'] || !$bf['npa'] || !$bf['ville']) throw new RuntimeException('Adresse de facturation incomplète');

    // --- LIVRAISON (peut être identique) ---
    $sf = [
        'rue'   => $norm($_POST['ship_address'] ?? $bf['rue']),
        'npa'   => $norm($_POST['ship_postal']  ?? $bf['npa']),
        'ville' => $norm($_POST['ship_city']    ?? $bf['ville']),
        'pays'  => 'Suisse',
        'tel'   => $norm($_POST['ship_phone']   ?? $bf['tel']),
        'nom'   => $norm($_POST['ship_lastname']?? $bf['nom']),
        'pre'   => $norm($_POST['ship_firstname']??$bf['pre']),
        'email' => $bf['email'],
    ];

    $pdo->beginTransaction();

    // Insert adresses simples (adapte aux colonnes de ta table ADRESSE)
    $insAdr = $pdo->prepare("
    INSERT INTO ADRESSE (ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE, ADR_NOM, ADR_PRENOM, ADR_TEL, ADR_EMAIL)
    VALUES (:rue,:npa,:ville,:pays,:type,:nom,:pre,:tel,:email)
  ");
    // facturation
    $insAdr->execute([
        ':rue'=>$bf['rue'],':npa'=>$bf['npa'],':ville'=>$bf['ville'],':pays'=>$bf['pays'],
        ':type'=>'FACTURATION', ':nom'=>$bf['nom'], ':pre'=>$bf['pre'], ':tel'=>$bf['tel'], ':email'=>$bf['email']
    ]);
    $adrFacId = (int)$pdo->lastInsertId();

    // livraison
    $insAdr->execute([
        ':rue'=>$sf['rue'],':npa'=>$sf['npa'],':ville'=>$sf['ville'],':pays'=>$sf['pays'],
        ':type'=>'LIVRAISON', ':nom'=>$sf['nom'], ':pre'=>$sf['pre'], ':tel'=>$sf['tel'], ':email'=>$sf['email']
    ]);
    $adrLivId = (int)$pdo->lastInsertId();

    // lier au client (si demandé)
    if (($_POST['save_billing'] ?? '0') === '1') {
        $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:p,:a)")
            ->execute([':p'=>$perId, ':a'=>$adrFacId]);
    }
    if (($_POST['save_shipping'] ?? '0') === '1') {
        $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:p,:a)")
            ->execute([':p'=>$perId, ':a'=>$adrLivId]);
    }

    // MAJ commande (adapte les noms de colonnes)
    $pdo->prepare("
    UPDATE COMMANDE
       SET ADR_FACT_ID = :f, ADR_LIV_ID = :l
     WHERE COM_ID = :c AND PER_ID = :p
     LIMIT 1
  ")->execute([':f'=>$adrFacId, ':l'=>$adrLivId, ':c'=>$comId, ':p'=>$perId]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'adr_fact_id'=>$adrFacId,'adr_liv_id'=>$adrLivId]);
} catch(Throwable $e){
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
