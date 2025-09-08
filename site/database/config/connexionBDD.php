<?php
$DB_HOST = getenv('DB_HOST') ?: 'hhva.myd.infomaniak.com';
$DB_NAME = getenv('DB_NAME') ?: 'hhva_t25_6';
$DB_USER = getenv('DB_USER') ?: 'hhva_t25_6';
$DB_PASS = getenv('DB_PASS') ?: '';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    $isApi = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'],'application/json');
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'db_connect','msg'=>$e->getMessage()]);
    } else {
        echo 'Erreur connexion BDD';
    }
    exit;
}
