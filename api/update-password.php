<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    header('Location: /dashboard.php?error=missing_fields');
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: /dashboard.php?error=password_mismatch');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Verify current password
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!password_verify($current_password, $user['password'])) {
    header('Location: /dashboard.php?error=invalid_password');
    exit;
}

// Update password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([$hashed_password, $_SESSION['user_id']]);

header('Location: /dashboard.php?success=password_updated');