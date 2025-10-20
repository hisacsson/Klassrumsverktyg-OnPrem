<?php
/**
 * CRON Script för att radera whiteboards äldre än tre dagar
 * och skapade av icke registrerade användare
 */

// Inkludera Database.php med korrekt sökväg baserat på felsökningsresultat
require_once __DIR__ . '/../src/Config/Database.php';

// Global variabel för att spåra statistik
$cleanupStats = [
    'found_whiteboards' => 0,
    'deleted_whiteboards' => 0,
    'deleted_widgets' => 0,
    'deleted_polls' => 0,
    'deleted_poll_options' => 0,
    'deleted_student_groups' => 0
];

// Hjälpfunktion: säkra att loggkatalog finns och är skrivbar
function ensureDir(string $dir): string {
    $dir = rtrim($dir, '/');
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Kunde inte skapa loggkatalog: $dir");
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("Loggkatalog ej skrivbar: $dir");
    }
    return $dir;
}

// Hitta loggkatalog dynamiskt (projektets /logs > /tmp/klassrumsverktyg_logs)
function getLogDir(): string {
    // 1) Projektets /logs
    $projectRoot = realpath(__DIR__ . '/../') ?: dirname(__DIR__);
    try {
        return ensureDir($projectRoot . '/logs');
    } catch (RuntimeException $e) {
        // 2) Fallback till /tmp
        return ensureDir(sys_get_temp_dir() . '/klassrumsverktyg_logs');
    }
}

// Skapa loggfunktion (med nivå)
function logMessage(string $message, string $level = 'INFO') {
    $date = date('Y-m-d H:i:s');
    $tag = strtoupper($level);
    $line = "[$date] [$tag] $message" . PHP_EOL;
    echo $line;

    try {
        $logDir = getLogDir();
        $logFile = $logDir . '/cleanup_' . date('Y-m') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND);
    } catch (Throwable $e) {
        // Skriv ut till STDERR så cron-mail fångar det
        fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] [ERROR] Loggning misslyckades: " . $e->getMessage() . PHP_EOL);
    }
}

