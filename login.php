<?php
session_start();
require_once __DIR__ . '/src/Config/Database.php';
require_once __DIR__ . '/src/Security/MFA.php';
use App\Security\MFA;
// Hämta instansens namn från system_settings (fallback: "Klassrumsverktyg")
try {
    if (!function_exists('kv_get_setting')) {
        function kv_get_setting(PDO $pdo, string $key, $default = null) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['setting_value'] ?? $default;
        }
    }
    $dbForName = new Database();
    $pdoForName = $dbForName->getConnection();
    $siteName = (string) kv_get_setting($pdoForName, 'site_name', 'Klassrumsverktyg');

    // Läs reCAPTCHA-inställningar för klienten
    $recaptchaEnabled = false;
    $recaptchaSiteKey = '';
    try {
        $stmt = $pdoForName->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('recaptcha_enabled','recaptcha_site_key')");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'recaptcha_enabled') {
                $recaptchaEnabled = ($row['setting_value'] === '1');
            } elseif ($row['setting_key'] === 'recaptcha_site_key') {
                $recaptchaSiteKey = (string)$row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        // lämna disabled
    }

    // Läs Google OAuth-inställningar för klienten
    $googleEnabled = false;
    $googleClientId = '';
    try {
        $stmt = $pdoForName->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_enabled','google_client_id')");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'google_enabled') {
                $googleEnabled = ($row['setting_value'] === '1');
            } elseif ($row['setting_key'] === 'google_client_id') {
                $googleClientId = (string)$row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        // lämna disabled
    }

    // Läs inställning för självregistrering
    $allowSelfRegistration = false;
    try {
        $stmt = $pdoForName->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_self_registration' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $allowSelfRegistration = ($val === '1');
    } catch (Throwable $e) {
        // fallback false
    }
} catch (Throwable $e) {
    $siteName = 'Klassrumsverktyg';
}
require_once 'auth/verify-login.php';

// Rate limiting
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

