<?php
// /site/pages/success.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

$secretKey = getenv('STRIPE_SECRET_KEY');
Stripe::setApiKey($secretKey);

$sessionId = $_GET['session_id'] ?? '';
$comId     = (int)($_GET['com_id'] ?? 0);

if ($sessionId) {
    $session = Session::retrieve($sessionId);
    // Affiche un "merci" + récap. La MAJ définitive sera faite par le webhook (fiable).
}
?>
<!DOCTYPE html>
<html lang="fr"><body>
<h1>Merci !</h1>
<p>Référence commande #<?= htmlspecialchars($comId) ?>. Un email de confirmation va vous être envoyé.</p>
<a href="interface_selection_produit.php">Continuer vos achats</a>
</body></html>
