<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

// Kontrollera att användaren är inloggad
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Kontrollera att en fil har laddats upp
if (!isset($_FILES['background'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['background'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validera filtyp
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}

// Validera filstorlek
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}

// Skapa uppladdningsmapp om den inte finns
$upload_dir = '../../assets/img/uploads/backgrounds/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generera unikt filnamn
$filename = uniqid('bg_') . '_' . time() . '_' . basename($file['name']);
$filepath = $upload_dir . $filename;

// Försök ladda upp filen
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $relative_path = '/assets/img/uploads/backgrounds/' . $filename;
    echo json_encode([
        'success' => true,
        'url' => $relative_path
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}