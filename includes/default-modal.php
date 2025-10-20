<!-- Profile Modal -->
<div id="profileModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl p-6 m-4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Profilinställningar</h2>
            <button onclick="closeProfileModal()" class="p-2 hover:bg-gray-100 rounded-full">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="border-b border-gray-200 mb-6">
            <div class="flex space-x-6">
                <button onclick="switchProfileTab('password')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="password">
                    Byt lösenord
                </button>
                <button onclick="switchProfileTab('defaults')" 
                        class="tab-button px-4 py-2 border-b-2 border-transparent hover:border-gray-300"
                        data-tab="defaults">
                    Grundinställningar
                </button>
            </div>
        </div>

        <div id="passwordTab" class="tab-content">
            <form action="/api/update-password.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Nuvarande lösenord</label>
                    <input type="password" name="current_password" required
                           class="w-full border rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Nytt lösenord</label>
                    <input type="password" name="new_password" required
                           class="w-full border rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Bekräfta nytt lösenord</label>
                    <input type="password" name="confirm_password" required
                           class="w-full border rounded-md p-2">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Uppdatera lösenord
                </button>
            </form>
        </div>

        <div id="defaultsTab" class="tab-content hidden">
            <form action="/api/update-defaults.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Bakgrundstyp</label>
                    <select name="background_type" onchange="toggleBackgroundOptions(this.value)"
                            class="w-full border rounded-md p-2">
                        <option value="color">Enfärgad</option>
                        <option value="gradient">Gradient</option>
                        <option value="image">Bild</option>
                    </select>
                </div>

                <!-- Preview -->
                <div id="backgroundPreview" class="w-full h-32 rounded-lg border"></div>

                <div id="colorOption">
                    <label class="block text-sm font-medium mb-2">Bakgrundsfärg</label>
                    <input type="color" name="background_color" value="#ffffff"
                           class="h-10 w-full border rounded-md">
                </div>

                <div id="gradientOption" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Första färgen</label>
                        <input type="color" name="gradient_color_1" value="#ffffff"
                               class="h-10 w-full border rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Andra färgen</label>
                        <input type="color" name="gradient_color_2" value="#e2e2e2"
                               class="h-10 w-full border rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Riktning</label>
                        <select name="gradient_direction" class="w-full border rounded-md p-2">
                            <option value="to right">Vänster till höger</option>
                            <option value="to bottom">Uppifrån och ner</option>
                            <option value="to bottom right">Diagonal</option>
                        </select>
                    </div>
                </div>

                <div id="imageOption" class="hidden">
                    <label class="block text-sm font-medium mb-2">Bakgrundsbild</label>
                    <div class="border-2 border-dashed rounded-lg p-4 text-center">
                        <input type="file" name="background_image" accept="image/*" class="hidden" id="background-upload">
                        <label for="background-upload" class="cursor-pointer">
                            <div class="mx-auto h-12 w-12 text-gray-400">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">Klicka för att välja bild</p>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Spara inställningar
                </button>
            </form>
        </div>
    </div>
</div>