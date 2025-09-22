<?php

// /site/pages/admin_supprimer_article.php
declare(strict_types=1);
session_start();

/* Base URL */
$dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* DB */
try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo "Erreur DB: " . htmlspecialchars($e->getMessage());
    exit;
}

/* Inputs (accepte GET ou POST) */
$type = strtolower(trim($_REQUEST['type'] ?? ''));  // fleur | bouquet | coffret | supplement | emballage
$id = (int)($_REQUEST['id'] ?? 0);
$action = strtolower(trim($_REQUEST['action'] ?? 'hide')); // hide | show (optionnel pour réactiver)
$return = $_REQUEST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? $BASE . 'admin_catalogue.php');

/* Mapping par type -> table, PK, colonne actif */
$map = [
    'fleur' => ['table' => 'PRODUIT', 'pk' => 'PRO_ID', 'col' => 'PRO_ACTIF'],
    'bouquet' => ['table' => 'PRODUIT', 'pk' => 'PRO_ID', 'col' => 'PRO_ACTIF'],
    'coffret' => ['table' => 'PRODUIT', 'pk' => 'PRO_ID', 'col' => 'PRO_ACTIF'],
    'supplement' => ['table' => 'SUPPLEMENT', 'pk' => 'SUP_ID', 'col' => 'SUP_ACTIF'],
    'emballage' => ['table' => 'EMBALLAGE', 'pk' => 'EMB_ID', 'col' => 'EMB_ACTIF'],
];

if (!isset($map[$type]) || $id <= 0) {
    http_response_code(400);
    echo "Paramètres invalides.";
    exit;
}

$cfg = $map[$type];
$newVal = ($action === 'show') ? 1 : 0; // par défaut on masque

$sql = "UPDATE `{$cfg['table']}` SET `{$cfg['col']}` = :v WHERE `{$cfg['pk']}` = :id";
$ok = $pdo->prepare($sql)->execute([':v' => $newVal, ':id' => $id]);

$_SESSION['toast'] = [
    'type' => $ok ? 'success' : 'error',
    'text' => $ok
        ? (($newVal === 0) ? 'Article masqué avec succès.' : 'Article rendu visible.')
        : 'Échec de la mise à jour.'
];

header("Location: $return");
exit;
