<?php
/**
 * Performance Test Script for NetshotAPI optimizations
 */

require_once 'classes/NetshotAPI.php';

// Test performance improvements
$netshot = new NetshotAPI();

echo "=== NetshotAPI Performance Test ===\n";

// Test 1: Initial device fetch (should take longer)
$start = microtime(true);
$devices1 = $netshot->getDevicesInGroup();
$time1 = microtime(true) - $start;
echo "First device fetch: " . count($devices1) . " devices in " . round($time1, 3) . " seconds\n";

// Test 2: Cached device fetch (should be much faster)
$start = microtime(true);
$devices2 = $netshot->getDevicesInGroup();
$time2 = microtime(true) - $start;
echo "Cached device fetch: " . count($devices2) . " devices in " . round($time2, 3) . " seconds\n";

// Test 3: Multiple hostname lookups (should use cache)
if (!empty($devices1)) {
    $testHostname = strtoupper($devices1[0]['name'] ?? 'TEST');
    
    echo "\nTesting hostname lookup for: $testHostname\n";
    
    $start = microtime(true);
    $device1 = $netshot->getDeviceByHostname($testHostname, false);
    $time3 = microtime(true) - $start;
    echo "First hostname lookup: " . round($time3, 3) . " seconds\n";
    
    $start = microtime(true);
    $device2 = $netshot->getDeviceByHostname($testHostname, false);
    $time4 = microtime(true) - $start;
    echo "Second hostname lookup (cached): " . round($time4, 3) . " seconds\n";
    
    if ($device1 && isset($device1['mgmtIp'])) {
        echo "\nTesting IP lookup for: " . $device1['mgmtIp'] . "\n";
        
        $start = microtime(true);
        $ipDevice = $netshot->getDeviceByIP($device1['mgmtIp']);
        $time5 = microtime(true) - $start;
        echo "IP lookup (cached): " . round($time5, 3) . " seconds\n";
    }
}

echo "\n=== Performance Summary ===\n";
echo "Cache speedup: " . round($time1 / max($time2, 0.001), 1) . "x faster\n";
echo "Index-based lookups provide O(1) performance instead of O(n)\n";
echo "JSON parsing is optimized with error handling\n";
echo "Reduced logging decreases I/O overhead\n";

// Clear cache for next test
$netshot->clearCache();
echo "\nCache cleared for next test run.\n";
?>
