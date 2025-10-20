class BackgroundHandler {
    constructor(whiteboardId) {
        this.whiteboardId = whiteboardId;
        this.initializeEventListeners();
        
        // Logga för debugging
        console.log('BackgroundHandler initialized with whiteboard ID:', whiteboardId);
    }

    initializeEventListeners() {
        // Lägg till event listeners för filuppladdning
        const uploadInput = document.getElementById('backgroundUpload');
        if (uploadInput) {
            uploadInput.addEventListener('change', (e) => this.handleFileUpload(e));
        }

        // Lägg till event listener för opacity slider
        const opacitySlider = document.getElementById('colorOpacity');
        if (opacitySlider) {
            opacitySlider.addEventListener('input', (e) => this.updateOpacity(e.target.value));
        }
        
        // Event listener för custom background upload
        const customBackgroundUpload = document.getElementById('customBackgroundUpload');
        if (customBackgroundUpload) {
            customBackgroundUpload.addEventListener('change', (e) => this.handleCustomBackgroundUpload(e));
        }
        
        // Event listener för bakgrundsnamnfält
        const customBackgroundName = document.getElementById('customBackgroundName');
        if (customBackgroundName) {
            customBackgroundName.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    document.getElementById('customBackgroundUpload').click();
                }
            });
        }
    }

    openModal() {
        document.getElementById('backgroundModal').classList.remove('hidden');
        this.switchTab('images');
    }

    closeModal() {
        document.getElementById('backgroundModal').classList.add('hidden');
    }

    switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        document.getElementById('tab-' + tabName).classList.remove('hidden');
        
        document.querySelectorAll('.tab-button').forEach(button => {
            if (button.dataset.tab === tabName) {
                button.classList.add('border-blue-500', 'text-blue-600');
            } else {
                button.classList.remove('border-blue-500', 'text-blue-600');
            }
        });
    }

    async selectBackground(type, value) {
        console.log('selectBackground called with type:', type, 'value:', value);
        
        try {
            const response = await fetch('/api/update-background.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    whiteboard_id: this.whiteboardId,
                    type: type,
                    value: value
                })
            });

            const data = await response.json();
            console.log('Server response:', data);
            
            if (data.success) {
                // For custom type, use the image_path from response if available
                if (type === 'custom' && data.image_path) {
                    this.applyBackground('image', data.image_path);
                } else {
                    this.applyBackground(type, value);
                }
                
                this.closeModal();
                
                // Visa toast notification
                if (window.showToast) {
                    showToast('Bakgrunden har uppdaterats', 'success');
                }
                
                return data;
            } else if (data.toast) {
                // Visa error toast
                if (window.showToast) {
                    showToast(data.toast.message, data.toast.type);
                }
                return null;
            } else if (data.error) {
                // Handle error case
                if (window.showToast) {
                    showToast(data.error, 'error');
                }
                return null;
            }
        } catch (error) {
            console.error('Error updating background:', error);
            if (window.showToast) {
                showToast('Ett fel uppstod när bakgrunden skulle uppdateras', 'error');
            }
            throw error;
        }
    }

    // Improved method to select user background - immediately applies the image path locally
    // while also sending the custom type to the server
    selectUserBackground(backgroundId, imagePath) {
        console.log('selectUserBackground called with:', backgroundId, imagePath);
        
        // Ensure we have valid parameters
        if (!backgroundId || !imagePath) {
            console.error('Invalid parameters for selectUserBackground');
            if (window.showToast) {
                showToast('Ogiltiga parametrar för bakgrund', 'error');
            }
            return;
        }
        
        // Apply background immediately
        this.applyDirectBackground(imagePath);
        
        // Then update on server
        this.updateBackgroundOnServer('image', imagePath)
            .catch(error => {
                console.error('Error updating background on server:', error);
            });
    }
    
    // New method to directly apply background without going through selectBackground
    applyDirectBackground(imagePath) {
        console.log('Applying direct background:', imagePath);
        document.body.style.backgroundImage = `url('${imagePath}')`;
        document.body.style.backgroundSize = 'cover';
        document.body.style.backgroundPosition = 'center';
        this.closeModal();
        
        if (window.showToast) {
            showToast('Bakgrunden har ändrats', 'success');
        }
    }
    
    // New method to update background on server
    async updateBackgroundOnServer(type, value) {
        console.log('Updating background on server:', type, value);
        try {
            const response = await fetch('/api/update-background.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    whiteboard_id: this.whiteboardId,
                    type: type,
                    value: value
                })
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Server error when updating background:', error);
            throw error;
        }
    }

    selectCustomColor() {
        const color = document.getElementById('customColor').value;
        const opacity = document.getElementById('colorOpacity').value;
        const rgba = this.hexToRgba(color, opacity / 100);
        this.selectBackground('color', rgba);
    }

    applyBackground(type, value) {
        console.log('applyBackground called with type:', type, 'value:', value);
        
        switch(type) {
            case 'color':
                document.body.style.backgroundImage = 'none';
                document.body.style.backgroundColor = value;
                break;
            case 'gradient':
                document.body.style.backgroundImage = value;
                document.body.style.backgroundColor = 'transparent';
                break;
            case 'image':
            case 'custom':
                document.body.style.backgroundImage = `url(${value})`;
                document.body.style.backgroundColor = 'transparent';
                document.body.style.backgroundSize = 'cover';
                document.body.style.backgroundPosition = 'center';
                break;
            default:
                console.error('Invalid background type:', type);
        }
    }

    updateOpacity(value) {
        const opacityValueElement = document.getElementById('opacityValue');
        opacityValueElement.textContent = `${value}%`;
        
        // Uppdatera direkt preview av färgen med opacity
        const colorInput = document.getElementById('customColor');
        const color = colorInput.value;
        const rgba = this.hexToRgba(color, value / 100);
        
        // Uppdatera preview istället för att applicera direkt
        const colorPreview = document.getElementById('colorPreview');
        if (colorPreview) {
            colorPreview.style.backgroundColor = rgba;
        }
    }

    hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    clearBackground() {
        this.selectBackground('color', '#ffffff'); // Återställ till vit bakgrund
    }

    async handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!this.validateFile(file)) {
            return;
        }

        const formData = new FormData();
        formData.append('background', file);

        try {
            const response = await fetch('/api/upload-background.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                this.selectBackground('image', data.url);
                
                if (window.showToast) {
                    showToast('Bakgrunden har laddats upp', 'success');
                }
            } else {
                const errorMessage = data.toast ? data.toast.message : (data.error || 'Ett fel uppstod vid uppladdningen');
                this.showUploadError(errorMessage);
                
                if (window.showToast) {
                    showToast(errorMessage, 'error');
                }
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            this.showUploadError('Ett fel uppstod vid uppladdningen');
            
            if (window.showToast) {
                showToast('Ett fel uppstod vid uppladdningen', 'error');
            }
        }
    }
    
    // Method for handling custom background upload
    async handleCustomBackgroundUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!this.validateFile(file)) {
            return;
        }
        
        const backgroundName = document.getElementById('customBackgroundName').value || 'Min bakgrund';
        
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('background_image', file);
        formData.append('background_name', backgroundName);

        // Visa laddningsmeddelande
        if (window.showToast) {
            showToast('Laddar upp bakgrund...', 'info');
        }

        try {
            const response = await fetch('/api/user-backgrounds.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reset form
                document.getElementById('customBackgroundName').value = '';
                document.getElementById('customBackgroundUpload').value = '';
                
                // Visa success toast och byt till custom-fliken för att visa den nya bakgrunden
                if (window.showToast) {
                    showToast(data.toast.message, data.toast.type);
                }
                
                // Ladda om custom-fliken för att visa den nya bakgrunden
                this.reloadCustomBackgrounds();
            } else {
                // Visa felmeddelande
                const errorMessage = data.toast ? data.toast.message : (data.error || 'Ett fel uppstod vid uppladdningen');
                this.showUploadError(errorMessage);
                
                if (window.showToast) {
                    showToast(errorMessage, 'error');
                }
            }
        } catch (error) {
            console.error('Error uploading custom background:', error);
            this.showUploadError('Ett fel uppstod vid uppladdningen');
            
            if (window.showToast) {
                showToast('Ett fel uppstod vid uppladdningen', 'error');
            }
        }
    }
    
    // Metod för att ladda om custom-fliken utan att ladda om hela sidan
    async reloadCustomBackgrounds() {
        try {
            const response = await fetch('/background-tabs/custom.php?whiteboard_id=' + this.whiteboardId);
            const html = await response.text();
            
            // Uppdatera innehållet i custom-fliken
            document.getElementById('tab-custom').innerHTML = html;
            
            // Byt till custom-fliken
            this.switchTab('custom');
        } catch (error) {
            console.error('Error reloading custom backgrounds:', error);
            
            if (window.showToast) {
                showToast('Kunde inte uppdatera listan över bakgrunder', 'error');
            }
        }
    }
    
    async deleteUserBackground(backgroundId) {
        if (!confirm('Är du säker på att du vill ta bort denna bakgrund?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('background_id', backgroundId);
        
        try {
            const response = await fetch('/api/user-backgrounds.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Visa success toast
                if (window.showToast) {
                    showToast(data.toast.message, data.toast.type);
                }
                
                // Ladda om custom-fliken
                this.reloadCustomBackgrounds();
            } else {
                // Visa felmeddelande
                if (window.showToast) {
                    showToast(data.toast ? data.toast.message : 'Ett fel uppstod när bakgrunden skulle tas bort', 'error');
                }
            }
        } catch (error) {
            console.error('Error deleting background:', error);
            
            if (window.showToast) {
                showToast('Ett fel uppstod när bakgrunden skulle tas bort', 'error');
            }
        }
    }

    validateFile(file) {
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            this.showUploadError('Endast jpg, jpeg, png, gif och webp-filer är tillåtna');
            
            if (window.showToast) {
                showToast('Endast jpg, jpeg, png, gif och webp-filer är tillåtna', 'error');
            }
            return false;
        }

        if (file.size > 5 * 1024 * 1024) {
            this.showUploadError('Filen får inte vara större än 5MB');
            
            if (window.showToast) {
                showToast('Filen får inte vara större än 5MB', 'error');
            }
            return false;
        }

        return true;
    }

    showUploadError(message) {
        const errorElement = document.getElementById('uploadError');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
    }
}