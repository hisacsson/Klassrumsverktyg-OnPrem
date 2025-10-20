<?php
require_once __DIR__ . '/src/Config/Database.php';

$widgetId = $_GET['widget_id'] ?? null;
$format = $_GET['format'] ?? 'html';

$db = new Database();
$pdo = $db->getConnection();

if ($format === 'json') {
    header('Content-Type: application/json');
    
    $stmt = $pdo->prepare("
        SELECT p.question, o.text, o.votes 
        FROM polls p
        JOIN poll_options o ON p.id = o.poll_id
        WHERE p.widget_id = ?
        ORDER BY o.id
    ");
    $stmt->execute([$widgetId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    exit;
}

// HTML-vyn fortsätter här
$stmt = $pdo->prepare("
    SELECT p.question, o.text, o.votes 
    FROM polls p
    JOIN poll_options o ON p.id = o.poll_id
    WHERE p.widget_id = ?
    ORDER BY o.id
");
$stmt->execute([$widgetId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$results) {
    die("Ingen omröstning hittades.");
}

$totalVotes = array_sum(array_column($results, 'votes'));
?>

<div class="bg-white rounded-lg shadow-lg p-6 max-w-lg w-full mx-4">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold"><?= htmlspecialchars($results[0]['question']) ?></h2>
        <button onclick="closePollResults()" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="space-y-4" id="pollOptionsContainer">
        <?php foreach ($results as $index => $result): 
            $percentage = $totalVotes > 0 ? round(($result['votes'] / $totalVotes) * 100) : 0;
        ?>
            <div class="poll-option opacity-0 transition-opacity duration-500" 
                 style="animation-delay: <?= $index * 150 ?>ms">
                <div class="flex justify-between mb-1">
                    <span class="font-medium"><?= htmlspecialchars($result['text']) ?></span>
                    <span class="percentage"><?= $percentage ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-6">
                    <div class="poll-bar bg-blue-500 h-6 rounded-full transition-all duration-1000 ease-out" 
                         style="width: 0%"
                         data-target-width="<?= $percentage ?>"></div>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                    <span class="votes"><?= $result['votes'] ?></span> röster
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="mt-4 pt-4 border-t">
            <p class="text-gray-600">Totalt antal röster: <span id="totalVotes"><?= $totalVotes ?></span></p>
        </div>
    </div>
</div>