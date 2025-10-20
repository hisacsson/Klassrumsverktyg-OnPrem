<?php
// admin/alerts.php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
require_once 'AdminController.php';

// Skapa databasanslutning
$database = new Database();
$pdo = $database->getConnection();
// Läs instansens namn från system_settings (fallback: "Klassrumsverktyg")
if (!function_exists('kv_get_setting')) {
    function kv_get_setting(PDO $pdo, string $key, $default = null) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['setting_value'] ?? $default;
    }
}
$siteName = (string) kv_get_setting($pdo, 'site_name', 'Klassrumsverktyg');

$message = '';
$error = '';

// Handle form submission for creating/updating alert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create or update alert
        if ($_POST['action'] === 'save') {
            $alert_message = trim($_POST['message']);
            $alert_type = $_POST['alert_type'];
            $start_at = $_POST['start_at'];
            $end_at = !empty($_POST['end_at']) ? $_POST['end_at'] : null;
            $pages = !empty($_POST['pages']) ? $_POST['pages'] : null;
            $user_id = $_SESSION['user_id'];
            
            // Basic validation
            if (empty($alert_message) || empty($start_at)) {
                $error = "Message and start date are required.";
            } else {
                // Insert or update
                $id = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : null;
                
                if ($id) {
                    // Update existing alert
                    $stmt = $pdo->prepare("UPDATE alerts SET 
                        message = :message, 
                        alert_type = :alert_type,
                        start_at = :start_at,
                        end_at = :end_at,
                        pages = :pages
                        WHERE id = :id AND user_id = :user_id");
                    $stmt->bindParam(':id', $id);
                } else {
                    // Create new alert
                    $stmt = $pdo->prepare("INSERT INTO alerts 
                        (user_id, message, alert_type, start_at, end_at, pages) 
                        VALUES (:user_id, :message, :alert_type, :start_at, :end_at, :pages)");
                }
                
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':message', $alert_message);
                $stmt->bindParam(':alert_type', $alert_type);
                $stmt->bindParam(':start_at', $start_at);
                $stmt->bindParam(':end_at', $end_at);
                $stmt->bindParam(':pages', $pages);
                
                if ($stmt->execute()) {
                    $message = "Alert has been saved successfully!";
                    // Clear form after successful submission if it was a new alert
                    if (!$id) {
                        $_POST = array();
                    }
                } else {
                    $error = "Error saving alert. Please try again.";
                }
            }
        }
        
        // Delete alert
        if ($_POST['action'] === 'delete' && isset($_POST['alert_id'])) {
            $alert_id = intval($_POST['alert_id']);
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = :id AND user_id = :user_id");
            $stmt->bindParam(':id', $alert_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $message = "Alert has been deleted successfully!";
            } else {
                $error = "Error deleting alert. Please try again.";
            }
        }
        
        // Toggle alert active status
        if ($_POST['action'] === 'toggle' && isset($_POST['alert_id'])) {
            $alert_id = intval($_POST['alert_id']);
            $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
            $new_status = $status ? 0 : 1; // Toggle the status
            $user_id = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("UPDATE alerts SET is_active = :status 
                WHERE id = :id AND user_id = :user_id");
            $stmt->bindParam(':status', $new_status);
            $stmt->bindParam(':id', $alert_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $message = "Alert status has been updated successfully!";
            } else {
                $error = "Error updating alert status. Please try again.";
            }
        }
    }
}

// Get all alerts - lägg till try/catch för att hantera om tabellen inte finns
try {
    $stmt = $pdo->prepare("SELECT * FROM alerts ORDER BY created_at DESC");
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Om tabellen inte finns, skapa den
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "not found") !== false) {
        $sql = "CREATE TABLE IF NOT EXISTS `alerts` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `message` text NOT NULL,
            `alert_type` enum('info', 'warning', 'error', 'success') NOT NULL DEFAULT 'info',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `start_at` datetime NOT NULL,
            `end_at` datetime NULL DEFAULT NULL,
            `pages` varchar(255) NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        $alerts = [];
    } else {
        $alerts = [];
        $error = "Database error occurred. Please try again later.";
    }
}

// Get alert for editing if id is provided
$edit_alert = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM alerts WHERE id = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $edit_alert = $stmt->fetch(PDO::FETCH_ASSOC);
}


