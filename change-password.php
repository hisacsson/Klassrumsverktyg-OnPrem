<?php
// change-password.php (ligger i samma mapp som login.php)
session_start();
require_once __DIR__ . '/src/Config/Database.php';

// Säkerställ att användaren är inloggad
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$forceChange = !empty($_SESSION['force_pw_change']) || (isset($_GET['first']) && $_GET['first'] === '1');
$errors = [];
$success = false;

// Denna sida används enbart för tvingat byte
if (!$forceChange) {
    header('Location: /dashboard.php');
    exit;
}

// Hämta instansnamn för titel/branding
$siteName = 'Klassrumsverktyg';
try {
    $dbTmp = new Database();
    $pdoTmp = $dbTmp->getConnection();
    $stmt = $pdoTmp->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name' LIMIT 1");
    $stmt->execute();
    $name = (string)($stmt->fetchColumn() ?: '');
    if ($name !== '') $siteName = $name;
} catch (Throwable $e) { /* fallback */ }

// Hjälpfunktion för lösenordskrav
function password_is_strong(string $pw): bool {
    // Minimi: 8 tecken, minst 1 bokstav och 1 siffra. (Justera efter policy)
    if (strlen($pw) < 8) return false;
    if (!preg_match('/[A-Za-zÅÄÖåäö]/u', $pw)) return false;
    if (!preg_match('/[0-9]/', $pw)) return false;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['confirm_password'] ?? '';

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        // Validera nytt lösenord
        if ($new1 !== $new2) {
            $errors[] = 'De nya lösenorden matchar inte.';
        } elseif (!password_is_strong($new1)) {
            $errors[] = 'Lösenordet måste vara minst 8 tecken och innehålla minst en bokstav och en siffra.';
        }

        if (!$errors) {
            $hash = password_hash($new1, PASSWORD_DEFAULT);

            // Uppdatera lösenord och nolla must_change_password om kolumnen finns
            // Försök först med must_change_password, om kolumn saknas, kör enklare update
            $updated = false;
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                $updated = true;
            } catch (Throwable $e) {
                // Troligen saknas kolumnen must_change_password – försök utan
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                $updated = true;
            }

            if ($updated) {
                // Ta bort force-flagga och skicka vidare
                unset($_SESSION['force_pw_change']);
                $success = true;
                header('Location: /dashboard.php');
                exit;
            } else {
                $errors[] = 'Kunde inte uppdatera lösenordet.';
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Ett fel inträffade. Försök igen.';
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Byt lösenord – <?= htmlspecialchars($siteName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-lg shadow p-6">
      <h1 class="text-2xl font-bold text-gray-800 mb-1">Välj ett nytt lösenord</h1>
      <p class="text-sm text-gray-600 mb-6">Av säkerhetsskäl behöver du sätta ett nytt lösenord innan du går vidare.</p>

      <?php if ($errors): ?>
        <div class="mb-4 rounded border border-red-200 bg-red-50 text-red-800 p-3 text-sm">
          <ul class="list-disc ml-5">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-4">

        <div>
          <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">Nytt lösenord</label>
          <input id="new_password" name="new_password" type="password" class="w-full border rounded-lg px-3 py-2" required>
          <p class="text-xs text-gray-500 mt-1">Minst 8 tecken, minst en bokstav och en siffra.</p>
        </div>

        <div>
          <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Bekräfta nytt lösenord</label>
          <input id="confirm_password" name="confirm_password" type="password" class="w-full border rounded-lg px-3 py-2" required>
        </div>

        <div class="pt-2">
          <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 font-medium">Spara nytt lösenord</button>
        </div>
      </form>

      <div class="mt-6 text-center">
        <a href="/dashboard.php" class="text-sm text-gray-600 hover:text-gray-800 underline">Tillbaka till dashboard</a>
      </div>
    </div>
  </div>

  <script>
    // Klientvalidering (extra UX)
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e){
      const newPw = document.getElementById('new_password').value;
      const cPw = document.getElementById('confirm_password').value;
      if (newPw !== cPw) {
        e.preventDefault();
        alert('De nya lösenorden matchar inte.');
      } else if (newPw.length < 8) {
        e.preventDefault();
        alert('Lösenordet måste vara minst 8 tecken.');
      }
    });
  </script>
</body>
</html>