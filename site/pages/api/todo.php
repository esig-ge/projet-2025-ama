<?php
// /site/pages/api/todo.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// === Garde admin simple ===
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['per_id']);
if (!$isAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'forbidden']); exit; }

$admId = (int)($_SESSION['per_id'] ?? 0);
if ($admId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'no per_id']); exit; }

// === Connexion BDD ===
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// === Helpers ===
function j($x){ echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
$op = $_POST['op'] ?? $_GET['op'] ?? 'list';

// === Ops ===
try {
    switch ($op) {

        case 'list': {
            $st = $pdo->prepare("SELECT TODO_ID, TEXTE, DONE, ORDRE
                                 FROM ADMIN_TODO
                                 WHERE PER_ID = :p
                                 ORDER BY ORDRE ASC, TODO_ID ASC");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true, 'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'add': {
            $txt = trim($_POST['text'] ?? '');
            if ($txt === '') j(['ok'=>false,'err'=>'empty']);
            // ORDRE = max+1
            $ord = (int)$pdo->query("SELECT COALESCE(MAX(ORDRE),0)+1 FROM ADMIN_TODO WHERE PER_ID=".$admId)->fetchColumn();
            $st = $pdo->prepare("INSERT INTO ADMIN_TODO (PER_ID, TEXTE, DONE, ORDRE, CREATED_AT, UPDATED_AT)
                                 VALUES (:p, :t, 0, :o, NOW(), NOW())");
            $st->execute([':p'=>$admId, ':t'=>$txt, ':o'=>$ord]);
            $id = (int)$pdo->lastInsertId();
            j(['ok'=>true,'item'=>['TODO_ID'=>$id,'TEXTE'=>$txt,'DONE'=>0,'ORDRE'=>$ord]]);
        }

        case 'toggle': {
            $id = (int)($_POST['id'] ?? 0);
            $done = (int)($_POST['done'] ?? 0) ? 1 : 0;
            $st = $pdo->prepare("UPDATE ADMIN_TODO
                                 SET DONE=:d, UPDATED_AT=NOW()
                                 WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':d'=>$done, ':id'=>$id, ':p'=>$admId]);
            j(['ok'=>true]);
        }

        case 'edit': {
            $id = (int)($_POST['id'] ?? 0);
            $txt = trim($_POST['text'] ?? '');
            $st = $pdo->prepare("UPDATE ADMIN_TODO
                                 SET TEXTE=:t, UPDATED_AT=NOW()
                                 WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':t'=>$txt, ':id'=>$id, ':p'=>$admId]);
            j(['ok'=>true]);
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':id'=>$id, ':p'=>$admId]);
            j(['ok'=>true]);
        }

        case 'clear_done': {
            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE PER_ID=:p AND DONE=1");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true]);
        }

        case 'clear_all': {
            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE PER_ID=:p");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true]);
        }

        case 'reorder': {
            // ids[] dans le nouvel ordre
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) $ids = [];
            $pdo->beginTransaction();
            $ord = 1;
            $st = $pdo->prepare("UPDATE ADMIN_TODO SET ORDRE=:o, UPDATED_AT=NOW()
                                 WHERE TODO_ID=:id AND PER_ID=:p");
            foreach ($ids as $id) {
                $st->execute([':o'=>$ord++, ':id'=>(int)$id, ':p'=>$admId]);
            }
            $pdo->commit();
            j(['ok'=>true]);
        }

        default: j(['ok'=>false,'err'=>'unknown op']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    j(['ok'=>false,'err'=>'server','msg'=>$e->getMessage()]);
}
