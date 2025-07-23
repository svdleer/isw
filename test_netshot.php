<?php
/**
 * Test script for Netshot API integration
 * 
 * This script tests the Netshot API class and its methods
 */

// Include required classes
require_once __DIR__ . '/classes/NetshotAPI.php';
require_once __DIR__ . '/classes/EnvLoader.php';

// Load environment variables
EnvLoader::load();

// Create a new NetshotAPI instance
$netshot = new NetshotAPI();

// Test IP address to look up
$testIP = '10.0.0.1'; // Replace with an IP that should exist in your Netshot system

echo "Testing Netshot API Integration\n";
echo "===============================\n\n";

// Test getDeviceByIP method
echo "Looking up device with IP: $testIP\n";
$device = $netshot->getDeviceByIP($testIP);

if ($device) {
    echo "Device found!\n";
    echo "ID: " . ($device['id'] ?? 'N/A') . "\n";
    echo "Name: " . ($device['name'] ?? 'N/A') . "\n";
    echo "IP: " . ($device['ip'] ?? 'N/A') . "\n";
    echo "Model: " . ($device['model'] ?? 'N/A') . "\n";
    echo "Vendor: " . ($device['vendor'] ?? 'N/A') . "\n";
    echo "Status: " . ($device['status'] ?? 'N/A') . "\n";
    echo "Software Version: " . ($device['software_version'] ?? 'N/A') . "\n";
} else {
    echo "No device found with IP $testIP\n";
}

echo "\n";

// Test getDevicesInGroup method
echo "Getting devices from default group\n";
$devices = $netshot->getDevicesInGroup();

echo "Found " . count($devices) . " devices in group\n";
if (count($devices) > 0) {
    echo "First 3 devices:\n";
    $count = 0;
    foreach ($devices as $device) {
        if ($count >= 3) break;
        echo "- " . ($device['name'] ?? 'Unknown') . " (" . ($device['mgmtIp'] ?? 'No IP') . ")\n";
        $count++;
    }
}

echo "\n";

// Test clearing cache
echo "Clearing cache for IP lookup: $testIP\n";
$netshot->clearCache("device_ip_$testIP");
echo "Cache cleared.\n";

echo "\nDone.\n";
