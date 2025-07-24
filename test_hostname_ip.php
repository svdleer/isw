<?php
/**
 * Test script to verify hostname search IP address fixes
 */

require_once 'classes/NetshotAPI.php';

echo "=== Hostname Search IP Address Test ===\n";

$netshot = new NetshotAPI();

// Test individual enrichDeviceData method first
echo "\n1. Testing enrichDeviceData method directly:\n";

// Test with a few known hostnames
$testHostnames = ['HLEN-LC0023-CCAP001', 'HLM-LC0001-CCAP201', 'HLM-LC0122-CCAP201'];

foreach ($testHostnames as $hostname) {
    echo "\nTesting hostname: $hostname\n";
    
    // Create a mock database device entry
    $mockDevice = [
        'hostname' => $hostname
    ];
    
    // Test enrichment
    $start = microtime(true);
    $enriched = $netshot->enrichDeviceData($mockDevice);
    $time = microtime(true) - $start;
    
    echo "Enrichment time: " . round($time, 3) . " seconds\n";
    echo "Result hostname: " . ($enriched['hostname'] ?? 'Missing') . "\n";
    echo "Result IP: " . ($enriched['ip_address'] ?? 'Missing/Empty') . "\n";
    
    if (empty($enriched['ip_address'])) {
        echo "❌ IP address is empty - this is the problem!\n";
        
        // Try direct Netshot lookup to see if device exists
        $directDevice = $netshot->getDeviceByHostname($hostname, false);
        if ($directDevice) {
            echo "✓ Device exists in Netshot: " . ($directDevice['name'] ?? 'Unknown') . "\n";
            
            // Check all possible IP fields
            $ipFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
            echo "Available IP fields in Netshot device:\n";
            foreach ($ipFields as $field) {
                if (isset($directDevice[$field])) {
                    $value = is_array($directDevice[$field]) ? 'Array: ' . json_encode($directDevice[$field]) : $directDevice[$field];
                    echo "  $field: $value\n";
                }
            }
        } else {
            echo "❌ Device not found in Netshot with getDeviceByHostname\n";
        }
    } else {
        echo "✅ IP address populated correctly: " . $enriched['ip_address'] . "\n";
    }
}

// Test 2: Check if devices are in the Netshot cache
echo "\n2. Testing Netshot device cache:\n";

$start = microtime(true);
$allDevices = $netshot->getDevicesInGroup();
$time = microtime(true) - $start;

echo "Retrieved " . count($allDevices) . " devices from Netshot in " . round($time, 3) . " seconds\n";

if (count($allDevices) > 0) {
    echo "Sample device structure:\n";
    $sampleDevice = $allDevices[0];
    foreach ($sampleDevice as $key => $value) {
        if (!is_array($value)) {
            echo "  $key: $value\n";
        } else {
            echo "  $key: [Array with " . count($value) . " elements]\n";
        }
    }
    
    // Check if our test hostnames exist in the cache
    echo "\nChecking if test hostnames exist in Netshot cache:\n";
    foreach ($testHostnames as $hostname) {
        $found = false;
        foreach ($allDevices as $device) {
            if (isset($device['name']) && strtoupper($device['name']) === strtoupper($hostname)) {
                $found = true;
                $ipFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                $deviceIp = null;
                foreach ($ipFields as $field) {
                    if (isset($device[$field]) && !empty($device[$field])) {
                        $deviceIp = is_array($device[$field]) ? $device[$field] : $device[$field];
                        break;
                    }
                }
                echo "✓ Found $hostname in cache, IP: " . ($deviceIp ? $deviceIp : 'None') . "\n";
                break;
            }
        }
        if (!$found) {
            echo "❌ $hostname not found in Netshot cache\n";
        }
    }
} else {
    echo "❌ No devices retrieved from Netshot - this is a major issue!\n";
    echo "Check your Netshot configuration and group settings.\n";
}

// Test 3: Debug the enrichDeviceData method
echo "\n3. Debugging enrichDeviceData method:\n";

if (count($allDevices) > 0) {
    $testDevice = ['hostname' => strtoupper($allDevices[0]['name'] ?? 'TEST')];
    echo "Testing with device: " . $testDevice['hostname'] . "\n";
    
    $enriched = $netshot->enrichDeviceData($testDevice);
    echo "Enriched result:\n";
    foreach ($enriched as $key => $value) {
        echo "  $key: " . (is_array($value) ? '[Array]' : $value) . "\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "If IP addresses are still empty, the issue is in the enrichDeviceData method\n";
echo "or the Netshot device cache is not being populated correctly.\n";
?>
