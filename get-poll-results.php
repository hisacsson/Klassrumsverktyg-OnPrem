<?php
require_once __DIR__ . '/src/Config/Database.php';
header('Content-Type: application/json');

$db = new Database();
$pdo = $db->getConnection();

$widgetId = $_GET['widget_id'] ?? null;

if (!$widgetId) {
    echo json_encode(['error' => 'Widget ID saknas']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.question, o.text, o.votes 
    FROM polls p
    JOIN poll_options o ON p.id = o.poll_id
    WHERE p.widget_id = ?
    ORDER BY o.id
");
$stmt->execute([$widgetId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$results) {
    echo json_encode(['error' => 'Ingen omröstning hittades']);
    exit;
}

echo json_encode($results);