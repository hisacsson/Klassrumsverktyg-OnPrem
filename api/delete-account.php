<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

// Verify user password before deletion
$password = $_POST['password'] ?? '';
if (empty($password)) {
    header('Location: /dashboard.php?error=password_required');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

// Verify password
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: /dashboard.php?error=invalid_password');
    exit();
}

// Start transaction
$pdo->beginTransaction();

try {
    // Get user's whiteboards
    $stmt = $pdo->prepare("SELECT id FROM whiteboards WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $whiteboards = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete widgets connected to user's whiteboards
    if (!empty($whiteboards)) {
        $placeholders = implode(',', array_fill(0, count($whiteboards), '?'));
        $stmt = $pdo->prepare("DELETE FROM widgets WHERE whiteboard_id IN ($placeholders)");
        $stmt->execute($whiteboards);
        
        // Delete student groups connected to user's whiteboards
        $stmt = $pdo->prepare("DELETE FROM student_groups WHERE whiteboard_id IN ($placeholders)");
        $stmt->execute($whiteboards);
    }
    
    // Delete whiteboards
    $stmt = $pdo->prepare("DELETE FROM whiteboards WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Delete whiteboard limits
    $stmt = $pdo->prepare("DELETE FROM whiteboard_limits WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Delete brain breaks
    $stmt = $pdo->prepare("DELETE FROM brain_breaks WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Delete password resets
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Delete account activations
    $stmt = $pdo->prepare("DELETE FROM account_activations WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Delete custom backgrounds
    $stmt = $pdo->prepare("DELETE FROM user_backgrounds WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Delete uploaded files
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/backgrounds/user_' . $_SESSION['user_id'] . '/';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($uploadDir);
}
    
    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Commit transaction
    $pdo->commit();
    
    // Destroy session and redirect to logout
    session_destroy();
    header('Location: /logout.php?action=account_deleted');
    exit();
    
} catch (Exception $e) {
    // Roll back transaction if something failed
    $pdo->rollBack();
    error_log("Account deletion failed: " . $e->getMessage());
    header('Location: /dashboard.php?error=deletion_failed');
    exit();
}