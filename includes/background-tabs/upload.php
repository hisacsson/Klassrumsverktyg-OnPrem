<!-- Upload Tab Content -->
<div id="tab-upload" class="tab-content hidden">
    <?php
    // Check how many backgrounds the user has uploaded
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_backgrounds WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $backgroundCount = $stmt->fetchColumn();
    $canUpload = $backgroundCount < 3;
    ?>
    
    <h3 class="text-lg font-medium mb-2">Ladda upp bakgrund</h3>
    
    <?php if ($canUpload): ?>
        <p class="text-sm text-gray-600 mb-4">
            Du kan ladda upp upp till 3 egna bakgrundsbilder. 
            Du har redan laddat upp <?= $backgroundCount ?> av 3 tillåtna bakgrunder.
        </p>
        
        <form id="customBackgroundForm" class="space-y-4">
            <input type="hidden" name="action" value="upload">
            <div>
                <label for="background_name" class="block text-sm font-medium text-gray-700">Namn på bakgrund</label>
                <input type="text" id="background_name" name="background_name"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"
                       placeholder="T.ex. 'Blå himmel'" maxlength="100" required>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Välj bild</label>
                <div class="mt-1 border-2 border-gray-300 border-dashed rounded-lg p-4 text-center relative" id="upload-container">
                    <input type="file" name="background_image" id="background_image_upload" required accept="image/jpeg,image/png,image/gif,image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <svg class="mx-auto h-12 w-12" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4h-12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" 
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <p class="mt-2 text-sm text-gray-600" id="file-text">Klicka för att välja bild</p>
                    <p class="text-xs text-gray-500">(Max 3MB, JPEG, PNG, GIF eller WEBP)</p>
                    
                    <!-- Preview container for selected images -->
                    <div id="preview-container" class="hidden mt-4">
                        <div id="image-preview" class="w-32 h-32 mx-auto bg-cover bg-center rounded-md border"></div>
                        <p id="selected-filename" class="mt-2 text-sm font-medium text-blue-600"></p>
                    </div>
                </div>
            </div>
            
            <div id="uploadError" class="hidden text-red-500 text-sm mt-2"></div>
            <div id="uploadSuccess" class="hidden text-green-500 text-sm mt-2"></div>
            
            <div class="flex justify-end items-center">
                <div id="uploadSpinner" class="hidden mr-3">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <button type="button" id="uploadButton"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Ladda upp
                </button>
            </div>
        </form>

        <script>
            // Enhanced file upload handling with AJAX upload
            (function() {
                const fileInput = document.getElementById('background_image_upload');
                const fileText = document.getElementById('file-text');
                const previewContainer = document.getElementById('preview-container');
                const imagePreview = document.getElementById('image-preview');
                const selectedFilename = document.getElementById('selected-filename');
                const uploadContainer = document.getElementById('upload-container');
                const uploadError = document.getElementById('uploadError');
                const uploadSuccess = document.getElementById('uploadSuccess');
                const uploadButton = document.getElementById('uploadButton');
                const uploadSpinner = document.getElementById('uploadSpinner');
                
                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const file = this.files[0];
                            
                            // Validate file size (max 3MB)
                            if (file.size > 3 * 1024 * 1024) {
                                uploadError.textContent = 'Filen är för stor. Maximal filstorlek är 3MB.';
                                uploadError.classList.remove('hidden');
                                uploadSuccess.classList.add('hidden');
                                this.value = '';
                                return;
                            }
                            
                            // Display filename
                            fileText.textContent = 'Fil vald';
                            fileText.classList.add('text-blue-600', 'font-medium');
                            selectedFilename.textContent = file.name;
                            
                            // Show preview container
                            previewContainer.classList.remove('hidden');
                            
                            // Update upload container style
                            uploadContainer.classList.add('border-blue-400', 'bg-blue-50');
                            
                            // Hide error message if it was shown
                            uploadError.classList.add('hidden');
                            uploadSuccess.classList.add('hidden');
                            
                            // Read file and display preview
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                imagePreview.style.backgroundImage = `url('${e.target.result}')`;
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Reset to original state
                            fileText.textContent = 'Klicka för att välja bild';
                            fileText.classList.remove('text-blue-600', 'font-medium');
                            previewContainer.classList.add('hidden');
                            uploadContainer.classList.remove('border-blue-400', 'bg-blue-50');
                        }
                    });
                }
                
                // Upload with AJAX
                uploadButton.addEventListener('click', function() {
                    const nameInput = document.getElementById('background_name');
                    
                    // Validate form
                    if (!nameInput.value.trim()) {
                        uploadError.textContent = 'Vänligen ange ett namn för bakgrunden.';
                        uploadError.classList.remove('hidden');
                        uploadSuccess.classList.add('hidden');
                        return;
                    }
                    
                    if (!fileInput.files || !fileInput.files[0]) {
                        uploadError.textContent = 'Vänligen välj en bild att ladda upp.';
                        uploadError.classList.remove('hidden');
                        uploadSuccess.classList.add('hidden');
                        return;
                    }
                    
                    // Create FormData object
                    const formData = new FormData();
                    formData.append('action', 'upload');
                    formData.append('background_name', nameInput.value.trim());
                    formData.append('background_image', fileInput.files[0]);
                    
                    // Show loading spinner and disable button
                    uploadSpinner.classList.remove('hidden');
                    uploadButton.disabled = true;
                    uploadButton.classList.add('opacity-75');
                    
                    // Send AJAX request
fetch('/api/user-backgrounds.php', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
})
.then(response => response.json())
.then(data => {
    // Hide spinner
    uploadSpinner.classList.add('hidden');
    uploadButton.disabled = false;
    uploadButton.classList.remove('opacity-75');
    
    if (data.success) {
        // Show success message
        uploadSuccess.textContent = data.toast.message || 'Bakgrunden har laddats upp framgångsrikt.';
        uploadSuccess.classList.remove('hidden');
        uploadError.classList.add('hidden');
        
        // Clear form
        nameInput.value = '';
        fileInput.value = '';
        fileText.textContent = 'Klicka för att välja bild';
        fileText.classList.remove('text-blue-600', 'font-medium');
        previewContainer.classList.add('hidden');
        uploadContainer.classList.remove('border-blue-400', 'bg-blue-50');
        
        // Show toast notification if available
        if (typeof showToast === 'function') {
            showToast(data.toast.message, data.toast.type);
        }
        
        // Uppdatera custom-fliken och byt till den om backgroundHandler finns
        if (typeof backgroundHandler !== 'undefined' && backgroundHandler.reloadCustomTab) {
            backgroundHandler.reloadCustomTab();
            backgroundHandler.switchTab('custom');
        }
        
        // If we're on the dashboard, refresh the backgrounds list
        if (typeof updateBackgroundsList === 'function') {
            updateBackgroundsList();
        }
    } else {
        // Show error message
        uploadError.textContent = data.toast.message || 'Något gick fel vid uppladdningen.';
        uploadError.classList.remove('hidden');
        uploadSuccess.classList.add('hidden');
        
        // Show toast notification if available
        if (typeof showToast === 'function') {
            showToast(data.toast.message, data.toast.type);
        }
    }
})
.catch(error => {
    // Hide spinner
    uploadSpinner.classList.add('hidden');
    uploadButton.disabled = false;
    uploadButton.classList.remove('opacity-75');
    
    // Show error message
    uploadError.textContent = 'Ett fel uppstod vid kommunikation med servern.';
    uploadError.classList.remove('hidden');
    uploadSuccess.classList.add('hidden');
    
    console.error('Upload error:', error);
});
                });
            })();
        </script>
    <?php else: ?>
        <div class="bg-yellow-50 text-yellow-800 p-4 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Maxgräns uppnådd</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Du har redan laddat upp maximalt antal bakgrundsbilder (3). För att ladda upp en ny bild, ta först bort en av dina befintliga bakgrunder i fliken "Mina bakgrunder".</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>