if ($_SESSION['login_attempts'] > 5) {
    $timeLeft = 900 - (time() - $_SESSION['last_attempt']);
    if ($timeLeft > 0) {
        $_SESSION['error'] = "För många försök. Vänta " . ceil($timeLeft/60) . " minuter.";
        header('Location: login.php');
        exit;
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // DB-anslutning (behövs för att läsa system_settings)
        $database = new Database();
        $db = $database->getConnection();

        // reCAPTCHA verifiering (dynamisk)
        $recaptchaResponse = $_POST['recaptcha_response'] ?? '';
        $settingsStmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('recaptcha_enabled','recaptcha_secret_key')");
        $settingsStmt->execute();
        $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $serverRecaptchaEnabled = isset($settings['recaptcha_enabled']) && $settings['recaptcha_enabled'] === '1';
        $recaptchaSecret = $settings['recaptcha_secret_key'] ?? '';

        if ($serverRecaptchaEnabled && $recaptchaSecret !== '') {
            $verify = @file_get_contents(
                'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptchaSecret) . '&response=' . urlencode($recaptchaResponse)
            );
            $captcha = $verify ? json_decode($verify, true) : null;
            if (!$captcha || empty($captcha['success'])) {
                throw new Exception('Captcha verification failed');
            }
            // Om du vill använda score (v3), avkommentera nedan och justera tröskel
            // if (isset($captcha['score']) && $captcha['score'] < 0.5) {
            //     throw new Exception('Captcha score too low');
            // }
        }
        
        $result = verifyLogin($_POST['email'], $_POST['password']);
        
        if ($result['success']) {
            // Sätt grundläggande session
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['role'] = $result['user']['role']; // Lagrar användarens roll
            $_SESSION['username'] = $result['user']['username']; // Lagrar användarnamn

            // Bestäm vart vi ska efter full autentisering
            $redirectAfter = !empty($result['must_change_password'])
                ? '/change-password.php?first=1'
                : '/dashboard.php';

            // --- MFA-kontroll ---
            $mfaEnabled = false;
            try {
                $stmt = $db->prepare('SELECT mfa_enabled FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$result['user']['id']]);
                $mfaEnabled = ((string)$stmt->fetchColumn() === '1');
            } catch (Throwable $e) {
                // Om något går fel här, anta att MFA inte är påtvingad
                $mfaEnabled = false;
            }

            $skipMfa = false;
            if ($mfaEnabled && isset($_COOKIE['mfa_remember']) && $_COOKIE['mfa_remember'] !== '') {
                // Om MFA::rememberCheck finns, använd den för att validera cookien
                if (method_exists('App\\Security\\MFA', 'rememberCheck')) {
                    try {
                        $skipMfa = (bool) MFA::rememberCheck($db, (int)$result['user']['id'], $_COOKIE['mfa_remember']);
                    } catch (Throwable $e) {
                        $skipMfa = false;
                    }
                }
            }

            if ($mfaEnabled && !$skipMfa) {
                // Kräv MFA: ställ in pending-variabler och skicka till verifieringssidan
                $_SESSION['pending_mfa_user_id'] = (int)$result['user']['id'];
                $_SESSION['pending_mfa_redirect'] = $redirectAfter;

                // Rensa eventuell tidigare flagga så vi inte råkar passera MFA
                unset($_SESSION['mfa_ok_for']);

                header('Location: /auth/mfa-verify.php');
                exit;
            }

            // --- Ingen MFA krävs eller enheten är ihågkommen ---
            try {
                $stmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $stmt->execute([$result['user']['id']]);
            } catch (Throwable $e) {
                // Ignorera tyst – inloggning ska ändå fortsätta
            }

            header('Location: ' . $redirectAfter);
            if (headers_sent($file, $line)) {
                die("Headers already sent in $file on line $line");
            }
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            throw new Exception($result['message']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logga in - <?= htmlspecialchars($siteName ?? 'Klassrumsverktyg') ?></title>
    <?php if ($recaptchaEnabled && $recaptchaSiteKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSiteKey) ?>"></script>
    <script>
      window.RECAPTCHA_ENABLED = true;
      window.RECAPTCHA_SITE_KEY = "<?= htmlspecialchars($recaptchaSiteKey) ?>";
    </script>
    <?php else: ?>
    <script>
      window.RECAPTCHA_ENABLED = false;
      window.RECAPTCHA_SITE_KEY = "";
    </script>
    <?php endif; ?>
    <?php if (!empty($googleEnabled) && !empty($googleClientId)): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
      window.GOOGLE_ENABLED = true;
      window.GOOGLE_CLIENT_ID = "<?= htmlspecialchars($googleClientId) ?>";
    </script>
    <?php else: ?>
    <script>
      window.GOOGLE_ENABLED = false;
      window.GOOGLE_CLIENT_ID = "";
    </script>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-center mb-8">Logga in</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (!empty($googleEnabled) && !empty($googleClientId)): ?>
            <!-- Google Sign-In knapp -->
            <div class="mb-6">
                <div id="googleSignInContainer" class="flex justify-center"></div>
                <div id="googleSignInStatus" class="mt-2 text-sm text-center"></div>
            </div>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">eller logga in med e-post</span>
                </div>
            </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" class="space-y-6">
                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">E-post eller användarnamn</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
       id="email" 
       name="email" 
       type="text" 
       placeholder="namn@exempel.se eller användarnamn"
       required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Lösenord</label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" 
                           id="password" name="password" type="password" required>
                </div>

                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
                            type="submit">Logga in</button>
                    <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" 
                       href="reset-password.php">Glömt lösenord?</a>
                </div>
                <?php if (!empty($allowSelfRegistration)): ?>
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">Har du inget konto?</p>
                    <a href="register.php" class="inline-block mt-2 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Skapa konto
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
// Funktion för att uppdatera reCAPTCHA token (om aktiverad)
function updateRecaptcha() {
    if (window.RECAPTCHA_ENABLED && typeof grecaptcha !== 'undefined' && window.RECAPTCHA_SITE_KEY) {
        grecaptcha.execute(window.RECAPTCHA_SITE_KEY, {action: 'login'})
            .then(function(token) {
                document.getElementById('recaptchaResponse').value = token;
            });
    }
}

// Initiera reCAPTCHA när sidan laddas
if (window.RECAPTCHA_ENABLED) {
    grecaptcha.ready(function() {
        updateRecaptcha();
        // Uppdatera token var 90:e sekund
        setInterval(updateRecaptcha, 90000);
    });
}

// Uppdatera token när formuläret skickas
const loginForm = document.getElementById('loginForm');
loginForm.addEventListener('submit', function(e) {
    if (window.RECAPTCHA_ENABLED && typeof grecaptcha !== 'undefined' && window.RECAPTCHA_SITE_KEY) {
        e.preventDefault();
        grecaptcha.execute(window.RECAPTCHA_SITE_KEY, {action: 'login'})
            .then(function(token) {
                document.getElementById('recaptchaResponse').value = token;
                loginForm.submit();
            })
            .catch(function(){
                // Om reCAPTCHA fallerar, låt servern avgöra
                loginForm.submit();
            });
    }
});
</script>
<script>
        // Google Identity Services
        window.onload = function() {
            if (window.GOOGLE_ENABLED && window.GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id) {
                google.accounts.id.initialize({
                    client_id: window.GOOGLE_CLIENT_ID,
                    callback: handleGoogleSignIn
                });

                const btnContainer = document.getElementById('googleSignInContainer');
                if (btnContainer) {
                    google.accounts.id.renderButton(btnContainer, {
                        type: 'standard',
                        theme: 'outline',
                        size: 'large',
                        text: 'continue_with',
                        shape: 'rectangular',
                        logo_alignment: 'left',
                        locale: 'sv_SE',
                        width: '100%'
                    });
                }
            }
        };

        function handleGoogleSignIn(response) {
    const statusContainer = document.getElementById('googleSignInStatus');
    statusContainer.innerHTML = `
        <div class="flex items-center justify-center">
            <svg class="animate-spin h-5 w-5 mr-2 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Verifierar...</span>
        </div>
    `;
    statusContainer.className = 'mt-4 text-sm text-gray-600';

    fetch('auth/google-signin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            credential: response.credential
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusContainer.innerHTML = `
                <div class="bg-green-50 border-l-4 border-green-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">Inloggning lyckades! Omdirigerar...</p>
                        </div>
                    </div>
                </div>
            `;
            setTimeout(() => window.location.href = data.redirect, 1000);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        let errorMessage = error.message;
        // Rensa upp felmeddelandet om det är ett JSON-svar
        try {
            const errorData = JSON.parse(errorMessage);
            if (errorData.message) {
                errorMessage = errorData.message;
            }
        } catch(e) {
            // Om det inte är JSON, använd originalmeddelandet
        }

        statusContainer.innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">${errorMessage}</p>
                    </div>
                </div>
            </div>
        `;
    });
}
    </script>
</body>
</html>