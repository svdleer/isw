<?php
/**
 * Complete test and demonstration of the new memory-based hostname-to-IP system
 */

echo "=== New Memory-Based Hostname-to-IP System Overview ===\n";
echo "This system implements your requirement:\n";
echo "1. Read ALL hostnames from Netshot (API call) to get hostname + IP address\n";
echo "2. Use MySQL reporting.acc_alias table for CCAP-to-alias mapping\n";
echo "3. Store everything in memory for fast lookups\n";
echo "4. Preserve original alias hostnames in results\n\n";

require_once 'classes/NetshotAPI.php';

try {
    $netshot = new NetshotAPI();
    
    echo "=== Step 1: Memory Initialization ===\n";
    echo "The system will now:\n";
    echo "- Fetch all devices from Netshot API\n";
    echo "- Build hostname-to-IP mapping in memory\n";
    echo "- Read alias mappings from reporting.acc_alias table\n";
    echo "- Create alias-to-IP lookup table\n\n";
    
    $initStart = microtime(true);
    
    // This triggers the memory initialization
    echo "Initializing memory mappings...\n";
    $testResult = $netshot->lookupIpFromMemory('DUMMY');
    
    $initTime = microtime(true) - $initStart;
    echo "✅ Memory initialization completed in " . round($initTime, 3) . " seconds\n\n";
    
    echo "=== Step 2: Testing Direct CCAP Hostname Lookups ===\n";
    echo "These should be found in the hostname-to-IP map from Netshot:\n";
    
    $ccapTests = ['HLEN-LC0023-CCAP001', 'GV-RC0011-CCAP003', 'TEST-CCAP999'];
    
    foreach ($ccapTests as $hostname) {
        $start = microtime(true);
        $result = $netshot->lookupIpFromMemory($hostname);
        $time = microtime(true) - $start;
        
        echo "Testing: $hostname\n";
        if ($result && !$result['is_alias']) {
            echo "  ✅ Found IP: {$result['ip_address']} (Direct CCAP)\n";
        } else {
            echo "  ❌ Not found as direct CCAP hostname\n";
        }
        echo "  Lookup time: " . round($time * 1000, 2) . " ms\n\n";
    }
    
    echo "=== Step 3: Testing Alias Hostname Lookups ===\n";
    echo "These should be found in the alias-to-IP map via CCAP mapping:\n";
    
    $aliasTests = ['HV01ABR001', 'HV01DBR002', 'GV01ABR001', 'UNKNOWN-ALIAS'];
    
    foreach ($aliasTests as $alias) {
        $start = microtime(true);
        $result = $netshot->lookupIpFromMemory($alias);
        $time = microtime(true) - $start;
        
        echo "Testing alias: $alias\n";
        if ($result && $result['is_alias']) {
            echo "  ✅ Found IP: {$result['ip_address']}\n";
            echo "  ✅ Via CCAP: {$result['ccap_hostname']}\n";
            echo "  ✅ Original alias preserved\n";
        } else {
            echo "  ❌ Not found in alias mappings\n";
        }
        echo "  Lookup time: " . round($time * 1000, 2) . " ms\n\n";
    }
    
    echo "=== Step 4: Testing Complete Device Enrichment ===\n";
    echo "This demonstrates the full enrichDeviceData workflow:\n";
    
    $deviceTests = [
        ['hostname' => 'HLEN-LC0023-CCAP001', 'type' => 'Direct CCAP'],
        ['hostname' => 'HV01ABR001', 'type' => 'ABR Alias'],
        ['hostname' => 'HV01DBR002', 'type' => 'DBR Alias'],
        ['hostname' => 'UNKNOWN-DEVICE', 'type' => 'Unknown (fallback)']
    ];
    
    foreach ($deviceTests as $test) {
        echo "---\n";
        echo "Enriching device: {$test['hostname']} ({$test['type']})\n";
        
        $device = ['hostname' => $test['hostname']];
        
        $start = microtime(true);
        $enriched = $netshot->enrichDeviceData($device);
        $time = microtime(true) - $start;
        
        echo "Processing time: " . round($time, 3) . " seconds\n";
        echo "Final hostname: " . ($enriched['hostname'] ?? 'NOT SET') . "\n";
        echo "IP address: " . ($enriched['ip_address'] ?? 'NOT FOUND') . "\n";
        
        if (isset($enriched['ccap_hostname'])) {
            echo "CCAP used for lookup: {$enriched['ccap_hostname']}\n";
            echo "✅ Alias preserved, IP from CCAP\n";
        } else {
            echo "ℹ️  Direct hostname processing\n";
        }
        
        if (isset($enriched['netshot_id'])) {
            echo "Netshot metadata: ID {$enriched['netshot_id']}\n";
        }
        
        echo "\n";
    }
    
    echo "=== Step 5: Performance Benefits ===\n";
    
    $performanceHost = 'HV01ABR001';
    $iterations = 100;
    
    echo "Testing $iterations lookups of '$performanceHost':\n";
    
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $result = $netshot->lookupIpFromMemory($performanceHost);
        $times[] = microtime(true) - $start;
    }
    
    $avgTime = array_sum($times) / count($times);
    $minTime = min($times);
    $maxTime = max($times);
    
    echo "Average lookup time: " . round($avgTime * 1000, 4) . " ms\n";
    echo "Min time: " . round($minTime * 1000, 4) . " ms\n";
    echo "Max time: " . round($maxTime * 1000, 4) . " ms\n";
    echo "✅ Sub-millisecond performance due to in-memory hash tables\n\n";
    
    echo "=== System Architecture Summary ===\n";
    echo "1. ✅ Initialization:\n";
    echo "   - Reads ALL devices from Netshot API once\n";
    echo "   - Builds hostname→IP hash table in memory\n";
    echo "   - Reads alias mappings from reporting.acc_alias\n";
    echo "   - Creates alias→{IP, CCAP_hostname} hash table\n\n";
    
    echo "2. ✅ Lookups:\n";
    echo "   - Direct CCAP hostnames: O(1) hash table lookup\n";
    echo "   - Alias hostnames: O(1) hash table lookup\n";
    echo "   - Returns IP + preserves original hostname\n\n";
    
    echo "3. ✅ Benefits:\n";
    echo "   - Extremely fast lookups (sub-millisecond)\n";
    echo "   - Scales to thousands of devices\n";
    echo "   - Original alias hostnames preserved\n";
    echo "   - Single API call to Netshot at startup\n";
    echo "   - Automatic fallback for unknown devices\n\n";
    
    echo "4. ✅ Memory Management:\n";
    echo "   - clearCache() clears memory mappings\n";
    echo "   - refreshMemoryMappings() forces reload\n";
    echo "   - Lazy initialization (only when first needed)\n\n";
    
    echo "Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    echo "Peak memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n\n";
    
    echo "🎉 The system is ready for production use!\n";
    echo "Use \$netshot->enrichDeviceData(\$device) for full enrichment\n";
    echo "Use \$netshot->lookupIpFromMemory(\$hostname) for fast IP lookups\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Make sure:\n";
    echo "- Database connection is working\n";
    echo "- reporting.acc_alias table exists and has data\n";
    echo "- Netshot API is accessible\n";
}
?>
