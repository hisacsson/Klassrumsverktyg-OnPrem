<?php
// includes/display_alerts.php

/**
 * Display active alerts on the current page
 */
function displayAlerts() {
    global $pdo;
    
    // Get current page filename
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_time = date('Y-m-d H:i:s');
    
    // Prepare the query to get active alerts
    $query = "
        SELECT * FROM alerts 
        WHERE is_active = 1 
        AND start_at <= :current_time 
        AND (end_at IS NULL OR end_at >= :current_time2)
        AND (
            pages IS NULL 
            OR pages = '' 
            OR FIND_IN_SET(:current_page, pages) > 0
        )
        ORDER BY created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':current_time', $current_time);
    $stmt->bindParam(':current_time2', $current_time); // Använder samma värde men med annan parameter-namn
    $stmt->bindParam(':current_page', $current_page);
    $stmt->execute();
    
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Display each alert
    foreach ($alerts as $alert) {
        echo getAlertHtml($alert);
    }
}

/**
 * Generate HTML for an alert based on its type
 */
function getAlertHtml($alert) {
    $css_classes = '';
    $icon = '';
    
    switch ($alert['alert_type']) {
        case 'info':
            $css_classes = 'bg-blue-100 border-blue-500 text-blue-700';
            $icon = '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
            break;
        case 'warning':
            $css_classes = 'bg-yellow-100 border-yellow-500 text-yellow-700';
            $icon = '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
            break;
        case 'error':
            $css_classes = 'bg-red-100 border-red-500 text-red-700';
            $icon = '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
            break;
        case 'success':
            $css_classes = 'bg-green-100 border-green-500 text-green-700';
            $icon = '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
            break;
        default:
            $css_classes = 'bg-gray-100 border-gray-500 text-gray-700';
            $icon = '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
    }
    
    return '
    <div class="alert-banner border-l-4 p-4 mb-4 ' . $css_classes . '" role="alert">
        <div class="flex">
            <div class="flex-shrink-0">
                ' . $icon . '
            </div>
            <div>
                <p class="font-medium">' . htmlspecialchars($alert['message']) . '</p>
            </div>
        </div>
    </div>';
}
?>