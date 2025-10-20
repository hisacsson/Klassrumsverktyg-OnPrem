<?php
require_once __DIR__ . '/src/Config/Database.php';

$db = new Database();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $widgetId = $_GET['widget_id'] ?? null;
        
        if (!$widgetId) {
            throw new Exception('Widget ID saknas');
        }

        // Börja transaktion
        $pdo->beginTransaction();

        // Hämta poll_id först
        $stmt = $pdo->prepare("SELECT id FROM polls WHERE widget_id = ?");
        $stmt->execute([$widgetId]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($poll) {
            // Ta bort poll options först
            $stmt = $pdo->prepare("DELETE FROM poll_options WHERE poll_id = ?");
            $stmt->execute([$poll['id']]);

            // Sedan ta bort själva pollen
            $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
            $stmt->execute([$poll['id']]);
        }

        // Commit transaktion
        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}