<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

try {
    session_start();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('Användare inte inloggad');
    }

    // Hämta break ID
    $breakId = $_POST['id'] ?? null;
    
    if (!$breakId) {
        throw new Exception('Inget break-ID angivet');
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Kontrollera att användaren äger denna break
    $stmt = $pdo->prepare("SELECT user_id FROM brain_breaks WHERE id = :id");
    $stmt->execute([':id' => $breakId]);
    $break = $stmt->fetch();

    if (!$break) {
        throw new Exception('Brain break hittades inte');
    }

    if ($break['user_id'] != $userId) {
        throw new Exception('Du har inte behörighet att ta bort denna brain break');
    }

    // Ta bort break
    $stmt = $pdo->prepare("DELETE FROM brain_breaks WHERE id = :id AND user_id = :userId");
    $stmt->execute([
        ':id' => $breakId,
        ':userId' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Brain break borttagen!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}