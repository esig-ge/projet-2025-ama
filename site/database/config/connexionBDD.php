<?php
$dsn  = 'mysql:host=127.0.0.1;port=3306;dbname=DK_BLOOM;charset=utf8mb4';
$user = 'root';
$pass = '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // <— plus besoin d'appeler setAttribute après
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // En API on renvoie un JSON + 500; en page, fais un die() simple
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'db_connect','msg'=>$e->getMessage()]);
    exit;
}


function dbConnect()
{
    try {
        $pdo = new PDO('mysql:host=hhva.myd.infomaniak.com;dbname=hhva_ardita', 'hhva_ardita', 'Ekq@@oE-6A130', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("SET NAMES 'UTF8'");
        return $pdo;
    } catch (PDOException $e) {
        die('Erreur de connexion : ' . $e->getMessage());
    }
}
