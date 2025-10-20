<?php
// api/generate-groups.php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Config/Database.php';

// Get POST data
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!isset($data['widget_id'])) {
    echo json_encode(['error' => 'Widget ID required']);
    exit;
}

$widgetId = $data['widget_id'];
$groupMethod = $data['groupMethod'] ?? 'count';
$groupCount = $data['groupCount'] ?? 2;
$groupSize = $data['groupSize'] ?? 2;
$shuffle = $data['shuffle'] ?? true;
$balance = $data['balance'] ?? true;

// Get saved names from the database
$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT settings FROM widgets WHERE id = ?");
$stmt->execute([$widgetId]);
$widget = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$widget) {
    echo json_encode(['error' => 'Widget not found']);
    exit;
}

$settings = json_decode($widget['settings'], true) ?? [];
$encodedNames = $settings['encodedNames'] ?? null;

// Decode saved names
$nameList = [];
if ($encodedNames) {
    $decoded = base64_decode($encodedNames);
    $nameText = $decoded ? $decoded : $encodedNames;
    
    try {
        // För att hantera UTF-8 tecken korrekt
        $nameText = mb_convert_encoding($nameText, 'UTF-8', 'UTF-8');
        $nameList = array_filter(array_map('trim', explode("\n", $nameText)));
    } catch (Exception $e) {
        // Fallback om det uppstår fel vid avkodning
        $nameList = array_filter(array_map('trim', explode("\n", utf8_encode($nameText))));
    }
}

// Remove empty names
$nameList = array_values(array_filter($nameList));

// Check if we have names
if (empty($nameList)) {
    echo json_encode(['error' => 'No names found', 'groups' => []]);
    exit;
}

// Shuffle names if requested
if ($shuffle) {
    shuffle($nameList);
}

// Determine how many groups to create based on method
$groups = [];

if ($groupMethod === 'size') {
    // Skapa grupper baserat på antal personer per grupp
    if ($groupSize <= 0) $groupSize = 2;
    
    // Beräkna hur många grupper vi behöver
    $actualGroupCount = ceil(count($nameList) / $groupSize);
    
    // Skapa tomma grupper
    for ($i = 0; $i < $actualGroupCount; $i++) {
        $groups[] = [];
    }
    
    // Fördela namn jämnt om balansering är på
    if ($balance) {
        // Beräkna gruppstorlekar
        $baseSize = floor(count($nameList) / $actualGroupCount);
        $remainder = count($nameList) % $actualGroupCount;
        
        $start = 0;
        for ($i = 0; $i < $actualGroupCount; $i++) {
            $currentSize = $baseSize + ($i < $remainder ? 1 : 0);
            $groups[$i] = array_slice($nameList, $start, $currentSize);
            $start += $currentSize;
        }
    } else {
        // Enkel fördelning utan balansering
        $currentGroup = 0;
        foreach ($nameList as $name) {
            if (count($groups[$currentGroup]) >= $groupSize && $currentGroup < $actualGroupCount - 1) {
                $currentGroup++;
            }
            $groups[$currentGroup][] = $name;
        }
    }
} else {
    // Skapa grupper baserat på antal grupper
    if ($groupCount <= 0) $groupCount = 2;
    if ($groupCount > count($nameList)) $groupCount = count($nameList);
    
    // Skapa tomma grupper
    for ($i = 0; $i < $groupCount; $i++) {
        $groups[] = [];
    }
    
    // Fördela namn jämnt i grupperna
    $currentGroup = 0;
    foreach ($nameList as $name) {
        $groups[$currentGroup][] = $name;
        $currentGroup = ($currentGroup + 1) % $groupCount;
    }
}

// Return the groups
echo json_encode(['groups' => $groups]);
?>