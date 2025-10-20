<?php
// This file should be saved as background-tabs/custom.php

// If directly accessed, load necessary files
if (!isset($pdo)) {
    session_start();
    require_once '../../private/src/Config/Database.php';
    $database = new Database();
    $pdo = $database->getConnection();
}

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="py-8 text-center text-gray-500">Du måste vara inloggad för att se dina bakgrunder.</div>';
    exit;
}

// Get whiteboard ID from request if not already set
if (!isset($whiteboardId) && isset($_GET['whiteboard_id'])) {
    $whiteboardId = intval($_GET['whiteboard_id']);
}
?>

<h3 class="text-lg font-medium mb-4">Mina uppladdade bakgrunder</h3>

<?php
// Get user's custom backgrounds
$stmt = $pdo->prepare("SELECT id, name, image_path FROM user_backgrounds WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userBackgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>



<?php if (empty($userBackgrounds)): ?>
    <div class="text-center py-8">
        <p class="text-gray-500">Du har inga uppladdade bakgrunder ännu.</p>
        <p class="text-sm text-gray-500 mt-2">Gå till "Ladda upp"-fliken för att lägga till egna bakgrunder.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <?php foreach ($userBackgrounds as $background): ?>
            <div class="bg-white border rounded-lg overflow-hidden">
            <div class="h-32 bg-center bg-cover cursor-pointer"
     style="background-image: url('<?= htmlspecialchars($background['image_path']) ?>')"
     onclick="selectBackground('image', '<?php echo htmlspecialchars($background['image_path']); ?>', 'Egen uppladdning', '')">
</div>
                <div class="p-2">
                    <h4 class="font-medium text-gray-800 truncate text-sm"><?= htmlspecialchars($background['name']) ?></h4>
                    <div class="flex justify-between items-center mt-2">
                    <button onclick="selectCustomBackground('<?= $background['id'] ?>', '<?= htmlspecialchars($background['image_path']) ?>')"
        class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
    Använd
</button>
<button onclick="deleteCustomBackground(<?= $background['id'] ?>)" 
        class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
    Ta bort
</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($userBackgrounds) < 3): ?>
        <div class="mt-6 text-sm text-gray-600">
            <p>Du kan ladda upp <?= 3 - count($userBackgrounds) ?> fler bakgrunder. Gå till "Ladda upp"-fliken för att lägga till fler.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>
<script>
  function selectCustomBackground(backgroundId, imageUrl) {
    console.log('Vald egenuppladdad bakgrund:', backgroundId, imageUrl);

    document.body.style.backgroundImage = `url('${imageUrl}')`;
    document.body.style.backgroundColor = 'transparent';
    document.body.style.backgroundSize = 'cover';
    document.body.style.backgroundPosition = 'center';
    document.body.style.backgroundRepeat = 'no-repeat';

    // Gör en AJAX-förfrågan för att spara bakgrunden på servern
    fetch('/api/update-background.php', { // Använder samma API som BackgroundHandler
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        whiteboard_id: <?php echo json_encode($whiteboardId); ?>, // Se till att $whiteboardId är tillgängligt här
        type: 'custom',
        value: backgroundId // Använder bakgrunds-ID:t som värde
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (window.showToast) {
          showToast('Bakgrunden har uppdaterats', 'success');
        }
        // Stäng modalen om den är öppen (förutsätter att backgroundHandler.closeModal() finns globalt eller kan anropas)
        if (typeof backgroundHandler !== 'undefined' && backgroundHandler.closeModal) {
          backgroundHandler.closeModal();
        } else {
          // Om backgroundHandler inte finns, kanske du behöver stänga modalen på ett annat sätt
          const backgroundModal = document.getElementById('backgroundModal');
          if (backgroundModal) {
            backgroundModal.classList.add('hidden');
          }
        }
      } else if (data.toast) {
        if (window.showToast) {
          showToast(data.toast.message, data.toast.type);
        }
      } else if (data.error) {
        if (window.showToast) {
          showToast(data.error, 'error');
        }
      }
    })
    .catch(error => {
      console.error('Fel vid uppdatering av bakgrund:', error);
      if (window.showToast) {
        showToast('Ett fel uppstod när bakgrunden skulle uppdateras', 'error');
      }
    });
  }
</script>

