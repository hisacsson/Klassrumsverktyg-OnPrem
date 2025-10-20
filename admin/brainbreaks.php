<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
require_once 'AdminController.php';
require_once 'BrainBreaksAdminController.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$admin = new AdminController($db);
$brainBreaksController = new BrainBreaksAdminController($db);

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $brainBreaksController->deleteBrainBreak($id);
    
    // Redirect to refresh the page and avoid form resubmission
    header('Location: /admin/brainbreaks.php?deleted=true');
    exit;
}

// Get filters from query parameters
$categoryFilter = $_GET['category'] ?? null;
$userFilter = $_GET['user_id'] ?? null;
$publicFilter = isset($_GET['public']) ? ($_GET['public'] === '1' ? 1 : 0) : null;

// Get data
$brainBreaks = $brainBreaksController->getAllBrainBreaks($categoryFilter, $userFilter, $publicFilter);
$categories = $brainBreaksController->getAllCategories();
$users = $brainBreaksController->getUsersWithBrainBreaks();
// Läs instansens namn från system_settings (fallback: "Klassrumsverktyg")
if (!function_exists('kv_get_setting')) {
    function kv_get_setting(PDO $pdo, string $key, $default = null) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['setting_value'] ?? $default;
    }
}
$siteName = (string) kv_get_setting($db, 'site_name', 'Klassrumsverktyg');
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Brain Breaks - <?= htmlspecialchars($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include_once 'nav.php'; ?>

    <!-- Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Brain Breaks</h1>
            <a href="/admin/add-brainbreak.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                Lägg till ny Brain Break
            </a>
        </div>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'true'): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Brain break har tagits bort.
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Filtrera</h2>
            
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                    <select id="category" name="category" class="w-full border border-gray-300 rounded-md py-2 px-3">
                        <option value="">Alla kategorier</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Användare</label>
                    <select id="user_id" name="user_id" class="w-full border border-gray-300 rounded-md py-2 px-3">
                        <option value="">Alla användare</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $userFilter == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="public" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="public" name="public" class="w-full border border-gray-300 rounded-md py-2 px-3">
                        <option value="">Alla</option>
                        <option value="1" <?= $publicFilter === 1 ? 'selected' : '' ?>>Publik</option>
                        <option value="0" <?= $publicFilter === 0 ? 'selected' : '' ?>>Privat</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        Filtrera
                    </button>
                    <a href="/admin/brainbreaks.php" class="ml-2 text-gray-600 hover:text-gray-900 px-4 py-2">
                        Återställ
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Brain Breaks Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titel</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Användare</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Längd</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skapad</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Åtgärder</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($brainBreaks)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            Inga brain breaks hittades med de valda filtren.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($brainBreaks as $break): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($break['title']) ?></div>
                                <?php if (!empty($break['youtube_id'])): ?>
                                <div class="text-xs text-gray-500">YouTube: <?= htmlspecialchars($break['youtube_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($break['category']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($break['first_name'] . ' ' . $break['last_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($break['username']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($break['duration']): ?>
                                    <?php 
                                    $minutes = floor($break['duration'] / 60);
                                    $seconds = $break['duration'] % 60;
                                    echo $minutes ? $minutes . ' min ' : '';
                                    echo $seconds ? $seconds . ' sek' : ($minutes ? '' : 'N/A');
                                    ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($break['is_public']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Publik
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Privat
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('Y-m-d H:i', strtotime($break['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if (!empty($break['youtube_id'])): ?>
                                <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($break['youtube_id']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-2">
                                    Visa
                                </a>
                                <?php endif; ?>
                                
                                <form method="post" class="inline-block" onsubmit="return confirm('Är du säker på att du vill ta bort denna brain break?');">
                                    <input type="hidden" name="id" value="<?= $break['id'] ?>">
                                    <button type="submit" name="delete" class="text-red-600 hover:text-red-900">Ta bort</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>