// Förenklad funktion för att centrera alla widgets på skärmen
function centerAllWidgets() {
    // Visa laddningsindikator eller meddelande
    showNotification('Centrerar widgets...', 'info');
    
    // Hämta alla widgets
    const widgets = document.querySelectorAll('.widget');
    if (!widgets.length) {
        showNotification('Inga widgets att centrera', 'info');
        return;
    }
    
    // Beräkna synlig skärmyta (minus lite marginal)
    const marginX = 50; // 50px marginal från sidorna
    const marginY = 100; // 100px marginal från topp/botten (för verktygsfält etc.)
    
    const viewportWidth = window.innerWidth - (marginX * 2);
    const viewportHeight = window.innerHeight - (marginY * 2);
    
    // Beräkna genomsnittlig widgetstorlek för avstånd
    let totalWidth = 0;
    let totalHeight = 0;
    
    widgets.forEach(widget => {
        totalWidth += widget.offsetWidth;
        totalHeight += widget.offsetHeight;
    });
    
    const avgWidth = totalWidth / widgets.length;
    const avgHeight = totalHeight / widgets.length;
    const spacing = Math.min(20, avgWidth * 0.2); // Dynamiskt avstånd baserat på widgetstorlek
    
    // Beräkna antal kolumner baserat på tillgänglig bredd
    const numCols = Math.max(1, Math.floor(viewportWidth / (avgWidth + spacing)));
    const numRows = Math.ceil(widgets.length / numCols);
    
    // Beräkna startposition för layouten (centrerad i viewporten)
    const startX = marginX + (viewportWidth - (numCols * avgWidth + (numCols - 1) * spacing)) / 2;
    const startY = marginY;
    
    // Placera widgets i ett grid-liknande mönster
    widgets.forEach((widget, index) => {
        const row = Math.floor(index / numCols);
        const col = index % numCols;
        
        const width = widget.offsetWidth;
        const height = widget.offsetHeight;
        
        // Beräkna position
        const x = startX + col * (avgWidth + spacing);
        const y = startY + row * (avgHeight + spacing);
        
        // Säkerställ att widget är inom skärmgränser
        const safeX = Math.max(marginX, Math.min(window.innerWidth - width - marginX, x));
        const safeY = Math.max(marginY, Math.min(window.innerHeight - height - marginY, y));
        
        // Animera förflyttningen för bättre visuell återkoppling
        widget.style.transition = 'left 0.5s ease, top 0.5s ease';
        
        // Uppdatera widget position
        widget.style.left = `${safeX}px`;
        widget.style.top = `${safeY}px`;
        
        // Uppdatera data-attribut för interact.js
        widget.setAttribute('data-x', safeX);
        widget.setAttribute('data-y', safeY);
        
        // Uppdatera positionen i databasen
        const widgetId = widget.id.replace('widget-', '');
        updateWidgetPosition(widgetId, safeX, safeY);
    });
    
    // Återställ transition efter animationen är klar
    setTimeout(() => {
        widgets.forEach(widget => {
            widget.style.transition = '';
        });
        showNotification('Widgets centrerade!', 'success');
    }, 600);
}

// Lägg till en centrerings-knapp med korrekt z-index
function addCenterButton() {
    // Ta bort eventuell befintlig knapp först
    const existingButton = document.getElementById('centerWidgetsBtn');
    if (existingButton) {
        existingButton.remove();
    }
    
    // Skapa en ny knapp med korrekt z-index
    const button = document.createElement('button');
    button.id = 'centerWidgetsBtn';
    // Ändra z-index till 30 (under sidebar som är 50, men över whiteboard)
    button.className = 'fixed bottom-4 left-4 z-30 bg-white p-2 rounded-lg shadow-md hover:bg-blue-50 transition-colors flex items-center space-x-1';
    button.setAttribute('aria-label', 'Centrera Widgets');
    button.setAttribute('title', 'Centrera alla widgets på skärmen');
    
    // Innehållet i knappen (ikon och text)
    button.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1">
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <path d="M9 3v18" />
            <path d="M15 3v18" />
            <path d="M3 9h18" />
            <path d="M3 15h18" />
        </svg>
        <span class="text-xs">Centrera</span>
    `;
    
    // Lägg till händelselyssnare
    button.addEventListener('click', centerAllWidgets);
    
    // Lägg till knappen i body
    document.body.appendChild(button);
    
    // Lägg också till ett menyalternativ i sidebaren
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        // Hitta en lämplig behållare för den nya knappen
        const settingsContainer = sidebar.querySelector('.space-y-2');
        if (settingsContainer) {
            // Ta bort eventuell befintlig knapp först
            const existingSidebarButton = settingsContainer.querySelector('button[data-center-widgets="true"]');
            if (existingSidebarButton) {
                existingSidebarButton.remove();
            }
            
            const sidebarButton = document.createElement('button');
            sidebarButton.className = 'flex items-center w-full p-2 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors';
            sidebarButton.setAttribute('data-center-widgets', 'true'); // För att kunna identifiera knappen
            sidebarButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 mr-3" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" /><path d="M9 3v18" /><path d="M15 3v18" /><path d="M3 9h18" /><path d="M3 15h18" /></svg>
                <span class="text-gray-700">Centrera widgets</span>
            `;
            
            // Lägg till klickhändelse som bara kör centreringen
            sidebarButton.addEventListener('click', centerAllWidgets);
            
            settingsContainer.appendChild(sidebarButton);
        }
    }
}

// Initialisera när DOM är fullständigt laddad
document.addEventListener('DOMContentLoaded', function() {
    // Vänta en kort stund för att säkerställa att alla andra initialiserings är klara
    setTimeout(function() {
        addCenterButton();
    }, 500);
});