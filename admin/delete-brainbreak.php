<?php
session_start();
require_once __DIR__ . '/../src/Config/Database.php';
require_once 'BrainBreaksAdminController.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Check if an ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /admin/brainbreaks.php?error=invalid_id');
    exit;
}

$id = (int)$_GET['id'];

$database = new Database();
$db = $database->getConnection();
$brainBreaksController = new BrainBreaksAdminController($db);

// Get the brain break to confirm it exists
$brainBreak = $brainBreaksController->getBrainBreakById($id);

if (!$brainBreak) {
    header('Location: /admin/brainbreaks.php?error=not_found');
    exit;
}

// If form was submitted and confirmed, delete the brain break
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    $brainBreaksController->deleteBrainBreak($id);
    header('Location: /admin/brainbreaks.php?deleted=true');
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ta bort Brain Break</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include_once 'nav.php'; ?>

    <!-- Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow p-6">
            <div class="text-center mb-6">
                <svg class="h-12 w-12 text-red-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <h2 class="text-xl font-bold text-gray-900 mt-4">Bekräfta borttagning</h2>
            </div>
            
            <p class="text-gray-700 mb-6">
                Är du säker på att du vill ta bort Brain Break "<span class="font-medium"><?= htmlspecialchars($brainBreak['title']) ?></span>"? 
                Denna åtgärd kan inte ångras.
            </p>
            
            <div class="flex justify-between">
                <a href="/admin/brainbreaks.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded">
                    Avbryt
                </a>
                
                <form method="post">
                    <input type="hidden" name="confirm" value="yes">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded">
                        Ta bort
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>