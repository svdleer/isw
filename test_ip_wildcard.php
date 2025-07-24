<?php
/**
 * Test script to verify IP wildcard search fixes
 */

require_once 'classes/NetshotAPI.php';

echo "=== IP Wildcard Search Test ===\n";

$netshot = new NetshotAPI();

// Test the searchDevicesByIp method directly
echo "\n1. Testing NetshotAPI searchDevicesByIp method directly:\n";

$testPatterns = ['192.168.*', '10.10.1.*', '172.16.*'];

foreach ($testPatterns as $pattern) {
    echo "\nTesting pattern: $pattern\n";
    
    $start = microtime(true);
    $results = $netshot->searchDevicesByIp($pattern);
    $time = microtime(true) - $start;
    
    echo "Found " . count($results) . " devices in " . round($time, 3) . " seconds\n";
    
    // Show first 3 results
    $showCount = min(3, count($results));
    for ($i = 0; $i < $showCount; $i++) {
        $device = $results[$i];
        echo "  - " . ($device['name'] ?? 'Unknown') . " -> " . ($device['ip'] ?? 'No IP') . "\n";
    }
    
    if (count($results) > 3) {
        echo "  ... and " . (count($results) - 3) . " more\n";
    }
}

// Test with specific IP patterns that should return results
echo "\n2. Testing with common network ranges:\n";

$commonPatterns = [
    '192.168.1.*',  // Common home network
    '10.0.*',       // Corporate network
    '172.16.*',     // Private network
    '169.254.*'     // Link-local
];

foreach ($commonPatterns as $pattern) {
    echo "\nTesting: $pattern\n";
    
    $results = $netshot->searchDevicesByIp($pattern);
    echo "Results: " . count($results) . " devices\n";
    
    // Check if IP addresses are properly populated
    $withIp = 0;
    $withoutIp = 0;
    
    foreach ($results as $device) {
        if (!empty($device['ip'])) {
            $withIp++;
        } else {
            $withoutIp++;
        }
    }
    
    echo "  With IP: $withIp, Without IP: $withoutIp\n";
    
    if ($withIp > 0) {
        echo "  ✓ IP addresses are populated correctly\n";
    } else if (count($results) > 0) {
        echo "  ✗ Results found but no IP addresses populated\n";
    } else {
        echo "  - No devices found for this pattern\n";
    }
}

// Test the regex pattern conversion
echo "\n3. Testing pattern conversion:\n";

$testConversions = [
    '*' => 'Should match anything',
    '192.168.1.*' => 'Should match 192.168.1.xxx',
    '10.*.*.1' => 'Should match 10.xxx.xxx.1',
    '172.16.%' => 'Should match 172.16.xxx'
];

foreach ($testConversions as $pattern => $description) {
    echo "$pattern -> $description\n";
    
    // Test the pattern to regex conversion (simulate the internal method)
    $regexPattern = '/^' . str_replace(['%', '*', '.'], ['.*', '.*', '\\.'], $pattern) . '$/i';
    echo "  Regex: $regexPattern\n";
    
    // Test a few sample IPs
    $testIps = ['192.168.1.100', '10.0.0.1', '172.16.50.1', '8.8.8.8'];
    foreach ($testIps as $testIp) {
        $matches = preg_match($regexPattern, $testIp);
        if ($matches) {
            echo "    ✓ Matches: $testIp\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
echo "If IP addresses are showing as empty in wildcard searches,\n";
echo "the fixes should resolve this issue.\n";
?>
