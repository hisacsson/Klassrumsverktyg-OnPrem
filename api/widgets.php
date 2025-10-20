<?php
require_once __DIR__ . '/../src/Config/Database.php';

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $data = json_decode(file_get_contents('php://input'), true);
   
   $defaultSettings = [
       'clock' => [],
       'timer' => ['minutes' => 5],
       'text' => ['content' => ''],
       'groups' => ['names' => '', 'groupCount' => 2],
       'brainbreak' => ['lastActivity' => '']
   ];
   
   $settings = $defaultSettings[$data['type']] ?? [];
   
   // Sätt standardstorlekar baserat på widget-typ
   $size_w = $data['type'] === 'groups' ? 300 : ($data['size_w'] ?? 200);
   $size_h = $data['type'] === 'groups' ? 250 : ($data['size_h'] ?? 200);
   
   $stmt = $pdo->prepare("
       INSERT INTO widgets (
           whiteboard_id, 
           type, 
           position_x, 
           position_y,
           size_w,
           size_h,
           settings
       ) 
       VALUES (?, ?, ?, ?, ?, ?, ?)
   ");
   
   $stmt->execute([
       $data['whiteboard_id'],
       $data['type'],
       $data['position_x'],
       $data['position_y'],
       $size_w,
       $size_h,
       json_encode($settings)
   ]);
   
   $widget = [
       'id' => $pdo->lastInsertId(),
       'type' => $data['type'],
       'position_x' => $data['position_x'],
       'position_y' => $data['position_y'],
       'size_w' => $size_w,
       'size_h' => $size_h,
       'settings' => $settings
   ];
   
   echo json_encode($widget);
}