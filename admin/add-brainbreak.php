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

// Get all distinct categories to use in the dropdown
$categories = $brainBreaksController->getAllCategories();

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        if (empty($_POST['title'])) {
            throw new Exception('Titel är obligatorisk');
        }
        
        if (empty($_POST['category'])) {
            throw new Exception('Kategori är obligatorisk');
        }
        
        // Check if it's a YouTube video
        if (!empty($_POST['youtube_id'])) {
            // If duration is not set, try to auto-calculate it (in a real implementation)
            // For now, we'll just use the provided duration
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
        } else {
            // For text-based brain breaks
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
        }
        
        $data = [
            'title' => $_POST['title'],
            'category' => $_POST['category'],
            'youtube_id' => $_POST['youtube_id'] ?? null,
            'text_content' => $_POST['text_content'] ?? null,
            'duration' => $duration,
            'is_public' => true // Admin-created brain breaks are always public
        ];
        
        $brainBreaksController->addBrainBreak($data, $_SESSION['user_id']);
        $message = 'Brain Break har lagts till!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Lägg till Brain Break - <?= htmlspecialchars($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include_once 'nav.php'; ?>

    <!-- Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Lägg till ny Brain Break</h1>
            <a href="/admin/brainbreaks.php" class="text-blue-600 hover:text-blue-800">
                &larr; Tillbaka till alla Brain Breaks
            </a>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6">
            <form method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Titel *</label>
                        <input type="text" id="title" name="title" required class="w-full border border-gray-300 rounded-md py-2 px-3">
                    </div>
                    
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
                        <select id="category" name="category" required class="w-full border border-gray-300 rounded-md py-2 px-3">
                            <option value="">Välj kategori</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                            <option value="new">Lägg till ny kategori...</option>
                        </select>
                    </div>
                    
                    <div id="newCategoryContainer" class="hidden">
                        <label for="new_category" class="block text-sm font-medium text-gray-700 mb-1">Ny kategori *</label>
                        <input type="text" id="new_category" name="new_category" class="w-full border border-gray-300 rounded-md py-2 px-3">
                    </div>
                    
                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700 mb-1">Längd (sekunder)</label>
                        <input type="number" id="duration" name="duration" min="1" class="w-full border border-gray-300 rounded-md py-2 px-3">
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="brainbreak_type" class="block text-sm font-medium text-gray-700 mb-1">Typ av Brain Break</label>
                    <div class="flex space-x-4">
                        <div class="flex items-center">
                            <input type="radio" id="type_youtube" name="brainbreak_type" value="youtube" class="mr-2" checked>
                            <label for="type_youtube">YouTube-video</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="type_text" name="brainbreak_type" value="text" class="mr-2">
                            <label for="type_text">Text-baserad</label>
                        </div>
                    </div>
                </div>
                
                <div id="youtube_container" class="mt-4">
                    <label for="youtube_id" class="block text-sm font-medium text-gray-700 mb-1">YouTube Video ID</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <input type="text" id="youtube_id" name="youtube_id" placeholder="t.ex. dQw4w9WgXcQ" class="w-full border border-gray-300 rounded-md py-2 px-3">
                            <p class="text-xs text-gray-500 mt-1">
                                YouTube ID är delen av YouTube-URL efter "v=". T.ex. i https://www.youtube.com/watch?v=dQw4w9WgXcQ är ID: dQw4w9WgXcQ
                            </p>
                        </div>
                        <div>
                            <button type="button" id="preview_button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded w-full">
                                Förhandsgranska
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="text_container" class="mt-4 hidden">
                    <label for="text_content" class="block text-sm font-medium text-gray-700 mb-1">Text för Brain Break</label>
                    <textarea id="text_content" name="text_content" rows="5" class="w-full border border-gray-300 rounded-md py-2 px-3"></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        Skriv in instruktioner eller övningar för denna Brain Break.
                    </p>
                </div>
                
                <div id="youtube_preview" class="mt-6 hidden">
                    <h3 class="font-medium text-gray-900 mb-2">Förhandsgranskning</h3>
                    <div class="aspect-w-16 aspect-h-9">
                        <div id="youtube_embed" class="border"></div>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <a href="/admin/brainbreaks.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded mr-2">
                        Avbryt
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                        Lägg till Brain Break
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Handle toggling between YouTube and text-based brain breaks
        const typeYoutube = document.getElementById('type_youtube');
        const typeText = document.getElementById('type_text');
        const youtubeContainer = document.getElementById('youtube_container');
        const textContainer = document.getElementById('text_container');
        
        typeYoutube.addEventListener('change', function() {
            if (this.checked) {
                youtubeContainer.classList.remove('hidden');
                textContainer.classList.add('hidden');
            }
        });
        
        typeText.addEventListener('change', function() {
            if (this.checked) {
                youtubeContainer.classList.add('hidden');
                textContainer.classList.remove('hidden');
                document.getElementById('youtube_preview').classList.add('hidden');
            }
        });
        
        // Handle category dropdown
        const categorySelect = document.getElementById('category');
        const newCategoryContainer = document.getElementById('newCategoryContainer');
        
        categorySelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newCategoryContainer.classList.remove('hidden');
                document.getElementById('new_category').setAttribute('required', 'required');
            } else {
                newCategoryContainer.classList.add('hidden');
                document.getElementById('new_category').removeAttribute('required');
            }
        });
        
        // YouTube preview functionality
        const previewButton = document.getElementById('preview_button');
        const youtubePreview = document.getElementById('youtube_preview');
        const youtubeEmbed = document.getElementById('youtube_embed');
        
        previewButton.addEventListener('click', function() {
            const youtubeId = document.getElementById('youtube_id').value.trim();
            
            if (youtubeId) {
                youtubePreview.classList.remove('hidden');
                youtubeEmbed.innerHTML = `<iframe width="100%" height="315" src="https://www.youtube.com/embed/${youtubeId}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            } else {
                alert('Vänligen ange ett YouTube ID först');
            }
        });
        
        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const brainbreakType = document.querySelector('input[name="brainbreak_type"]:checked').value;
            
            if (brainbreakType === 'youtube' && !document.getElementById('youtube_id').value.trim()) {
                e.preventDefault();
                alert('Vänligen ange ett YouTube ID för video-baserade Brain Breaks');
                return;
            }
            
            if (brainbreakType === 'text' && !document.getElementById('text_content').value.trim()) {
                e.preventDefault();
                alert('Vänligen ange text-innehåll för text-baserade Brain Breaks');
                return;
            }
            
            if (categorySelect.value === 'new' && !document.getElementById('new_category').value.trim()) {
                e.preventDefault();
                alert('Vänligen ange ett namn för den nya kategorin');
                return;
            }
            
            // Add new category to form data
            if (categorySelect.value === 'new') {
                const newCategoryValue = document.getElementById('new_category').value.trim();
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = 'new_category';
                hiddenField.value = newCategoryValue;
                this.appendChild(hiddenField);
            }
        });
    </script>
</body>
</html>