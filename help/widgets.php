<?php
// widgets.php - Lista över tillgängliga widgets med detaljerad information
?>


<div class="space-y-6">
    <section>
        <h2 class="text-2xl font-bold mb-4">Widgets för din whiteboard</h2>
        <p class="text-gray-700 mb-6">Här hittar du en detaljerad översikt över alla tillgängliga widgets. Varje widget har sina unika funktioner som hjälper dig att skapa en interaktiv och engagerande undervisningsmiljö.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Klocka -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-blue-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-clock h-6 w-6" data-lucide="clock"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Klocka</h4>
                </div>
                <p class="text-gray-600">Visar aktuell tid i realtid. Perfekt för att hålla koll på lektionstider.</p>
            </div>

            <!-- Timer -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-blue-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-timer h-6 w-6" data-lucide="timer"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Timer</h4>
                </div>
                <p class="text-gray-600">Ställ in en timer för olika aktiviteter. Du kan justera minuter och sekunder och starta, pausa eller återställa timern.</p>
            </div>

            <!-- Text -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-purple-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-file-text h-6 w-6" data-lucide="file-text"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Text</h4>
                </div>
                <p class="text-gray-600">Skriv och visa anteckningar direkt på whiteboarden. Perfekt för instruktioner eller påminnelser.</p>
            </div>

            <!-- Grupper -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-blue-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-users h-6 w-6" data-lucide="users"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Grupper</h4>
                </div>
                <p class="text-gray-600">Dela in elever i slumpmässiga grupper. Ange antal grupper och generera automatiskt.</p>
            </div>

            <!-- Brain Break -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-pink-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-activity h-6 w-6" data-lucide="activity"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Brain Break</h4>
                </div>
                <p class="text-gray-600">Slumpa korta aktivitetsövningar för att ge eleverna en paus och öka koncentrationen.</p>
            </div>

            <!-- QR-kod -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-green-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-qr-code h-6 w-6" data-lucide="qr-code"></i>
                    </div>
                    <h4 class="text-lg font-semibold">QR-kod</h4>
                </div>
                <p class="text-gray-600">Generera QR-koder som eleverna kan skanna för att snabbt komma åt länkar eller dokument.</p>
            </div>

            <!-- YouTube -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-red-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-youtube h-6 w-6" data-lucide="youtube"></i>
                    </div>
                    <h4 class="text-lg font-semibold">YouTube</h4>
                </div>
                <p class="text-gray-600">Bädda in YouTube-videor direkt på whiteboarden för att visa utbildningsmaterial.</p>
            </div>

            <!-- Att Göra -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-blue-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-check-square h-6 w-6" data-lucide="check-square"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Att Göra</h4>
                </div>
                <p class="text-gray-600">Skapa att-göra-listor för att hålla koll på uppgifter och aktiviteter.</p>
            </div>

            <!-- Trafikljus -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-purple-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-ellipsis-vertical h-6 w-6" data-lucide="ellipsis-vertical"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Trafikljus</h4>
                </div>
                <p class="text-gray-600">Använd trafikljusfärger för att visa ljudnivån i klassrummet: Grön (prata fritt), Gul (viska), Röd (tystnad).</p>
            </div>

            <!-- Omröstning -->
            <div class="p-4 border rounded-lg hover:shadow-md transition-shadow">
                <div class="flex items-center space-x-4 mb-3">
                    <div class="bg-indigo-500 text-white p-3 rounded-full">
                        <i class="lucide lucide-bar-chart-2 h-6 w-6"data-lucide="bar-chart"></i>
                    </div>
                    <h4 class="text-lg font-semibold">Omröstning</h4>
                </div>
                <p class="text-gray-600">Skapa snabba omröstningar och låt eleverna delta genom att skanna en QR-kod eller via en länk.</p>
            </div>
        </div>
    </section>

    <section class="bg-blue-50 p-6 rounded-lg mt-8">
        <h3 class="text-lg font-semibold mb-3">Tips för widgets</h3>
        <ul class="space-y-2 text-gray-700">
            <li class="flex items-start space-x-2">
                <i class="lucide lucide-check-circle text-blue-500 h-5 w-5 mt-0.5 flex-shrink-0"></i>
                <span>Dubbelklicka på en widget för att öppna dess inställningar</span>
            </li>
            <li class="flex items-start space-x-2">
                <i class="lucide lucide-check-circle text-blue-500 h-5 w-5 mt-0.5 flex-shrink-0"></i>
                <span>Dra i hörnen för att ändra storleken på widgets</span>
            </li>
        </ul>
    </section>
</div>