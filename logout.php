<?php
session_start();

// Rensa alla sessionsvariabler
$_SESSION = [];

// Förstör sessionen
session_destroy();

// Omdirigera till inloggningssidan
header('Location: login.php');
exit;