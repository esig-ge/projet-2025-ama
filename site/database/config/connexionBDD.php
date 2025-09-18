<?php
// site/database/config/connexionBDD.php

if (!function_exists('dbConnect')) {
    function dbConnect(): PDO {
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
        return new PDO($dsn, $DB_USER, $DB_PASS, $options);
    }
}

$pdo = dbConnect();
return $pdo;
