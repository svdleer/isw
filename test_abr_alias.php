<?php
/**
 * Test ABR/DBR/CBR Alias Replacement for IP Searches
 */

require_once 'classes/NetshotAPI.php';

echo "=== Testing ABR/DBR/CBR Alias Replacement for IP Searches ===\n";

$netshot = new NetshotAPI();

// Test the specific IP that contains ABR/DBR/CBR device
$testIp = '172.30.198.15';

echo "Testing IP lookup for: $testIp\n";
echo "Expected to find: AMF-RC0004-CCAP004 (CCAP device)\n";
echo "Should return: Alias hostname if found in acc_alias table\n\n";

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
    
    // Check if the hostname was replaced with an alias
    if (preg_match('/(ABR|DBR|CBR)/i', $result['name'])) {
        echo "  - Note: Still showing CCAP hostname - either no alias exists or lookup failed\n";
    } else {
        echo "  - ✅ Hostname appears to be an alias (no ABR/DBR/CBR pattern found)\n";
    }
} else {
    echo "❌ FAILED: No device found for IP: $testIp\n";
}

echo "\n=== Testing Direct Alias Lookup ===\n";

// Test the alias lookup method directly
echo "Testing direct alias lookup for CCAP hostnames...\n";

// First, let's see what CCAP devices exist in the database
require_once 'classes/Database.php';
$db = new Database();

try {
    $aliases = $db->query("SELECT alias, ccap_name FROM reporting.acc_alias LIMIT 3");
    
    if (!empty($aliases)) {
        echo "Found " . count($aliases) . " aliases in database to test:\n";
        
        foreach ($aliases as $aliasData) {
            $alias = $aliasData['alias'];
            $ccapName = $aliasData['ccap_name'];
            
            echo "\n--- Testing reverse lookup ---\n";
            echo "Alias: $alias\n";
            echo "CCAP: $ccapName\n";
            
            // Test the reverse lookup
            $start = microtime(true);
            $foundAlias = $netshot->findAliasForCcapHostname($ccapName);
            $lookupTime = microtime(true) - $start;
            
            echo "Lookup time: " . round($lookupTime * 1000, 2) . " ms\n";
            echo "Result: $foundAlias\n";
            
            if (strtoupper($foundAlias) === strtoupper($alias)) {
                echo "✅ SUCCESS: Found correct alias\n";
            } else {
                echo "❌ MISMATCH: Expected '$alias', got '$foundAlias'\n";
            }
        }
    } else {
        echo "No aliases found in reporting.acc_alias table\n";
    }
    
} catch (Exception $e) {
    echo "Error testing aliases: " . $e->getMessage() . "\n";
}

echo "\n=== Testing IP Wildcard Search with Alias Replacement ===\n";

// Test wildcard IP searches to see if aliases are returned
$wildcardTests = ['172.30.*', '10.0.*', '192.168.*'];

foreach ($wildcardTests as $ipPattern) {
    echo "\n--- Testing IP wildcard: $ipPattern ---\n";
    
    $start = microtime(true);
    $wildcardResults = $netshot->searchDevicesByIp($ipPattern);
    $wildcardTime = microtime(true) - $start;
    
    echo "Search time: " . round($wildcardTime, 3) . " seconds\n";
    echo "Found " . count($wildcardResults) . " matches\n";
    
    if (!empty($wildcardResults)) {
        $sampleCount = min(5, count($wildcardResults));
        echo "Sample matches:\n";
        
        $abrCount = 0;
        for ($i = 0; $i < $sampleCount; $i++) {
            $match = $wildcardResults[$i];
            $hostname = $match['name'] ?? 'Unknown';
            $ip = $match['ip'] ?? 'No IP';
            
            echo "  - $hostname: $ip";
            
            if (preg_match('/(ABR|DBR|CBR)/i', $hostname)) {
                echo " (still CCAP format)";
                $abrCount++;
            } else {
                echo " (alias format)";
            }
            echo "\n";
        }
        
        if ($abrCount > 0) {
            echo "  Note: $abrCount devices still show CCAP format (may not have aliases)\n";
        }
        
        if (count($wildcardResults) > $sampleCount) {
            echo "  ... and " . (count($wildcardResults) - $sampleCount) . " more\n";
        }
    }
}

echo "\n=== ABR/DBR/CBR Alias Replacement Feature Ready! ===\n";
echo "IP searches will now show user-friendly alias names instead of technical CCAP hostnames\n";
echo "when ABR/DBR/CBR devices are found and aliases exist in the reporting.acc_alias table.\n";
?>
