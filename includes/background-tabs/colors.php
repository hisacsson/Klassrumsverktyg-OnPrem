<div id="tab-colors" class="tab-content hidden">
    <!-- Fördefinierade färger -->
    <div class="grid grid-cols-6 gap-4 mb-6">
        <?php
        $colors = [
            // Neutrala toner
            ['value' => '#f8fafc', 'name' => 'Ljusgrå'],
            ['value' => '#f1f5f9', 'name' => 'Silvergrå'],
            ['value' => '#e2e8f0', 'name' => 'Grå'],
            
            // Blå toner
            ['value' => '#f0f9ff', 'name' => 'Ljusblå'],
            ['value' => '#e0f2fe', 'name' => 'Himmelsblå'],
            ['value' => '#bae6fd', 'name' => 'Havsblå'],
            
            // Gröna toner
            ['value' => '#f0fdf4', 'name' => 'Mintgrön'],
            ['value' => '#dcfce7', 'name' => 'Ljusgrön'],
            ['value' => '#bbf7d0', 'name' => 'Skogsgrön'],
            
            // Varma toner
            ['value' => '#fff7ed', 'name' => 'Beige'],
            ['value' => '#ffedd5', 'name' => 'Persika'],
            ['value' => '#fed7aa', 'name' => 'Aprikos']
        ];
        
        foreach ($colors as $color): ?>
            <button 
                onclick="backgroundHandler.selectBackground('color', '<?php echo $color['value']; ?>')"
                class="group relative aspect-square rounded-lg hover:ring-2 hover:ring-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                style="background-color: <?php echo $color['value']; ?>"
                title="<?php echo htmlspecialchars($color['name']); ?>"
            >
                <!-- Tooltip för färgnamn -->
                <span class="invisible group-hover:visible absolute -top-8 left-1/2 transform -translate-x-1/2 
                           px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                    <?php echo htmlspecialchars($color['name']); ?>
                </span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Egen färgväljare -->
    <div class="space-y-4">
        <div class="flex items-center space-x-4">
            <label class="block text-sm font-medium text-gray-700">
                Egen färg:
                <input type="color" 
                       id="customColor" 
                       class="h-10 w-20 mt-1 cursor-pointer"
                       value="#ffffff">
            </label>
            <button onclick="backgroundHandler.selectCustomColor()"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                Välj färg
            </button>
        </div>
        
        <!-- Opacity slider för bakgrundsfärg -->
        <div class="flex items-center space-x-4">
            <label class="block text-sm font-medium text-gray-700">
                Opacitet:
                <input type="range" 
                       id="colorOpacity" 
                       min="0" 
                       max="100" 
                       value="100"
                       class="w-32 mt-1"
                       onchange="backgroundHandler.updateOpacity(this.value)">
            </label>
            <span id="opacityValue" class="text-sm text-gray-600">100%</span>
        </div>
    </div>

    <!-- Gradient section -->
    <div class="mt-6 pt-6 border-t border-gray-200">
        <h3 class="text-sm font-medium text-gray-700 mb-4">Färggradienter</h3>
        <div class="grid grid-cols-3 gap-4">
            <?php
            $gradients = [
                [
                    'name' => 'Soluppgång',
                    'value' => 'linear-gradient(to right, #ff6b6b, #feca57)'
                ],
                [
                    'name' => 'Ocean',
                    'value' => 'linear-gradient(to right, #4facfe, #00f2fe)'
                ],
                [
                    'name' => 'Skog',
                    'value' => 'linear-gradient(to right, #43c6ac, #f8ffae)'
                ],
                [
                    'name' => 'Lavendel',
                    'value' => 'linear-gradient(to right, #834d9b, #d04ed6)'
                ],
                [
                    'name' => 'Skymning',
                    'value' => 'linear-gradient(to right, #281483, #8f6ed5)'
                ],
                [
                    'name' => 'Höst',
                    'value' => 'linear-gradient(to right, #f6d365, #fda085)'
                ]
            ];
            
            foreach ($gradients as $gradient): ?>
                <button 
                    onclick="backgroundHandler.selectBackground('gradient', '<?php echo $gradient['value']; ?>')"
                    class="group relative h-20 rounded-lg hover:ring-2 hover:ring-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    style="background: <?php echo $gradient['value']; ?>"
                    title="<?php echo htmlspecialchars($gradient['name']); ?>"
                >
                    <span class="invisible group-hover:visible absolute -top-8 left-1/2 transform -translate-x-1/2 
                               px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                        <?php echo htmlspecialchars($gradient['name']); ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>