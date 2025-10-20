<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Hämta användar-ID från sessionen
    session_start();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('Användare inte inloggad');
    }

    // Hämta breaks som antingen tillhör användaren eller är publika
    $sql = "SELECT * FROM brain_breaks 
            WHERE user_id = :userId 
            OR is_public = true 
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':userId' => $userId]);
    
    $breaks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatera duration till mer läsbar form
    foreach ($breaks as &$break) {
        if ($break['duration']) {
            if ($break['duration'] < 60) {
                $break['duration_text'] = $break['duration'] . " sekunder";
            } else {
                $minutes = floor($break['duration'] / 60);
                $seconds = $break['duration'] % 60;
                $break['duration_text'] = $minutes . " min" . ($seconds ? " " . $seconds . " sek" : "");
            }
        } else {
            $break['duration_text'] = "Ingen tid satt";
        }
        
        // Markera om det är användarens egen break
        $break['is_owner'] = ($break['user_id'] == $userId);
    }

    echo json_encode($breaks);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Ett fel uppstod: ' . $e->getMessage()
    ]);
}