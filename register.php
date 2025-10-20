<?php
session_start();

require_once __DIR__ . '/src/Config/Database.php';

// Hämta inställningen allow_self_registration (default 0)
try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute(['allow_self_registration']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $allowSelfRegistration = isset($row['setting_value']) ? $row['setting_value'] === '1' : false; // default false
} catch (Throwable $e) {
    // Vid DB‑fel: fail‑safe till false (stäng registrering) i on‑prem‑läge
    $allowSelfRegistration = false;
}

// Hämta reCAPTCHA-inställningar
$recaptchaEnabled = false;
$recaptchaSiteKey = '';
try {
    if (!isset($pdo)) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('recaptcha_enabled','recaptcha_site_key')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'recaptcha_enabled') {
            $recaptchaEnabled = ($row['setting_value'] === '1');
        } elseif ($row['setting_key'] === 'recaptcha_site_key') {
            $recaptchaSiteKey = (string)$row['setting_value'];
        }
    }
} catch (Throwable $e) {
    // Vid DB-fel: håll reCAPTCHA avstängd
    $recaptchaEnabled = false;
    $recaptchaSiteKey = '';
}

// Hämta Google OAuth‑inställningar
$googleEnabled = false;
$googleClientId = '';
try {
    if (!isset($pdo)) {
        $database = new Database();
        $pdo = $database->getConnection();
    }
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('google_enabled','google_client_id')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'google_enabled') {
            $googleEnabled = ($row['setting_value'] === '1');
        } elseif ($row['setting_key'] === 'google_client_id') {
            $googleClientId = (string)$row['setting_value'];
        }
    }
} catch (Throwable $e) {
    $googleEnabled = false;
    $googleClientId = '';
}

// Blockera registrering om ej tillåten
if (!$allowSelfRegistration) {
    // Om användaren redan är autentiserad, skicka till dashboard
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        header('Location: /dashboard');
        exit;
    }

    // Skicka 403 + redirect till login
    http_response_code(403);
    header('Location: /login?r=registration_disabled');
    exit;
}

