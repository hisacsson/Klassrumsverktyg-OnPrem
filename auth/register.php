<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
require_once '../src/Config/mail.php';

// Load reCAPTCHA v3 secret from environment (avoid hardcoding)
$recaptchaSecret = getenv('RECAPTCHA_SECRET') ?: '';
require_once 'password.php';

// Add these PHPMailer includes
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
    $_SESSION['error'] = 'Ett systemfel uppstod. Vänligen försök igen senare.';
    header('Location: ../register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF validation
        $postedToken  = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($postedToken) || !is_string($sessionToken) || $postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
            throw new Exception('Ogiltig förfrågan, försök igen.');
        }

        // Verifiera reCAPTCHA v3 (server-side)
        $recaptchaResponse = $_POST['recaptcha_response'];
        if (empty($recaptchaResponse)) {
            throw new Exception('Captcha saknas. Försök igen.');
        }
        if ($recaptchaSecret === '') {
            throw new Exception('Captcha saknas i serverns konfiguration.');
        }
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret'   => $recaptchaSecret,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]),
        ]);
        $verifyBody = curl_exec($ch);
        $curlErr    = curl_error($ch);
        curl_close($ch);
        if ($verifyBody === false) {
            throw new Exception('Kunde inte verifiera captcha: ' . ($curlErr ?: 'okänt fel'));
        }
        $captcha = json_decode($verifyBody, true);
        if (!($captcha['success'] ?? false) || (($captcha['score'] ?? 0) < 0.5)) {
            throw new Exception('Captcha verification failed');
        }

        // Validera lösenord
        $password = $_POST['password'];
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d|\W).{8,}$/', $password)) {
            throw new Exception('Password does not meet requirements');
        }
        
        // Skapa salt och hasha lösenord
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
        
        // Skapa aktiveringstoken
        $activationToken = bin2hex(random_bytes(32));
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $db->beginTransaction();
        
        // Skapa användaren
        $stmt = $db->prepare("
    INSERT INTO users (
        username,
        email, 
        password, 
        first_name, 
        last_name, 
        school, 
        role, 
        is_active, 
        terms_accepted, 
        accepts_communication
    ) VALUES (
        ?, ?, ?, ?, ?, ?, 'teacher', 0, NOW(), ?
    )
");

$stmt->execute([
    $_POST['username'],
    $_POST['email'],
    $hashedPassword,
    $_POST['firstName'],
    $_POST['lastName'],
    $_POST['school'],
    isset($_POST['communication']) ? 1 : 0
]);
        
        $userId = $db->lastInsertId();
        
        // Spara aktiveringstoken
        // I register.php, ersätt den gamla password_resets-delen med:
        $stmt = $db->prepare("
        INSERT INTO account_activations (user_id, token, expires_at) 
        VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $activationToken, $tokenExpiry]);
        
        // Bygg aktiveringslänk utifrån inställningar (ej hårdkodad domän)
        if (!function_exists('kv_get_setting')) {
            function kv_get_setting(PDO $pdo, string $key, $default = null) {
                try {
                    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                    $stmt->execute([$key]);
                    $val = $stmt->fetchColumn();
                    if ($val === false || $val === null) {
                        $env = getenv(strtoupper($key));
                        return $env !== false ? $env : $default;
                    }
                    return $val;
                } catch (Throwable $e) {
                    $env = getenv(strtoupper($key));
                    return $env !== false ? $env : $default;
                }
            }
        }
        $defaultBase = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $baseUrl = kv_get_setting($db, 'site_url', getenv('SITE_URL') ?: $defaultBase);
        $baseUrl = rtrim($baseUrl, '/');
        $activationLink = $baseUrl . '/activate.php?token=' . $activationToken;
        
        // Ladda mailmallen
        ob_start();
        $firstName = $_POST['firstName'];
        require '../email-templates/activation.php';
        $emailBody = ob_get_clean();
        
        // Skicka mail via SMTP (settings-driven)
        $smtpHost = kv_get_setting($db, 'smtp_host', getenv('SMTP_HOST') ?: '');
        $smtpUser = kv_get_setting($db, 'smtp_username', getenv('SMTP_USERNAME') ?: '');
        $smtpPass = kv_get_setting($db, 'smtp_password', getenv('SMTP_PASSWORD') ?: '');
        $smtpPort = (int) (kv_get_setting($db, 'smtp_port', getenv('SMTP_PORT') ?: 587));
        $smtpEnc  = strtolower((string) kv_get_setting($db, 'smtp_encryption', getenv('SMTP_ENCRYPTION') ?: 'tls'));
        $fromAddr = kv_get_setting($db, 'smtp_from_address', getenv('SMTP_FROM_ADDRESS') ?: 'svarainte@klassrumsverktyg.se');
        $fromName = kv_get_setting($db, 'smtp_from_name', getenv('SMTP_FROM_NAME') ?: 'Klassrumsverktyg');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            throw new Exception('SMTP är inte korrekt konfigurerat (host/username/password saknas).');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = $smtpPort ?: 587;

        // Encryption mapping
        if ($smtpEnc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpEnc === 'none' || $smtpEnc === 'off' || $smtpEnc === 'false' || $smtpEnc === '') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else { // default tls
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // From header
        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($_POST['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Aktivera ditt konto';
        $mail->Body = $emailBody;
        $mail->CharSet = 'UTF-8';
        
        if (!$mail->send()) {
            throw new Exception('Kunde inte skicka aktiveringsmail: ' . $mail->ErrorInfo);
        }
        
        $db->commit();
        
        // Omdirigera till bekräftelsesida
        header('Location: ../register-success.php');
        exit;
        
    } catch (Exception $e) {
        // Rulla tillbaka transaktionen om något gick fel
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Logga felet
        error_log('Registration error: ' . $e->getMessage());
        
        // Spara felmeddelandet för visning till användaren
        $_SESSION['error'] = 'Ett fel uppstod vid registreringen. Vänligen försök igen eller kontakta support.';
        
        // Spara formulärdata för att återfylla formuläret
        $_SESSION['form_data'] = [
            'firstName' => $_POST['firstName'],
            'lastName' => $_POST['lastName'],
            'email' => $_POST['email'],
            'school' => $_POST['school'],
            'communication' => isset($_POST['communication'])
        ];
        
        // Omdirigera tillbaka till registreringsformuläret
        header('Location: ../register.php');
        exit;
    }
}

// Om det är en GET-request, visa registreringsformuläret
// Hämta eventuella tidigare formulärdata och fel från sessionen
$formData = $_SESSION['form_data'] ?? [];
$error = $_SESSION['error'] ?? null;

// Rensa sessionsdata
unset($_SESSION['form_data']);
unset($_SESSION['error']);

// Inkludera vyn med formuläret
require_once '../register.php';