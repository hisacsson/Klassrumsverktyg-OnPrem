<!-- Modal container -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-5xl h-full max-h-[calc(100vh-2rem)] flex flex-col">
            <!-- Modal header - fast position -->
            <div class="flex items-center justify-between p-4 border-b shrink-0">
                <h3 class="text-xl font-semibold">Hjälp</h3>
                <button onclick="HelpModal.close()" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Modal content -->
            <div class="flex-1 min-h-0 flex flex-col">
                <!-- Tabs navigation - fast position -->
                <div class="border-b shrink-0">
                    <nav class="flex space-x-4 px-4" role="tablist">
                        <button 
                            onclick="HelpModal.switchTab('overview')" 
                            id="help-overview-tab"
                            class="px-3 py-2 text-sm font-medium border-b-2 border-blue-500"
                            role="tab">
                            Översikt
                        </button>
                        <button 
                            onclick="HelpModal.switchTab('widgets')" 
                            id="help-widgets-tab"
                            class="px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700"
                            role="tab">
                            Widgets
                        </button>
                        <button 
                            onclick="HelpModal.switchTab('tips')" 
                            id="help-tips-tab"
                            class="px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700"
                            role="tab">
                            Tips & Tricks
                        </button>
                    </nav>
                </div>

                <!-- Scrollable content area -->
                <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-track-gray-100 scrollbar-thumb-gray-300">
                    <!-- Overview content - inbäddat direkt i HTML -->
                    <div id="help-overview-content" class="p-4">
                        <div class="space-y-4">
                            <div class="max-w-3xl">
                                <h2 class="text-2xl font-bold mb-4">Välkommen till Whiteboard-hjälpen!</h2>
                                <p class="text-gray-700 text-lg">
                                    Här hittar du information om hur du använder de olika funktionerna som finns tillgängliga på whiteboarden. 
                                    Välj en kategori ovan för att lära dig mer om specifika funktioner eller bläddra nedåt för en snabb översikt.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                <div class="bg-blue-50 p-6 rounded-lg">
                                    <h3 class="text-lg font-semibold mb-2">Kom igång snabbt</h3>
                                    <ul class="space-y-2">
                                        <li class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="m9 12 2 2 4-4"/>
                                            </svg>
                                            Lägg till widgets genom att klicka på ikonerna i vänstermenyn
                                        </li>
                                        <li class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="m9 12 2 2 4-4"/>
                                            </svg>
                                            Dra widgets till önskad position
                                        </li>
                                        <li class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="m9 12 2 2 4-4"/>
                                            </svg>
                                            Dra i nedre högra hörnet på en widget för att öka storleken på widgeten
                                        </li>
                                        <li class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="m9 12 2 2 4-4"/>
                                            </svg>
                                            Anpassa inställningar genom att klicka på en widget
                                        </li>
                                    </ul>
                                </div>

                                <div class="bg-green-50 p-6 rounded-lg">
                                <h3 class="text-lg font-semibold mb-2">Anpassa widgets</h3>
                <p class="text-gray-700 mb-4">Förstå vad de olika ikonerna på en widget betyder och hur du använder dem:</p>
                <ul class="space-y-2">
                <li class="flex items-center">
                        <svg class="h-4 w-4 text-black-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span><strong>Redigera:</strong> Klicka för att öppna redigeringsverktyget för widgeten.</span>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-4 w-4 text-black-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span><strong>Visa:</strong> Klicka för att visa innehållet för widgeten.</span>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-4 w-4 text-black-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                        <span><strong>Dölj:</strong> Klicka för att dölja innehållet för widgeten.</span>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-4 w-4 text-black-500 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 20V10M12 20V4M6 20v-6" />
                        </svg>
                        <span><strong>Resultat:</strong> Klicka för att visa resultaten från en omröstning.</span>
                    </li>
                </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Widgets content - laddas via AJAX -->
                    <div id="help-widgets-content" class="hidden p-4"></div>
                    
                    <!-- Tips content - laddas via AJAX -->
                    <div id="help-tips-content" class="hidden p-4"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const HelpModal = {
    // Definiera sökvägar till innehållsfiler
    paths: {
        widgets: '/help/widgets.php',
        tips: '/help/tips.php'
    },
    
    // Håller reda på aktiv flik
    activeTab: 'overview',  // Sätt overview som default
    
    // Öppnar modalen
    open() {
        document.getElementById('helpModal').classList.remove('hidden');
        lucide.createIcons();  // Detta säkerställer att ikonerna laddas när modalen öppnas
    },
    
    // Stänger modalen
    close() {
        document.getElementById('helpModal').classList.add('hidden');
    },
    
    // Byter flik
    async switchTab(tabName) {
        if (this.activeTab === tabName) return;
        
        // Dölj alla innehåll
        document.querySelectorAll('[id^="help-"][id$="-content"]').forEach(content => {
            content.classList.add('hidden');
        });
        
        // Visa valt innehåll
        document.getElementById(`help-${tabName}-content`).classList.remove('hidden');
        
        // Uppdatera flikarnas utseende
        document.querySelectorAll('[id^="help-"][id$="-tab"]').forEach(tab => {
            tab.classList.remove('border-b-2', 'border-blue-500');
            tab.classList.add('text-gray-500');
        });
        
        document.getElementById(`help-${tabName}-tab`).classList.add('border-b-2', 'border-blue-500');
        document.getElementById(`help-${tabName}-tab`).classList.remove('text-gray-500');
        
        // Ladda innehåll via AJAX endast för widgets och tips
        if (tabName !== 'overview' && !document.getElementById(`help-${tabName}-content`).innerHTML.trim()) {
            await this.loadTabContent(tabName);
        }
        
        this.activeTab = tabName;
    },
    
    // Laddar innehåll för en flik via AJAX
    async loadTabContent(tabName) {
        if (!this.paths[tabName]) {
            console.error(`Ingen sökväg definierad för flik: ${tabName}`);
            return;
        }

        try {
            const response = await fetch(this.paths[tabName]);
            if (!response.ok) throw new Error(`Kunde inte ladda innehållet från ${this.paths[tabName]}`);
            const content = await response.text();
            document.getElementById(`help-${tabName}-content`).innerHTML = content;
        } catch (error) {
            console.error('Fel vid laddning av innehåll:', error);
            document.getElementById(`help-${tabName}-content`).innerHTML = 
                '<div class="p-4 text-red-500">Ett fel uppstod vid laddning av innehållet.</div>';
        }
    }
};
</script>
<script>
    lucide.createIcons();
</script>
