<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT settings FROM widgets WHERE id = ?");
    $stmt->execute([$id]);
    $widget = $stmt->fetch();
    
    if (!$widget) {
        http_response_code(404);
        echo json_encode(['error' => 'Widget not found']);
        exit;
    }
    
    echo json_encode(['settings' => json_decode($widget['settings'] ?? '{}', true) ?? []]);
    exit;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}