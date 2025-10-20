<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['widget_id']) || !isset($data['settings'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Hämta befintliga inställningar
    $stmt = $pdo->prepare("SELECT settings FROM widgets WHERE id = ?");
    $stmt->execute([$data['widget_id']]);
    $widget = $stmt->fetch();
    
    if (!$widget) {
        http_response_code(404);
        echo json_encode(['error' => 'Widget not found']);
        exit;
    }
    
    // Slå samman befintliga och nya inställningar
    $currentSettings = json_decode($widget['settings'] ?? '{}', true) ?? [];
    $newSettings = array_merge($currentSettings, $data['settings']);

    if (isset($newSettings['encodedNames'])) {
        // Redan base64-kodat från JavaScript
        $newSettings['encodedNames'] = $newSettings['encodedNames'];
    }
    
    // Uppdatera inställningar
    $stmt = $pdo->prepare("UPDATE widgets SET settings = ?, updated_at = NOW() WHERE id = ?");
    
    try {
        $stmt->execute([json_encode($newSettings), $data['widget_id']]);
        echo json_encode(['success' => true, 'settings' => $newSettings]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}