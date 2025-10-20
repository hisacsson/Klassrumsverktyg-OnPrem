<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

// Sätt headers
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Stäng av HTML-felmeddelanden

try {
    $breakId = $_GET['id'] ?? null;

    if (!$breakId) {
        throw new Exception('ID saknas');
    }

    $db = new Database();
    $pdo = $db->getConnection();
    
    // Lägg till error logging
    error_log("Försöker hämta break med ID: " . $breakId);
    
    // Hämta brain break direkt
    $stmt = $pdo->prepare("SELECT * FROM brain_breaks WHERE id = :id");
    $stmt->execute([':id' => $breakId]);
    
    $break = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($break) {
        error_log("Break hittad: " . json_encode($break));
        echo json_encode($break);
    } else {
        throw new Exception('Brain break hittades inte');
    }

} catch (Exception $e) {
    error_log('Error i get-break.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>