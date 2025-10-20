<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
error_log('Starting Google Sign-In process');

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../src/Config/Database.php';

// Prefer Composer autoload if available
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Add PHPMailer includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log('Failed to connect to database: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ett systemfel uppstod. Vänligen försök igen senare.'
    ]);
    exit;
}

// Read settings needed for Google Sign-In, SMTP and branding
$settings = [];
try {
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (
        'site_name',
        'google_client_id',
        'smtp_enabled','smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_address','smtp_from_name'
    )");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
} catch (Throwable $e) {
    // continue with defaults below
}

$siteName        = $settings['site_name']        ?? 'Klassrumsverktyg';
$googleClientId  = $settings['google_client_id'] ?? '';
$smtpEnabled     = isset($settings['smtp_enabled']) && $settings['smtp_enabled'] === '1';
$smtpHost        = $settings['smtp_host']        ?? '';
$smtpPort        = (int)($settings['smtp_port']  ?? 587);
$smtpUser        = $settings['smtp_username']    ?? '';
$smtpPass        = $settings['smtp_password']    ?? '';
$smtpEnc         = strtolower($settings['smtp_encryption'] ?? 'tls'); // tls|ssl|none
$smtpFromAddress = $settings['smtp_from_address'] ?? '';
$smtpFromName    = $settings['smtp_from_name']    ?? $siteName;

// Läs om självregistrering är tillåten
$allowSelfRegistration = false;
try {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_self_registration' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    $allowSelfRegistration = ($val === '1');
} catch (Throwable $e) {
    $allowSelfRegistration = false; // fail-safe: av
}

function base64UrlDecode($input) {
    return base64_decode(strtr($input, '-_', '+/'));
}

try {
    $rawInput = file_get_contents('php://input');
    error_log('Received raw input: ' . $rawInput);
    
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if (empty($data['credential'])) {
        throw new Exception('Ingen credential mottagen');
    }

    // JWT parsing
    $jwt = explode('.', $data['credential']);
    if (count($jwt) !== 3) {
        throw new Exception('Ogiltig JWT-format');
    }

    // Decode payload
    $payload = json_decode(base64UrlDecode($jwt[1]), true);
    error_log('Decoded payload: ' . print_r($payload, true));
    
    if (!$payload || !isset($payload['email'])) {
        throw new Exception('Ogiltig token payload');
    }

    // Verify token issuer and audience against configured client id
    $issValid = in_array($payload['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true);
    if (!$issValid) {
        throw new Exception('Ogiltig token utgivare');
    }
    if ($googleClientId === '') {
        throw new Exception('Google OAuth klient‑ID saknas i inställningarna.');
    }
    if (($payload['aud'] ?? '') !== $googleClientId) {
        throw new Exception('Ogiltig token mottagare (audience).');
    }

    $email = $payload['email'];
    $firstName = $payload['given_name'] ?? '';
    $lastName = $payload['family_name'] ?? '';
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, username, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!$user['is_active']) {
            throw new Exception('Kontot är inte aktiverat. Kontrollera din e-post för aktiveringslänk.');
        }

        // Uppdatera last_login för den nya användaren
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['authenticated'] = true;
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        $response = [
            'success' => true,
            'message' => 'Inloggning lyckades',
            'redirect' => '/dashboard.php'
        ];
    } else {
        // Blockera nyregistrering via Google om självregistrering är avstängd
        if (!$allowSelfRegistration) {
            throw new Exception('Självregistrering är avstängd. Kontakta administratören.');
        }

        $db->beginTransaction();
        
        // Create username from email
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
        $username = $baseUsername;
        $counter = 0;

        while (true) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if (!$checkStmt->fetch()) break;
            $counter++;
            $username = $baseUsername . $counter;
        }

        // Create random password
        $tempPassword = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($tempPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);

        // Extract domain as school
        $school = explode('@', $email)[1];

        // Create activation token
        $activationToken = bin2hex(random_bytes(32));
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Add new user
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, first_name, last_name, 
                password, role, terms_accepted, is_active,
                school
            ) VALUES (
                ?, ?, ?, ?, 
                ?, 'teacher', NOW(), 0,
                ?
            )
        ");

        $stmt->execute([
            $username,
            $email,
            $firstName,
            $lastName,
            $hashedPassword,
            $school
        ]);

        $newUserId = $db->lastInsertId();

        // Save activation token
        $stmt = $db->prepare("
            INSERT INTO account_activations (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$newUserId, $activationToken, $tokenExpiry]);

        // Prepare activation email with dynamic absolute URL
        $scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http');
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/auth'), '/');
        $activationLink = $scheme . '://' . $host . $base . '/activate.php?token=' . urlencode($activationToken);
        
        // Load email template
        ob_start();
        require '../email-templates/activation.php';
        $emailBody = ob_get_clean();
        
        // Send email via SMTP (using settings)
        if (!$smtpEnabled) {
            throw new Exception('E‑post är inte konfigurerad. Kontakta administratören.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = ($smtpUser !== '' || $smtpPass !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
        }
        if ($smtpEnc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpEnc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
        }

        $fromAddr = $smtpFromAddress !== '' ? $smtpFromAddress : ('noreply@' . $host);
        $mail->setFrom($fromAddr, $smtpFromName ?: $siteName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Aktivera ditt konto – ' . ($smtpFromName ?: $siteName);
        $mail->Body = $emailBody;
        $mail->CharSet = 'UTF-8';
        
        if (!$mail->send()) {
            throw new Exception('Kunde inte skicka aktiveringsmail: ' . $mail->ErrorInfo);
        }

        $db->commit();

        // Uppdatera last_login för den nya användaren
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$newUserId]);
        
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['authenticated'] = true;
        $_SESSION['role'] = 'teacher';
        $_SESSION['username'] = $username;

        $response = [
            'success' => true,
            'message' => 'Konto skapat! Kontrollera din e-post för aktiveringslänk.',
            'redirect' => '/register-success.php'
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Error in Google Sign-In: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ett fel uppstod: ' . $e->getMessage()
    ]);
}