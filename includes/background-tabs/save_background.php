<?php
// save_background.php
header('Content-Type: application/json');

// Inkludera din databasanslutning
require_once __DIR__ . '/../../../private/src/Config/Database.php';

$db = new Database();
$pdo = $db->getConnection();

// Kontrollera att rätt data har skickats
if (!isset($_POST['type']) || !isset($_POST['value']) || !isset($_POST['whiteboard_id'])) {
    echo json_encode(['success' => false, 'error' => 'Saknar obligatoriska parametrar']);
    exit;
}

// Hämta och validera data
$type = $_POST['type'];
$value = $_POST['value'];
$attribution = isset($_POST['attribution']) ? $_POST['attribution'] : '';
$attribution_link = isset($_POST['attribution_link']) ? $_POST['attribution_link'] : '';
$whiteboard_id = (int)$_POST['whiteboard_id'];

// Enkel validering
if ($whiteboard_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ogiltigt whiteboard ID']);
    exit;
}

// Validera bakgrundstypen
$allowed_types = ['color', 'gradient', 'image'];
if (!in_array($type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Ogiltig bakgrundstyp']);
    exit;
}

try {
    // Uppdatera i databasen
    $stmt = $pdo->prepare("UPDATE whiteboards SET 
        background_type = ?, 
        background_value = ?, 
        background_attribution = ?, 
        background_attribution_link = ?,
        updated_at = NOW() 
        WHERE id = ?");
    $stmt->execute([$type, $value, $attribution, $attribution_link, $whiteboard_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Whiteboard hittades inte eller ingen ändring gjordes']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Databasfel: ' . $e->getMessage()]);
}
?>