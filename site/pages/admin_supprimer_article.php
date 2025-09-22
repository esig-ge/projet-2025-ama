<?php
// /site/pages/admin_supprimer_article.php
declare(strict_types=1);
session_start();

/* Base */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

/* DB */
$pdo = require __DIR__ . '/../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Inputs */
$type   = strtolower(trim($_POST['type'] ?? $_GET['type'] ?? ''));
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = strtolower(trim($_POST['action'] ?? $_GET['action'] ?? 'hide')); // hide|show
$return = $_POST['return'] ?? $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? $BASE.'admin_catalogue.php');

/* Mapping */
$map = [
    'fleur'      => ['table'=>'PRODUIT',   'pk'=>'PRO_ID', 'col'=>'PRO_ACTIF'],
    'bouquet'    => ['table'=>'PRODUIT',   'pk'=>'PRO_ID', 'col'=>'PRO_ACTIF'],
    'coffret'    => ['table'=>'PRODUIT',   'pk'=>'PRO_ID', 'col'=>'PRO_ACTIF'],
    'supplement' => ['table'=>'SUPPLEMENT','pk'=>'SUP_ID', 'col'=>'SUP_ACTIF'],
    'emballage'  => ['table'=>'EMBALLAGE', 'pk'=>'EMB_ID', 'col'=>'EMB_ACTIF'],
];

if (!isset($map[$type]) || $id <= 0) {
    http_response_code(400);
    echo "Paramètres invalides.";
    exit;
}

$cfg    = $map[$type];
$newVal = ($action === 'show') ? 1 : 0;

/* Vérifie que la ligne existe */
$check = $pdo->prepare("SELECT 1 FROM `{$cfg['table']}` WHERE `{$cfg['pk']}` = :id LIMIT 1");
$check->execute([':id'=>$id]);
if (!$check->fetchColumn()) {
    $_SESSION['toast'] = ['type'=>'error','text'=>"L'élément #$id est introuvable."];
    header("Location: $return"); exit;
}

/* UPDATE UNE SEULE LIGNE */
$sql = "UPDATE `{$cfg['table']}` SET `{$cfg['col']}` = :v WHERE `{$cfg['pk']}` = :id LIMIT 1";
$st  = $pdo->prepare($sql);
$st->execute([':v'=>$newVal, ':id'=>$id]);

$_SESSION['toast'] = [
    'type' => 'success',
    'text' => $newVal ? 'Article rendu visible.' : 'Article masqué avec succès.'
];

header("Location: $return");
exit;
