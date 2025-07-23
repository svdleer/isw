<?php
/**
 * Test Netshot API Connectivity
 * 
 * This script checks if the Netshot API is accessible and returns devices.
 * It can help debug connectivity issues with the Netshot API.
 */

// Load required files
require_once __DIR__ . '/classes/EnvLoader.php';
require_once __DIR__ . '/classes/NetshotAPI.php';

// Load environment variables
EnvLoader::load();

// Get Netshot URL and API key from command line arguments or environment variables
$apiUrl = $_ENV['NETSHOT_URL'] ?? $_ENV['NETSHOT_API_URL'] ?? $_ENV['NETSHOT_OSS_URL'] ?? 'https://netshot.oss.local/api';

// Use the server URL from your code snippet if environment variables aren't set
if ($apiUrl == 'https://netshot.oss.local/api' && !isset($_ENV['NETSHOT_URL'])) {
    $apiUrl = 'https://netshot.oss.local'; // Base URL
}

// Make sure URL ends with /api
if (substr($apiUrl, -4) !== '/api') {
    $apiUrl .= '/api';
}

$apiKey = $_ENV['NETSHOT_API_KEY'] ?? $_ENV['NETSHOT_API_TOKEN'] ?? $_ENV['NETSHOT_OSS_TOKEN'] ?? 'UqRf6NkgvKru3rxRRrRKck1VoANQJvP2';

// Get the group name (default to ACCESS as per your Python code)
$groupName = $_ENV['NETSHOT_GROUP'] ?? 'ACCESS';

// Check if we should clear the cache
$clearCache = $_ENV['CLEAR_CACHE'] ?? false;
if ($clearCache) {
    echo "Clearing Netshot cache as requested...\n";
    $cacheDir = __DIR__ . '/cache/netshot';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        echo "Cleared " . count($files) . " cache files\n";
    } else {
        echo "Cache directory not found\n";
    }
}

