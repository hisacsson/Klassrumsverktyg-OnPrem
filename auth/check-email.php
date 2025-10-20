<?php
// auth/check-email.php

while (ob_get_level()) {
    ob_end_clean();
}

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../src/Config/Database.php';
    
    // Skapa en databasinstans och hämta anslutningen
    $database = new Database();
    $db = $database->getConnection();

    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($inputData['email'])) {
        throw new Exception('Missing email parameter');
    }

    $email = trim($inputData['email']);
    
    // Validering
    if (empty($email)) {
        throw new Exception('Email cannot be empty');
    }

    // Validera e-postformat
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Kontrollera om e-postadressen redan finns
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetchColumn() > 0;
    
    $response = [
        'available' => !$exists,
        'message' => $exists ? 'E-postadressen är redan registrerad' : 'E-postadressen är tillgänglig'
    ];
    
    // Säkerställ att vi har ett rent svar
    if (ob_get_length()) ob_clean();
    
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
    exit;
}