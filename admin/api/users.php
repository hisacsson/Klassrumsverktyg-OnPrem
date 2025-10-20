<?php
// admin/api/users.php
// REST-liknande endpoints för användarhantering

// ---- Bootstrap ----
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

// Enkelt CSRF-skydd via header (matchar meta-tag i users.php)
function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { return; } // GET är idempotent här
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$hdr || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $hdr)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-validering misslyckades']);
        exit;
    }
}

require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../AdminController.php';

$database = new Database();
$db = $database->getConnection();
$admin = new AdminController($db);

// ---- URL-parsning ----
// Förväntat mönster: /admin/api/users/<action>/<id>
// ---- URL-parsning ----
// Förväntat mönster: /admin/api/users/<action>/<id>
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($uriPath, '/'));
$parts = array_values(array_filter($parts, 'strlen'));
$usersIdx = array_search('users', $parts);
$action = null;
$userId = null;
if ($usersIdx !== false) {
    $action = $parts[$usersIdx + 1] ?? null;
    $idStr  = $parts[$usersIdx + 2] ?? null;
    $userId = $idStr !== null ? (int)$idStr : null;
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Fallback: stöd för query-parametrar när pretty URLs inte är routade
if (!$action) {
    $action = $_GET['action'] ?? $_GET['a'] ?? null;
}
if (!$userId) {
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : null);
}

// Debug-läge: /admin/api/users.php?debug=1 visar hur servern tolkar anropet
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo json_encode([
        'success' => true,
        'debug' => [
            'uriPath' => $uriPath,
            'parts' => $parts,
            'usersIdx' => $usersIdx,
            'action' => $action,
            'userId' => $userId,
            'method' => $method,
            'has_csrf' => isset($_SESSION['csrf_token']),
        ]
    ]);
    exit;
}

// Läs JSON-body om skickad
$payload = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $dec = json_decode($raw, true);
    if (is_array($dec)) { $payload = $dec; }
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'get-user' && $userId) {
                $user = $admin->getUser($userId);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Användare saknas']);
                    break;
                }
                echo json_encode(['success' => true, 'user' => $user]);
                break;
            }
            http_response_code(404);
            $known = ['get-user'];
            echo json_encode(['success' => false, 'message' => 'Endpoint saknas', 'known_actions' => $known, 'received' => $action]);
            break;

        case 'POST':
            require_csrf();
            if ($action === 'reset-password' && $userId) {
                $admin->resetPassword($userId); // ev. skicka mail här
                echo json_encode(['success' => true]);
                break;
            }
            if ($action === 'toggle-user-status' && $userId) {
                // Tar emot { status: 0|1 }
                $status = null;
                if (isset($payload['status'])) { $status = (int)$payload['status']; }
                elseif (isset($_POST['status'])) { $status = (int)$_POST['status']; }
                if ($status === null) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'status saknas']);
                    break;
                }
                $ok = $admin->toggleUserStatus($userId, $status);
                echo json_encode(['success' => (bool)$ok]);
                break;
            }
            if ($action === 'update-user' && $userId) {
                // Tillåt både form-data och JSON
                $data = array_merge($_POST, $payload);

                // Hantera ev. nytt lösenord separat
                if (isset($data['password']) && is_string($data['password'])) {
                    $newPass = trim($data['password']);
                    if ($newPass !== '') {
                        if (mb_strlen($newPass) < 6) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'message' => 'Lösenordet måste vara minst 6 tecken.']);
                            break;
                        }
                        if (!$admin->setPassword((int)$userId, $newPass)) {
                            http_response_code(500);
                            echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera lösenordet.']);
                            break;
                        }
                    }
                    unset($data['password']); // ta bort så den inte behandlas som vanligt fält
                }

                $ok = $admin->updateUser($userId, $data);
                echo json_encode(['success' => (bool)$ok]);
                break;
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint saknas']);
            break;

        case 'DELETE':
            require_csrf();
            if ($action === 'delete-user' && $userId) {
                $ok = $admin->deleteUser($userId);
                echo json_encode(['success' => (bool)$ok]);
                break;
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint saknas']);
            break;

        case 'OPTIONS':
            header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
            http_response_code(204);
            exit;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}