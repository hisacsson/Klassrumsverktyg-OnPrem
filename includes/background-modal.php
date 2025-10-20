<!-- Background Modal -->
<div id="backgroundModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl p-6 m-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Välj bakgrund</h2>
            <button onclick="backgroundHandler.closeModal()" 
                    class="p-2 hover:bg-gray-100 rounded-full">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <div class="flex space-x-6">
                <button onclick="backgroundHandler.switchTab('images')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="images">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="image" class="h-4 w-4"></i>
                        <span>Bakgrundsbilder</span>
                    </div>
                </button>
                <button onclick="backgroundHandler.switchTab('colors')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="colors">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="palette" class="h-4 w-4"></i>
                        <span>Färger</span>
                    </div>
                </button>
                <?php if (isset($_SESSION['user_id'])): ?>
                <button onclick="backgroundHandler.switchTab('custom')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="custom">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="bookmark" class="h-4 w-4"></i>
                        <span>Mina bakgrunder</span>
                    </div>
                </button>
                <button onclick="backgroundHandler.switchTab('upload')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="upload">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        <span>Ladda upp</span>
                    </div>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Contents -->
        <div class="mt-6">
            <?php include 'background-tabs/images.php'; ?>
            <?php include 'background-tabs/colors.php'; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Custom backgrounds tab -->
                <div id="tab-custom" class="tab-content hidden">
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
                                    <!-- Make the entire image area clickable to select the background -->
                                    <div class="h-32 bg-center bg-cover cursor-pointer"
     style="background-image: url('<?= htmlspecialchars($background['image_path']) ?>')"
     onclick="selectBackground('image', '<?php echo htmlspecialchars($background['image_path']); ?>', 'Egen bild', '')">
</div>
                                    <div class="p-2">
                                        <h4 class="font-medium text-gray-800 truncate text-sm"><?= htmlspecialchars($background['name']) ?></h4>
                                        <div class="flex justify-end items-center mt-2">
                                            <!-- Only keep the delete button, since clicking the image now selects it -->
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
                </div>
                
                <?php include 'background-tabs/upload.php'; ?>
            <?php endif; ?>
        </div>

        <!-- Footer med knappar -->
        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-4">
            <button onclick="backgroundHandler.closeModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Stäng
            </button>
            <button onclick="backgroundHandler.clearBackground()" 
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Återställ bakgrund
            </button>
        </div>
    </div>
