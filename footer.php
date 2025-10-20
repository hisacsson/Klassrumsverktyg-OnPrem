<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$siteName = 'Klassrumsverktyg';
try {
    require_once __DIR__ . '/src/Config/Database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    if (!function_exists('kv_get_setting')) {
        function kv_get_setting(PDO $pdo, string $key, $default = null) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['setting_value'] ?? $default;
        }
    }
    $name = (string) kv_get_setting($pdo, 'site_name', '');
    if ($name !== '') { $siteName = $name; }
} catch (Throwable $e) {
    // fallback
}
?>
<!-- Footer -->
<footer class="bg-gray-800 text-white">
    <div class="container mx-auto px-6 py-10">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Column 1: Company Mission -->
            <div>
                <h4 class="text-lg font-semibold text-white mb-4">Om <?= htmlspecialchars($siteName) ?></h4>
                <p class="text-gray-400 text-sm">
                    <?= htmlspecialchars($siteName) ?> är en plattform designad för att underlätta samarbete mellan lärare och elever. Vårt mål är att göra digitala verktyg tillgängliga för alla.
                </p>
            </div>
            <!-- Column 2: Links -->
            <div>
                <h4 class="text-lg font-semibold text-white mb-4">Snabblänkar</h4>
                <ul class="space-y-2">
                    <li><a href="/static/about.php" class="text-gray-400 hover:text-white text-sm">Om sidan</a></li>
                    <li><a href="/static/features.php" class="text-gray-400 hover:text-white text-sm">Funktioner</a></li>
                    <li><a href="/register.php" class="text-gray-400 hover:text-white text-sm">Skapa konto</a></li>
                    <li><a href="/static/contact.php" class="text-gray-400 hover:text-white text-sm">Kontakt</a></li>
                </ul>
            </div>
            <!-- Column 3: Resources -->
            <div>
                <h4 class="text-lg font-semibold text-white mb-4">Resurser</h4>
                <ul class="space-y-2">
                    <li><a href="/static/help.php" class="text-gray-400 hover:text-white text-sm">Hjälpcenter</a></li>
                    <li><a href="/static/privacy.php" class="text-gray-400 hover:text-white text-sm">Integritetspolicy</a></li>
                    <li><a href="/static/terms.php" class="text-gray-400 hover:text-white text-sm">Användarvillkor</a></li>
                </ul>
            </div>
            
        <div class="text-center text-gray-400 text-sm mt-8">
            <p>&copy; <?php echo date('Y'); ?> <?= htmlspecialchars($siteName) ?>. Alla rättigheter förbehållna.</p>
        </div>
    </div>
</footer>