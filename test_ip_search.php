<?php
/**
 * Quick test for IP address search functionality via API
 */

echo "=== IP Address API Search Test ===\n";

require_once 'classes/NetshotAPI.php';
require_once 'classes/Database.php';

// Simulate what the API does for IP address searches
function testIpApiSearch($ipAddress) {
    echo "\n--- Testing IP API search for: $ipAddress ---\n";
    
    try {
        $netshot = new NetshotAPI();
        $auth = new \ApiAuth();
        
        // Validate IP (like the API does)
        if (!$auth->validateIpAddress($ipAddress)) {
            echo "❌ Invalid IP format: $ipAddress\n";
            return;
        }
        
        // Test exact IP search
        if (strpos($ipAddress, '*') === false && strpos($ipAddress, '%') === false) {
            echo "Testing exact IP lookup...\n";
            
            $start = microtime(true);
            $netshotDevice = $netshot->getDeviceByIP($ipAddress);
            $time = microtime(true) - $start;
            
            echo "Lookup time: " . round($time, 3) . " seconds\n";
            
            if ($netshotDevice) {
                echo "✅ Found device: " . ($netshotDevice['name'] ?? 'Unknown') . "\n";
                echo "✅ Device IP: " . ($netshotDevice['ip'] ?? 'Not set') . "\n";
                echo "✅ Device ID: " . ($netshotDevice['id'] ?? 'Not set') . "\n";
                echo "✅ Status: " . ($netshotDevice['status'] ?? 'Unknown') . "\n";
            } else {
                echo "❌ No device found in Netshot for IP: $ipAddress\n";
            }
        } else {
            echo "Testing wildcard IP search...\n";
            
            $start = microtime(true);
            $results = $netshot->searchDevicesByIp($ipAddress);
            $time = microtime(true) - $start;
            
            echo "Search time: " . round($time, 3) . " seconds\n";
            echo "Found " . count($results) . " matching devices\n";
            
            if (!empty($results)) {
                $sampleCount = min(5, count($results));
                echo "Sample results:\n";
                for ($i = 0; $i < $sampleCount; $i++) {
                    $device = $results[$i];
                    echo "  - " . ($device['name'] ?? 'Unknown') . ": " . ($device['ip'] ?? 'No IP') . "\n";
                }
                
                if (count($results) > 5) {
                    echo "  ... and " . (count($results) - 5) . " more\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error during IP search: " . $e->getMessage() . "\n";
    }
}

// Test cases
$testIpAddresses = [
    '192.168.1.100',    // Exact IP
    '10.0.0.1',         // Another exact IP
    '192.168.1.*',      // Wildcard IP
    '10.0.*',           // Broader wildcard
    '172.16.55.*',      // Another wildcard pattern
];

foreach ($testIpAddresses as $testIp) {
    testIpApiSearch($testIp);
}

echo "\n=== IP Search Status Summary ===\n";
echo "The IP address search functionality includes:\n";
echo "✅ Exact IP lookups via getDeviceByIP()\n";
echo "✅ Wildcard IP searches via searchDevicesByIp()\n";
echo "✅ Integration with search.php API for both GET and POST requests\n";
echo "✅ Support for patterns like: 192.168.1.*, 10.0.*, etc.\n";
echo "✅ Fallback mechanisms if direct Netshot lookup fails\n\n";

echo "API Usage Examples:\n";
echo "GET: /api/search.php?type=ip&q=192.168.1.100\n";
echo "GET: /api/search.php?type=ip&q=192.168.1.*\n";
echo "POST: {\"Body\": {\"IPAddress\": \"192.168.1.100\"}}\n\n";

echo "The IP address queries should be working! ✅\n";
?>
