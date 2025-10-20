<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$background_type = $_POST['background_type'] ?? 'color';
$background_value = '';

if ($background_type === 'color') {
    $background_value = $_POST['background_color'] ?? '#ffffff';
} elseif ($background_type === 'gradient') {
    // For gradient, we store the CSS values
    $color1 = $_POST['gradient_color_1'] ?? '#ffffff';
    $color2 = $_POST['gradient_color_2'] ?? '#e2e2e2';
    $direction = $_POST['gradient_direction'] ?? 'to right';
    $background_value = "linear-gradient($direction, $color1, $color2)";
} elseif ($background_type === 'custom' && isset($_POST['custom_background_id'])) {
    // For custom backgrounds, we reference the background ID
    $background_type = 'custom';
    $background_value = $_POST['custom_background_id'];
} elseif ($background_type === 'image' && isset($_FILES['background_image'])) {
    $file = $_FILES['background_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = uniqid() . '_' . basename($file['name']);
        $upload_dir = __DIR__ . '/../../public/uploads/backgrounds/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $background_value = '/uploads/backgrounds/' . $filename;
        }
    }
}

$database = new Database();
$pdo = $database->getConnection();

// Update user default settings
$stmt = $pdo->prepare("UPDATE users SET 
    default_background_type = ?,
    default_background_value = ?
    WHERE id = ?");
$stmt->execute([
    $background_type,
    $background_value,
    $_SESSION['user_id']
]);

header('Location: /dashboard.php?success=defaults_updated');