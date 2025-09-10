<?php
// site/database/config/connexionBDD.php
// (optionnel mais conseillé) ini_set('display_errors','0');

$DB_HOST = getenv('DB_HOST') ?: 'hhva.myd.infomaniak.com';
$DB_NAME = getenv('DB_NAME') ?: 'hhva_t25_6';
$DB_USER = getenv('DB_USER') ?: 'hhva_t25_6';
$DB_PASS = getenv('DB_PASS') ?: '8oP#CGNRXJ';
$DB_PORT = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'db_connect','msg'=>$e->getMessage()]);
    } else {
        echo 'Erreur connexion BDD';
    }
    exit;
}

return $pdo;   // ⬅⬅⬅ IMPORTANT


/*
function dbConnect()
{
    try {
        $pdo = new PDO('mysql:host=hhva.myd.infomaniak.com;dbname=hhva_t25_6', 'hhva_t25_6', '8oP#CGNRXJ', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("SET NAMES 'UTF8'");
        return $pdo;
    } catch (PDOException $e) {
        die('Erreur de connexion : ' . $e->getMessage());
    }
}

*/

function getAllPersonnes(PDO $pdo): array {
    $sql = "SELECT PER_ID, PER_NOM, PER_PRENOM, PER_EMAIL, PER_NUM_TEL
            FROM PERSONNE";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
