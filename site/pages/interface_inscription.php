<?php
// /site/pages/interface_inscription.php
session_start();

// Base URL
if (!isset($BASE)) {
    $dir  = rtrim(dirname($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME']), '/\\');
    $BASE = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>DK Bloom — Inscription</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/style_header_footer.css">
    <link rel="stylesheet" href="<?= $BASE ?>css/style_connexion_inscription.css">

    <!--
    ======================================================================================
    [NOUVEAU DESIGN — EN ATTENTE DE VALIDATION]
    Pour ACTIVER ce design :
      1) Dé-commentez ce <style> CI-DESSOUS
      2) Dans <body>, ajoutez class="logout"
      3) Remplacez le <main> "ANCIEN DESIGN" par le <main> "NOUVEAU DESIGN" plus bas
    ======================================================================================

    <style>
      :root{
        --dk-bg-1:#5C0012;   /* bordeaux foncé validé */
        --dk-bg-2:#8A1B2E;   /* bordeaux rosé pour le dégradé */
        --card-bg:rgba(255,255,255,.10);
        --card-bd:rgba(255,255,255,.28);
        --glass-blur:16px;
        --accent:#ffffff;
      }
      body.logout{
        min-height:100vh; margin:0; color:#fff; display:flex; flex-direction:column;
        background:
          radial-gradient(1200px 600px at 10% -10%, #ffb3c9 0%, transparent 60%),
          radial-gradient(900px 500px at 110% 110%, #ffcfe0 0%, transparent 60%),
          linear-gradient(120deg, var(--dk-bg-1), var(--dk-bg-2));
      }
      .logout-wrap{ flex:1; display:grid; place-items:center; padding:clamp(20px,6vw,64px); position:relative; overflow:hidden; }
      .bubble{ position:absolute; border-radius:50%; opacity:.25; filter:blur(2px);
               background: radial-gradient(closest-side,#fff,rgba(255,255,255,.15));
               animation: float 12s ease-in-out infinite; }
      .b1{ width:220px; height:220px; top:8%;  left:8%;  animation-delay:0s; }
      .b2{ width:160px; height:160px; bottom:12%; right:14%; animation-delay:1.2s; }
      .b3{ width:120px; height:120px; top:18%; right:28%; animation-delay:.6s; }
      @keyframes float{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }

      .logout-card{
        width:min(620px,92vw);
        background:var(--card-bg); border:1px solid var(--card-bd);
        border-radius:22px; backdrop-filter:blur(var(--glass-blur)); -webkit-backdrop-filter:blur(var(--glass-blur));
        box-shadow:0 18px 50px rgba(0,0,0,.25);
        padding:clamp(22px,3.8vw,36px); color:#fff; text-align:left;
      }
      .logout-card h1{ margin:.2rem 0 1rem; font-size:clamp(24px,2.8vw,32px); font-weight:800; text-align:center; }
      .subtitle{ margin:-6px auto 18px; opacity:.95; text-align:center; font-size:clamp(14px,1.4vw,16px); }

      form.form-inscription{ display:grid; gap:14px; }
      .logout-card label{ font-size:14px; opacity:.95; }
      .logout-card input[type="text"],
      .logout-card input[type="email"],
      .logout-card input[type="tel"],
      .logout-card input[type="password"]{
        width:100%; padding:12px 14px; border-radius:12px;
        border:1px solid rgba(255,255,255,.35);
        background:rgba(255,255,255,.12); color:#fff; outline:none;
      }
      .logout-card input::placeholder{ color:rgba(255,255,255,.75); }
      .logout-card input:focus{ border-color:var(--accent); box-shadow:0 0 0 3px rgba(255,255,255,.22); }

      .password-wrapper{ position:relative; display:flex; align-items:center; }
      .password-wrapper input{ flex:1; padding-right:44px; }
      .toggle-password{ position:absolute; right:10px; top:50%; transform:translateY(-50%);
        background:none; border:0; cursor:pointer; font-size:1.2rem; line-height:1; color:#fff; opacity:.85; }
      .toggle-password:hover{ opacity:1; }

      .actions{ display:flex; align-items:center; gap:12px; margin-top:6px; flex-wrap:wrap; }
      .btn-primary{
        display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer; text-decoration:none;
        padding:12px 20px; border-radius:999px; font-weight:700;
        background:#fff; color:#5C0012;
        box-shadow:0 10px 24px rgba(255,255,255,.18);
        transition:transform .15s ease, box-shadow .2s ease, filter .2s ease;
      }
      .btn-primary:hover{ transform:translateY(-1px); filter:brightness(1.03); }
      .link{ color:#ffd7de; text-decoration:none; font-size:14px; }
      .link:hover{ text-decoration:underline; }
    </style>
    -->

    <!-- ====== styles MINIMAUX de l'ANCIEN DESIGN (actifs) ====== -->
    <style>
        .password-wrapper {
            position: relative; display: flex; align-items: center;
        }
        .password-wrapper input {
            flex: 1; padding-right: 45px; font-size: 1rem;
        }
        .toggle-password {
            position: absolute; right: 10px; cursor: pointer;
            background: none; border: none; font-size: 1.4rem; line-height: 1; padding: 4px;
        }
    </style>
</head>

<!-- ======================================================================================
     ANCIEN DESIGN — ACTIF (garde l’apparence actuelle)
     Pour tester le NOUVEAU DESIGN, voir plus bas la section commentée "NOUVEAU DESIGN "
====================================================================================== -->
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="container">
    <div class="conteneur_form">
        <h2>S'inscrire</h2>

        <form action="<?= $BASE ?>traitement_inscription.php" method="POST" novalidate>
            <label for="lastname">Nom</label>
            <input type="text" id="lastname" name="lastname" required maxlength="50" autocomplete="family-name" placeholder="Dupont">

            <label for="firstname">Prénom</label>
            <input type="text" id="firstname" name="firstname" required maxlength="30" autocomplete="given-name" placeholder="Alice">

            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone"
                   required inputmode="numeric"
                   pattern="^0?7[0-9](?:[ .]?[0-9]{3}){2}[ .]?[0-9]{2}$" maxlength="14"
                   placeholder="Ex.: 079 123 45 67" autocomplete="tel">

            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email"
                   pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$" required maxlength="50"
                   autocomplete="email" placeholder="Ex.: luci@gmail.com">

            <label for="password">Mot de passe</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password"
                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$"
                       required minlength="8" autocomplete="new-password"">
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)">👁</button>
            </div>

            <input type="submit" value="S'inscrire">
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
    function togglePassword(fieldId, btn) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        if (field.type === "password") { field.type = "text"; btn.textContent = "🕶"; }
        else { field.type = "password"; btn.textContent = "👁"; }
    }
</script>

<!-- toasts -->
<script>window.DKBASE = <?= json_encode($BASE) ?>;</script>
<script src="<?= $BASE ?>js/commande.js"></script>
<?php if (!empty($_SESSION['toast'])):
    $t = $_SESSION['toast']; unset($_SESSION['toast']); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const msg  = <?= json_encode($t['text'], JSON_UNESCAPED_UNICODE) ?>;
            const type = <?= json_encode($t['type']) ?>; // 'error' | 'success' | 'info'
            if (typeof window.toast === 'function') { window.toast(msg, type); }
            else if (typeof window.showToast === 'function') { window.showToast(msg, type); }
        });
    </script>
<?php endif; ?>

<!--
======================================================================================
NOUVEAU DESIGN — COMMENTÉ (à activer après validation)
Étapes d’activation :
  A) Ajouter class="logout" au <body> ci-dessus
  B) Dé-commenter le <style> "NOUVEAU DESIGN" dans le <head>
  C) Remplacer le <main> "ANCIEN DESIGN" par ce bloc <main> :
