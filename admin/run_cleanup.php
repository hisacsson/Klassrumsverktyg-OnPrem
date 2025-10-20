<?php
/**
 * Skript för att manuellt köra cleanup_whiteboards.php
 * Denna fil ska placeras i admin-mappen
 */

// Starta session för att kunna spara resultat
session_start();

// Säkerhetskontroll - endast tillåt admin
require_once __DIR__ . '/../src/Config/Database.php';

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: /login.php');
        exit;
    }
    
    return true;
}

// Kontrollera att användaren är admin
requireAdmin();

// Sökväg till cleanup-skriptet
$cleanupScript = '/var/www/morbysupport.nu/klassrumsverktyg/cron/cleanup_whiteboards.php';

// Kontrollera att filen existerar
if (!file_exists($cleanupScript)) {
    $_SESSION['cleanup_result'] = [
        'success' => false,
        'message' => 'Fel: Rensningsskriptet kunde inte hittas på ' . $cleanupScript,
        'output' => [],
        'time' => date('Y-m-d H:i:s')
    ];
    
    header('Location: /admin/index.php');
    exit;
}

// Kör skriptet
$output = [];
$return_var = 0;
exec('php ' . $cleanupScript, $output, $return_var);

// Spara resultatet
$success = $return_var === 0;
$message = $success ? 'Rensningsskriptet kördes framgångsrikt!' : 'Ett fel uppstod vid körning av rensningsskriptet.';

$_SESSION['cleanup_result'] = [
    'success' => $success,
    'message' => $message,
    'output' => $output,
    'time' => date('Y-m-d H:i:s')
];

// Omdirigera tillbaka till admin dashboard
header('Location: /admin/index.php');
exit;