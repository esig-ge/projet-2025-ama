



<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DK Bloom — Accueil</title>
    <link rel="stylesheet" href="<?= $BASE ?>css/adminproduit.css">
</head>
<body>
<div class="wrap">
    <h1>Produits</h1>
    <table>
        <thead>
        <tr>
            <th>Nom</th>
            <th>ID</th>
            <th>Couleur</th>
            <th>Qté min</th>
            <th>Qté max</th>
            <th>Qté actuelle</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td data-label="Nom">Rose rouge</td>
            <td data-label="ID">#12</td>
            <td data-label="Couleur">rouge</td>
            <td data-label="Qté min">5</td>
            <td data-label="Qté max">50</td>
            <td data-label="Qté actuelle">22</td>
            <td data-label="Actions">
                <a href="#" class="btn">Modifier</a>
                <a href="#" class="btn danger">Supprimer</a>
            </td>
        </tr>
        <tr>
            <td data-label="Nom">Rose blanche</td>
            <td data-label="ID">#13</td>
            <td data-label="Couleur">blanc</td>
            <td data-label="Qté min">3</td>
            <td data-label="Qté max">40</td>
            <td data-label="Qté actuelle">0</td>
            <td data-label="Actions">
                <a href="#" class="btn">Modifier</a>
                <a href="#" class="btn danger">Supprimer</a>
            </td>
        </tr>
        <tr>
            <td data-label="Nom">Bouquet mix</td>
            <td data-label="ID">#14</td>
            <td data-label="Couleur">multicolore</td>
            <td data-label="Qté min">10</td>
            <td data-label="Qté max">100</td>
            <td data-label="Qté actuelle">74</td>
            <td data-label="Actions">
                <a href="#" class="btn">Modifier</a>
                <a href="#" class="btn danger">Supprimer</a>
            </td>
        </tr>
        </tbody>
    </table>
</div>
</body>