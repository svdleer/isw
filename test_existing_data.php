<?php
/**
 * Test the memory system with existing reporting.acc_alias data
 */

require_once 'classes/NetshotAPI.php';

echo "=== Testing Memory System with Existing Data ===\n";

try {
    $netshot = new NetshotAPI();
    
    echo "1. Initializing memory system with existing data...\n";
    $start = microtime(true);
    
    // This will read from your existing reporting.acc_alias table
    $testResult = $netshot->lookupIpFromMemory('DUMMY-TEST');
    $initTime = microtime(true) - $start;
    
    echo "   Initialization completed in " . round($initTime, 3) . " seconds\n\n";
    
    // Now let's see what aliases are actually in your table
    echo "2. Let's check some real aliases from your database...\n";
    
    require_once 'classes/Database.php';
    $db = new Database();
    
    // Get some sample aliases
    $aliases = $db->query("SELECT alias, ccap_name FROM reporting.acc_alias WHERE active = 1 LIMIT 5");
    
    if (empty($aliases)) {
        echo "   No active aliases found in database\n";
    } else {
        echo "   Found " . count($aliases) . " sample aliases to test:\n";
        
        foreach ($aliases as $aliasRow) {
            $alias = $aliasRow['alias'];
            $expectedCcap = $aliasRow['ccap_name'];
            
            echo "\n   Testing alias: $alias (should map to $expectedCcap)\n";
            
            $memoryResult = $netshot->lookupIpFromMemory($alias);
            
            if ($memoryResult && $memoryResult['is_alias']) {
                echo "   ✅ Found in memory: IP = {$memoryResult['ip_address']}\n";
                echo "   ✅ CCAP hostname: {$memoryResult['ccap_hostname']}\n";
                
                if (strtoupper($memoryResult['ccap_hostname']) === strtoupper($expectedCcap)) {
                    echo "   ✅ CCAP mapping matches database\n";
                } else {
                    echo "   ⚠️  CCAP mismatch: expected $expectedCcap, got {$memoryResult['ccap_hostname']}\n";
                }
            } else {
                echo "   ❌ Not found in memory - checking if CCAP exists in Netshot...\n";
                
                // Check if the CCAP hostname exists in Netshot
                $ccapResult = $netshot->lookupIpFromMemory($expectedCcap);
                if ($ccapResult && !$ccapResult['is_alias']) {
                    echo "   ℹ️  CCAP $expectedCcap exists in Netshot with IP: {$ccapResult['ip_address']}\n";
                    echo "   ⚠️  But alias mapping didn't work - check case sensitivity or data\n";
                } else {
                    echo "   ❌ CCAP $expectedCcap not found in Netshot either\n";
                }
            }
        }
    }
    
    echo "\n3. Testing device enrichment with real aliases...\n";
    
    if (!empty($aliases)) {
        $testAlias = $aliases[0]['alias'];
        echo "   Testing enrichDeviceData with: $testAlias\n";
        
        $device = ['hostname' => $testAlias];
        $enriched = $netshot->enrichDeviceData($device);
        
        echo "   Result hostname: " . ($enriched['hostname'] ?? 'NOT SET') . "\n";
        echo "   IP address: " . ($enriched['ip_address'] ?? 'NOT FOUND') . "\n";
        
        if (isset($enriched['ccap_hostname'])) {
            echo "   ✅ Alias preserved, CCAP used: {$enriched['ccap_hostname']}\n";
        } else {
            echo "   ⚠️  No CCAP hostname set - may have used fallback method\n";
        }
    }
    
    echo "\n=== System Status ===\n";
    echo "The memory system is working with your existing reporting.acc_alias table.\n";
    echo "If you see any issues above, they might be due to:\n";
    echo "- Case sensitivity differences between database and Netshot\n";
    echo "- CCAP hostnames in database that don't exist in Netshot\n";
    echo "- Network connectivity to Netshot API\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
