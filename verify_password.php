<?php
session_start();
require_once __DIR__ . '/src/Config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$boardCode = $data['board_code'] ?? null;
$password = $data['password'] ?? null;

if (!$boardCode || !$password) {
    http_response_code(400);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->prepare("SELECT password FROM whiteboards WHERE board_code = ?");
$stmt->execute([$boardCode]);
$board = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$board || !password_verify($password, $board['password'])) {
    http_response_code(401);
    exit;
}

$_SESSION['verified_boards'][$boardCode] = true;
http_response_code(200);