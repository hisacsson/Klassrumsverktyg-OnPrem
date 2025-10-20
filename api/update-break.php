<?php
session_start(); // Viktigt: Starta sessionen!
require_once __DIR__ . '/../src/Config/Database.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ingen eller ogiltig JSON-data mottagen.']);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Användaren är inte inloggad.");  // Bättre felmeddelande
    }

    $whiteboardUserId = (int)$_SESSION['user_id'];  // Hämta från sessionen och typomvandla/validera

    if ($whiteboardUserId <= 0) {
        throw new Exception("Ogiltigt användar-ID.");
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "UPDATE brain_breaks SET 
            title = :title,
            category = :category,
            duration = :duration,
            youtube_id = :youtube_id,
            text_content = :text_content,
            is_public = :is_public
            WHERE id = :id AND user_id = :user_id"; // Viktigt: Använd user_id i WHERE-klausulen

    $stmt = $pdo->prepare($sql);

    // Bind parameters for security (prevents SQL injection)
    $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
    $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
    $stmt->bindValue(':category', $data['category'], PDO::PARAM_STR);
    $stmt->bindValue(':duration', $data['duration'] === null ? null : (int)$data['duration'], PDO::PARAM_INT); // Handle null duration
    $stmt->bindValue(':youtube_id', $data['youtube_id'], PDO::PARAM_STR);
    $stmt->bindValue(':text_content', $data['text_content'], PDO::PARAM_STR);
    $stmt->bindValue(':is_public', $data['is_public'] ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $whiteboardUserId, PDO::PARAM_INT); // Make sure $whiteboardUserId is an integer

    $result = $stmt->execute();

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Database error: " . $errorInfo[2]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>