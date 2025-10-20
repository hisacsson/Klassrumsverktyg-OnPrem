<?php
// reset-password.php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Verifiera reCAPTCHA dynamiskt från system_settings
        $recaptchaResponse = $_POST['recaptcha_response'] ?? '';
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('recaptcha_enabled','recaptcha_secret_key')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $recaptchaEnabled = isset($settings['recaptcha_enabled']) && $settings['recaptcha_enabled'] === '1';
        $recaptchaSecret  = $settings['recaptcha_secret_key'] ?? '';

        if ($recaptchaEnabled && $recaptchaSecret !== '') {
            $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".urlencode($recaptchaSecret)."&response=".urlencode($recaptchaResponse));
            $captchaSuccess = json_decode($verify, true);
            if (!$captchaSuccess || empty($captchaSuccess['success'])) {
                throw new Exception('Captcha verification failed');
            }
        }

        $stmt = $db->prepare("SELECT id, is_active FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();

        if ($user) {
            if (!$user['is_active']) {
                throw new Exception('Kontot är inte aktiverat än. Kontrollera din e-post för aktiveringslänk.');
            }

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            // Bygg absolut länk dynamiskt utifrån aktuell begäran (stöder proxy via X-Forwarded-Proto)
            $scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http');
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'); // t.ex. /auth
            $resetLink = $scheme . '://' . $host . $basePath . '/reset-password-confirm.php?token=' . urlencode($token);
            
            // Läs e-post/SMTP-inställningar från system_settings
            $settingsStmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_enabled','smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_address','smtp_from_name','site_name')");
            $settingsStmt->execute();
            $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $smtpEnabled = isset($settings['smtp_enabled']) && $settings['smtp_enabled'] === '1';
            if (!$smtpEnabled) {
                throw new Exception('E-post är inte konfigurerad. Kontakta administratören.');
            }

            $smtpHost = $settings['smtp_host'] ?? '';
            $smtpPort = (int)($settings['smtp_port'] ?? 587);
            $smtpUser = $settings['smtp_username'] ?? '';
            $smtpPass = $settings['smtp_password'] ?? '';
            $smtpEnc  = strtolower($settings['smtp_encryption'] ?? 'tls'); // tls|ssl|none
            $fromAddr = $settings['smtp_from_address'] ?? ('noreply@' . (parse_url($host, PHP_URL_HOST) ?: $host));
            $fromName = $settings['smtp_from_name'] ?? ($settings['site_name'] ?? 'Klassrumsverktyg');

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

            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($_POST['email']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Återställ ditt lösenord' . ($fromName ? ' – ' . $fromName : '');
            $mail->Body = "Klicka på länken för att återställa ditt lösenord: <a href='{$resetLink}'>{$resetLink}</a>";

            $mail->send();

            $_SESSION['success'] = 'Ett mail med instruktioner har skickats till din e-postadress.';
        } else {
            // Simulera processeringstid för att förhindra enumeration
            sleep(1);
        }

        header('Location: reset-password-sent.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: reset-password.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Återställ lösenord - <?= htmlspecialchars($siteName ?? 'Klassrumsverktyg') ?></title>
    <?php
    $siteName = 'Klassrumsverktyg';
    $recaptchaSiteKey = '';
    try {
        if (!isset($db)) {
            $database = new Database();
            $db = $database->getConnection();
        }
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name','recaptcha_site_key')");
        $stmt->execute();
        $pairs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($pairs['site_name'])) { $siteName = (string)$pairs['site_name']; }
        $recaptchaSiteKey = (string)($pairs['recaptcha_site_key'] ?? '');
    } catch (Throwable $e) {}
    ?>
    <?php if (!empty($recaptchaSiteKey)): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSiteKey) ?>"></script>
    <script>
      window.RECAPTCHA_SITE_KEY = "<?= htmlspecialchars($recaptchaSiteKey) ?>";
    </script>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-center mb-8">Återställ lösenord</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form id="resetForm" method="POST" class="space-y-6">
                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">E-post</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="email" name="email" type="email" required>
                </div>

                <button class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
                        type="submit">Skicka återställningslänk</button>
            </form>
        </div>
    </div>

    <script>
        if (window.RECAPTCHA_SITE_KEY) {
            grecaptcha.ready(function() {
                grecaptcha.execute(window.RECAPTCHA_SITE_KEY, {action: 'reset_password'})
                    .then(function(token) {
                        document.getElementById('recaptchaResponse').value = token;
                    });
            });
        }
    </script>
</body>
</html>