// Huvudfunktion för att radera gamla whiteboards
function cleanupWhiteboards() {
    global $cleanupStats;
    logMessage("Startar rensning av gamla whiteboards...");
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Förbered datum för tre dagar sedan
        $threeDaysAgo = date('Y-m-d H:i:s', strtotime('-3 days'));
        
        // Hämta antal whiteboards som kommer raderas
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM whiteboards 
            WHERE user_id IS NULL 
            AND created_at < :threeDaysAgo
        ");
        $countStmt->bindParam(':threeDaysAgo', $threeDaysAgo);
        $countStmt->execute();
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $count = isset($countRow['total']) ? (int)$countRow['total'] : 0;
        
        // Uppdatera statistik
        $cleanupStats['found_whiteboards'] = $count;
        
        if ($count > 0) {
            logMessage("Hittade $count whiteboards att radera");
        } else {
            logMessage("Inga whiteboards behöver raderas", "SUMMARY");
            return;
        }
        
        // Hämta alla whiteboard IDs som ska raderas (för att kunna radera relaterade data)
        $selectStmt = $pdo->prepare("
            SELECT id 
            FROM whiteboards 
            WHERE user_id IS NULL 
            AND created_at < :threeDaysAgo
        ");
        $selectStmt->bindParam(':threeDaysAgo', $threeDaysAgo);
        $selectStmt->execute();
        $whiteboardIds = $selectStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Om vi har whiteboards att radera
        if (!empty($whiteboardIds)) {
            // Börja en transaktion för att säkerställa att all relaterad data raderas
            $pdo->beginTransaction();
            
            try {
                // Hämta alla widgets från dessa whiteboards
                $widgetStmt = $pdo->prepare("
                    SELECT id 
                    FROM widgets 
                    WHERE whiteboard_id IN (" . implode(',', array_fill(0, count($whiteboardIds), '?')) . ")
                ");
                $widgetStmt->execute($whiteboardIds);
                $widgetIds = $widgetStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Radera relaterade polls och poll_options
                if (!empty($widgetIds)) {
                    // Först hämta poll IDs
                    $pollStmt = $pdo->prepare("
                        SELECT id 
                        FROM polls 
                        WHERE widget_id IN (" . implode(',', array_fill(0, count($widgetIds), '?')) . ")
                    ");
                    $pollStmt->execute($widgetIds);
                    $pollIds = $pollStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Radera poll_options först (eftersom de refererar till polls)
                    if (!empty($pollIds)) {
                        $deletePollOptionsStmt = $pdo->prepare("
                            DELETE FROM poll_options 
                            WHERE poll_id IN (" . implode(',', array_fill(0, count($pollIds), '?')) . ")
                        ");
                        $deletePollOptionsStmt->execute($pollIds);
                        $pollOptionsDeleted = $deletePollOptionsStmt->rowCount();
                        $cleanupStats['deleted_poll_options'] = $pollOptionsDeleted;
                        logMessage("Raderade $pollOptionsDeleted poll-alternativ");
                    }
                    
                    // Sedan radera polls
                    $deletePollsStmt = $pdo->prepare("
                        DELETE FROM polls 
                        WHERE widget_id IN (" . implode(',', array_fill(0, count($widgetIds), '?')) . ")
                    ");
                    $deletePollsStmt->execute($widgetIds);
                    $pollsDeleted = $deletePollsStmt->rowCount();
                    $cleanupStats['deleted_polls'] = $pollsDeleted;
                    logMessage("Raderade $pollsDeleted polls");
                }
                
                // Radera relaterade widgets
                if (!empty($whiteboardIds)) {
                    $deleteWidgetsStmt = $pdo->prepare("
                        DELETE FROM widgets 
                        WHERE whiteboard_id IN (" . implode(',', array_fill(0, count($whiteboardIds), '?')) . ")
                    ");
                    $deleteWidgetsStmt->execute($whiteboardIds);
                    $widgetsDeleted = $deleteWidgetsStmt->rowCount();
                    $cleanupStats['deleted_widgets'] = $widgetsDeleted;
                    logMessage("Raderade $widgetsDeleted widgets");
                }
                
                // Radera relaterade student groups
                if (!empty($whiteboardIds)) {
                    $deleteGroupsStmt = $pdo->prepare("
                        DELETE FROM student_groups 
                        WHERE whiteboard_id IN (" . implode(',', array_fill(0, count($whiteboardIds), '?')) . ")
                    ");
                    $deleteGroupsStmt->execute($whiteboardIds);
                    $groupsDeleted = $deleteGroupsStmt->rowCount();
                    $cleanupStats['deleted_student_groups'] = $groupsDeleted;
                    logMessage("Raderade $groupsDeleted studentgrupper");
                }
                
                // Slutligen radera whiteboards
                if (!empty($whiteboardIds)) {
                    $deleteWhiteboardsStmt = $pdo->prepare("
                        DELETE FROM whiteboards 
                        WHERE id IN (" . implode(',', array_fill(0, count($whiteboardIds), '?')) . ")
                    ");
                    $deleteWhiteboardsStmt->execute($whiteboardIds);
                    $whiteboardsDeleted = $deleteWhiteboardsStmt->rowCount();
                    $cleanupStats['deleted_whiteboards'] = $whiteboardsDeleted;
                    
                    // Detta är ett viktigt meddelande, så markera det som SUMMARY
                    logMessage("Rensning slutförd: Raderade $whiteboardsDeleted whiteboards med tillhörande data", "SUMMARY");
                }
                
                // Genomför transaktionen
                $pdo->commit();
            } catch (Exception $e) {
                // Om något går fel, återställ databasen
                $pdo->rollBack();
                $cleanupStats['error'] = $e->getMessage();
                logMessage("Rensning misslyckades: " . $e->getMessage(), "ERROR");
                exit(1);
            }
        } else {
            logMessage("Inga whiteboards behöver raderas", "SUMMARY");
        }
    } catch (Exception $e) {
        $cleanupStats['error'] = $e->getMessage();
        logMessage($e->getMessage(), "ERROR");
        exit(1);
    }
}

// Kör rensningen
cleanupWhiteboards();

// Skapa en sammanfattning för enkelt visning på admin dashboard
function createSummaryLog() {
    $logDir = getLogDir();
    $summaryFile = $logDir . '/cleanup_summary.json';
    
    // Samla in statistik från den senaste körningen
    global $cleanupStats;
    
    $summary = [
        'last_run' => date('Y-m-d H:i:s'),
        'status' => isset($cleanupStats['error']) ? 'error' : 'success',
        'total_runs' => 1,
        'statistics' => $cleanupStats
    ];
    
    // Uppdatera existerande sammanfattning om den finns
    if (file_exists($summaryFile)) {
        $existingSummary = json_decode(file_get_contents($summaryFile), true);
        if (is_array($existingSummary)) {
            $summary['total_runs'] = ($existingSummary['total_runs'] ?? 0) + 1;
        }
    }
    
    // Spara sammanfattning
    file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT));
}

// Skapa sammanfattning
createSummaryLog();