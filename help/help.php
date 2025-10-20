<?php
// help.php - Hjälpsida för whiteboard.php
?>

<div class="space-y-6">
    <!-- Introduktion -->
    <section>
        <h2 class="text-2xl font-bold mb-2">Välkommen till Whiteboard-hjälpen!</h2>
        <p class="text-gray-700">Här hittar du information om hur du använder de olika widgets som finns tillgängliga på whiteboarden. Klicka på en widget för att interagera med den och anpassa den efter dina behov.</p>
    </section>

    <!-- Widget-översikt -->
    <section>
        <h3 class="text-xl font-semibold mb-4">Tillgängliga Widgets</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Klocka -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-blue-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-clock h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Klocka</h4>
                    <p class="text-gray-600">Visar aktuell tid i realtid. Perfekt för att hålla koll på lektionstider.</p>
                </div>
            </div>

            <!-- Timer -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-blue-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-timer h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Timer</h4>
                    <p class="text-gray-600">Ställ in en timer för olika aktiviteter. Du kan justera minuter och sekunder och starta, pausa eller återställa timern.</p>
                </div>
            </div>

            <!-- Text -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-purple-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-file-text h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Text</h4>
                    <p class="text-gray-600">Skriv och visa anteckningar direkt på whiteboarden. Perfekt för instruktioner eller påminnelser.</p>
                </div>
            </div>

            <!-- Grupper -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-blue-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-users h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Grupper</h4>
                    <p class="text-gray-600">Dela in elever i slumpmässiga grupper. Ange antal grupper och generera automatiskt.</p>
                </div>
            </div>

            <!-- Brain Break -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-pink-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-activity h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Brain Break</h4>
                    <p class="text-gray-600">Slumpa korta aktivitetsövningar för att ge eleverna en paus och öka koncentrationen.</p>
                </div>
            </div>

            <!-- QR-kod -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-green-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-qrcode h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">QR-kod</h4>
                    <p class="text-gray-600">Generera QR-koder som eleverna kan skanna för att snabbt komma åt länkar eller dokument.</p>
                </div>
            </div>

            <!-- YouTube -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-red-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-youtube h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">YouTube</h4>
                    <p class="text-gray-600">Bädda in YouTube-videor direkt på whiteboarden för att visa utbildningsmaterial.</p>
                </div>
            </div>

            <!-- Att Göra -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-blue-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-check-square h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Att Göra</h4>
                    <p class="text-gray-600">Skapa att-göra-listor för att hålla koll på uppgifter och aktiviteter.</p>
                </div>
            </div>

            <!-- Trafikljus -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-purple-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-traffic-light h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Trafikljus</h4>
                    <p class="text-gray-600">Använd trafikljusfärger för att visa ljudnivån i klassrummet: Grön (prata fritt), Gul (viska), Röd (tystnad).</p>
                </div>
            </div>

            <!-- Omröstning -->
            <div class="p-4 border rounded-lg flex items-center space-x-4">
                <div class="bg-indigo-500 text-white p-3 rounded-full">
                    <i class="lucide lucide-bar-chart-2 h-6 w-6"></i>
                </div>
                <div>
                    <h4 class="text-lg font-semibold">Omröstning</h4>
                    <p class="text-gray-600">Skapa snabba omröstningar och låt eleverna delta genom att skanna en QR-kod eller via en länk.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Generella tips -->
    <section>
        <h3 class="text-xl font-semibold mb-4">Tips och Tricks</h3>
        <ul class="list-disc list-inside space-y-2 text-gray-700">
            <li><strong>Dra och släpp:</strong> Flytta widgets runt på whiteboarden genom att dra dem till önskad plats.</li>
            <li><strong>Ta bort widgets:</strong> Klicka på ×-ikonen i hörnet för att ta bort en widget.</li>
            <li><strong>Spara inställningar:</strong> Eventuella ändringar i widgets sparas automatiskt.</li>
        </ul>
    </section>
</div>
