<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

// Kontrollera om användaren är inloggad
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Hämta POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['board_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing board code']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// Hämta whiteboard
$stmt = $pdo->prepare("SELECT * FROM whiteboards WHERE board_code = ?");
$stmt->execute([$data['board_code']]);
$whiteboard = $stmt->fetch();

if (!$whiteboard) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Whiteboard not found']);
    exit;
}

// Uppdatera whiteboard med användar-ID och nytt utgångsdatum
$stmt = $pdo->prepare("
    UPDATE whiteboards 
    SET user_id = ?, 
        expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY)
    WHERE board_code = ?
");

try {
    $stmt->execute([$_SESSION['user_id'], $data['board_code']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>