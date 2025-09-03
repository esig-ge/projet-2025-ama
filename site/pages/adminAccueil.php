<?php
session_start();
// ---- Sécurité & en-tête
include 'includes/header.php';


if (session_status() === PHP_SESSION_NONE) session_start();
// Vérifie le rôle (adapte selon ta structure de session)
if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header('Location: login.php'); exit;
}

// CSRF (token simple)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// --- Données démo (remplace par des SELECT SQL)
$stats = [
    'orders_today'   => 7,
    'orders_pending' => 12,
    'deliveries_today' => 5,
    'revenue_month'  => 3420.50
];

$recent_orders = [
    ['id' => 1024, 'client' => 'L. Martin', 'total' => 129.90, 'status' => 'en préparation', 'date' => '2025-09-01 10:12'],
    ['id' => 1023, 'client' => 'S. Bakker', 'total' => 59.00,  'status' => 'validée',        'date' => '2025-09-01 09:48'],
    ['id' => 1022, 'client' => 'A. Costa',  'total' => 210.00, 'status' => 'expédiée',       'date' => '2025-08-31 17:22'],
    ['id' => 1021, 'client' => 'D. Nguyen', 'total' => 89.90,  'status' => 'livrée',         'date' => '2025-08-31 16:05'],
];

// Aide simple pour badge de statut
function badge_class($status) {
    $status = mb_strtolower($status);
    if (str_contains($status, 'livr'))   return 'badge badge-success';
    if (str_contains($status, 'expéd'))  return 'badge badge-info';
    if (str_contains($status, 'prépa'))  return 'badge badge-warn';
    if (str_contains($status, 'valid'))  return 'badge badge-neutral';
    if (str_contains($status, 'annul'))  return 'badge badge-danger';
    return 'badge';
}
?>