</div>
<script>
// Utöka backgroundHandler med en reloadCustomTab-funktion
if (typeof backgroundHandler !== 'undefined') {
    // Lägg till reloadCustomTab-funktionen
    backgroundHandler.reloadCustomTab = function() {
        // Eftersom custom-fliken är inbäddad i samma sida kan vi inte
        // använda fetch för att ladda om bara den delen. Istället behöver vi
        // göra en AJAX-förfrågan för att få den uppdaterade listan med bakgrunder.
        
        const customTab = document.getElementById('tab-custom');
        
        // Visa laddningsindikator
        const loadingMessage = document.createElement('div');
        loadingMessage.className = 'text-center py-8';
        loadingMessage.innerHTML = '<p class="text-gray-500">Laddar...</p>';
        
        // Spara bara den ursprungliga rubriken och ersätt resten med laddningsmeddelande
        const heading = customTab.querySelector('h3');
        customTab.innerHTML = '';
        customTab.appendChild(heading);
        customTab.appendChild(loadingMessage);
        
        // Gör en AJAX-förfrågan för att få de uppdaterade användarens bakgrunder
        fetch('/api/get-user-backgrounds.php', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            // Ta bort laddningsmeddelandet
            loadingMessage.remove();
            
            if (data.success && Array.isArray(data.backgrounds)) {
                const backgrounds = data.backgrounds;
                
                if (backgrounds.length === 0) {
                    // Inga bakgrunder - visa tomt meddelande
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'text-center py-8';
                    emptyMessage.innerHTML = `
                        <p class="text-gray-500">Du har inga uppladdade bakgrunder ännu.</p>
                        <p class="text-sm text-gray-500 mt-2">Gå till "Ladda upp"-fliken för att lägga till egna bakgrunder.</p>
                    `;
                    customTab.appendChild(emptyMessage);
                } else {
                    // Skapa grid för bakgrunder
                    const grid = document.createElement('div');
                    grid.className = 'grid grid-cols-2 md:grid-cols-3 gap-4';
                    
                    // Lägg till varje bakgrund i grid
                    backgrounds.forEach(bg => {
                        grid.innerHTML += `
                            <div class="bg-white border rounded-lg overflow-hidden">
                                <div class="h-32 bg-center bg-cover cursor-pointer"
                                     style="background-image: url('${bg.image_path}')"
                                     onclick="selectBackground('image', '${bg.image_path}', 'Egen bild', '')">
                                </div>
                                <div class="p-2">
                                    <h4 class="font-medium text-gray-800 truncate text-sm">${bg.name}</h4>
                                    <div class="flex justify-end items-center mt-2">
                                        <button onclick="deleteCustomBackground(${bg.id})"
                                                class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                            Ta bort
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    customTab.appendChild(grid);
                    
                    // Lägg till meddelande om antal tillgängliga uppladdningar
                    if (backgrounds.length < 3) {
                        const quotaMessage = document.createElement('div');
                        quotaMessage.className = 'mt-6 text-sm text-gray-600';
                        quotaMessage.innerHTML = `
                            <p>Du kan ladda upp ${3 - backgrounds.length} fler bakgrunder. Gå till "Ladda upp"-fliken för att lägga till fler.</p>
                        `;
                        customTab.appendChild(quotaMessage);
                    }
                }
            } else {
                // Visa felmeddelande
                const errorMessage = document.createElement('div');
                errorMessage.className = 'text-center py-8';
                errorMessage.innerHTML = '<p class="text-red-500">Ett fel uppstod när bakgrundslistan skulle laddas.</p>';
                customTab.appendChild(errorMessage);
            }
        })
        .catch(error => {
            console.error('Fel vid omladdning av custom-fliken:', error);
            
            // Visa felmeddelande
            loadingMessage.innerHTML = '<p class="text-red-500">Ett fel uppstod när bakgrundslistan skulle laddas.</p>';
        });
    };
}

// Ersätt den befintliga deleteCustomBackground-funktionen
function deleteCustomBackground(backgroundId) {
  showConfirmDialog('Är du säker på att du vill ta bort denna bakgrund?', 
    // On confirm (yes)
    function() {
      fetch('/api/user-backgrounds.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=delete&background_id=${encodeURIComponent(backgroundId)}`
      })
      .then(response => {
        // Kontrollera om svaret är JSON innan vi försöker tolka det
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
          return response.json();
        } else {
          console.warn("Servern returnerade inte JSON. Uppdaterar fliken ändå.");
          // Även om svaret inte är JSON, antar vi att operationen lyckades
          if (window.showToast) {
            showToast('Bakgrunden har tagits bort', 'success');
          }
          
          // Uppdatera fliken ändå
          if (backgroundHandler && backgroundHandler.reloadCustomTab) {
            backgroundHandler.reloadCustomTab();
          }
          
          // Kasta ett fel för att skippa resten av then-kedjan
          throw new Error("Servern returnerade inte JSON");
        }
      })
      .then(data => {
        if (data.success) {
          if (window.showToast) {
            showToast(data.toast.message, data.toast.type);
          }
          
          // Använd den nya funktionen för att ladda om fliken
          if (backgroundHandler && backgroundHandler.reloadCustomTab) {
            backgroundHandler.reloadCustomTab();
          } else {
            // Fallback till det ursprungliga
            backgroundHandler.switchTab('custom');
          }
        } else {
          if (window.showToast) {
            showToast(data.toast ? data.toast.message : 'Ett fel uppstod när bakgrunden skulle tas bort', 'error');
          }
        }
      })
      .catch(error => {
        console.error('Error deleting background:', error);
        
        // Om vi redan hanterade felet (servern returnerade inte JSON), gör inget mer
        if (error.message === "Servern returnerade inte JSON") {
          return;
        }
        
        // Annars visa ett felmeddelande
        if (window.showToast) {
          showToast('Ett fel uppstod när bakgrunden skulle tas bort', 'error');
        }
        
        // Försök ändå uppdatera fliken efter en kort fördröjning
        setTimeout(() => {
          if (backgroundHandler && backgroundHandler.reloadCustomTab) {
            backgroundHandler.reloadCustomTab();
          }
        }, 1000);
      });
    },
    // On cancel (no) - do nothing
    function() {}
  );
}
</script>