<?php
// cleanup_inactive_accounts.php
require_once __DIR__ . '/../src/Config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Hitta alla ej aktiverade användare där activation token har gått ut
$stmt = $db->prepare("
    DELETE users 
    FROM users 
    INNER JOIN account_activations ON users.id = account_activations.user_id
    WHERE users.is_active = 0 
    AND account_activations.expires_at < NOW()
");

$stmt->execute();