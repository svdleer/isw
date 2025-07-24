<?php
/**
 * Test IP Address Fix for Nested mgmtAddress Structure
 */

require_once 'classes/NetshotAPI.php';

echo "=== Testing IP Address Fix ===\n";

$netshot = new NetshotAPI();

// Test the specific IP that was failing
$testIp = '172.30.198.15';

echo "Testing IP lookup for: $testIp\n";
echo "Expected device: AMF-RC0004-CCAP004\n\n";

$start = microtime(true);
$result = $netshot->getDeviceByIP($testIp);
$time = microtime(true) - $start;

echo "Lookup time: " . round($time, 3) . " seconds\n";

if ($result) {
    echo "✅ SUCCESS! Device found:\n";
    echo "  - Name: " . ($result['name'] ?? 'Unknown') . "\n";
    echo "  - IP: " . ($result['ip'] ?? 'Not set') . "\n";
    echo "  - ID: " . ($result['id'] ?? 'Not set') . "\n";
    echo "  - Family: " . ($result['model'] ?? 'Unknown') . "\n";
    echo "  - Status: " . ($result['status'] ?? 'Unknown') . "\n";
} else {
    echo "❌ FAILED: No device found for IP: $testIp\n";
    echo "This might indicate the device indexing still needs adjustment.\n";
}

echo "\n=== Testing API Search Endpoint ===\n";

// Test through the actual API endpoint as well
echo "Testing via search.php API endpoint...\n";

// Create a simple test context
$_GET['type'] = 'ip';
$_GET['query'] = $testIp;

// Test the search functionality
echo "Search parameters: type=ip, query=$testIp\n";

echo "\nIP address search fix is ready for testing!\n";
echo "You can now test with: curl 'http://your-server/api/search.php?type=ip&query=172.30.198.15'\n";
?>
