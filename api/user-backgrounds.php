<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Check if AJAX request
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'Unauthorized', 'error');
    } else {
        header('HTTP/1.1 403 Forbidden');
        exit('Unauthorized');
    }
}

$database = new Database();
$pdo = $database->getConnection();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'upload':
        handleUpload($pdo, $_SESSION['user_id']);
        break;
    case 'delete':
        handleDelete($pdo, $_SESSION['user_id']);
        break;
    default:
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Invalid action', 'error');
        } else {
            // Get the referring page to redirect back to it instead of dashboard
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=invalid_action");
            exit();
        }
}

// Function to check if request is AJAX
function isAjaxRequest() {
    return (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

// Function to send JSON response for AJAX requests
function sendJsonResponse($success, $message, $type) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'toast' => [
            'message' => $message,
            'type' => $type
        ]
    ]);
    exit();
}

function handleUpload($pdo, $userId) {
    // Check if user already has 3 backgrounds (max limit)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_backgrounds WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    if ($count >= 3) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Du har redan laddat upp max antal bakgrunder (3)', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=max_backgrounds_reached");
            exit();
        }
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['background_image']['error'] ?? 'unknown';
        error_log("Uppladdningsfel: " . $error);
        
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Filuppladdningen misslyckades', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=upload_failed");
            exit();
        }
    }
    
    $file = $_FILES['background_image'];
    $fileName = basename($file['name']);
    $fileSize = $file['size'];
    $fileType = $file['type'];
    $backgroundName = $_POST['background_name'] ?? 'Min bakgrund';
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($fileType, $allowedTypes)) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Ogiltig filtyp. Endast JPEG, PNG, GIF och WEBP tillåts', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=invalid_file_type");
            exit();
        }
    }
    
    // Validate file size (max 3MB)
    if ($fileSize > 3 * 1024 * 1024) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Filen är för stor. Max storlek är 3MB', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=file_too_large");
            exit();
        }
    }
    
    // Define the upload directory path - corrected path
    $publicDir = $_SERVER['DOCUMENT_ROOT']; // Korrekt rotmapp för webbservern
    $uploadDir = $publicDir . '/uploads/backgrounds/user_' . $userId . '/';
    
    error_log("Ska skapa katalog i: " . $uploadDir);
    
    // Create complete directory path if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $error = error_get_last();
            error_log("Kunde inte skapa katalogen: " . $error['message']);
            
            if (isAjaxRequest()) {
                sendJsonResponse(false, 'Kunde inte skapa uppladdningskatalog', 'error');
            } else {
                // Get the referring page to redirect back to it
                $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
                header("Location: $referer?error=failed_to_create_directory");
                exit();
            }
        }
        error_log("Katalog skapad: " . $uploadDir);
    }
    
    // Generate unique filename
    $uniqueFileName = uniqid() . '_' . $fileName;
    $uploadPath = $uploadDir . $uniqueFileName;
    
    error_log("Försöker ladda upp fil till: " . $uploadPath);
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Save to database with correct relative path
        $relativePath = '/uploads/backgrounds/user_' . $userId . '/' . $uniqueFileName;
        $stmt = $pdo->prepare("INSERT INTO user_backgrounds (user_id, image_path, name) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $relativePath, $backgroundName]);
        
        error_log("Fil uppladdad framgångsrikt: " . $relativePath);
        
        if (isAjaxRequest()) {
            // Return additional data for AJAX response
            $backgroundId = $pdo->lastInsertId(); // Get the ID of the newly inserted background
            
            // Prepare response with data that might be needed for UI updates
            $response = [
                'success' => true,
                'toast' => [
                    'message' => 'Bakgrunden har laddats upp',
                    'type' => 'success'
                ],
                'background' => [
                    'id' => $backgroundId,
                    'name' => $backgroundName,
                    'path' => $relativePath
                ]
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?success=background_uploaded");
            exit();
        }
    } else {
        $error = error_get_last();
        error_log("Kunde inte flytta uppladdad fil: " . ($error ? $error['message'] : 'Okänt fel'));
        
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Kunde inte slutföra filuppladdningen', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=move_failed");
            exit();
        }
    }
}

function handleDelete($pdo, $userId) {
    $backgroundId = $_POST['background_id'] ?? 0;
    if (!$backgroundId) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Ogiltig bakgrund', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=invalid_background");
            exit();
        }
    }
    
    // Get background info
    $stmt = $pdo->prepare("SELECT image_path FROM user_backgrounds WHERE id = ? AND user_id = ?");
    $stmt->execute([$backgroundId, $userId]);
    $background = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$background) {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Bakgrunden hittades inte', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=background_not_found");
            exit();
        }
    }
    
    // Delete file
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $background['image_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM user_backgrounds WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$backgroundId, $userId]);
    
    if ($success) {
        if (isAjaxRequest()) {
            sendJsonResponse(true, 'Bakgrunden har tagits bort', 'success');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?success=background_deleted");
            exit();
        }
    } else {
        if (isAjaxRequest()) {
            sendJsonResponse(false, 'Kunde inte ta bort bakgrunden', 'error');
        } else {
            // Get the referring page to redirect back to it
            $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
            header("Location: $referer?error=delete_failed");
            exit();
        }
    }
}