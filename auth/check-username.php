<?php
// Stäng av all output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Stäng av PHP:s felrapportering för att förhindra att felmeddelanden blandas med JSON
ini_set('display_errors', 0);
error_reporting(0);

// Sätt headers
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../src/Config/Database.php';
    
    // Skapa en databasinstans och hämta anslutningen
    $database = new Database();
    $db = $database->getConnection();

    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($inputData['username'])) {
        throw new Exception('Missing username parameter');
    }

    $username = trim($inputData['username']);
    
    // Validering
    if (empty($username)) {
        throw new Exception('Username cannot be empty');
    }

    if (strlen($username) < 3) {
        throw new Exception('Username must be at least 3 characters long');
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        throw new Exception('Username can only contain letters, numbers and underscores');
    }

    // Kontrollera om användarnamnet finns
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $exists = $stmt->fetchColumn() > 0;
    
    $response = [];
    
    if (!$exists) {
        $response = [
            'available' => true,
            'message' => 'Användarnamnet är tillgängligt'
        ];
    } else {
        $suggestions = [];
        $baseUsername = preg_replace('/[0-9]+$/', '', $username);
        
        for ($i = 2; $i <= 5; $i++) {
            $suggestions[] = $baseUsername . $i;
            if (count($suggestions) >= 3) break;
        }
        
        $response = [
            'available' => false,
            'message' => 'Användarnamnet är upptaget',
            'suggestions' => $suggestions
        ];
    }
    
    // Säkerställ att vi har ett rent svar
    if (ob_get_length()) ob_clean();
    
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Säkerställ att vi har ett rent svar även vid fel
    if (ob_get_length()) ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
    exit;
}