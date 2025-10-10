<?php
/* ========= admin_header.php =========
 * Partiel d’en-tête admin réutilisable (titre à gauche, actions à droite).
 *
 * Utilisation sur une page :
 *   $admin_title   = 'Gestion des livraisons';
 *   $admin_actions = '
 *      <a class="btn btn-ghost" href="'.$BASE.'adminAccueil.php">← Retour au dashboard</a>
 *      <a class="btn btn-primary" href="'.$BASE.'admin_commande.php">Commandes</a>
 *   ';
 *   // Optionnel : décalage si tu as un header global fixe (ex. 64px)
 *   $admin_top_offset = 0; // ou 64, etc.
 *   require __DIR__.'/../partials/admin_header.php';
 */

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$admin_title      = $admin_title      ?? '';
$admin_actions    = $admin_actions    ?? '';
$admin_top_offset = isset($admin_top_offset) ? (int)$admin_top_offset : 0;

/* Injecte le CSS une seule fois, même si le partiel est inclus plusieurs fois */
if (!defined('ADMIN_TOPBAR_CSS_ONCE')) {
    define('ADMIN_TOPBAR_CSS_ONCE', true);
    ?>
    <style>
        /* ====== HEADER ADMIN COMMUN ====== */
        .admin-topbar{
            display:flex;
            align-items:center;
            justify-content:space-between; /* Titre à gauche / actions à droite */
            gap:16px;
            padding:14px 20px;
            background:#fff;
            border:1px solid #e5e5e5;
            border-radius:12px;
            margin:10px 0 25px;
            position:sticky;
            top:0;                 /* Ajusté dynamiquement via style inline si besoin */
            z-index:50;
            box-shadow:0 1px 3px rgba(0,0,0,0.05);
        }
        .admin-topbar__title{
            margin:0;
            font-size:22px;
            font-weight:700;
            color:#1a1a1a;
        }
        .admin-topbar__actions{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
        }

        /* ====== Boutons ====== */
        .btn{
            display:inline-flex; align-items:center; justify-content:center; gap:6px;
            padding:8px 16px;
            font-size:15px; font-weight:500;
            text-decoration:none;
            border-radius:999px;
            border:1px solid transparent;
            transition:all .2s ease;
            cursor:pointer;
        }
        .btn-ghost{ background:#fff; border-color:#8A1B2E55; color:#8A1B2E; }
        .btn-ghost:hover{ background:#8A1B2E0F; transform:translateY(-1px); }

        .btn-primary{ background:#8A1B2E; color:#fff; }
        .btn-primary:hover{ filter:brightness(0.95); transform:translateY(-1px); }

        /* ====== Responsive ====== */
        @media (max-width:768px){
            .admin-topbar{ flex-direction:column; align-items:flex-start; gap:12px; }
            .admin-topbar__actions{ width:100%; justify-content:flex-start; }
        }
    </style>
    <?php
}

/* Rend le header (top sticky ajustable via style inline) */
?>
<header class="admin-topbar" role="banner" style="<?= $admin_top_offset ? 'top:'.(int)$admin_top_offset.'px' : '' ?>">
    <h1 class="admin-topbar__title"><?= h($admin_title) ?></h1>
    <nav class="admin-topbar__actions" aria-label="Actions">
        <?= $admin_actions ?>
    </nav>
</header>
