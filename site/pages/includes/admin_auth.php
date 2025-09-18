<?php
// /site/includes/admin_guard.php
session_start();
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo "<h1 style='font-family:Arial,sans-serif;margin:48px;text-align:center'>
            Accès refusé — Administrateur uniquement
          </h1>";
    exit;
}

// Base URL (slash final) dispo pour toutes les pages admin
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';


// À mettre dans chaque page admin
//require __DIR__ . '/../includes/admin_guard.php';
