<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests with JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

// Validate required fields
if (!isset($data['whiteboard_id']) || !isset($data['type']) || !isset($data['value'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$whiteboardId = $data['whiteboard_id'];
$type = $data['type'];
$value = $data['value'];

// Connect to database
$database = new Database();
$pdo = $database->getConnection();

// Verify if user has access to this whiteboard
$stmt = $pdo->prepare("SELECT id FROM whiteboards WHERE id = ? AND user_id = ?");
$stmt->execute([$whiteboardId, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to modify this whiteboard']);
    exit;
}

// If type is 'custom', we need to get the image path from the user_backgrounds table
if ($type === 'custom') {
    $backgroundId = $value;
    $stmt = $pdo->prepare("SELECT image_path FROM user_backgrounds WHERE id = ? AND user_id = ?");
    $stmt->execute([$backgroundId, $_SESSION['user_id']]);
    $background = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$background) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Background not found']);
        exit;
    }
    
    // Store the background reference ID in a custom field or JSON metadata if needed
    // But use 'image' type with the actual path for consistency
    $type = 'image';
    $value = $background['image_path'];
    $imagePath = $background['image_path'];
} else {
    $imagePath = ($type === 'image') ? $value : null;
}

// Update the whiteboard background
try {
    $stmt = $pdo->prepare("UPDATE whiteboards SET background_type = ?, background_value = ? WHERE id = ?");
    $stmt->execute([$type, $value, $whiteboardId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Background updated successfully',
        'type' => $type,
        'value' => $value,
        'image_path' => $imagePath
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}