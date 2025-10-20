<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lösenordsskydd</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <h2 class="text-2xl font-bold mb-6">Lösenordsskyddad whiteboard</h2>
        <form id="passwordForm" class="space-y-4">
            <input type="hidden" id="boardCode" value="<?php echo htmlspecialchars($boardCode); ?>">
            <div>
                <label class="block text-gray-700 mb-2" for="password">Lösenord</label>
                <input type="password" id="password" 
                       class="w-full p-2 border rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500" 
                       required>
            </div>
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                Fortsätt
            </button>
        </form>
        <div id="errorMessage" class="mt-4 text-red-500 hidden"></div>
    </div>

    <script>
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const boardCode = document.getElementById('boardCode').value;
            const password = document.getElementById('password').value;
            
            fetch('/verify_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ board_code: boardCode, password: password })
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    document.getElementById('errorMessage').textContent = 'Felaktigt lösenord';
                    document.getElementById('errorMessage').classList.remove('hidden');
                }
            });
        });
    </script>
</body>
</html>