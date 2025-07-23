<?php
/**
 * Test script to debug Netshot API integration
 * Run with: php -f test_netshot_integration.php
 */
require_once __DIR__ . '/classes/NetshotAPI.php';

// Function to test the hostname search
function test_hostname_search($hostname) {
    $netshot = new NetshotAPI();
    echo "Testing hostname search for: $hostname\n";
    
    // Clear cache to ensure fresh results
    $netshot->clearCache();
    
    // Get devices from Netshot
    echo "Fetching all devices from Netshot...\n";
    $devices = $netshot->getDevicesInGroup();
    echo "Found " . count($devices) . " devices in Netshot\n";
    
    // Search for the hostname
    echo "Searching for device by hostname...\n";
    $device = $netshot->getDeviceByHostname($hostname);
    
    if ($device) {
        echo "Device found!\n";
        echo "Device details:\n";
        print_r($device);
        
        // Check if the device has an IP address
        $ipFields = ['mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
        $ipFound = false;
        
        foreach ($ipFields as $field) {
            if (isset($device[$field]) && !empty($device[$field])) {
                echo "IP Address found in field '$field': " . $device[$field] . "\n";
                $ipFound = true;
            }
        }
        
        if (!$ipFound) {
            echo "WARNING: No IP address found in device data!\n";
            echo "Available fields in device object:\n";
            foreach (array_keys($device) as $field) {
                echo "- $field: " . (is_array($device[$field]) ? "[array]" : $device[$field]) . "\n";
            }
        }
    } else {
        echo "Device not found by exact hostname match.\n";
        echo "Trying fuzzy matching...\n";
        
        $foundMatches = [];
        foreach ($devices as $potentialMatch) {
            $deviceName = strtoupper($potentialMatch['name'] ?? '');
            $searchName = strtoupper($hostname);
            
            if (!empty($deviceName)) {
                $similarity = similar_text($deviceName, $searchName, $percent);
                
                // Check if one contains the other or if they're similar
                if (strpos($deviceName, $searchName) !== false || 
                    strpos($searchName, $deviceName) !== false ||
                    $percent > 70) {
                    $foundMatches[] = [
                        'device' => $potentialMatch,
                        'similarity' => $percent
                    ];
                }
            }
        }
        
        if (empty($foundMatches)) {
            echo "No fuzzy matches found for '$hostname'.\n";
            echo "Top 5 devices in Netshot (for reference):\n";
            for ($i = 0; $i < min(5, count($devices)); $i++) {
                echo ($i+1) . ". " . ($devices[$i]['name'] ?? 'Unknown') . "\n";
            }
        } else {
            // Sort matches by similarity
            usort($foundMatches, function($a, $b) {
                return $b['similarity'] - $a['similarity'];
            });
            
            echo "Found " . count($foundMatches) . " potential fuzzy matches:\n";
            foreach (array_slice($foundMatches, 0, 5) as $index => $match) {
                echo ($index+1) . ". " . ($match['device']['name'] ?? 'Unknown') . 
                     " (Similarity: " . round($match['similarity'], 2) . "%)";
                
                // Check for IP
                $ipFields = ['mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                foreach ($ipFields as $field) {
                    if (isset($match['device'][$field]) && !empty($match['device'][$field])) {
                        echo " - IP($field): " . $match['device'][$field];
                        break;
                    }
                }
                echo "\n";
            }
        }
    }
}

// Test with specific hostnames
test_hostname_search('GV-RC0011-CCAP003');
test_hostname_search('CCAP003');
