<?php
// /site/pages/api/save_totaux_commande.php
declare(strict_types=1);                 // Typage strict : évite des conversions implicites dangereuses.
session_start();                         // On a besoin de la session pour per_id & le token CSRF.
header('Content-Type: application/json'); // Toutes les réponses sont renvoyées en JSON.

try {
    /* =========================================================
       =============== 1) GARDE MÉTHODE + CSRF + AUTH ==========
       ========================================================= */

    // N'accepte que la méthode POST (sécurité + cohérence API).
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Méthode interdite');

    // Vérification CSRF : compare le token envoyé au token stocké en session.
    // - hash_equals évite les timings attacks.
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_checkout'] ?? '', $_POST['csrf'])) {
        throw new RuntimeException('CSRF invalide');
    }

    // Authentification requise (identité du client).
    $perId = (int)($_SESSION['per_id'] ?? 0);
    if ($perId <= 0) throw new RuntimeException('Non authentifié');

    /* =========================================================
       =================== 2) CONNEXION PDO ====================
       ========================================================= */

    /** @var PDO $pdo */
    // Le fichier doit retourner un PDO prêt à l'emploi.
    $pdo = require __DIR__ . '/../../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Exceptions sur erreurs SQL.

    /* =========================================================
       ================ 3) LECTURE DES ENTRÉES =================
       =========================================================
       - com_id : identifiant de la commande à mettre à jour
       - tva_taux : taux de TVA (%) tel qu'affiché/calculé côté front (ex: 8.1)
       - tva_chf  : montant de TVA calculé côté front (ex: 1.18)
       - liv_chf  : frais de livraison (CHF) (ex: 5.00)
       - liv_mode : mode de livraison ('standard' par défaut)
    */

    $comId      = (int)($_POST['com_id'] ?? 0);
    $tvaTaux    = (float)($_POST['tva_taux'] ?? 0);   // ex 8.1
    $tvaMontant = (float)($_POST['tva_chf']  ?? 0);   // ex 1.18
    $livMontant = (float)($_POST['liv_chf']  ?? 0);   // ex 5.00
    $livMode    = trim((string)($_POST['liv_mode'] ?? 'standard'));

    if ($comId <= 0) throw new RuntimeException('COM_ID manquant');

    /* =========================================================
       ===== 4) VÉRIFICATION QUE LA COMMANDE EST « OUVERTE » ===
       =========================================================
       - On impose que la commande appartienne à l'utilisateur
         ET soit en statut 'en preparation'.
       - Évite qu'un utilisateur altère une commande d'autrui ou finalisée.
    */
    $ok = $pdo->prepare("SELECT 1 FROM COMMANDE WHERE COM_ID=:c AND PER_ID=:p AND COM_STATUT='en preparation' LIMIT 1");
    $ok->execute([':c'=>$comId, ':p'=>$perId]);
    if (!$ok->fetchColumn()) throw new RuntimeException('Commande invalide');

    /* =========================================================
       =================== 5) DÉBUT TRANSACTION ================
       =========================================================
       - On regroupe la MAJ/creation de LIVRAISON et la MAJ TVA COMMANDE
         pour garantir l’atomicité (tout ou rien).
    */
    $pdo->beginTransaction();

    /* =========================================================
       =========== 6) (UPSERT) INFORMATIONS LIVRAISON ==========
       =========================================================
       Étapes :
       a) On verrouille la ligne commande ciblée (FOR UPDATE) pour
          lire le LIV_ID actuel en contexte transactionnel (concurrence).
       b) Si un LIV_ID est déjà lié :
            - on met à jour LIVRAISON (mode + montant).
          Sinon :
            - on crée la ligne LIVRAISON (statut par défaut 'en preparation'),
              puis on rattache le LIV_ID à COMMANDE.
    */

    // a) Lecture du LIV_ID lié à la commande (et verrouillage ligne COMMANDE).
    $get = $pdo->prepare("SELECT LIV_ID FROM COMMANDE WHERE COM_ID=:c FOR UPDATE");
    $get->execute([':c'=>$comId]);
    $livId = $get->fetchColumn();

    if ($livId) {
        // b.1) Déjà une livraison liée → on met à jour les champs variables.
        $up = $pdo->prepare("
            UPDATE LIVRAISON
               SET LIV_MODE=:m, LIV_MONTANT_FRAIS=:f
             WHERE LIV_ID=:id
        ");
        $up->execute([':m'=>$livMode, ':f'=>$livMontant, ':id'=>$livId]);
    } else {
        // b.2) Aucune livraison liée → on en crée une nouvelle…
        $ins = $pdo->prepare("
            INSERT INTO LIVRAISON (LIV_STATUT, LIV_MODE, LIV_MONTANT_FRAIS, LIV_DATE)
            VALUES ('en preparation', :m, :f, NOW())
        ");
        $ins->execute([':m'=>$livMode, ':f'=>$livMontant]);
        $livId = (int)$pdo->lastInsertId();

        // …puis on attache ce LIV_ID à la commande.
        $link = $pdo->prepare("UPDATE COMMANDE SET LIV_ID=:liv WHERE COM_ID=:c");
        $link->execute([':liv'=>$livId, ':c'=>$comId]);
    }

    /* =========================================================
       =============== 7) ENREGISTRER TVA SUR COMMANDE =========
       =========================================================
       - On persiste le taux et le montant de TVA calculés côté front.
       - (Tu peux plus tard recalculer côté serveur selon ta politique.)
    */
    $upTva = $pdo->prepare("
        UPDATE COMMANDE
           SET COM_TVA_TAUX=:taux, COM_TVA_MONTANT=:mt
         WHERE COM_ID=:c
    ");
    $upTva->execute([':taux'=>$tvaTaux, ':mt'=>$tvaMontant, ':c'=>$comId]);

    /* =========================================================
       ==================== 8) COMMIT + RÉPONSE ================
       ========================================================= */
    $pdo->commit();

    // Réponse JSON standard avec l’identifiant livraison (utile pour suivi côté front).
    echo json_encode(['ok'=>true, 'liv_id'=>$livId]);

} catch (Throwable $e) {
    /* =========================================================
       ====================== 9) ERREURS =======================
       =========================================================
       - En cas d’exception :
         * rollback si une transaction est ouverte,
         * code HTTP 400 (générique ici),
         * JSON d’erreur structuré.
    */
    if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
