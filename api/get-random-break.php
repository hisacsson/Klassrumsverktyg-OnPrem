<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

try {
    $category = isset($_GET['category']) && $_GET['category'] !== '' ? trim(strtolower($_GET['category'])) : null;
    $duration = $_GET['duration'] ?? '';
    $userId = $_GET['user_id'] ?? null;
    $ownOnly = isset($_GET['own_only']) && $_GET['own_only'] == 'true'; // Kontrollera om own-breaks-filter är valt

    // Skapa basquery för att hämta alla matchande ID:n
    $sql = "SELECT id FROM brain_breaks WHERE 1=1";
    $params = [];

    // Lägg till kategorifilter om det finns
    if ($category !== null) {
        $sql .= " AND category COLLATE utf8mb4_general_ci = :category";
        $params[':category'] = $category;
    }

    // Lägg till längdfilter om det finns
    if ($duration) {
        switch ($duration) {
            case 'short':
                $sql .= " AND duration < 120"; // Mindre än 2 minuter
                break;
            case 'medium':
                $sql .= " AND duration BETWEEN 120 AND 300"; // 2-5 minuter
                break;
            case 'long':
                $sql .= " AND duration > 300"; // Mer än 5 minuter
                break;
        }
    }

    // Hantera filtrering baserat på "Visa endast mina aktiviteter"
    if ($userId && $ownOnly) {
        // Om "Visa endast mina aktiviteter" är valt, visa BARA egna
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    } elseif ($userId) {
        // Om användaren är inloggad men "Visa endast mina aktiviteter" INTE är valt, visa både egna och publika
        $sql .= " AND (user_id = :user_id OR is_public = 1)";
        $params[':user_id'] = $userId;
    } 

    // Om ingen användare är inloggad, visa alla publika brain breaks
    if (!$userId) {
        $sql .= " AND is_public = 1";
    }

    // Kör query för att hämta alla matchande ID:n
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Logga hämtade ID:n för debugging
    error_log("Hämtade ID:n: " . json_encode($ids));

    // Om inga ID:n hittades, returnera standardmeddelande
    if (!$ids) {
        echo json_encode([
            'title' => 'Inga brain breaks hittades med valda filter',
            'duration' => null,
            'category' => null,
            'youtube_id' => null,
            'text_content' => null
        ]);
        exit;
    }

    // Slumpa ett av ID:erna
    $randomId = $ids[array_rand($ids)];

    // Logga slumpat ID
    error_log("Slumpat ID: " . $randomId);

    // Hämta det slumpade brain break
    $stmt = $pdo->prepare("SELECT * FROM brain_breaks WHERE id = :id");
    $stmt->execute([':id' => $randomId]);
    $break = $stmt->fetch(PDO::FETCH_ASSOC);

    // Returnera det slumpade breaket
    echo json_encode($break);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ett fel uppstod: ' . $e->getMessage()]);
}
