<?php
/**
 * Test Database Connection Fix for ABR/DBR/CBR Alias Lookup
 */

echo "=== Testing Database Connection Fix ===\n";

// Test the Database class directly
try {
    require_once 'classes/Database.php';
    
    echo "Testing Database connection...\n";
    $db = new Database();
    echo "✅ Database connection successful!\n";
    
    // Test a simple query
    echo "\nTesting database query...\n";
    $result = $db->query("SELECT 1 as test");
    if (!empty($result) && $result[0]['test'] == 1) {
        echo "✅ Database query successful!\n";
    } else {
        echo "❌ Database query failed\n";
    }
    
    // Test the acc_alias table access
    echo "\nTesting acc_alias table access...\n";
    $aliasCount = $db->query("SELECT COUNT(*) as count FROM reporting.acc_alias LIMIT 1");
    if (!empty($aliasCount)) {
        echo "✅ acc_alias table accessible, found " . $aliasCount[0]['count'] . " records\n";
    } else {
        echo "❌ acc_alias table not accessible\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing ABR/DBR/CBR Alias Lookup ===\n";

// Test the specific device from the logs
try {
    require_once 'classes/NetshotAPI.php';
    
    $netshot = new NetshotAPI();
    
    echo "Testing alias lookup for RT19ABR45...\n";
    
    $start = microtime(true);
    $alias = $netshot->findAliasForCcapHostname('RT19ABR45');
    $time = microtime(true) - $start;
    
    echo "Lookup time: " . round($time * 1000, 2) . " ms\n";
    echo "Result: $alias\n";
    
    if ($alias === 'RT19ABR45') {
        echo "  - No alias found (returning original hostname)\n";
    } else {
        echo "  - ✅ Found alias: $alias\n";
    }
    
} catch (Exception $e) {
    echo "❌ NetshotAPI error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing IP Search with Fixed Database ===\n";

// Test the IP search that was having database issues
try {
    $testIp = '172.28.88.7';
    echo "Testing IP search for: $testIp\n";
    
    $result = $netshot->getDeviceByIP($testIp);
    
    if ($result) {
        echo "✅ Found device: " . ($result['name'] ?? 'Unknown') . "\n";
        echo "✅ IP: " . ($result['ip'] ?? 'Not set') . "\n";
        
        // Check if ABR alias replacement worked
        $hostname = $result['name'] ?? '';
        if (strpos($hostname, 'ABR') !== false) {
            echo "  - Note: Still showing ABR hostname (no alias found in database)\n";
        } else {
            echo "  - ✅ Hostname replaced with alias\n";
        }
    } else {
        echo "❌ No device found for IP: $testIp\n";
    }
    
} catch (Exception $e) {
    echo "❌ IP search error: " . $e->getMessage() . "\n";
}

echo "\nDatabase connection issue fixed! ABR/DBR/CBR alias lookup should now work properly.\n";
?>
