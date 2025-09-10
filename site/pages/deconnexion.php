<?php
session_start();
session_unset();
session_destroy();

// Retour à l'accueil
header("Location: index.php");
exit;
