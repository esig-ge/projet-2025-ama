<?php
// /site/pages/api/todo.php
declare(strict_types=1);                                // Mode strict : évite certaines conversions implicites risquées.
session_start();                                        // Session nécessaire (pour lire is_admin / per_id).
header('Content-Type: application/json; charset=utf-8'); // Toute réponse sera du JSON en UTF-8.

/* =========================================================
   0) GARDE D’ACCÈS « ADMIN SIMPLE »
   =========================================================
   - is_admin OU per_id doivent exister en session.
   - Ici, la logique considère qu’un utilisateur connecté (per_id) suffit
     pour utiliser cet endpoint (et/ou is_admin). Adapte si besoin.
*/
$isAdmin = !empty($_SESSION['is_admin']) || !empty($_SESSION['per_id']);
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'err'=>'forbidden']);
    exit;
}

// Identifiant de « l’admin » (ou utilisateur) courant
$admId = (int)($_SESSION['per_id'] ?? 0);
if ($admId <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'err'=>'no per_id']);
    exit;
}

/* =========================================================
   1) CONNEXION BDD (PDO)
   =========================================================
   - Le fichier de config doit retourner une instance PDO.
   - Les erreurs SQL lèveront des exceptions (mode exception).
*/
/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================================================
   2) HELPERS + ROUTAGE D’OPÉRATION
   =========================================================
   - j($x) : sérialise $x en JSON puis exit (raccourci de réponse).
   - $op   : opération demandée (POST d’abord, sinon GET). Défaut = 'list'.
*/
function j($x){ echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
$op = $_POST['op'] ?? $_GET['op'] ?? 'list';

/* =========================================================
   3) ROUTER / OPERATIONS CRUD SUR ADMIN_TODO
   =========================================================
   TABLE ciblée : ADMIN_TODO
   Colonnes utilisées : TODO_ID (PK), PER_ID (FK user), TEXTE, DONE (0/1),
                        ORDRE (tri manuel), CREATED_AT, UPDATED_AT.
   Remarques :
   - Pas de break; dans le switch car chaque case se termine par j(...) → exit.
   - Chaque action filtre par PER_ID = $admId (sécurité multi-utilisateurs).
*/
try {
    switch ($op) {

        /* --------------------------------------------
           a) LIST — Liste toutes les tâches de l’utilisateur
           Entrée : op=list
           Sortie : { ok:true, items:[{TODO_ID, TEXTE, DONE, ORDRE},…] }
           Tri : ORDRE ASC puis TODO_ID ASC
        -------------------------------------------- */
        case 'list': {
            $st = $pdo->prepare("SELECT TODO_ID, TEXTE, DONE, ORDRE
                                 FROM ADMIN_TODO
                                 WHERE PER_ID = :p
                                 ORDER BY ORDRE ASC, TODO_ID ASC");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true, 'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        /* --------------------------------------------
           b) ADD — Ajoute une tâche
           Entrées : op=add, text=string (non vide)
           Logique :
           - calcule ORDRE = max(ORDRE)+1 pour cet utilisateur
           - insère la ligne avec DONE=0, timestamps NOW()
           Sortie : { ok:true, item:{TODO_ID, TEXTE, DONE, ORDRE} }
        -------------------------------------------- */
        case 'add': {
            $txt = trim($_POST['text'] ?? '');
            if ($txt === '') j(['ok'=>false,'err'=>'empty']);

            // Détermine le prochain ORDRE (max+1) pour cet utilisateur.
            // NB : concatène $admId directement dans la requête (valeur entière).
            $ord = (int)$pdo->query("SELECT COALESCE(MAX(ORDRE),0)+1 FROM ADMIN_TODO WHERE PER_ID=".$admId)->fetchColumn();

            // Insert de la tâche
            $st = $pdo->prepare("INSERT INTO ADMIN_TODO (PER_ID, TEXTE, DONE, ORDRE, CREATED_AT, UPDATED_AT)
                                 VALUES (:p, :t, 0, :o, NOW(), NOW())");
            $st->execute([':p'=>$admId, ':t'=>$txt, ':o'=>$ord]);

            // Récupère l’ID créé
            $id = (int)$pdo->lastInsertId();

            j(['ok'=>true,'item'=>['TODO_ID'=>$id,'TEXTE'=>$txt,'DONE'=>0,'ORDRE'=>$ord]]);
        }

        /* --------------------------------------------
           c) TOGGLE — Coche/décoche une tâche
           Entrées : op=toggle, id=int, done=(0|1)
           Logique : met DONE à 1 ou 0 + met à jour UPDATED_AT
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'toggle': {
            $id = (int)($_POST['id'] ?? 0);
            $done = (int)($_POST['done'] ?? 0) ? 1 : 0; // Normalisation 0/1

            $st = $pdo->prepare("UPDATE ADMIN_TODO
                                 SET DONE=:d, UPDATED_AT=NOW()
                                 WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':d'=>$done, ':id'=>$id, ':p'=>$admId]);

            j(['ok'=>true]);
        }

        /* --------------------------------------------
           d) EDIT — Édite le texte d’une tâche
           Entrées : op=edit, id=int, text=string
           Logique : met à jour TEXTE + UPDATED_AT
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'edit': {
            $id = (int)($_POST['id'] ?? 0);
            $txt = trim($_POST['text'] ?? '');

            $st = $pdo->prepare("UPDATE ADMIN_TODO
                                 SET TEXTE=:t, UPDATED_AT=NOW()
                                 WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':t'=>$txt, ':id'=>$id, ':p'=>$admId]);

            j(['ok'=>true]);
        }

        /* --------------------------------------------
           e) DELETE — Supprime une tâche
           Entrées : op=delete, id=int
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);

            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE TODO_ID=:id AND PER_ID=:p");
            $st->execute([':id'=>$id, ':p'=>$admId]);

            j(['ok'=>true]);
        }

        /* --------------------------------------------
           f) CLEAR_DONE — Supprime toutes les tâches cochées
           Entrée : op=clear_done
           Logique : DELETE où DONE=1 pour cet utilisateur
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'clear_done': {
            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE PER_ID=:p AND DONE=1");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true]);
        }

        /* --------------------------------------------
           g) CLEAR_ALL — Supprime toutes les tâches
           Entrée : op=clear_all
           Logique : DELETE de toutes les lignes pour PER_ID
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'clear_all': {
            $st = $pdo->prepare("DELETE FROM ADMIN_TODO WHERE PER_ID=:p");
            $st->execute([':p'=>$admId]);
            j(['ok'=>true]);
        }

        /* --------------------------------------------
           h) REORDER — Réordonne les tâches
           Entrées : op=reorder, ids[]=array d’IDs dans le nouvel ordre
           Logique :
           - passe en revue le tableau 'ids' (si absent → tableau vide)
           - transaction : met ORDRE=1,2,3… selon l’ordre donné
           - filtre toujours par PER_ID (sécurité)
           Sortie : { ok:true }
        -------------------------------------------- */
        case 'reorder': {
            // Récupère le tableau 'ids' depuis POST (nouvel ordre)
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

        /* --------------------------------------------
           i) PAR DÉFAUT — Opération inconnue
        -------------------------------------------- */
        default:
            j(['ok'=>false,'err'=>'unknown op']);
    }
} catch (Throwable $e) {
    /* =========================================================
       4) GESTION D’ERREUR GLOBALE
       =========================================================
       - Si une exception survient (PDO ou autre), on renvoie un 500
         et un JSON standardisé : { ok:false, err:'server', msg:'...' }
    */
    http_response_code(500);
    j(['ok'=>false,'err'=>'server','msg'=>$e->getMessage()]);
}
