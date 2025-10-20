<?php
// admin/api/import_users.php
// Endpoint för att importera användare via CSV
// Returnerar JSON

header('Content-Type: application/json; charset=utf-8');

// --- Bootstrap ---
// Lös upp projektroten från /admin/api/ (två nivåer upp)
$root = dirname(__DIR__, 2); // ex: /var/www/site/klassverktyg
$dbPathCandidates = [
    $root . '/src/Config/Database.php',                 // förväntad
    __DIR__ . '/../../src/Config/Database.php',         // relativ fallback
    $root . '/Config/Database.php',                      // alternativ struktur
    __DIR__ . '/../../Config/Database.php',              // relativ alt
];
$found = null;
foreach ($dbPathCandidates as $cand) {
    if (is_file($cand)) { $found = $cand; break; }
}
if ($found === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Kunde inte hitta Database.php',
        'checked' => $dbPathCandidates,
        'cwd' => __DIR__,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $found;

session_start();

// --- Enkla hjälpare ---
// Observera: Den temporära CSV med genererade lösenord returneras endast i minnet och sparas inte på servern.
function json_error($message, $http = 400) {
    http_response_code($http);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_success($data = []) {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function has_col(array $cols, string $needle): bool { return in_array(strtolower($needle), $cols, true); }
function slug_username_from_email(string $email): string {
    $base = preg_replace('/@.*/', '', $email);
    $base = preg_replace('/[^a-z0-9._-]/i', '', $base);
    return $base ?: 'user'.substr(bin2hex(random_bytes(3)),0,6);
}
function strong_password(int $len = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_+';
    $out = '';
    for ($i=0;$i<$len;$i++) { $out .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
    return $out;
}

// --- Behörighet: endast inloggad admin ---
// --- Hämta template ---
if (isset($_GET['template']) && $_GET['template'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users-import-template.csv"');
    $fh = fopen('php://output', 'w');
    // Header row
    fputcsv($fh, ['email','first_name','last_name','role','school','is_active','password']);
    // Example row
    fputcsv($fh, ['teacher@example.com','Anna','Andersson','teacher','Skola A','1','']);
    fclose($fh);
    exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    json_error('Endast administratörer får importera användare.', 403);
}

// --- DB ---
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    json_error('Kunde inte ansluta till databasen.');
}

// --- Users-metadata och prepared statements (delas av single/CSV) ---
$usersCols = [];
try {
    $q = $pdo->query("SHOW COLUMNS FROM users");
    while ($col = $q->fetch(PDO::FETCH_ASSOC)) { $usersCols[] = $col['Field']; }
} catch (Throwable $e) {
    // Fortsätt med antagande
    $usersCols = ['username','email','password','first_name','last_name','role','is_active','school','must_change_password','created_at','updated_at'];
}
$usersColsLower = array_map('strtolower', $usersCols);
$hasSchool    = in_array('school', $usersColsLower, true);
$hasIsActive  = in_array('is_active', $usersColsLower, true);
$hasMustChange= in_array('must_change_password', $usersColsLower, true);

$checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$checkUsername = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');

$insertCols = ['username','email','password','first_name','last_name','role'];
if ($hasIsActive)   $insertCols[] = 'is_active';
if ($hasSchool)     $insertCols[] = 'school';
if ($hasMustChange) $insertCols[] = 'must_change_password';
$insertCols[] = 'created_at';
$insertCols[] = 'updated_at';

$placeholders = '(' . implode(',', array_fill(0, count($insertCols), '?')) . ')';
$sql = 'INSERT INTO users (' . implode(',', $insertCols) . ') VALUES ' . $placeholders;
$insert = $pdo->prepare($sql);

$validRoles = ['admin','teacher'];

// --- Uppladdad fil eller single-user-läge ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Fel metod. Använd POST.', 405);
}

// Läs ev. JSON-body (om Content-Type: application/json)
$payload = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $dec = json_decode($raw, true);
    if (is_array($dec)) { $payload = $dec; }
}

$mode = $_POST['mode'] ?? $payload['mode'] ?? '';
// Heuristik: om email finns men ingen fil, anta single
if ($mode === '' || $mode === null) {
    $hasEmailPost = isset($_POST['email']) && trim((string)$_POST['email']) !== '';
    $hasEmailJson = isset($payload['email']) && trim((string)$payload['email']) !== '';
    if ($hasEmailPost || $hasEmailJson) {
        $mode = 'single';
    }
}

if ($mode === 'single') {
    // --- Skapa en enstaka användare ---
    $email = strtolower(trim((string)($_POST['email'] ?? $payload['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Ogiltig e-postadress.');
    }
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        json_error('E-post används redan.');
    }

    $first = trim((string)($_POST['first_name'] ?? $payload['first_name'] ?? ''));
    $last  = trim((string)($_POST['last_name']  ?? $payload['last_name']  ?? ''));
    $role  = strtolower(trim((string)($_POST['role'] ?? $payload['role'] ?? 'teacher')));
    if (!in_array($role, $validRoles, true)) { $role = 'teacher'; }
    $school = trim((string)($_POST['school'] ?? $payload['school'] ?? ''));
    $isActive = (string)($_POST['is_active'] ?? $payload['is_active'] ?? '1');
    $isActive = ($isActive === '0') ? '0' : '1';

    $usernameReq = trim((string)($_POST['username'] ?? $payload['username'] ?? ''));
    $username = $usernameReq !== '' ? $usernameReq : slug_username_from_email($email);
    $candidate = $username; $tries=0;
    while (true) {
        $checkUsername->execute([$candidate]);
        if (!$checkUsername->fetch()) { $username = $candidate; break; }
        $tries++; $candidate = $username . $tries;
        if ($tries > 50) { $username = $username . '_' . substr(bin2hex(random_bytes(2)),0,4); break; }
    }

    $providedPass = (string)($_POST['password'] ?? $payload['password'] ?? '');
    if ($providedPass === '') {
        $passwordPlain = strong_password(12);
        $mustChange = '1';
    } else {
        $passwordPlain = $providedPass;
        $mustChange = '0';
    }
    if (defined('PASSWORD_ARGON2ID')) {
        $passwordHash = password_hash($passwordPlain, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3,
        ]);
    } else {
        $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
    }

    $values = [ $username, $email, $passwordHash, $first, $last, $role ];
    if ($hasIsActive)   $values[] = $isActive;
    if ($hasSchool)     $values[] = $school;
    if ($hasMustChange) $values[] = $mustChange;
    $now = date('Y-m-d H:i:s');
    $values[] = $now; $values[] = $now;

    try {
        $insert->execute($values);
    } catch (Throwable $e) {
        json_error('Kunde inte skapa användaren: ' . $e->getMessage(), 500);
    }

    json_success([
        'imported' => 1,
        'skipped' => 0,
        'errors' => [],
        'user' => [
            'email' => $email,
            'username' => $username,
            'first_name' => $first,
            'last_name' => $last,
            'role' => $role,
            'is_active' => $isActive,
            'school' => $school,
        ],
        'temp_csv_b64' => ($mustChange === '1') ? base64_encode("email,username,temp_password\n{$email},{$username},{$passwordPlain}\n") : null,
        'temp_csv_filename' => ($mustChange === '1') ? ('user-temp-password-'.date('Ymd-His').'.csv') : null,
    ]);
}

// --- CSV-flöde kräver fil ---
if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_error('Ingen fil uppladdad. För batchimport krävs en CSV-fil. För att skapa en enstaka användare, skicka fälten (email, first_name, etc.) eller sätt mode=single.');
}
$file = $_FILES['file'];

// --- Importalternativ ---
$delimiter = $_POST['delimiter'] ?? ','; // endast för CSV
$hasHeader = isset($_POST['has_header']) && ($_POST['has_header'] === 'on' || $_POST['has_header'] === 'true' || $_POST['has_header'] === '1');
$defaultRole = $_POST['default_role'] ?? 'teacher';
$defaultActive = (string)($_POST['default_active'] ?? '1');
$passwordStrategy = $_POST['password_strategy'] ?? 'provided_or_generate';

if (!in_array($defaultRole, $validRoles, true)) $defaultRole = 'teacher';
$defaultActive = ($defaultActive === '0') ? '0' : '1';

// --- Läs kolumner från fil ---
$rows = [];
try {
    $fh = fopen($file['tmp_name'], 'r');
    if ($fh === false) json_error('Kunde inte läsa CSV-filen.');
    if ($delimiter === '\\t') $delimiter = "\t";
    while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
        $rows[] = $data;
    }
    fclose($fh);
} catch (Throwable $e) {
    json_error('Kunde inte tolka CSV-filen: ' . $e->getMessage());
}

