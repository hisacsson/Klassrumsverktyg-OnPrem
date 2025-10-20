<?php
// admin/api/limits.php
header('Content-Type: application/json');

require_once $root . '../../../src/Config/Database.php';

// Kontrollera om användaren är inloggad och är admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Hämta POST-data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($data['action']) {
    case 'set_global_limit':
        if (!isset($data['limit']) || !is_numeric($data['limit']) || $data['limit'] < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid limit value']);
            exit;
        }
        
        // Kontrollera om inställningen redan finns
        $stmt = $db->prepare("SELECT id FROM system_settings WHERE setting_key = 'global_whiteboard_limit'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Uppdatera existerande inställning
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = :limit WHERE setting_key = 'global_whiteboard_limit'");
        } else {
            // Skapa ny inställning
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                                VALUES ('global_whiteboard_limit', :limit, 'Det maximala antalet whiteboards som varje användare kan skapa som standard')");
        }
        
        $stmt->bindParam(':limit', $data['limit']);
        $result = $stmt->execute();
        
        echo json_encode(['success' => $result]);
        break;
        
    case 'set_user_limit':
        if (!isset($data['user_id']) || !is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        if (!isset($data['limit']) || !is_numeric($data['limit']) || $data['limit'] < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid limit value']);
            exit;
        }
        
        // Kontrollera om användaren finns
        $stmt = $db->prepare("SELECT id FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Kontrollera om användaren redan har en begränsning
        $stmt = $db->prepare("SELECT id FROM whiteboard_limits WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Uppdatera existerande begränsning
            $stmt = $db->prepare("UPDATE whiteboard_limits SET max_whiteboards = :limit, updated_at = NOW() WHERE user_id = :user_id");
        } else {
            // Skapa ny begränsning
            $stmt = $db->prepare("INSERT INTO whiteboard_limits (user_id, max_whiteboards) VALUES (:user_id, :limit)");
        }
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':limit', $data['limit']);
        $result = $stmt->execute();
        
        echo json_encode(['success' => $result]);
        break;
        
    case 'reset_user_limit':
        if (!isset($data['user_id']) || !is_numeric($data['user_id']) || $data['user_id'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        // Ta bort användarens anpassade begränsning
        $stmt = $db->prepare("DELETE FROM whiteboard_limits WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $data['user_id']);
        $result = $stmt->execute();
        
        echo json_encode(['success' => $result]);
        break;
        
    case 'reset_all_limits':
        // Ta bort alla anpassade begränsningar
        $stmt = $db->prepare("DELETE FROM whiteboard_limits WHERE user_id IS NOT NULL");
        $result = $stmt->execute();
        
        echo json_encode(['success' => $result]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
?>