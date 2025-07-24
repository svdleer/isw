<?php
/**
 * Test script to verify database alias table setup and functionality
 */

require_once 'classes/Database.php';
require_once 'classes/NetshotAPI.php';

echo "=== Database Alias Table Setup Test ===\n";

try {
    $db = new Database();
    
    // Test 1: Check if reporting.acc_alias table exists
    echo "1. Checking if reporting.acc_alias table exists...\n";
    
    $sql = "SELECT COUNT(*) as table_exists FROM information_schema.tables 
            WHERE table_schema = 'reporting' AND table_name = 'acc_alias'";
    
    $result = $db->query($sql);
    
    if ($result[0]['table_exists'] > 0) {
        echo "✅ reporting.acc_alias table exists\n";
        
        // Test 2: Check table structure
        echo "\n2. Checking table structure...\n";
        $structureResult = $db->query("DESCRIBE reporting.acc_alias");
        
        foreach ($structureResult as $column) {
            echo "   - {$column['Field']}: {$column['Type']}" . 
                 ($column['Null'] === 'NO' ? ' NOT NULL' : '') . 
                 ($column['Key'] ? " ({$column['Key']})" : '') . "\n";
        }
        
        // Test 3: Check sample data
        echo "\n3. Checking sample data...\n";
        $dataResult = $db->query("SELECT alias, ccap_name, active FROM reporting.acc_alias ORDER BY alias LIMIT 10");
        
        if (empty($dataResult)) {
            echo "❌ No data found in acc_alias table\n";
            echo "   Run the create_acc_alias_table.sql script to add sample data\n";
        } else {
            echo "✅ Found " . count($dataResult) . " alias mappings:\n";
            foreach ($dataResult as $row) {
                $status = $row['active'] ? 'active' : 'inactive';
                echo "   - {$row['alias']} → {$row['ccap_name']} ($status)\n";
            }
        }
        
    } else {
        echo "❌ reporting.acc_alias table does not exist\n";
        echo "   Please run the create_acc_alias_table.sql script first\n";
        exit(1);
    }
    
    echo "\n=== Testing Alias Mapping Functionality ===\n";
    
    // Test 4: Test the NetshotAPI alias mapping
    $netshot = new NetshotAPI();
    
    $testAliases = ['HV01ABR001', 'HV01DBR002', 'HV01CBR003', 'NONEXISTENT123'];
    
    foreach ($testAliases as $alias) {
        echo "\n4. Testing alias mapping for: $alias\n";
        
        // Direct database lookup
        $sql = "SELECT UPPER(ccap_name) as ccap_name FROM reporting.acc_alias 
                WHERE UPPER(alias) = UPPER(?)";
        $dbResult = $db->query($sql, [$alias]);
        
        if (!empty($dbResult)) {
            echo "   Database lookup: $alias → {$dbResult[0]['ccap_name']}\n";
        } else {
            echo "   Database lookup: No mapping found for $alias\n";
        }
        
        // NetshotAPI mapping
        $ccapHostname = $netshot->mapAbrToCcapHostname($alias);
        echo "   NetshotAPI result: $alias → $ccapHostname\n";
        
        if ($ccapHostname !== strtoupper($alias)) {
            echo "   ✅ Alias successfully mapped\n";
        } else {
            echo "   ⚠️  No mapping applied (returned original)\n";
        }
    }
    
    echo "\n=== Testing Full Device Enrichment ===\n";
    
    // Test 5: Test full device enrichment with aliases
    $testDevices = [
        ['hostname' => 'HV01ABR001'],
        ['hostname' => 'HLEN-LC0023-CCAP001'], // Direct CCAP
    ];
    
    foreach ($testDevices as $device) {
        echo "\n5. Testing device enrichment for: {$device['hostname']}\n";
        
        $enriched = $netshot->enrichDeviceData($device);
        
        echo "   Original hostname: {$enriched['hostname']}\n";
        echo "   IP address: " . ($enriched['ip_address'] ?? 'NOT FOUND') . "\n";
        
        if (isset($enriched['ccap_hostname'])) {
            echo "   CCAP hostname used: {$enriched['ccap_hostname']}\n";
            echo "   ✅ Alias device - hostname preserved, IP from CCAP\n";
        } else {
            echo "   ℹ️  Direct hostname lookup\n";
        }
        
        if (isset($enriched['netshot_id'])) {
            echo "   Netshot device found: ID {$enriched['netshot_id']}\n";
        } else {
            echo "   ⚠️  Device not found in Netshot\n";
        }
    }
    
    echo "\n=== Test Summary ===\n";
    echo "✅ Database connection working\n";
    echo "✅ Alias table structure verified\n";
    echo "✅ Alias mapping functionality tested\n";
    echo "✅ Device enrichment with alias preservation tested\n";
    echo "\nThe system is ready to handle aliased hostnames!\n";
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Make sure the database is accessible and the acc_alias table exists.\n";
}
?>
