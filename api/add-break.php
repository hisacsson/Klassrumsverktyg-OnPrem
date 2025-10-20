<?php
session_start(); // Starta sessionen så att $_SESSION är tillgänglig
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

try {
    // Kolla om användaren är inloggad
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Användaren är inte inloggad.");
    }

    $whiteboardUserId = (int)$_SESSION['user_id']; // Hämta användar-ID från sessionen
    if ($whiteboardUserId <= 0) {
        throw new Exception("Ogiltigt användar-ID.");
    }

    // Hämta POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validera nödvändig data
    if (!isset($data['title']) || !isset($data['category'])) {
        throw new Exception('Titel och kategori krävs');
    }

    $db = new Database();
    $pdo = $db->getConnection();
    
    // Förbered data för insert
    $params = [
        ':title' => $data['title'],
        ':category' => $data['category'],
        ':duration' => $data['duration'] ?? null,
        ':youtube_id' => $data['youtube_id'] ?? null,
        ':text_content' => $data['text_content'] ?? null,
        ':is_public' => isset($data['is_public']) && $data['is_public'] ? 1 : 0,
        ':user_id' => $whiteboardUserId // Nu är denna variabel korrekt definierad
    ];

    $sql = "INSERT INTO brain_breaks (title, category, duration, youtube_id, text_content, is_public, user_id) 
            VALUES (:title, :category, :duration, :youtube_id, :text_content, :is_public, :user_id)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ett fel uppstod: ' . $e->getMessage()]);
}
?>
