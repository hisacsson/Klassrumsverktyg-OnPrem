<?php
// admin/init.php – körs högst upp på varje admin-sida
session_start();

// CSRF protection helpers and verification
// For fetch/AJAX, send header X-CSRF-Token: csrf_token()
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return $_SESSION['csrf_token'] ?? '';
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

// POST-only CSRF check (unless SKIP_CSRF is defined)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('SKIP_CSRF')) {
    $provided = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($provided) || !hash_equals($_SESSION['csrf_token'], $provided)) {
        http_response_code(419);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = (stripos($accept, 'application/json') !== false) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF token invalid or missing']);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Ogiltig eller saknad CSRF-token. Ladda om sidan och försök igen.';
        }
        exit;
    }
}
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Security/MFA.php';

use App\Security\MFA;

// Ensure we have a DB connection for lookups
$pdo = $pdo ?? (new Database())->getConnection();

// Normalize/refresh session user if needed
$sessionUser = $_SESSION['user'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? ($sessionUser['id'] ?? null);

if (!$sessionUser && $sessionUserId) {
    // Legacy sessions that only store user_id
    $stmt = $pdo->prepare("SELECT id, email, username, role, mfa_enabled, mfa_last_verified_at FROM users WHERE id=? AND is_active=1");
    $stmt->execute([$sessionUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $_SESSION['user'] = $row;
        $sessionUser = $row;
    }
}

// If still missing, bounce to login
if (!$sessionUser) {
    header('Location: /login.php');
    exit;
}

// Some installs might have different casing/whitespace
$role = strtolower(trim($sessionUser['role'] ?? ''));
if ($role !== 'admin') {
    http_response_code(403);
    exit('Endast admin');
}

// Keep session copy in sync with latest MFA flags
if (!array_key_exists('mfa_enabled', $sessionUser)) {
    $stmt = $pdo->prepare("SELECT mfa_enabled, mfa_last_verified_at FROM users WHERE id=?");
    $stmt->execute([$sessionUser['id']]);
    if ($mf = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['user']['mfa_enabled'] = (int)$mf['mfa_enabled'];
        $_SESSION['user']['mfa_last_verified_at'] = $mf['mfa_last_verified_at'];
        $sessionUser = $_SESSION['user'];
    }
}

$uid = (int)$sessionUser['id'];

// MFA guard: only if enabled
if ((int)($sessionUser['mfa_enabled'] ?? 0) === 1) {
    if (!isset($_SESSION['mfa_ok_for']) || $_SESSION['mfa_ok_for'] !== $uid) {
        if (!MFA::rememberCheck($pdo, $uid, $_COOKIE['mfa_remember'] ?? null)) {
            $_SESSION['pending_mfa_user_id'] = $uid;
            $_SESSION['pending_mfa_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
            header('Location: /auth/mfa-verify.php');
            exit;
        } else {
            $_SESSION['mfa_ok_for'] = $uid; // markera OK
        }
    }
}