?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Aviseringar - <?= htmlspecialchars($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include_once 'nav.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Manage Alert Banners</h1>
    
    <?php if (!empty($message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Alert Form -->
    <div class="bg-white shadow-md rounded p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">
            <?php echo $edit_alert ? 'Edit Alert' : 'Create New Alert'; ?>
        </h2>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="save">
            <?php if ($edit_alert): ?>
                <input type="hidden" name="alert_id" value="<?php echo $edit_alert['id']; ?>">
            <?php endif; ?>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="message">
                    Alert Message
                </label>
                <textarea 
                    id="message" 
                    name="message" 
                    rows="3" 
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    required
                ><?php echo isset($edit_alert['message']) ? htmlspecialchars($edit_alert['message']) : ''; ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="alert_type">
                    Alert Type
                </label>
                <select 
                    id="alert_type" 
                    name="alert_type" 
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                >
                    <option value="info" <?php echo (isset($edit_alert['alert_type']) && $edit_alert['alert_type'] === 'info') ? 'selected' : ''; ?>>Information</option>
                    <option value="warning" <?php echo (isset($edit_alert['alert_type']) && $edit_alert['alert_type'] === 'warning') ? 'selected' : ''; ?>>Warning</option>
                    <option value="error" <?php echo (isset($edit_alert['alert_type']) && $edit_alert['alert_type'] === 'error') ? 'selected' : ''; ?>>Error</option>
                    <option value="success" <?php echo (isset($edit_alert['alert_type']) && $edit_alert['alert_type'] === 'success') ? 'selected' : ''; ?>>Success</option>
                </select>
            </div>
            
            <div class="flex flex-wrap -mx-2">
                <div class="w-full md:w-1/2 px-2 mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="start_at">
                        Start Date & Time
                    </label>
                    <input 
                        type="datetime-local" 
                        id="start_at" 
                        name="start_at" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo isset($edit_alert['start_at']) ? date('Y-m-d\TH:i', strtotime($edit_alert['start_at'])) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="w-full md:w-1/2 px-2 mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="end_at">
                        End Date & Time (Optional)
                    </label>
                    <input 
                        type="datetime-local" 
                        id="end_at" 
                        name="end_at" 
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo isset($edit_alert['end_at']) && $edit_alert['end_at'] ? date('Y-m-d\TH:i', strtotime($edit_alert['end_at'])) : ''; ?>"
                    >
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="pages">
                    Display on Pages (Optional, comma-separated, e.g.: dashboard.php,whiteboard.php)
                </label>
                <input 
                    type="text" 
                    id="pages" 
                    name="pages" 
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    value="<?php echo isset($edit_alert['pages']) ? htmlspecialchars($edit_alert['pages']) : ''; ?>"
                    placeholder="Leave empty to show on all pages"
                >
            </div>
            
            <div class="flex items-center justify-between">
                <button 
                    type="submit" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                >
                    <?php echo $edit_alert ? 'Update Alert' : 'Create Alert'; ?>
                </button>
                
                <?php if ($edit_alert): ?>
                    <a 
                        href="alerts.php" 
                        class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800"
                    >
                        Cancel Editing
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Alerts List -->
    <div class="bg-white shadow-md rounded p-6">
        <h2 class="text-xl font-semibold mb-4">Current Alerts</h2>
        
        <?php if (count($alerts) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b text-left">Message</th>
                            <th class="py-2 px-4 border-b text-left">Type</th>
                            <th class="py-2 px-4 border-b text-left">Time Period</th>
                            <th class="py-2 px-4 border-b text-left">Status</th>
                            <th class="py-2 px-4 border-b text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                            <tr>
                                <td class="py-2 px-4 border-b">
                                    <?php echo mb_substr(htmlspecialchars($alert['message']), 0, 50) . (mb_strlen($alert['message']) > 50 ? '...' : ''); ?>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <span class="inline-block px-2 py-1 text-xs rounded 
                                        <?php echo getAlertTypeClass($alert['alert_type']); ?>">
                                        <?php echo ucfirst($alert['alert_type']); ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <?php echo date('Y-m-d H:i', strtotime($alert['start_at'])); ?>
                                    <?php if (!empty($alert['end_at'])): ?>
                                        <br>to<br>
                                        <?php echo date('Y-m-d H:i', strtotime($alert['end_at'])); ?>
                                    <?php else: ?>
                                        <br><em>No end date</em>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <?php 
                                    $current_time = time();
                                    $start_time = strtotime($alert['start_at']);
                                    $end_time = !empty($alert['end_at']) ? strtotime($alert['end_at']) : null;
                                    
                                    if (!$alert['is_active']) {
                                        echo '<span class="text-gray-500">Inactive</span>';
                                    } elseif ($current_time < $start_time) {
                                        echo '<span class="text-yellow-500">Scheduled</span>';
                                    } elseif ($end_time && $current_time > $end_time) {
                                        echo '<span class="text-gray-500">Expired</span>';
                                    } else {
                                        echo '<span class="text-green-500">Active</span>';
                                    }
                                    ?>
                                </td>
                                <td class="py-2 px-4 border-b text-center">
                                    <div class="flex justify-center space-x-2">
                                        <!-- Edit Button -->
                                        <a href="?edit=<?php echo $alert['id']; ?>" class="text-blue-500 hover:text-blue-700">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        
                                        <!-- Toggle Status Form -->
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $alert['is_active']; ?>">
                                            <button type="submit" class="<?php echo $alert['is_active'] ? 'text-green-500 hover:text-green-700' : 'text-gray-500 hover:text-gray-700'; ?>">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete Form -->
                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this alert?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No alerts have been created yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper function to get the appropriate CSS class for alert type
function getAlertTypeClass($type) {
    switch ($type) {
        case 'info':
            return 'bg-blue-100 text-blue-800';
        case 'warning':
            return 'bg-yellow-100 text-yellow-800';
        case 'error':
            return 'bg-red-100 text-red-800';
        case 'success':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

?>