<main class="container" style="padding:28px 0 56px">
    <h1 style="margin:0 0 12px">Tableau de bord <span class="accent">administrateur</span></h1>
    <p class="muted">Bienvenue <?php echo htmlspecialchars($_SESSION['user']['firstname'] ?? ''); ?> — gérez vos commandes, livraisons, promotions et produits.</p>

    <!-- Cartes statistiques -->
    <section class="admin-stats">
        <article class="stat-card card">
            <h3>Commandes du jour</h3>
            <div class="stat-value"><?= (int)$stats['orders_today'] ?></div>
            <a class="btn btn-ghost" href="admin/commandes.php">Voir</a>
        </article>
        <article class="stat-card card">
            <h3>En attente</h3>
            <div class="stat-value"><?= (int)$stats['orders_pending'] ?></div>
            <a class="btn btn-ghost" href="admin/commandes.php?filtre=en_attente">Traiter</a>
        </article>
        <article class="stat-card card">
            <h3>Livraisons aujourd’hui</h3>
            <div class="stat-value"><?= (int)$stats['deliveries_today'] ?></div>
            <a class="btn btn-ghost" href="admin/livraisons.php?date=today">Planifier</a>
        </article>
        <article class="stat-card card">
            <h3>CA (mois)</h3>
            <div class="stat-value">CHF <?= number_format($stats['revenue_month'], 2, '.', '\'') ?></div>
            <a class="btn btn-ghost" href="admin/rapport.php">Rapports</a>
        </article>
    </section>

    <!-- Actions rapides -->
    <section class="quick-actions">
        <a class="btn btn-primary" href="#form-promo">Créer une promotion</a>
        <a class="btn btn-secondary" href="admin/commandes.php">Gérer les commandes</a>
        <a class="btn btn-secondary" href="admin/livraisons.php">Gérer les livraisons</a>
        <a class="btn btn-secondary" href="admin/produits.php">Gérer les produits</a>
        <a class="btn btn-secondary" href="admin/clients.php">Clients</a>
    </section>

    <div class="admin-grid">
        <!-- Formulaire création promotion -->
        <section class="card" id="form-promo" aria-labelledby="promo-title">
            <h2 id="promo-title" style="margin-top:0">Créer une promotion</h2>
            <form action="app/controllers/promotions_create.php" method="post" class="grid-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <div>
                    <label for="promo_nom">Nom de la promo</label>
                    <input type="text" id="promo_nom" name="nom" required placeholder="Anniversaire -25%">
                </div>
                <div>
                    <label for="promo_type">Type</label>
                    <select id="promo_type" name="type" required>
                        <option value="pourcentage">Pourcentage</option>
                        <option value="montant">Montant fixe</option>
                        <option value="frais_livraison_offerts">Frais de livraison offerts</option>
                    </select>
                </div>
                <div>
                    <label for="promo_valeur">Valeur</label>
                    <input type="number" id="promo_valeur" name="valeur" step="0.01" min="0" required placeholder="ex: 25">
                </div>
                <div>
                    <label for="promo_code">Code (optionnel)</label>
                    <input type="text" id="promo_code" name="code" placeholder="HAPPY25">
                </div>
                <div>
                    <label for="promo_debut">Début</label>
                    <input type="date" id="promo_debut" name="date_debut" required>
                </div>
                <div>
                    <label for="promo_fin">Fin</label>
                    <input type="date" id="promo_fin" name="date_fin" required>
                </div>
                <div>
                    <label for="promo_ciblage">Ciblage (facultatif)</label>
                    <input type="text" id="promo_ciblage" name="ciblage" placeholder="catégorie:roses; min_panier:50">
                </div>
                <div class="full">
                    <label for="promo_desc">Description</label>
                    <textarea id="promo_desc" name="description" rows="3" placeholder="Conditions, exclusions, etc."></textarea>
                </div>
                <div class="full" style="display:flex; gap:.5rem; flex-wrap:wrap">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <button class="btn btn-ghost" type="reset">Réinitialiser</button>
                    <a class="btn btn-secondary" href="admin/promotions.php">Gérer les promotions</a>
                </div>
            </form>
        </section>

        <!-- Dernières commandes -->
        <section class="card" aria-labelledby="orders-title">
            <h2 id="orders-title" style="margin-top:0">Dernières commandes</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th><th>Client</th><th>Total</th><th>Statut</th><th>Date</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_orders as $o): ?>
                        <tr>
                            <td><?= (int)$o['id'] ?></td>
                            <td><?= htmlspecialchars($o['client']) ?></td>
                            <td>CHF <?= number_format($o['total'], 2, '.', '\'') ?></td>
                            <td><span class="<?= badge_class($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                            <td><?= htmlspecialchars($o['date']) ?></td>
                            <td class="actions">
                                <a class="btn btn-ghost" href="admin/commande.php?id=<?= (int)$o['id'] ?>">Ouvrir</a>
                                <a class="btn btn-secondary" href="admin/commande_edit.php?id=<?= (int)$o['id'] ?>">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:.75rem">
                <a class="btn btn-ghost" href="admin/commandes.php">Voir toutes les commandes</a>
            </div>
        </section>
    </div>

    <!-- Tuiles de gestion -->
    <section class="features" style="margin-top:18px">
        <article class="card">
            <h3>Promotions</h3>
            <p class="muted">Créez, activez/désactivez, ciblez par catégorie ou code, export CSV.</p>
            <a class="btn btn-secondary" href="admin/promotions.php">Ouvrir</a>
        </article>
        <article class="card">
            <h3>Livraisons</h3>
            <p class="muted">Planifiez les tournées, créneaux, coûts, statuts et preuves de livraison.</p>
            <a class="btn btn-secondary" href="admin/livraisons.php">Ouvrir</a>
        </article>
        <article class="card">
            <h3>Produits</h3>
            <p class="muted">Bouquets/coffrets, variantes, stock, prix, images et catégories.</p>
            <a class="btn btn-secondary" href="admin/produits.php">Ouvrir</a>
        </article>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
