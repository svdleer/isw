<?php
/**
 * Test script for alias hostname mapping (ABR/DBR/CBR to CCAP)
 */

require_once 'classes/NetshotAPI.php';

echo "=== Alias Hostname Mapping Test ===\n";

$netshot = new NetshotAPI();

// Test cases for different alias types
$testCases = [
    // Example alias hostnames that should map to CCAP
    ['hostname' => 'HV01ABR001', 'description' => 'ABR alias format'],
    ['hostname' => 'HV01DBR002', 'description' => 'DBR alias format'],
    ['hostname' => 'HV01CBR003', 'description' => 'CBR alias format'],
    ['hostname' => 'HLEN-LC0023-CCAP001', 'description' => 'Direct CCAP hostname'],
    ['hostname' => 'GV-RC0011-CCAP003', 'description' => 'Another CCAP hostname'],
];

echo "Testing enrichDeviceData with alias mapping...\n\n";

foreach ($testCases as $testCase) {
    $testDevice = [
        'hostname' => $testCase['hostname']
    ];
    
    echo "---\n";
    echo "Testing: {$testCase['hostname']} ({$testCase['description']})\n";
    
    $start = microtime(true);
    $enrichedDevice = $netshot->enrichDeviceData($testDevice);
    $duration = microtime(true) - $start;
    
    echo "Processing time: " . round($duration, 3) . " seconds\n";
    echo "Result hostname: " . ($enrichedDevice['hostname'] ?? 'NOT SET') . "\n";
    echo "IP address: " . ($enrichedDevice['ip_address'] ?? 'EMPTY/NOT FOUND') . "\n";
    
    if (isset($enrichedDevice['ccap_hostname'])) {
        echo "CCAP hostname used for lookup: " . $enrichedDevice['ccap_hostname'] . "\n";
        echo "✅ Alias mapping detected - original alias preserved\n";
    } else {
        echo "ℹ️  Direct hostname lookup (no alias mapping)\n";
    }
    
    if (isset($enrichedDevice['netshot_id'])) {
        echo "Netshot device ID: " . $enrichedDevice['netshot_id'] . "\n";
        echo "Netshot status: " . ($enrichedDevice['netshot_status'] ?? 'unknown') . "\n";
    } else {
        echo "❌ Device not found in Netshot\n";
    }
    
    echo "\n";
}

echo "=== Database Alias Lookup Test ===\n";

// Test the alias lookup directly
echo "Testing direct alias-to-CCAP mapping...\n";

$aliasTestCases = ['HV01ABR001', 'HV01DBR002', 'HV01CBR003'];

foreach ($aliasTestCases as $alias) {
    echo "Testing alias: $alias\n";
    $ccapHostname = $netshot->mapAbrToCcapHostname($alias);
    
    if ($ccapHostname !== strtoupper($alias)) {
        echo "✅ Mapped to CCAP: $ccapHostname\n";
    } else {
        echo "❌ No mapping found - returned original: $ccapHostname\n";
    }
    echo "\n";
}

echo "=== Wildcard Search Test ===\n";

// Test wildcard handling
$wildcardTests = ['*CCAP*', 'HV01*', '*ABR*'];

foreach ($wildcardTests as $wildcard) {
    echo "Testing wildcard: $wildcard\n";
    $result = $netshot->mapAbrToCcapHostname($wildcard);
    echo "Result: $result (should preserve wildcard pattern)\n";
    echo "\n";
}

echo "Test completed!\n";
?>
