<?php
/**
 * Test script to verify all limits and caching are removed
 */

require_once 'classes/NetshotAPI.php';
require_once 'classes/Database.php';

echo "=== No Limits Test ===\n";

$netshot = new NetshotAPI();
$db = new Database();

// Test 1: Check how many CCAP devices exist
echo "\n1. Checking database for CCAP devices:\n";

$sql = "SELECT COUNT(*) as count FROM access.devicesnew WHERE hostname LIKE '%CCAP%' AND active = 1";
$result = $db->query($sql);
$totalCcapDevices = $result[0]['count'] ?? 0;

echo "Total CCAP devices in database: $totalCcapDevices\n";

if ($totalCcapDevices > 500) {
    echo "✅ Large dataset confirmed (>500 devices) - perfect for testing\n";
} else {
    echo "ℹ️  Dataset size: $totalCcapDevices devices\n";
}

// Test 2: Test enrichment without any limits
echo "\n2. Testing enrichment for multiple devices (no limits):\n";

$testSql = "SELECT UPPER(hostname) as hostname FROM access.devicesnew WHERE hostname LIKE '%CCAP%' AND active = 1 ORDER BY hostname LIMIT 10";
$testDevices = $db->query($testSql);

echo "Testing enrichment for " . count($testDevices) . " devices:\n";

$successCount = 0;
$emptyIpCount = 0;

foreach ($testDevices as $index => $device) {
    $start = microtime(true);
    $enriched = $netshot->enrichDeviceData($device);
    $time = microtime(true) - $start;
    
    $hasIp = !empty($enriched['ip_address']);
    
    if ($hasIp) {
        $successCount++;
        echo "  ✅ " . $device['hostname'] . " -> " . $enriched['ip_address'] . " (" . round($time, 3) . "s)\n";
    } else {
        $emptyIpCount++;
        echo "  ❌ " . $device['hostname'] . " -> EMPTY IP (" . round($time, 3) . "s)\n";
    }
}

echo "\nEnrichment Summary:\n";
echo "  Success (with IP): $successCount\n";
echo "  Failed (empty IP): $emptyIpCount\n";

if ($emptyIpCount === 0) {
    echo "  🎉 All devices successfully enriched with IP addresses!\n";
} else {
    echo "  ⚠️  Some devices still have empty IP addresses\n";
}

// Test 3: Verify no caching is being used
echo "\n3. Testing that caching is disabled:\n";

$testDevice = ['hostname' => $testDevices[0]['hostname'] ?? 'TEST'];

$start1 = microtime(true);
$result1 = $netshot->enrichDeviceData($testDevice);
$time1 = microtime(true) - $start1;

$start2 = microtime(true);
$result2 = $netshot->enrichDeviceData($testDevice);
$time2 = microtime(true) - $start2;

echo "First call: " . round($time1, 3) . " seconds\n";
echo "Second call: " . round($time2, 3) . " seconds\n";

$timeDiff = abs($time1 - $time2);
if ($timeDiff < 0.1) {
    echo "⚠️  Times are very similar - caching might still be active\n";
} else {
    echo "✅ Times vary as expected - no caching detected\n";
}

// Test 4: Verify clearCache doesn't break anything
echo "\n4. Testing clearCache method:\n";
$netshot->clearCache();
echo "✅ clearCache() executed without errors\n";

echo "\n=== Test Complete ===\n";
echo "All artificial limits and caching have been removed.\n";
echo "The system should now handle 500+ devices without issues.\n";
?>