// Om användaren redan har autentiserats via Google, skicka till dashboard
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: /dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrera konto</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <?php if ($googleEnabled && $googleClientId !== ''): ?>
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
</head>
<body class="bg-gray-50">
  <!-- Huvudcontainer -->
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <!-- Inre container -->
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
      <!-- Rubrik -->
      <div>
        <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">
          Skapa ditt konto
        </h2>
      </div>
      
      <!-- Felmeddelande -->
      <?php if (isset($error)): ?>
      <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Formulär -->
      <form class="mt-8 space-y-6" action="auth/register.php" method="POST" id="registerForm">
        <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
        
        <?php if ($googleEnabled && $googleClientId !== ''): ?>
        <div class="border-b border-gray-200 pb-6">
          <div id="googleSignInContainer" class="flex justify-center"></div>
          <div id="googleSignInStatus"></div>
        </div>
        
        <!-- Divider -->
        <div class="mt-6 relative w-full">
          <div class="absolute inset-0 flex items-center"></div>
          <div class="relative flex justify-center text-sm">
            <span class="px-2 bg-white text-gray-500">eller registrera med e‑post</span>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Resterande formulärfält -->
        <div class="space-y-4">
          <!-- Användarnamn -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">
              Användarnamn <span class="text-red-500">*</span>
            </label>
            <div class="mt-1 relative">
              <input type="text" 
                     name="username" 
                     id="username" 
                     required 
                     pattern="^[a-zA-Z0-9_]+$"
                     minlength="3"
                     value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                     class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                     onblur="checkUsername(this.value)">
              <div id="usernameStatus" class="mt-1 text-sm"></div>
            </div>
          </div>
          
          <!-- E-post -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
              E-postadress <span class="text-red-500">*</span>
            </label>
            <div class="mt-1">
              <input type="email" 
                     name="email" 
                     id="email" 
                     required 
                     onblur="checkEmail(this.value)"
                     value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                     class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              <div id="emailStatus" class="mt-1 text-sm"></div>
            </div>
          </div>
          
          <!-- Förnamn och efternamn -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="firstName" class="block text-sm font-medium text-gray-700">
                Förnamn <span class="text-red-500">*</span>
              </label>
              <div class="mt-1">
                <input type="text" 
                       name="firstName" 
                       id="firstName" 
                       required 
                       value="<?= htmlspecialchars($formData['firstName'] ?? '') ?>"
                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              </div>
            </div>
            <div>
              <label for="lastName" class="block text-sm font-medium text-gray-700">
                Efternamn <span class="text-red-500">*</span>
              </label>
              <div class="mt-1">
                <input type="text" 
                       name="lastName" 
                       id="lastName" 
                       required 
                       value="<?= htmlspecialchars($formData['lastName'] ?? '') ?>"
                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              </div>
            </div>
          </div>
          
          <!-- Skola -->
          <div>
            <label for="school" class="block text-sm font-medium text-gray-700">
              Skola <span class="text-red-500">*</span>
            </label>
            <div class="mt-1">
              <input type="text" 
                     name="school" 
                     id="school" 
                     required 
                     value="<?= htmlspecialchars($formData['school'] ?? '') ?>"
                     class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
          </div>
          
          <!-- Lösenord -->
          <div class="space-y-4">
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700">
                Lösenord <span class="text-red-500">*</span>
              </label>
              <div class="mt-1">
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       onkeyup="checkPassword(this.value)"
                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <!-- Styrkeindikator -->
                <div id="passwordStrength" class="h-2 w-full bg-gray-200 rounded-full overflow-hidden mt-2">
                  <div class="h-full bg-gray-400 transition-all duration-300" style="width: 0%"></div>
                </div>
                <!-- Feedback lista -->
                <div id="passwordFeedback" class="mt-2"></div>
              </div>
            </div>
            <div>
              <label for="passwordConfirm" class="block text-sm font-medium text-gray-700">
                Bekräfta lösenord <span class="text-red-500">*</span>
              </label>
              <div class="mt-1">
                <input type="password" 
                       name="passwordConfirm" 
                       id="passwordConfirm" 
                       required 
                       onkeyup="checkPasswordMatch()"
                       class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <div id="passwordMatchStatus" class="mt-1 text-sm"></div>
              </div>
            </div>
          </div>
          
          <!-- Användarvillkor -->
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input type="checkbox" 
                     name="terms" 
                     id="terms" 
                     required 
                     <?= isset($formData['terms']) && $formData['terms'] ? 'checked' : '' ?>
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            </div>
            <div class="ml-3 text-sm">
              <label for="terms" class="font-medium text-gray-700">
                Jag accepterar <a href="/terms" class="text-blue-600 hover:text-blue-500">användarvillkoren</a>
              </label>
            </div>
          </div>
          
          <!-- Kommunikation -->
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input type="checkbox" 
                     name="communication" 
                     id="communication" 
                     <?= isset($formData['communication']) && $formData['communication'] ? 'checked' : '' ?>
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            </div>
            <div class="ml-3 text-sm">
              <label for="communication" class="font-medium text-gray-700">
                Jag vill ta emot information och uppdateringar via e-post
              </label>
            </div>
          </div>
        </div>
        
        <!-- Skapa konto-knapp -->
        <div>
          <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Skapa konto
          </button>
        </div>
      </form>
    </div>
  </div>

    <script>
    // (reCAPTCHA och lösenordsvalidering hanteras i ett gemensamt submit-event längre ned)

    async function checkUsername(username) {
        const statusElement = document.getElementById('usernameStatus');
        
        if (username.length < 3) {
            statusElement.textContent = 'Användarnamnet måste vara minst 3 tecken';
            statusElement.className = 'mt-1 text-sm text-red-600';
            return;
        }

        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            statusElement.textContent = 'Användarnamnet får endast innehålla bokstäver, siffror och understreck';
            statusElement.className = 'mt-1 text-sm text-red-600';
            return;
        }
        
        statusElement.textContent = 'Kontrollerar...';
        statusElement.className = 'mt-1 text-sm text-gray-600';
        
        try {
            const response = await fetch('auth/check-username.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ username: username })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            if (!text) {
                throw new Error('Tomt svar från servern');
            }
            
            const data = JSON.parse(text);
            
            if (data.error) {
                throw new Error(data.message);
            }
            
            if (data.available) {
                statusElement.textContent = '✓ ' + data.message;
                statusElement.className = 'mt-1 text-sm text-green-600';
            } else {
                statusElement.innerHTML = `${data.message}<br>Förslag: ${data.suggestions.join(', ')}`;
                statusElement.className = 'mt-1 text-sm text-red-600';
            }
        } catch (error) {
            console.error('Error:', error);
            statusElement.textContent = 'Ett fel uppstod vid kontroll av användarnamn: ' + error.message;
            statusElement.className = 'mt-1 text-sm text-red-600';
        }
    }

    async function checkEmail(email) {
    const statusElement = document.getElementById('emailStatus');
    
    if (!email) {
        statusElement.textContent = 'E-postadressen får inte vara tom';
        statusElement.className = 'mt-1 text-sm text-red-600';
        return;
    }

    // Enkel e-postvalidering på klientsidan
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        statusElement.textContent = 'Ogiltig e-postadress';
        statusElement.className = 'mt-1 text-sm text-red-600';
        return;
    }
    
    statusElement.textContent = 'Kontrollerar...';
    statusElement.className = 'mt-1 text-sm text-gray-600';
    
    try {
        const response = await fetch('/auth/check-email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.message);
        }
        
        if (data.available) {
            statusElement.textContent = '✓ ' + data.message;
            statusElement.className = 'mt-1 text-sm text-green-600';
        } else {
            statusElement.textContent = data.message;
            statusElement.className = 'mt-1 text-sm text-red-600';
        }
    } catch (error) {
        console.error('Error:', error);
        statusElement.textContent = 'Ett fel uppstod vid kontroll av e-postadress: ' + error.message;
        statusElement.className = 'mt-1 text-sm text-red-600';
    }
}

