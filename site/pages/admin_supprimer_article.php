<?php
// /site/pages/admin_supprimer_article.php
declare(strict_types=1);
session_start();

/* Base URL */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* DB */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Inputs (depuis ton <form>) */
$type    = strtolower(trim($_POST['type'] ?? $_GET['type'] ?? ''));   // fleur | bouquet | coffret | supplement | emballage
$id      = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$visible = isset($_POST['visible']) ? (int)$_POST['visible'] : (isset($_GET['visible']) ? (int)$_GET['visible'] : 0);
$return  = $_POST['return'] ?? $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? $BASE.'admin_catalogue.php');

if (!in_array($type, ['fleur','bouquet','coffret','supplement','emballage'], true) || $id <= 0) {
    http_response_code(400); echo "Paramètres invalides."; exit;
}

/* Mapping: table, PK et colonne de stock */
$map = [
    'fleur'      => ['table'=>'FLEUR',      'pk'=>'PRO_ID', 'stock'=>'FLE_QTE_STOCK'],
    'bouquet'    => ['table'=>'BOUQUET',    'pk'=>'PRO_ID', 'stock'=>'BOU_QTE_STOCK'],
    'coffret'    => ['table'=>'COFFRET',    'pk'=>'PRO_ID', 'stock'=>'COF_QTE_STOCK'],
    'supplement' => ['table'=>'SUPPLEMENT', 'pk'=>'SUP_ID', 'stock'=>'SUP_QTE_STOCK'],
    'emballage'  => ['table'=>'EMBALLAGE',  'pk'=>'EMB_ID', 'stock'=>'EMB_QTE_STOCK'],
];
$cfg = $map[$type];

/* Vérifie que la ligne existe */
$check = $pdo->prepare("SELECT 1 FROM `{$cfg['table']}` WHERE `{$cfg['pk']}`=:id LIMIT 1");
$check->execute([':id'=>$id]);
if (!$check->fetchColumn()) {
    $_SESSION['toast'] = ['type'=>'error','text'=>"Élément #$id introuvable dans {$cfg['table']}."];
    header("Location: $return"); exit;
}

/* visible=0 => cache (stock=0) ; visible=1 => ré-affiche vite (stock=GREATEST(1, stock)) */
if ($visible === 0) {
    $sql = "UPDATE `{$cfg['table']}` SET `{$cfg['stock']}` = 0 WHERE `{$cfg['pk']}` = :id LIMIT 1";
    $pdo->prepare($sql)->execute([':id'=>$id]);
    $_SESSION['toast'] = ['type'=>'success','text'=>'Article masqué (stock=0).'];
} else {
    // On remet au moins 1 en stock pour le ré-afficher rapidement (tu pourras ajuster après)
    $sql = "UPDATE `{$cfg['table']}` SET `{$cfg['stock']}` = GREATEST(1, `{$cfg['stock']}`) WHERE `{$cfg['pk']}` = :id LIMIT 1";
    $pdo->prepare($sql)->execute([':id'=>$id]);
    $_SESSION['toast'] = ['type'=>'success','text'=>'Article rendu visible (stock>=1).'];
}

header("Location: $return");
exit;
