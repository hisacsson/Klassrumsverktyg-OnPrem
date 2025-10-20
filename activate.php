<?php
session_start();
require_once __DIR__ . '/src/Config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    if (empty($_GET['token'])) {
        throw new Exception('Ingen aktiveringskod angiven');
    }
    
    $stmt = $db->prepare("
        SELECT a.*, u.email 
        FROM account_activations a
        JOIN users u ON u.id = a.user_id
        WHERE a.token = ? AND a.expires_at > NOW()
    ");
    
    $stmt->execute([$_GET['token']]);
    $activation = $stmt->fetch();
    
    if (!$activation) {
        throw new Exception('Ogiltig eller utgången aktiveringskod');
    }
    
    // Aktivera kontot
    $stmt = $db->prepare("
        UPDATE users 
        SET is_active = 1 
        WHERE id = ?
    ");
    
    $stmt->execute([$activation['user_id']]);
    
    // Ta bort aktiveringstoken
    $stmt = $db->prepare("
        DELETE FROM account_activations 
        WHERE user_id = ?
    ");
    
    $stmt->execute([$activation['user_id']]);
    
    $_SESSION['success'] = 'Ditt konto har aktiverats! Du kan nu logga in.';
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: register.php');
    exit;
}