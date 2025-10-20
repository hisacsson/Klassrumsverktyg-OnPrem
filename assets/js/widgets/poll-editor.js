// poll-editor.js
function addOption() {
    const container = document.getElementById('optionsContainer');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'flex mb-2';
    div.innerHTML = '<input type="text" name="options[]" value="" class="flex-grow p-2 border rounded" required> <button type="button" class="ml-2 text-red-500" onclick="removeOption(this)">Ta bort</button>';
    container.appendChild(div);
}

function removeOption(btn) {
    btn.parentElement.remove();
}