======================================================================================

<main class="logout-wrap">
  <span class="bubble b1"></span>
  <span class="bubble b2"></span>
  <span class="bubble b3"></span>

  <section class="logout-card">
    <h1>Créer un compte</h1>
    <p class="subtitle">Rejoignez l’univers DK Bloom — élégance, raffinement & livraison soignée.</p>

    <form action="<?= $BASE ?>traitement_inscription.php" method="POST" class="form-inscription" novalidate>
      <label for="lastname">Nom</label>
      <input type="text" id="lastname" name="lastname" required maxlength="50" autocomplete="family-name" placeholder="Dupont">

      <label for="firstname">Prénom</label>
      <input type="text" id="firstname" name="firstname" required maxlength="30" autocomplete="given-name" placeholder="Alice">

      <label for="phone">Téléphone</label>
      <input type="tel" id="phone" name="phone" required inputmode="numeric"
             placeholder="07x xxx xx xx"
             pattern="^0?7[0-9](?:[ .]?[0-9]{3}){2}[ .]?[0-9]{2}$"
             maxlength="14" autocomplete="tel">

      <label for="email">Adresse e-mail</label>
      <input type="email" id="email" name="email" required maxlength="50" autocomplete="email"
             placeholder="prenom.nom@email.com"
             pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$">

      <label for="password">Mot de passe</label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required autocomplete="new-password"
               minlength="8"
               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$"
               placeholder="Min. 8 caractères (Maj, min, chiffre, spécial)">
        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">👁</button>
      </div>

      <div class="actions">
        <button type="submit" class="btn-primary">S'inscrire</button>
        <a class="link" href="<?= $BASE ?>interface_connexion.php">Déjà inscrit ? Se connecter</a>
      </div>
    </form>
  </section>
</main>

======================================================================================
FIN — NOUVEAU DESIGN
======================================================================================
-->

</body>
</html>
