<?php
// /auth/mfa-verify.php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Security/MFA.php';


use App\Security\MFA;

// Minimal CSRF helper if none is loaded
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }
}

$database = new \Database(); $pdo = $database->getConnection();

$userId = $_SESSION['pending_mfa_user_id'] ?? null;
$redirectTo = $_SESSION['pending_mfa_redirect'] ?? '/admin/dashboard.php';
if (!$userId) { header('Location: /login.php'); exit; }

// Rate limit enkelt (per session)
$_SESSION['mfa_try'] = ($_SESSION['mfa_try'] ?? 0);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token first
    $postedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($postedToken) || !is_string($sessionToken) || $postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $error = 'Ogiltig förfrågan, försök igen.';
    } elseif (($_SESSION['mfa_try'] ?? 0) > 10) {
        $error = 'För många försök. Vänta en stund.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $remember = isset($_POST['remember']);
        if (MFA::verifyTotpOrRecovery($pdo, (int)$userId, $code)) {
            $_SESSION['mfa_ok_for'] = (int)$userId;
            unset($_SESSION['pending_mfa_user_id'], $_SESSION['mfa_try']);
            unset($_SESSION['csrf_token']); // rotate token after successful POST

            if ($remember) {
                $r = MFA::rememberSet($pdo, (int)$userId, 30);
                // Sätt cookie (HttpOnly, Secure, SameSite=Lax)
                setcookie('mfa_remember', $r['token'], [
                    'expires'  => time()+60*60*24*30,
                    'path'     => '/',
                    'secure'   => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            header("Location: {$redirectTo}"); exit;
        }
        $_SESSION['mfa_try']++;
        $error = 'Ogiltig kod. Försök igen.';
    }
}
?>
<!DOCTYPE html><html lang="sv"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>MFA-verifiering</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-100 min-h-screen">
<div class="max-w-md mx-auto mt-16 bg-white shadow border border-gray-200 rounded-lg p-6">
  <h1 class="text-2xl font-semibold mb-4">Bekräfta med säkerhetskod</h1>
  <?php if ($error): ?><div class="mb-3 text-red-700 bg-red-50 border border-red-200 rounded p-3"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <form method="post" class="space-y-4">
    <?php
    if (function_exists('csrf_field')) {
        echo csrf_field();
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }
    ?>
    <label class="block">
      <span class="text-sm text-gray-700">6-siffrig kod eller reservkod</span>
      <input name="code" autocomplete="one-time-code" inputmode="numeric" class="mt-1 w-full border rounded-lg px-3 py-2" required>
    </label>
    <label class="inline-flex items-center">
      <input type="checkbox" name="remember" class="mr-2">
      <span>Kom ihåg den här enheten i 30 dagar</span>
    </label>
    <div class="pt-2">
      <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Verifiera</button>
    </div>
  </form>
</div>
</body></html>