<?php
// reset-password-confirm.php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';

// Load reCAPTCHA v3 keys from environment (avoid hardcoding)
$recaptchaSite   = getenv('RECAPTCHA_SITE_KEY') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
       $database = new Database();
       $db = $database->getConnection();

       // Verifiera reCAPTCHA v3
       if (empty($_POST['recaptcha_response'])) {
           throw new Exception('Captcha saknas. Försök igen.');
       }
       if ($recaptchaSecret === '') {
           throw new Exception('Captcha är inte konfigurerad. Kontakta administratören.');
       }

       $recaptchaResponse = $_POST['recaptcha_response'];
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

       if ($_POST['password'] !== $_POST['password_confirm']) {
           throw new Exception('Lösenorden matchar inte');
       }

       if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d|\W).{8,}$/', $_POST['password'])) {
           throw new Exception('Lösenordet uppfyller inte kraven');
       }

       $stmt = $db->prepare("
           SELECT pr.user_id 
           FROM password_resets pr 
           WHERE pr.token = ? AND pr.expires_at > NOW()
           LIMIT 1
       ");
       $stmt->execute([$_POST['token']]);
       $reset = $stmt->fetch();

       if (!$reset) {
           throw new Exception('Ogiltig eller utgången återställningslänk');
       }

       $hashedPassword = password_hash($_POST['password'], PASSWORD_ARGON2ID, [
           'memory_cost' => 65536,
           'time_cost' => 4,
           'threads' => 3,
       ]);

       $db->beginTransaction();

       $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
       $stmt->execute([$hashedPassword, $reset['user_id']]);

       $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
       $stmt->execute([$reset['user_id']]);

       $db->commit();

       $_SESSION['success'] = 'Ditt lösenord har uppdaterats. Du kan nu logga in.';
       header('Location: login.php');
       exit;

   } catch (Exception $e) {
       if ($db->inTransaction()) {
           $db->rollBack();
       }
       $_SESSION['error'] = $e->getMessage();
       header('Location: reset-password-confirm.php?token=' . $_POST['token']);
       exit;
   }
}

// Verifiera token vid GET request
if (empty($_GET['token'])) {
   header('Location: login.php');
   exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Välj nytt lösenord - Klassrumsverktyg</title>
<?php if (!empty($recaptchaSite)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSite) ?>"></script>
<?php endif; ?>
   <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
   <div class="min-h-screen flex items-center justify-center">
       <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
           <h2 class="text-2xl font-bold text-center mb-8">Välj nytt lösenord</h2>
           
           <?php if (isset($_SESSION['error'])): ?>
               <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                   <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
               </div>
           <?php endif; ?>

           <form id="resetConfirmForm" method="POST" class="space-y-6">
               <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
               <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
               
               <div>
                   <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Nytt lösenord</label>
                   <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" 
                          id="password" name="password" type="password" required minlength="8">
                   <p class="text-gray-600 text-xs">Minst 8 tecken, bokstäver och siffror</p>
               </div>

               <div>
                   <label class="block text-gray-700 text-sm font-bold mb-2" for="password_confirm">Bekräfta nytt lösenord</label>
                   <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" 
                          id="password_confirm" name="password_confirm" type="password" required minlength="8">
               </div>

               <button class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
                       type="submit">Uppdatera lösenord</button>
           </form>
       </div>
   </div>

   <script>
       (function(){
           var siteKey = <?= json_encode($recaptchaSite) ?>;
           if (!siteKey) { return; }
           grecaptcha.ready(function() {
               grecaptcha.execute(siteKey, {action: 'reset_password_confirm'})
                   .then(function(token) {
                       var el = document.getElementById('recaptchaResponse');
                       if (el) el.value = token;
                   });
           });
       })();
   </script>
</body>
</html>