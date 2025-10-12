<?php
// /site/pages/api/save_addresses.php
declare(strict_types=1);                 // Active le mode strict : conversions de types implicites plus contrôlées.
session_start();                         // Démarre/récupère la session PHP (accès à $_SESSION['per_id']).
header('Content-Type: application/json'); // On renvoie systématiquement du JSON au client (front).

try{
    /** @var PDO $pdo */
    // Récupération d'un objet PDO via ton fichier de configuration (doit retourner un PDO).
    $pdo = require __DIR__.'/../../database/config/connexionBDD.php';
    // Les erreurs PDO lèveront des exceptions (meilleur handling dans le catch).
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================
    // 1) CONTROLES D'AUTH/ENTRÉES
    // ==========================

    // L'utilisateur doit être connecté (per_id stocké en session).
    if (empty($_SESSION['per_id'])) throw new RuntimeException('Auth requise');
    $perId  = (int)$_SESSION['per_id'];                 // Identifiant du client connecté.
    $comId  = (int)($_POST['com_id'] ?? 0);             // Identifiant de la commande ciblée (POST).

    // COM_ID est obligatoire : c'est la commande sur laquelle on va lier les adresses.
    if ($comId<=0) throw new RuntimeException('COM_ID manquant');

    // ==========================
    // 2) HELPERS LOCAUX
    // ==========================

    // Petit normaliseur : trim + cast string. Utile pour nettoyer les champs.
    $norm = static fn($s)=>trim((string)$s);

    /**
     * Tente d'extraire "rue" et "numéro" à partir d'une chaîne d'adresse.
     * Exemple: "Rue de Lausanne 12A" → ["Rue de Lausanne", "12A"].
     * - Si $number est déjà fourni, on renvoie (street, number) tel quel.
     * - Sinon on essaie de détecter un numéro en fin de $street (regex).
     * - Si rien trouvé, on renvoie (street, '') avec numéro vide.
     *
     * @param ?string $street  Chaîne d'adresse complète potentielle (ex: "Avenue X 10").
     * @param ?string $number  Numéro explicite si déjà séparé.
     * @return array{0:string,1:string} Tableau [rue, numero]
     */
    $splitStreet = static function (?string $street, ?string $number=null): array {
        $street = trim((string)$street);
        $number = trim((string)$number);

        // Cas 1 : le numéro est déjà fourni séparément → on ne tente rien, on renvoie tel quel.
        if ($number !== '') return [$street, $number];

        // Cas 2 : essayer d'inférer le numéro à la fin de la chaîne d'adresse.
        // Regex : on capture tout puis un séparateur (espace ou virgule), puis un bloc numérique + lettres/traits éventuels.
        if ($street !== '' && preg_match('~^(.*?)[\s,]+(\d+[A-Za-z\-\/]*)$~u', $street, $m)) {
            $s = trim($m[1]); // Partie "rue"
            $n = trim($m[2]); // Partie "numéro" (ex: 12, 12A, 12-14, 12/2)
            if ($s !== '') return [$s, $n];
        }

        // Cas 3 : impossible d'extraire un numéro → on renvoie la rue telle quelle et un numéro vide.
        return [$street, ''];
    };

    // ==========================
    // 3) LECTURE & VALIDATION ADRESSE DE FACTURATION
    // ==========================

    // On sépare l'adresse de facturation en rue + numéro :
    [$billRue, $billNumero] = $splitStreet($_POST['bill_address'] ?? '', $_POST['bill_number'] ?? null);

    // On constitue le tableau normalisé pour la facturation.
    $bf = [
        'rue'   => $billRue,                              // Rue (ex: "Rue de Lausanne")
        'numero'=> $billNumero,                           // Numéro (ex: "12A")
        'npa'   => $norm($_POST['bill_postal']  ?? ''),   // NPA (code postal)
        'ville' => $norm($_POST['bill_city']    ?? ''),   // Ville
        'pays'  => 'Suisse',                              // Pays fixé ici (adapter si besoin)
    ];

    // Validation minimale : rue, NPA et ville doivent être présents pour la facturation.
    if (!$bf['rue'] || !$bf['npa'] || !$bf['ville']) {
        throw new RuntimeException('Adresse de facturation incomplète');
    }

    // ==========================
    // 4) LECTURE & "FALLBACK" ADRESSE DE LIVRAISON
    // ==========================

    // Même logique pour la livraison ; si un champ n'est pas fourni, on reprend la valeur de facturation.
    [$shipRue, $shipNumero] = $splitStreet($_POST['ship_address'] ?? '', $_POST['ship_number'] ?? null);

    $sf = [
        'rue'   => $shipRue !== '' ? $shipRue : $bf['rue'],           // Rue livraison ou fallback facturation
        'numero'=> $shipNumero !== '' ? $shipNumero : $bf['numero'],   // Numéro livraison ou fallback
        'npa'   => $norm($_POST['ship_postal']  ?? $bf['npa']),        // NPA livraison ou fallback
        'ville' => $norm($_POST['ship_city']    ?? $bf['ville']),      // Ville livraison ou fallback
        'pays'  => 'Suisse',                                           // Pays (identique ici)
    ];

    // ==========================
    // 5) DÉBUT TRANSACTION
    // ==========================

    // Toutes les écritures BDD (INSERT adresses, liaison client, mise à jour commande)
    // sont faites dans une transaction : atomicité garantie (tout ou rien).
    $pdo->beginTransaction();

    // ==========================
    // 6) INSERTIONS DANS ADRESSE (FACTURATION puis LIVRAISON)
    // ==========================

    // Requête préparée unique qui respecte précisément les colonnes de ta table ADRESSE.
    $insAdr = $pdo->prepare("
        INSERT INTO ADRESSE (ADR_RUE, ADR_NUMERO, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_TYPE)
        VALUES (:rue, :numero, :npa, :ville, :pays, :type)
    ");

    // --- Insertion adresse de FACTURATION ---
    $insAdr->execute([
        ':rue'    => $bf['rue'],
        ':numero' => $bf['numero'],
        ':npa'    => $bf['npa'],
        ':ville'  => $bf['ville'],
        ':pays'   => $bf['pays'],
        ':type'   => 'FACTURATION',  // Tag typé (utile pour différencier en base)
    ]);
    $adrFacId = (int)$pdo->lastInsertId(); // Clé primaire de l'adresse de facturation insérée.

    // --- Insertion adresse de LIVRAISON ---
    $insAdr->execute([
        ':rue'    => $sf['rue'],
        ':numero' => $sf['numero'],
        ':npa'    => $sf['npa'],
        ':ville'  => $sf['ville'],
        ':pays'   => $sf['pays'],
        ':type'   => 'LIVRAISON',    // Tag typé (livraison)
    ]);
    $adrLivId = (int)$pdo->lastInsertId(); // Clé primaire de l'adresse de livraison insérée.

    // ==========================
    // 7) SAUVEGARDE DANS ADRESSE_CLIENT (OPTIONNEL)
    // ==========================

    // Si l'utilisateur a coché "sauvegarder" l'adresse de facturation pour son profil :
    if (($_POST['save_billing'] ?? '0') === '1') {
        // INSERT IGNORE permet d'éviter les doublons si une contrainte unique existe (PER_ID, ADR_ID).
        $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:p,:a)")
            ->execute([':p'=>$perId, ':a'=>$adrFacId]);
    }

    // Idem pour l'adresse de livraison :
    if (($_POST['save_shipping'] ?? '0') === '1') {
        $pdo->prepare("INSERT IGNORE INTO ADRESSE_CLIENT (PER_ID, ADR_ID) VALUES (:p,:a)")
            ->execute([':p'=>$perId, ':a'=>$adrLivId]);
    }

    // ==========================
    // 8) MISE A JOUR DE LA COMMANDE (liaison des 2 adresses)
    // ==========================

    // On lie la commande aux 2 adresses nouvellement créées, en s'assurant que la commande
    // appartient bien à l'utilisateur connecté (WHERE COM_ID=:c AND PER_ID=:p).
    $pdo->prepare("
        UPDATE COMMANDE
           SET ADR_FACT_ID = :f, ADR_LIV_ID = :l
         WHERE COM_ID = :c AND PER_ID = :p
         LIMIT 1
    ")->execute([':f'=>$adrFacId, ':l'=>$adrLivId, ':c'=>$comId, ':p'=>$perId]);

    // ==========================
    // 9) COMMIT & RÉPONSE JSON
    // ==========================

    // Si tout s'est bien passé, on valide définitivement la transaction…
    $pdo->commit();

    // …et on renvoie un succès avec les IDs des adresses.
    echo json_encode(['ok'=>true,'adr_fact_id'=>$adrFacId,'adr_liv_id'=>$adrLivId]);

} catch(Throwable $e){
    // En cas d'erreur (exception) :
    // - Si une transaction est en cours, on annule toutes les opérations (rollback).
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

    // - On renvoie un code HTTP 400 (erreur générique côté client ici — à affiner si besoin).
    http_response_code(400);

    // - Réponse JSON standardisée avec le message d'erreur.
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
