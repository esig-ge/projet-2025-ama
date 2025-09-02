<?php

$host = "127.0.0.1";   // ou "localhost"
$dbname = "DK_Bloom";
$username = "root";    // ton utilisateur MySQL
$password = "";        // ton mot de passe MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
