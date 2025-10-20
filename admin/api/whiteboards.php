<?php
// admin/api/whiteboards.php
require_once $root . '../../../src/Config/Database.php';
require_once '../AdminController.php';

$database = new Database();
$db = $database->getConnection();
$admin = new AdminController($db);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = explode('/', trim($uri, '/'));
$whiteboardId = isset($path[count($path) - 1]) ? intval($path[count($path) - 1]) : null;

try {
    if ($method === 'DELETE' && $whiteboardId) {
        $success = $admin->deleteWhiteboard($whiteboardId);
        echo json_encode(['success' => $success]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}