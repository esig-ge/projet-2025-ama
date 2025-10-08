<?php
declare(strict_types=1);

/* =========================
   SUPPRESSION DU COMPTE
   ========================= */

session_start();

/* =========================
   0) GARDES + BASES
   ========================= */
$dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
$BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

// Vérifier que l'utilisateur est connecté
if (empty($_SESSION['per_id'])) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_msg']  = "Veuillez vous connecter pour supprimer votre compte.";
    header('Location: '.$BASE.'interface_connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $perId = (int)$_SESSION['per_id'];

    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../database/config/connexionBDD.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        // Démarrer la transaction
        $pdo->beginTransaction();

        // 1) Suppression des commandes
        $pdo->prepare("DELETE FROM COMMANDE WHERE PER_ID = :id")->execute([':id' => $perId]);

        // 2) Suppression des adresses liées
        $adrIds = $pdo->prepare("SELECT ADR_ID FROM ADRESSE_CLIENT WHERE PER_ID = :id");
        $adrIds->execute([':id' => $perId]);
        foreach ($adrIds->fetchAll(PDO::FETCH_COLUMN) as $adrId) {
            $pdo->prepare("DELETE FROM ADRESSE WHERE ADR_ID = :id")->execute([':id' => $adrId]);
        }
        $pdo->prepare("DELETE FROM ADRESSE_CLIENT WHERE PER_ID = :id")->execute([':id' => $perId]);

        // 3) Suppression du client et de la personne
        $pdo->prepare("DELETE FROM CLIENT WHERE PER_ID = :id")->execute([':id' => $perId]);
        $pdo->prepare("DELETE FROM PERSONNE WHERE PER_ID = :id")->execute([':id' => $perId]);

        // Valider la transaction
        $pdo->commit();

        // Nettoyer la session et rediriger
        unset($_SESSION['toast_type'], $_SESSION['toast_msg']);
        session_destroy();

        // Redirection vers la page d’adieu
        header('Location: '.$BASE.'goodbye.php');
        exit;


    } catch (Throwable $e) {
        // Rollback uniquement si la transaction est active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = "Erreur lors de la suppression du compte : " . $e->getMessage();
        header('Location: '.$BASE.'info_perso.php');
        exit;
    }
}
?>

<!-- Formulaire suppression compte -->
<section class="card" aria-label="Supprimer mon compte">
    <h2 class="section-title">Supprimer mon compte</h2>
    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.');">
        <button type="submit" name="delete_account" class="btn-danger">Supprimer mon compte</button>
    </form>
</section>
