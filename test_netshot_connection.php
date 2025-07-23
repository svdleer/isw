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
    $apiUrl = 'https://netshot.oss.local'; // Remove /api as it might be added in the test
}

$apiKey = $_ENV['NETSHOT_API_KEY'] ?? $_ENV['NETSHOT_API_TOKEN'] ?? $_ENV['NETSHOT_OSS_TOKEN'] ?? 'UqRf6NkgvKru3rxRRrRKck1VoANQJvP2';

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

// Test the configured URL
echo "\nTesting with configured URL:\n";
testNetshotConnection($configuredUrl, $configuredKey);

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
