<?php
// File: /api/get-user-backgrounds.php

session_start();
require_once __DIR__ . '/../src/Config/Database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

try {
    // Get user backgrounds
    $stmt = $pdo->prepare("SELECT id, name, image_path, created_at FROM user_backgrounds WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'backgrounds' => $backgrounds
    ]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Ett databasfel uppstod vid hämtning av bakgrunder.'
    ]);
}
?>