<?php
require_once '../classes/Database.php';
require_once '../classes/AdminAuth.php';
require_once '../classes/ApiKeyManager.php';

$db = new Database();
$auth = new AdminAuth($db);
$auth->requireLogin();

$apiKeyManager = new ApiKeyManager($db);
$currentUser = $auth->getCurrentUser();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $keyName = $_POST['key_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            
            if (!empty($keyName)) {
                $newKey = $apiKeyManager->createKey($keyName, $description, $currentUser['id'], $expiresAt);
                $success = "API Key created successfully: " . $newKey;
            }
            break;
            
        case 'toggle':
            $keyId = $_POST['key_id'] ?? '';
            if (!empty($keyId)) {
                $apiKeyManager->toggleKeyStatus($keyId);
                $success = "API Key status updated successfully.";
            }
            break;
            
        case 'delete':
            $keyId = $_POST['key_id'] ?? '';
            if (!empty($keyId)) {
                $apiKeyManager->deleteKey($keyId);
                $success = "API Key deleted successfully.";
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    if (isset($success)) {
        header('Location: index.php?success=' . urlencode($success));
        exit();
    }
}

$apiKeys = $apiKeyManager->getAllKeys();
$stats = $apiKeyManager->getKeyStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISW CMDB Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-blue-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-database text-xl mr-2"></i>
                    <span class="font-semibold text-lg">ISW CMDB Admin</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../docs/" target="_blank" class="text-blue-100 hover:text-white text-sm">
                        <i class="fas fa-book mr-1"></i> API Docs
                    </a>
                    <span class="text-sm">Welcome, <?= htmlspecialchars($currentUser['username']) ?></span>
                    <a href="logout.php" class="bg-blue-700 hover:bg-blue-800 px-3 py-2 rounded text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($_GET['success']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-key text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total API Keys</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $stats['total'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Active Keys</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $stats['active'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-bar text-purple-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Usage</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= number_format($stats['total_usage']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create New API Key -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    <i class="fas fa-plus mr-2"></i>Create New API Key
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="key_name" class="block text-sm font-medium text-gray-700">Key Name</label>
                            <input type="text" name="key_name" id="key_name" required
                                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-gray-700">Expires At (Optional)</label>
                            <input type="datetime-local" name="expires_at" id="expires_at"
                                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="3"
                                  class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                            <i class="fas fa-plus mr-2"></i>Create API Key
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- API Keys Table -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    <i class="fas fa-list mr-2"></i>API Keys Management
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">API Key</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($apiKeys as $key): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($key['key_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($key['description']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                        <span class="select-all"><?= htmlspecialchars($key['api_key']) ?></span>
                                        <button onclick="copyToClipboard('<?= htmlspecialchars($key['api_key']) ?>')" 
                                                class="ml-2 text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($key['is_active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($key['usage_count']) ?>
                                    <?php if ($key['last_used']): ?>
                                        <div class="text-xs text-gray-500">Last: <?= date('M j, Y', strtotime($key['last_used'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div><?= date('M j, Y', strtotime($key['created_at'])) ?></div>
                                    <div class="text-xs text-gray-500">by <?= htmlspecialchars($key['created_by_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                        <button type="submit" class="text-blue-600 hover:text-blue-900">
                                            <?= $key['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this API key?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // You could add a toast notification here
                alert('API key copied to clipboard!');
            });
        }
    </script>
</body>
</html>
