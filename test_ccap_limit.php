<?php
/**
 * Test script to verify *ccap* vs *ccap0* issue fix
 */

require_once 'classes/Database.php';
require_once 'classes/NetshotAPI.php';

echo "=== CCAP Search Comparison Test ===\n";

$db = new Database();
$netshot = new NetshotAPI();

// Test 1: Check how many results each search returns from database
echo "\n1. Database result counts:\n";

$ccapQuery = "SELECT UPPER(hostname) as hostname FROM access.devicesnew WHERE hostname LIKE '%CCAP%' AND active = 1 ORDER BY hostname";
$ccap0Query = "SELECT UPPER(hostname) as hostname FROM access.devicesnew WHERE hostname LIKE '%CCAP0%' AND active = 1 ORDER BY hostname";

$ccapResults = $db->query($ccapQuery);
$ccap0Results = $db->query($ccap0Query);

echo "*ccap* search: " . count($ccapResults) . " results\n";
echo "*ccap0* search: " . count($ccap0Results) . " results\n";

if (count($ccapResults) > 50) {
    echo "⚠️  *ccap* search returns >" . count($ccapResults) . " results - this was triggering the 50-device limit!\n";
} else {
    echo "✅ *ccap* search is under 50 results\n";
}

// Test 2: Test enrichment for a sample of each
echo "\n2. Testing enrichment for sample devices:\n";

$sampleCcap = array_slice($ccapResults, 0, 3);
$sampleCcap0 = array_slice($ccap0Results, 0, 3);

echo "\nTesting *ccap* sample devices:\n";
foreach ($sampleCcap as $device) {
    $enriched = $netshot->enrichDeviceData($device);
    $ip = $enriched['ip_address'] ?? 'EMPTY';
    echo "  " . $device['hostname'] . " -> IP: $ip\n";
}

echo "\nTesting *ccap0* sample devices:\n";
foreach ($sampleCcap0 as $device) {
    $enriched = $netshot->enrichDeviceData($device);
    $ip = $enriched['ip_address'] ?? 'EMPTY';
    echo "  " . $device['hostname'] . " -> IP: $ip\n";
}

// Test 3: Check if our fix works by testing devices beyond the 50th position
if (count($ccapResults) > 55) {
    echo "\n3. Testing devices beyond position 50 (these would have been cut off):\n";
    
    $beyondLimit = array_slice($ccapResults, 50, 5); // Get devices 51-55
    foreach ($beyondLimit as $device) {
        $enriched = $netshot->enrichDeviceData($device);
        $ip = $enriched['ip_address'] ?? 'EMPTY';
        echo "  Position 50+: " . $device['hostname'] . " -> IP: $ip\n";
        
        if ($ip === 'EMPTY') {
            echo "    ❌ Still no IP - the issue persists!\n";
        } else {
            echo "    ✅ IP populated correctly\n";
        }
    }
} else {
    echo "\n3. Not enough *ccap* results to test beyond position 50\n";
}

echo "\n=== Test Complete ===\n";
echo "If devices beyond position 50 now have IP addresses,\n";
echo "the fix for the 50-device limit is working.\n";
?>