// Create a function to test different Netshot API URLs
function testNetshotConnection($url, $apiKey) {
    echo "Testing Netshot API connection to: $url\n";
    echo "Using API Key: " . substr($apiKey, 0, 5) . "..." . substr($apiKey, -5) . "\n";
    
    try {
        // Initialize cURL
        $ch = curl_init();
        $testEndpoint = $url . "/devices?limit=5"; // Only request 5 devices
        
        echo "Sending request to: $testEndpoint\n";
        
        curl_setopt($ch, CURLOPT_URL, $testEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Netshot-API-Token: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        // Skip SSL verification for testing only
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Set timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        echo "Executing request...\n";
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        echo "HTTP Status Code: $httpCode\n";
        
        if ($error) {
            echo "cURL Error: $error\n";
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $count = is_array($data) ? count($data) : 0;
            echo "Success! Received $count device(s)\n";
            
            if ($count > 0) {
                echo "First device: " . json_encode($data[0]) . "\n";
            }
        } else {
            echo "Failed with status code: $httpCode\n";
            echo "Response: " . substr($response, 0, 500) . "\n";
        }
        
        curl_close($ch);
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}

// Use the variables defined at the top of the file
$configuredUrl = $apiUrl;
$configuredKey = $apiKey;

// Try to add specific group parameter like in your Python code
$groupParam = isset($_ENV['NETSHOT_GROUP']) ? $_ENV['NETSHOT_GROUP'] : 'ACCESS';

echo "=== Netshot API Connection Test ===\n";

// Create a NetshotAPI instance
$netshot = new NetshotAPI($apiUrl, $apiKey);

echo "\n=== Testing Netshot API Integration ===\n";
echo "API URL: {$apiUrl}\n";
echo "API Key: " . substr($apiKey, 0, 5) . "..." . substr($apiKey, -5) . "\n";
echo "Group Name: {$groupName}\n\n";

// Test 1: Get Groups
echo "1. Testing getting groups from Netshot...\n";
$groups = $netshot->getGroups();
echo "Found " . count($groups) . " groups in Netshot\n";
if (!empty($groups)) {
    echo "Sample groups: \n";
    $sampleCount = min(3, count($groups));
    for ($i = 0; $i < $sampleCount; $i++) {
        echo "  - " . $groups[$i]['name'] . " (ID: " . $groups[$i]['id'] . ")\n";
    }
}

// Test 2: Find Group ID by Name
echo "\n2. Finding group ID for '{$groupName}'...\n";
$groupId = $netshot->findGroupIdByName($groupName);
if ($groupId) {
    echo "Found group ID: {$groupId}\n";
} else {
    echo "Group '{$groupName}' not found in Netshot\n";
}

// Test 3: Get Devices in Group
echo "\n3. Getting devices for group ID: " . ($groupId ?: 'default') . "\n";
$devices = $netshot->getDevicesInGroup($groupId);
echo "Found " . count($devices) . " devices in group\n";
if (!empty($devices)) {
    echo "Sample devices: \n";
    $sampleCount = min(3, count($devices));
    for ($i = 0; $i < $sampleCount; $i++) {
        echo "  - " . ($devices[$i]['name'] ?? 'Unnamed') . 
             " (IP: " . ($devices[$i]['mgmtIp'] ?? $devices[$i]['ip'] ?? 'Unknown') . ")\n";
    }
}

// Test 4: Lookup specific hostname
$hostname = "GV-RC0052-CCAP002"; // From previous error log
echo "\n4. Looking up hostname: {$hostname}\n";
$device = $netshot->getDeviceByHostname($hostname);
if ($device) {
    echo "Found device in Netshot:\n";
    echo "  Name: " . ($device['name'] ?? 'Unknown') . "\n";
    echo "  IP: " . ($device['mgmtIp'] ?? $device['ip'] ?? 'Unknown') . "\n";
    echo "  Domain: " . ($device['domain'] ?? 'Unknown') . "\n";
    echo "  Family: " . ($device['family'] ?? 'Unknown') . "\n";
    echo "  Status: " . ($device['status'] ?? 'Unknown') . "\n";
} else {
    echo "Hostname not found in Netshot\n";
}

// Test the configured URL directly with curl
echo "\n5. Testing direct API connection with curl:\n";
testNetshotConnection($apiUrl, $apiKey);

// Try some alternative URLs if the primary one fails
echo "\nTrying alternative URLs:\n";

// Alternative 1: Try with HTTP instead of HTTPS
$alt1 = str_replace('https://', 'http://', $configuredUrl);
if ($alt1 !== $configuredUrl) {
    echo "\nAlternative 1 (HTTP instead of HTTPS):\n";
    testNetshotConnection($alt1, $configuredKey);
}

// Alternative 2: Try without /api suffix
$alt2 = preg_replace('/\/api$/', '', $configuredUrl);
if ($alt2 !== $configuredUrl) {
    echo "\nAlternative 2 (without /api suffix):\n";
    testNetshotConnection($alt2, $configuredKey);
}

// Alternative 3: Try with different domain
$configuredDomain = parse_url($configuredUrl, PHP_URL_HOST);
$alt3 = str_replace($configuredDomain, 'netshot.local', $configuredUrl);
echo "\nAlternative 3 (different domain):\n";
testNetshotConnection($alt3, $configuredKey);

// Alternative 4: Try another common domain
$alt4 = str_replace($configuredDomain, 'netshot-api.local', $configuredUrl);
echo "\nAlternative 4 (another domain):\n";
testNetshotConnection($alt4, $configuredKey);

echo "\n=== Connection Test Complete ===\n";
echo "If all tests failed, check:\n";
echo "1. Is Netshot server running and accessible from this server?\n";
echo "2. Is the API key valid and active?\n";
echo "3. Are there network/firewall restrictions between servers?\n";
echo "4. Does the Netshot API require additional authentication or headers?\n";
echo "5. Check server logs for more information about connection attempts\n";

// Create a .env file with correct settings if it doesn't exist
if (!file_exists(__DIR__ . '/.env')) {
    echo "\nCreating .env file with current settings...\n";
    $envContent = "# Environment Configuration\n\n";
    $envContent .= "# Database Configuration\n";
    $envContent .= "DB_HOST=" . ($_ENV['DB_HOST'] ?? 'localhost') . "\n";
    $envContent .= "DB_USER=" . ($_ENV['DB_USER'] ?? 'root') . "\n";
    $envContent .= "DB_PASS=" . ($_ENV['DB_PASS'] ?? 'your_password_here') . "\n\n";
    $envContent .= "# API Authentication Configuration\n";
    $envContent .= "API_USERNAME=" . ($_ENV['API_USERNAME'] ?? 'isw') . "\n";
    $envContent .= "API_PASSWORD=" . ($_ENV['API_PASSWORD'] ?? 'Spyem_OtGheb4') . "\n\n";
    $envContent .= "# Netshot API Configuration\n";
    $envContent .= "NETSHOT_API_URL=" . $configuredUrl . "\n";
    $envContent .= "NETSHOT_API_KEY=" . $configuredKey . "\n";
    file_put_contents(__DIR__ . '/.env', $envContent);
    echo "Created .env file with current settings\n";
}

// Instructions
echo "\nTo update Netshot settings, edit the .env file with the correct URL and API key\n";
