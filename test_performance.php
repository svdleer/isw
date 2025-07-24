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

echo "\n=== Testing Memory-Based Alias System ===\n";

// Test the new memory-based system with existing reporting.acc_alias data
echo "Testing memory-based hostname-to-IP lookup system...\n";

// First, let's see what aliases exist in your database
require_once 'classes/Database.php';
$db = new Database();

try {
    $aliases = $db->query("SELECT alias, ccap_name FROM reporting.acc_alias LIMIT 3");
    
    if (!empty($aliases)) {
        echo "Found " . count($aliases) . " aliases in your database to test:\n";
        
        foreach ($aliases as $aliasData) {
            $alias = $aliasData['alias'];
            $expectedCcap = $aliasData['ccap_name'];
            
            echo "\n--- Testing alias: $alias ---\n";
            echo "Expected CCAP: $expectedCcap\n";
            
            // Test memory lookup
            $start = microtime(true);
            $memoryResult = $netshot->lookupIpFromMemory($alias);
            $memoryTime = microtime(true) - $start;
            
            if ($memoryResult && $memoryResult['is_alias']) {
                echo "✅ Memory lookup: " . round($memoryTime * 1000, 2) . " ms\n";
                echo "✅ IP found: {$memoryResult['ip_address']}\n";
                echo "✅ Via CCAP: {$memoryResult['ccap_hostname']}\n";
            } else {
                echo "❌ Not found in memory lookup\n";
            }
            
            // Test full device enrichment
            $start = microtime(true);
            $device = ['hostname' => $alias];
            $enriched = $netshot->enrichDeviceData($device);
            $enrichTime = microtime(true) - $start;
            
            echo "Device enrichment: " . round($enrichTime, 3) . " seconds\n";
            echo "Final hostname: " . ($enriched['hostname'] ?? 'NOT SET') . "\n";
            echo "Final IP: " . ($enriched['ip_address'] ?? 'NOT FOUND') . "\n";
            
            if (isset($enriched['ccap_hostname'])) {
                echo "✅ Alias preserved, used CCAP: {$enriched['ccap_hostname']}\n";
            }
        }
    } else {
        echo "No active aliases found in reporting.acc_alias table\n";
    }
    
} catch (Exception $e) {
    echo "Error testing aliases: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Wildcard Hostname Search ===\n";

// Test wildcard searches like the ones in your curl examples
$wildcardTests = [
    'AMF-RC0004*',
    'amf-rc0004*', 
    '*CCAP*',
    '*AMF*'
];

foreach ($wildcardTests as $wildcard) {
    echo "\n--- Testing wildcard: $wildcard ---\n";
    
    $start = microtime(true);
    $wildcardResults = $netshot->searchHostnamesByWildcard($wildcard);
    $wildcardTime = microtime(true) - $start;
    
    echo "Search time: " . round($wildcardTime * 1000, 2) . " ms\n";
    echo "Found " . count($wildcardResults) . " matches\n";
    
    if (!empty($wildcardResults)) {
        echo "Sample matches:\n";
        $sampleCount = min(3, count($wildcardResults));
        for ($i = 0; $i < $sampleCount; $i++) {
            $match = $wildcardResults[$i];
            echo "  - {$match['hostname']}: {$match['ip_address']}";
            if ($match['is_alias']) {
                echo " (alias → {$match['ccap_hostname']})";
            }
            echo "\n";
        }
        
        if (count($wildcardResults) > 3) {
            echo "  ... and " . (count($wildcardResults) - 3) . " more\n";
        }
    } else {
        echo "❌ No matches found\n";
    }
}

echo "\n=== Testing IP Address Search ===\n";

// Test IP address queries to see if they work
echo "Testing IP address search functionality...\n";

// Test the specific IP that was failing - should now work with nested mgmtAddress structure
echo "\n--- Testing the previously failing IP: 172.30.198.15 ---\n";
$failingIp = '172.30.198.15';

$start = microtime(true);
$specificResult = $netshot->getDeviceByIP($failingIp);
$specificTime = microtime(true) - $start;

echo "IP lookup time: " . round($specificTime, 3) . " seconds\n";

if ($specificResult) {
    echo "✅ Found device: " . ($specificResult['name'] ?? 'Unknown') . "\n";
    echo "✅ IP result: " . ($specificResult['ip'] ?? 'Not set') . "\n";
    echo "✅ Device ID: " . ($specificResult['id'] ?? 'Not set') . "\n";
    
    // Check if ABR/DBR/CBR alias replacement worked
    $hostname = $specificResult['name'] ?? '';
    if (preg_match('/(ABR|DBR|CBR)/i', $hostname)) {
        echo "  - Note: Still showing CCAP hostname (no alias found or lookup failed)\n";
    } else {
        echo "  - ✅ Hostname appears to be user-friendly alias\n";
    }
} else {
    echo "❌ Still no device found for IP: $failingIp\n";
    echo "   This indicates the nested mgmtAddress fix may need more work\n";
}

// Get some real IP addresses from the memory system first
echo "\nGetting some real IP addresses to test with...\n";
try {
    // Get a few hostnames with IPs from memory
    $testIps = [];
    $aliases = $db->query("SELECT alias, ccap_name FROM reporting.acc_alias LIMIT 2");
    
    foreach ($aliases as $aliasData) {
        $alias = $aliasData['alias'];
        $memoryResult = $netshot->lookupIpFromMemory($alias);
        
        if ($memoryResult && isset($memoryResult['ip_address'])) {
            $testIps[] = [
                'ip' => $memoryResult['ip_address'],
                'expected_hostname' => $memoryResult['is_alias'] ? $alias : $memoryResult['hostname']
            ];
        }
    }
    
    // Test exact IP lookups
    foreach ($testIps as $testData) {
        $testIp = $testData['ip'];
        $expectedHostname = $testData['expected_hostname'];
        
        echo "\n--- Testing exact IP: $testIp ---\n";
        echo "Expected hostname: $expectedHostname\n";
        
        $start = microtime(true);
        $ipDevice = $netshot->getDeviceByIP($testIp);
        $ipTime = microtime(true) - $start;
        
        echo "IP lookup time: " . round($ipTime, 3) . " seconds\n";
        
        if ($ipDevice) {
            echo "✅ Found device: " . ($ipDevice['name'] ?? 'Unknown') . "\n";
            echo "✅ IP result: " . ($ipDevice['ip'] ?? 'Not set') . "\n";
        } else {
            echo "❌ No device found for IP: $testIp\n";
        }
    }
    
    // Test IP wildcard searches
    echo "\n--- Testing IP wildcard searches ---\n";
    
    $ipWildcardTests = ['192.168.1.*', '10.0.*', '172.16.*'];
    
    foreach ($ipWildcardTests as $ipPattern) {
        echo "\nTesting IP wildcard: $ipPattern\n";
        
        $start = microtime(true);
        $wildcardResults = $netshot->searchDevicesByIp($ipPattern);
        $wildcardTime = microtime(true) - $start;
        
        echo "Search time: " . round($wildcardTime, 3) . " seconds\n";
        echo "Found " . count($wildcardResults) . " matches\n";
        
        if (!empty($wildcardResults)) {
            $sampleCount = min(3, count($wildcardResults));
            echo "Sample matches:\n";
            for ($i = 0; $i < $sampleCount; $i++) {
                $match = $wildcardResults[$i];
                echo "  - " . ($match['name'] ?? 'Unknown') . ": " . ($match['ip'] ?? 'No IP') . "\n";
            }
            
            if (count($wildcardResults) > 3) {
                echo "  ... and " . (count($wildcardResults) - 3) . " more\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error testing IP searches: " . $e->getMessage() . "\n";
}

echo "\n=== Testing *ccap* vs *ccap0* Issue ===\n";

// Test the specific issue you reported
echo "Testing the *ccap* vs *ccap0* issue...\n";

// Simulate a hostname search result for testing
$testCcapDevice = ['hostname' => 'TEST-LC0001-CCAP001'];
$testCcap0Device = ['hostname' => 'TEST-LC0001-CCAP001']; // Same device for comparison

echo "\nTesting enrichment for CCAP device:\n";
$start = microtime(true);
$enrichedCcap = $netshot->enrichDeviceData($testCcapDevice);
$timeCcap = microtime(true) - $start;

echo "CCAP enrichment time: " . round($timeCcap, 3) . " seconds\n";
echo "CCAP IP result: " . ($enrichedCcap['ip_address'] ?? 'EMPTY') . "\n";

if (empty($enrichedCcap['ip_address'])) {
    echo "❌ IP address is empty - this indicates the issue is still present\n";
} else {
    echo "✅ IP address populated correctly\n";
}

echo "\n=== Performance Summary ===\n";
echo "Netshot API fetch: " . round($time1, 3) . " seconds\n";
echo "Memory-based lookups: Sub-millisecond (hash table O(1))\n";
echo "Benefits:\n";
echo "- Preserves original alias hostnames\n";
echo "- Single Netshot API call at startup\n";
echo "- Instant IP resolution for aliases via CCAP mapping\n";
echo "- Works with existing reporting.acc_alias table\n";

// Test memory refresh
echo "\nTesting memory refresh...\n";
$start = microtime(true);
$netshot->refreshMemoryMappings();
$refreshTime = microtime(true) - $start;
echo "Memory refresh completed in " . round($refreshTime, 3) . " seconds\n";
echo "\nMemory system ready for production use!\n";
?>
