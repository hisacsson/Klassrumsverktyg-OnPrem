<div id="tab-images" class="tab-content">
    <div class="grid grid-cols-3 gap-4">
        <?php
        $backgrounds = [
            [
                'id' => 1, 
                'url' => '/assets/img/backgrounds/abstract.jpg',
                'attribution' => 'Foto av Lucas K på Unsplash',
                'attribution_link' => 'https://unsplash.com/photos/white-and-blue-glass-building-6mXdf8QIybA?utm_content=creditShareLink&utm_medium=referral&utm_source=unsplash'
            ],
            [
                'id' => 2, 
                'url' => '/assets/img/backgrounds/nature.jpg',
                'attribution' => 'Foto av v2osk på Unsplash',
                'attribution_link' => 'https://unsplash.com/photos/foggy-mountain-summit-1Z2niiBPg5A'
            ],
            [
                'id' => 3, 
                'url' => '/assets/img/backgrounds/geometric.jpg',
                'attribution' => 'Foto av the blowup på Unsplash',
                'attribution_link' => 'https://unsplash.com/photos/white-and-blue-glass-building-6mXdf8QIybA'
            ],
        ];
        foreach ($backgrounds as $bg): ?>
            <div class="flex flex-col">
                <button onclick="selectBackground('image', '<?php echo $bg['url']; ?>', '<?php echo htmlspecialchars($bg['attribution']); ?>', '<?php echo htmlspecialchars($bg['attribution_link']); ?>')"
                        class="aspect-video rounded-lg overflow-hidden hover:ring-2 hover:ring-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 mb-1">
                    <img data-src="<?php echo $bg['url']; ?>" 
                         src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 3 2'%3E%3C/svg%3E"
                         alt="Bakgrund" 
                         class="w-full h-full object-cover lazy-image">
                </button>
                <!-- Attribution under bilden -->
                <div class="text-xs text-gray-600 truncate">
                    <a href="<?php echo htmlspecialchars($bg['attribution_link']); ?>" 
                       target="_blank" 
                       class="hover:underline" 
                       onclick="event.stopPropagation()"
                       title="<?php echo htmlspecialchars($bg['attribution']); ?>">
                        <?php echo htmlspecialchars($bg['attribution']); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // Uppdaterad selectBackground-funktion för att hantera attribution
    function selectBackground(type, value, attribution, attributionLink) {
        // Spara valet i en variabel eller skicka direkt till servern
        console.log(`Vald bakgrund: ${type}, ${value}`);
        
        // Uppdatera gränssnittet för att visa vald bakgrund
        if (type === 'image') {
            document.body.style.backgroundImage = `url('${value}')`;
            document.body.style.backgroundColor = 'transparent';
            document.body.style.backgroundSize = 'cover';
            document.body.style.backgroundPosition = 'center';
            document.body.style.backgroundRepeat = 'no-repeat';
            
            // Visa attribution
            updateBackgroundAttribution(attribution, attributionLink);
        }
        
        // Valfritt: spara valet på servern via AJAX
        fetch('includes/background-tabs/save_background.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}&attribution=${encodeURIComponent(attribution)}&attribution_link=${encodeURIComponent(attributionLink)}&whiteboard_id=${encodeURIComponent(whiteboardId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Bakgrund sparad');
            } else {
                console.error('Fel vid sparande av bakgrund:', data.error);
            }
        });
    }
    
    // Funktion för att uppdatera attribution på skärmen
    function updateBackgroundAttribution(attribution, attributionLink) {
        // Ta bort tidigare attribution om den finns
        const existingAttribution = document.getElementById('background-attribution');
        if (existingAttribution) {
            existingAttribution.remove();
        }
        
        // Skapa ny attribution om vi har någon
        if (attribution && attribution.trim() !== '') {
            const attributionElement = document.createElement('div');
            attributionElement.id = 'background-attribution';
            attributionElement.className = 'fixed bottom-2 right-2 bg-black bg-opacity-50 text-white text-xs p-1 rounded';
            
            if (attributionLink && attributionLink.trim() !== '') {
                const link = document.createElement('a');
                link.href = attributionLink;
                link.target = '_blank';
                link.className = 'text-white hover:underline';
                link.textContent = attribution;
                attributionElement.appendChild(link);
            } else {
                attributionElement.textContent = attribution;
            }
            
            document.body.appendChild(attributionElement);
        }
    }

    // Lazy loading-kod för bilderna
    document.addEventListener('DOMContentLoaded', function() {
        // Välj fliken med bilder
        const imagesTab = document.getElementById('tab-images');
        
        // Funktion för att ladda bilderna när fliken blir synlig
        function loadImagesInTab() {
            if (imagesTab.classList.contains('active') || isVisible(imagesTab)) {
                const lazyImages = imagesTab.querySelectorAll('img.lazy-image');
                lazyImages.forEach(img => {
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                });
            }
        }
        
        // Funktion för att kontrollera om ett element är synligt
        function isVisible(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        
        // Lyssna på klick/ändringar i flikar
        document.querySelectorAll('[data-tab]').forEach(tab => {
            tab.addEventListener('click', function() {
                if (this.dataset.tab === 'images') {
                    setTimeout(loadImagesInTab, 100);
                }
            });
        });
        
        // Använd Intersection Observer om det finns stöd för det
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        loadImagesInTab();
                    }
                });
            });
            observer.observe(imagesTab);
        } else {
            // Fallback för äldre webbläsare
            window.addEventListener('scroll', loadImagesInTab);
            window.addEventListener('resize', loadImagesInTab);
            
            // Kontrollera också om fliken är synlig vid sidinladdning
            setTimeout(loadImagesInTab, 500);
        }
    });
</script>