function checkPassword(password) {
    const statusElement = document.getElementById('passwordStatus');
    const requirements = {
        length: password.length >= 8,
        special: /[\d\W]/.test(password)  // minst en siffra eller specialtecken
    };
    
    const strengthIndicator = document.getElementById('passwordStrength');
    const feedbackList = document.getElementById('passwordFeedback');
    
    // Uppdatera statuselement och styrkeindikator
    if (!requirements.length || !requirements.special) {
        strengthIndicator.innerHTML = '<div class="h-full bg-red-500 transition-all duration-300" style="width: 33%"></div>';
    } else {
        strengthIndicator.innerHTML = '<div class="h-full bg-green-500 transition-all duration-300" style="width: 100%"></div>';
    }

    // Generera feedback
    let feedback = '<ul class="mt-1 text-sm space-y-1">';
    feedback += `<li class="flex items-center ${requirements.length ? 'text-green-600' : 'text-red-600'}">
        ${requirements.length ? '✓' : '✗'} Minst 8 tecken</li>`;
    feedback += `<li class="flex items-center ${requirements.special ? 'text-green-600' : 'text-red-600'}">
        ${requirements.special ? '✓' : '✗'} Minst en siffra eller ett specialtecken</li>`;
    feedback += '</ul>';
    
    feedbackList.innerHTML = feedback;

    // Kontrollera om lösenorden matchar när första lösenordet ändras
    checkPasswordMatch();
    
    return requirements.length && requirements.special;
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('passwordConfirm').value;
    const matchStatus = document.getElementById('passwordMatchStatus');
    
    if (!confirmPassword) {
        matchStatus.textContent = '';
        return false;
    }
    
    if (password === confirmPassword) {
        matchStatus.textContent = '✓ Lösenorden matchar';
        matchStatus.className = 'mt-1 text-sm text-green-600';
        return true;
    } else {
        matchStatus.textContent = '✗ Lösenorden matchar inte';
        matchStatus.className = 'mt-1 text-sm text-red-600';
        return false;
    }
}

// Validera hela formuläret innan det skickas
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;

    if (!checkPassword(password)) {
        e.preventDefault();
        alert('Lösenordet uppfyller inte kraven');
        return;
    }

    if (!checkPasswordMatch()) {
        e.preventDefault();
        alert('Lösenorden matchar inte');
        return;
    }

    // Kör reCAPTCHA om aktiverad, annars låt formuläret skickas normalt
    if (window.RECAPTCHA_ENABLED && typeof grecaptcha !== 'undefined' && window.RECAPTCHA_SITE_KEY) {
        e.preventDefault();
        grecaptcha.execute(window.RECAPTCHA_SITE_KEY, { action: 'register' })
            .then(function(token) {
                document.getElementById('recaptchaResponse').value = token;
                e.target.submit();
            })
            .catch(function() {
                // Om reCAPTCHA misslyckas, skicka ändå — serversidan får avgöra
                e.target.submit();
            });
    }
});

// Google Identity Services
window.onload = function() {
    if (window.GOOGLE_ENABLED && window.GOOGLE_CLIENT_ID && window.google && google.accounts && google.accounts.id) {
        google.accounts.id.initialize({
            client_id: window.GOOGLE_CLIENT_ID,
            callback: handleGoogleSignIn
        });

        const container = document.getElementById('googleSignInContainer');
        if (container) {
            google.accounts.id.renderButton(container, {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'continue_with',
                shape: 'rectangular',
                logo_alignment: 'left',
                locale: 'sv_SE'
            });
        }
    }
};

function handleGoogleSignIn(response) {
    console.log('Google Sign-In response received'); // Debug logging
    
    if (!response.credential) {
        console.error('No credential received');
        return;
    }

    const statusContainer = document.getElementById('googleSignInContainer');
    const statusElement = document.createElement('div');
    statusElement.className = 'mt-4 text-sm text-gray-600';
    statusElement.textContent = 'Verifierar Google-inloggning...';
    statusContainer.appendChild(statusElement);

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
.then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    if (!response.ok) {
        return response.text().then(text => {
            console.error('Error response:', text);
            throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
        });
    }
    return response.text();
})
.then(text => {
    console.log('Success response:', text);
    try {
        const data = JSON.parse(text);
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            throw new Error(data.message || 'Ett ospecificerat fel uppstod');
        }
    } catch (e) {
        console.error('JSON parse error:', e);
        throw new Error('Kunde inte tolka serverns svar');
    }
})
.catch(error => {
    console.error('Detailed error:', error);
    const statusElement = document.getElementById('googleSignInStatus');
    statusElement.textContent = 'Ett fel uppstod vid Google-inloggningen: ' + error.message;
    statusElement.className = 'mt-4 text-sm text-red-600';
});
}
    </script>
</body>
</html>