if (count($rows) === 0) {
    json_error('Filen är tom.');
}

// --- Bestäm kolumnnamn ---
$header = [];
if ($hasHeader) {
    $header = array_map(fn($s) => strtolower(trim((string)$s)), $rows[0]);
    array_shift($rows);
} else {
    // Standardordning om ingen header: email, first_name, last_name, role, school, is_active, password
    $header = ['email','first_name','last_name','role','school','is_active','password'];
}

// Tillåtna in-kolumner
$colsLower = $header; // redan lower

if (!has_col($colsLower,'email')) {
    json_error('Kolumnen "email" saknas.');
}

// --- Importloop ---
$tempPasswords = [];
$imported = 0; $skipped = 0; $errorsDetail = [];
$rowNum = $hasHeader ? 2 : 1; // mänsklig radnummering

foreach ($rows as $r) {
    // Mappa rad -> assoc
    $rowAssoc = [];
    foreach ($header as $i => $name) {
        $rowAssoc[$name] = isset($r[$i]) ? trim((string)$r[$i]) : '';
    }

    $email = strtolower($rowAssoc['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++; $errorsDetail[] = "Rad $rowNum: ogiltig e-post"; $rowNum++; continue;
    }

    // Unik e-post
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) { $skipped++; $errorsDetail[] = "Rad $rowNum: e-post används redan"; $rowNum++; continue; }

    // Namn
    $first = $rowAssoc['first_name'] ?? '';
    $last  = $rowAssoc['last_name'] ?? '';

    // Roll
    $role  = strtolower($rowAssoc['role'] ?? '') ?: $defaultRole;
    if (!in_array($role, $validRoles, true)) $role = $defaultRole;

    // Skola / enhet
    $school = $rowAssoc['school'] ?? '';

    // Aktiv
    $isActive = isset($rowAssoc['is_active']) && $rowAssoc['is_active'] !== '' ? ( ($rowAssoc['is_active'] === '0') ? '0' : '1') : $defaultActive;

    // Username (om kolumn saknas: skapa från e-post och säkerställ unikt)
    $username = $rowAssoc['username'] ?? slug_username_from_email($email);
    // säkerställ unikt username
    $candidate = $username; $tries=0;
    while (true) {
        $checkUsername->execute([$candidate]);
        if (!$checkUsername->fetch()) { $username = $candidate; break; }
        $tries++; $candidate = $username . $tries;
        if ($tries > 50) { $username = $username . '_' . substr(bin2hex(random_bytes(2)),0,4); break; }
    }

    // Lösenord
    $providedPass = $rowAssoc['password'] ?? '';
    if ($passwordStrategy === 'generate_always') {
        $passwordPlain = strong_password(12);
    } elseif ($passwordStrategy === 'reject_missing' && $providedPass === '') {
        $skipped++; $errorsDetail[] = "Rad $rowNum: lösenord saknas (policy kräver lösenord)"; $rowNum++; continue;
    } else { // provided_or_generate
        $passwordPlain = $providedPass !== '' ? $providedPass : strong_password(12);
    }
    // Match PasswordUtils::hashPassword (Argon2id) with safe fallback
    if (defined('PASSWORD_ARGON2ID')) {
        $passwordHash = password_hash($passwordPlain, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3,
        ]);
    } else {
        // Fallback if Argon2id is not available on this PHP build
        $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
    }

    // Bestäm om användaren måste byta lösenord vid första inloggning
    $generated = false;
    if ($passwordStrategy === 'generate_always') {
        $generated = true;
    } elseif ($passwordStrategy === 'provided_or_generate' && $providedPass === '') {
        $generated = true;
    }
    $mustChange = $generated ? '1' : '0';

    // Samla temp-lösenord för export om det genererades
    if ($generated) {
        $tempPasswords[] = [
            'email' => $email,
            'username' => $username,
            'temp_password' => $passwordPlain,
        ];
    }

    // Bygg värden i samma ordning som $insertCols
    $values = [
        $username,
        $email,
        $passwordHash,
        $first,
        $last,
        $role,
    ];
    if ($hasIsActive) $values[] = $isActive;
    if ($hasSchool) $values[] = $school;
    if ($hasMustChange) $values[] = $mustChange;
    $now = date('Y-m-d H:i:s');
    $values[] = $now; // created_at
    $values[] = $now; // updated_at

    try {
        $insert->execute($values);
        $imported++;
    } catch (Throwable $e) {
        $skipped++; $errorsDetail[] = "Rad $rowNum: " . $e->getMessage();
    }

    $rowNum++;
}

// Om vi genererat några temporära lösenord, skapa en CSV att ladda ned i UI
$tempCsvB64 = null;
if (!empty($tempPasswords)) {
    $fh = fopen('php://temp', 'w+');
    // header
    fputcsv($fh, ['email','username','temp_password']);
    foreach ($tempPasswords as $tp) {
        fputcsv($fh, [$tp['email'], $tp['username'], $tp['temp_password']]);
    }
    rewind($fh);
    $csvData = stream_get_contents($fh);
    fclose($fh);
    $tempCsvB64 = base64_encode($csvData);
}

json_success([
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errorsDetail,
    'temp_csv_b64' => $tempCsvB64,
    'temp_csv_filename' => 'users-temp-passwords-'.date('Ymd-His').'.csv',
]);
