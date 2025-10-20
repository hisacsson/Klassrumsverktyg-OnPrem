<?php
require_once __DIR__ . '/../src/Config/Database.php';

$database = new Database();
$db = $database->getConnection();

require_once 'AdminController.php';
$admin = new AdminController($db);
$users = $admin->listUsers();
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hantera användare</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include_once 'nav.php'; ?>
    <!-- Huvudinnehåll -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Hantera användare</h1>
        
        <div class="bg-white shadow rounded-lg p-6">
            <table class="min-w-full">
                <!-- Tidigare tabellkod här -->
            </table>
        </div>
    </div>

    <!-- Redigera användare modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-8 max-w-md w-full">
                <h2 class="text-xl font-bold mb-4">Redigera användare</h2>
                <form id="editForm">
                    <input type="hidden" id="userId" name="userId">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Förnamn</label>
                        <input type="text" id="firstName" name="first_name" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Efternamn</label>
                        <input type="text" id="lastName" name="last_name" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" name="email" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Skola</label>
                        <input type="text" id="school" name="school" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Roll</label>
                        <select id="role" name="role" class="w-full border rounded px-3 py-2">
                            <option value="teacher">Lärare</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Status</label>
                        <select id="isActive" name="is_active" class="w-full border rounded px-3 py-2">
                            <option value="1">Aktiv</option>
                            <option value="0">Inaktiv</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeEditModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded">
                            Avbryt
                        </button>
                        <button type="submit" 
                                class="bg-blue-500 text-white px-4 py-2 rounded">
                            Spara
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function editUser(userId) {
        document.getElementById('editModal').classList.remove('hidden');
        // Hämta användardata och fyll i formuläret
        fetch(`/admin/get-user/${userId}`)
            .then(response => response.json())
            .then(user => {
                document.getElementById('userId').value = user.id;
                document.getElementById('firstName').value = user.first_name;
                document.getElementById('lastName').value = user.last_name;
                document.getElementById('email').value = user.email;
                document.getElementById('school').value = user.school;
                document.getElementById('role').value = user.role;
                document.getElementById('isActive').value = user.is_active;
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const userId = formData.get('userId');

        fetch(`/admin/update-user/${userId}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeEditModal();
                location.reload();
            }
        });
    });

    // Tidigare JavaScript-funktioner här
    </script>
</body>
</html>