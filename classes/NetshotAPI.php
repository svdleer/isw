<?php
/**
 * Netshot API Integration Class
 * 
 * This class handles communication with the Netshot API to enhance
 * device searches with additional information.
 */
class NetshotAPI {
    private $apiUrl;
    private $apiKey;
    private $group;
    
    /**
     * Constructor
     * 
     * @param string $apiUrl The base URL for Netshot API
     * @param string $apiKey The API key for authentication
     * @param string|int $group The Netshot group name or ID to use
     */
    public function __construct($apiUrl = null, $apiKey = null, $group = null) {
        // Load environment variables
        require_once __DIR__ . '/EnvLoader.php';
        EnvLoader::load();
        
        // Use provided values or fall back to environment variables with multiple possible names
        $this->apiUrl = $apiUrl ?: 
            $_ENV['NETSHOT_API_URL'] ?? 
            $_ENV['NETSHOT_OSS_URL'] ?? 
            'https://netshot.oss.local/api';
            
        $this->apiKey = $apiKey ?: 
            $_ENV['NETSHOT_API_KEY'] ?? 
            $_ENV['NETSHOT_API_TOKEN'] ?? 
            $_ENV['NETSHOT_OSS_TOKEN'] ?? 
            'UqRf6NkgvKru3rxRRrRKck1VoANQJvP2';
            
        // Store group information from parameter or environment variable
        $this->group = $group ?: 
            $_ENV['NETSHOT_GROUP'] ?? 
            $_ENV['NETSHOT_GROUP_ID'] ?? 
            'ACCESS';
    }

    /**
     * Get all groups from Netshot
     * 
     * @return array List of groups
     */
    public function getGroups() {
        
        $url = "{$this->apiUrl}/groups";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Netshot-API-Token: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
            // Skip SSL verification for development environments
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                error_log("Netshot API error getting groups: HTTP {$httpCode} - " . curl_error($ch));
                return [];
            }
            
            curl_close($ch);
            
            $groups = json_decode($response, true) ?: [];
            
            error_log("Retrieved " . count($groups) . " groups from Netshot");
            
            // Return the results directly without caching
            
            return $groups;
        } catch (Exception $e) {
            error_log("Exception in getGroups: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a group ID by name - Kept for backward compatibility
     * 
     * @param string $groupName The name of the group to find
     * @return int|null The group ID if found, null otherwise
     * @deprecated Use getDevicesInGroup() directly with group name parameter instead
     */
    public function findGroupIdByName($groupName = 'ACCESS') {
        error_log("Warning: findGroupIdByName is deprecated. Use getDevicesInGroup() directly.");
        
        // For backwards compatibility we still implement this
        $groups = $this->getGroups();
        
        foreach ($groups as $group) {
            if (isset($group['name']) && strpos($group['name'], $groupName) !== false) {
                error_log("Found Netshot group: {$group['name']} with ID {$group['id']}");
                return $group['id'];
            }
        }
        
        error_log("Group '{$groupName}' not found in Netshot");
        return null;
    }
    
    /**
     * Get devices from a specific group
     * 
     * @param int|string|null $groupParam Optional override for the group ID/name to fetch devices from
     * @return array Devices in the specified group
     */
    /**
     * Filter devices to only include those with INPRODUCTION status
     * 
     * @param array $devices Array of devices to filter
     * @return array Filtered devices
     */
    private function filterInProductionDevices($devices) {
        $filtered = array_filter($devices, function($device) {
            return isset($device['status']) && $device['status'] === 'INPRODUCTION';
        });
        
        error_log("Filtered " . count($devices) . " devices to " . count($filtered) . " INPRODUCTION devices");
        return $filtered;
    }
    
    /**
     * Get devices from a specific group
     * 
     * @param int|string|null $groupParam Optional override for the group ID/name to fetch devices from
     * @param bool $onlyInProduction Whether to filter for only INPRODUCTION devices (default: true)
     * @return array Devices in the specified group
     */
    public function getDevicesInGroup($groupParam = null, $onlyInProduction = true) {
        // Use parameter if provided, otherwise use the class property
        $group = $groupParam !== null ? $groupParam : $this->group;
        $groupQueryParam = '';
        
        // Handle the group parameter based on whether it's numeric (ID) or a string (name)
        if (is_numeric($group)) {
            // If it's a number, use it directly as the group ID
            $groupQueryParam = "group=" . $group;
            error_log("Using numeric group ID: {$group}");
        } else {
            // If it's a string and looks like a group name
            if (preg_match('/^[A-Za-z]/', $group)) {
                // It's a name, so we search by name filter
                $groupQueryParam = "groupNameFilter=" . urlencode($group);
                error_log("Using group name filter: {$group}");
            } else {
                // Default to a reasonable group ID if all else fails
                $groupQueryParam = "group=240"; // Fallback to default group ID
                error_log("Using fallback group ID: 240");
            }
        }
        
        $url = "{$this->apiUrl}/devices?{$groupQueryParam}";
        error_log("Querying Netshot for devices: {$url}");
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Netshot-API-Token: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
            // Skip SSL verification for development environments
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                error_log("Netshot API error: HTTP {$httpCode} - " . curl_error($ch) . " - URL: {$url}");
                return [];
            }
            
            curl_close($ch);
            
            $result = json_decode($response, true) ?: [];
            error_log("Retrieved " . count($result) . " devices from Netshot using {$groupQueryParam}");
            
            // Filter for INPRODUCTION devices if requested
            if ($onlyInProduction) {
                $result = $this->filterInProductionDevices($result);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Netshot API exception: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Placeholder method for backward compatibility
     * 
     * @param string|null $key The cache key (ignored)
     * @return void
     */
    public function clearCache($key = null) {
        // Method kept for backward compatibility but does nothing
        return;
    }
    
    /**
     * Search for a device by IP address
     * 
     * @param string $ipPattern IP address pattern (can include wildcards as * or %)
     * @return array Matching devices
     */
    public function searchDevicesByIp($ipPattern) {
        try {
            
            // Convert SQL LIKE pattern to regex
            $regexPattern = $this->patternToRegex($ipPattern);
            
            // Get only INPRODUCTION devices
            $devices = $this->getDevicesInGroup(null, true);
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices with IP pattern: " . $ipPattern . " (regex: " . $regexPattern . ")");
            
            // Filter devices by IP
            $results = [];
            foreach ($devices as $device) {
                if (!isset($device['mgmtIp'])) {
                    continue;
                }
                
                if (preg_match($regexPattern, $device['mgmtIp'])) {
                    $results[] = [
                        'id' => $device['id'] ?? null,
                        'name' => strtoupper($device['name'] ?? ''),
                        'ip' => $device['mgmtIp'] ?? '',
                        'model' => $device['family'] ?? null,
                        'vendor' => $device['domain'] ?? null,
                        'status' => $device['status'] ?? null
                    ];
                }
            }
            
            error_log("Found " . count($results) . " matching devices for IP pattern: " . $ipPattern);
            return $results;
        } catch (Exception $e) {
            error_log("Error in searchDevicesByIp: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get device details by hostname
     * 
     * @param string $hostname The hostname to search for
     * @param bool $validateFormat Whether to validate the hostname format (default true)
     * @return array|null|false Device details, null if not found, false if invalid format
     */
    public function getDeviceByHostname($hostname, $validateFormat = true) {
        try {
            // Check if this is an ABR/DBR/CBR hostname and map to CCAP if found
            $originalHostname = $hostname;
            $hostname = $this->mapAbrToCcapHostname($hostname);
            
            // If hostname was mapped from ABR to CCAP, skip validation
            if ($hostname !== $originalHostname) {
                $validateFormat = false; // Skip validation since we trust the DB mapping
                error_log("Hostname mapped from ABR/DBR/CBR format: $originalHostname to CCAP: $hostname");
            }
            
            // Validate hostname format if required and not an ABR/DBR/CBR that was mapped
            if ($validateFormat) {
                // Compliant hostname regex: 2-4 letters, followed by -RC or -LC, followed by 0 and 3 digits, followed by -CCAP and digit 1-6 followed by 0 and another digit
                $hostnamePattern = '/^[a-zA-Z]{2,4}-(RC|LC)0\d{3}-CCAP[1-6]0[0-9]$/i';
                if (!preg_match($hostnamePattern, $hostname)) {
                    error_log("Invalid hostname format: " . $hostname . " - must match pattern: " . $hostnamePattern);
                    return false;
                }
            }
            
            // Convert hostname to uppercase for consistency
            $hostname = strtoupper($hostname);
            
            // Get only INPRODUCTION devices
            $devices = $this->getDevicesInGroup(null, true);
            
            error_log("Searching " . count($devices) . " Netshot INPRODUCTION devices for hostname: " . $hostname);
            
            // Log a sample device to see the structure
            if (!empty($devices)) {
                $sampleDevice = $devices[0];
                error_log("Sample Netshot device structure: " . json_encode(array_keys($sampleDevice)));
                
                // Check for IP address fields in the sample device
                $ipFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                foreach ($ipFields as $field) {
                    if (isset($sampleDevice[$field])) {
                        error_log("Found IP field in Netshot data: '$field' with value: " . $sampleDevice[$field]);
                    }
                }
            }
            
            // First try exact match - handle case conversion to match your Python approach
            foreach ($devices as $device) {
                // Try a few different case variations to handle case sensitivity
                if (isset($device['name']) && 
                    (strtoupper($device['name']) === $hostname)    // Now hostname is already uppercase
                   ) {
                    error_log("Found exact hostname match in Netshot: " . $device['name']);
                    
                    // Log all fields in the device
                    foreach ($device as $key => $value) {
                        if (!is_array($value)) {
                            error_log("Device field '$key': " . $value);
                        } else {
                            error_log("Device field '$key': [array]");
                        }
                    }
                    
                    return $device;
                }
            }
            
            // If exact match failed, try partial/fuzzy matching
            // Common scenarios: device name might have prefix/suffix or use different delimiter
            foreach ($devices as $device) {
                if (!isset($device['name'])) {
                    continue;
                }
                
                $deviceName = strtoupper($device['name']);
                // $hostname is already uppercase from above
                $searchName = $hostname;
                
                // Check if one contains the other, or if there's a match after stripping non-alphanumeric chars
                $cleanDeviceName = preg_replace('/[^A-Z0-9]/', '', $deviceName);
                $cleanSearchName = preg_replace('/[^A-Z0-9]/', '', $searchName);
                
                if (strpos($deviceName, $searchName) !== false || 
                    strpos($searchName, $deviceName) !== false ||
                    strpos($cleanDeviceName, $cleanSearchName) !== false ||
                    strpos($cleanSearchName, $cleanDeviceName) !== false) {
                    
                    // Check for CCAP in both names as extra verification
                    if (strpos($deviceName, 'CCAP') !== false && strpos($searchName, 'CCAP') !== false) {
                        error_log("Found fuzzy hostname match: " . $device['name'] . " for query: " . $hostname);
                        
                        // Check IP field
                        $ipFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                        foreach ($ipFields as $field) {
                            if (isset($device[$field]) && !empty($device[$field])) {
                                error_log("IP field '$field' contains: " . $device[$field]);
                            }
                        }
                        
                        return $device;
                    }
                }
            }
            
            // No match found
            error_log("No hostname match found in Netshot for: " . $hostname);
            return null;
        } catch (Exception $e) {
            error_log("Error in getDeviceByHostname: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Convert SQL LIKE pattern (with % or * wildcards) to a regex pattern
     * 
     * @param string $pattern SQL LIKE pattern
     * @return string Regex pattern
     */
    private function patternToRegex($pattern) {
        // Replace SQL LIKE wildcards with regex equivalents
        $pattern = str_replace(['%', '*'], ['.+', '.+'], $pattern);
        
        // Escape regex special characters except the ones we just added
        $pattern = preg_quote($pattern, '/');
        
        // Replace back our wildcards
        $pattern = str_replace(['\\.+'], ['.+'], $pattern);
        
        return '/^' . $pattern . '$/i';
    }
    
    /**
     * Get device details by IP address (exact match)
     * 
     * @param string $ipAddress The IP address to search for
     * @return array|null Device details or null if not found
     */
    /**
     * Helper method to convert ABR/DBR/CBR hostnames to CCAP hostnames via database lookup
     * 
     * @param string $hostname The hostname to check and potentially convert
     * @return string The original hostname or the mapped CCAP hostname
     */
    public function mapAbrToCcapHostname($hostname) {
        // Check for ABR/DBR/CBR pattern - more flexible to match various formats
        $abrPattern = '/^[a-zA-Z]{2}\d{2}(abr|dbr|cbr)\d{1,4}$/i';
        $alternativePattern = '/(abr|dbr|cbr)/i';
        
        if (!preg_match($abrPattern, $hostname) && !preg_match($alternativePattern, $hostname)) {
            // Not an ABR/DBR/CBR hostname, return as is
            return $hostname;
        }
        
        error_log("ABR/DBR/CBR format detected: " . $hostname . ". Looking up corresponding CCAP device.");
        
        try {
            // Try to load the Database class
            if (!class_exists('Database')) {
                require_once __DIR__ . '/Database.php';
            }
            
            // Create database connection
            $db = new Database();
            
            // Query for the CCAP name
            $escapedHostname = str_replace("'", "''", strtoupper($hostname)); // Simple SQL escape
            $sql = "SELECT ccap_name FROM reporting.acc_alias WHERE alias = '$escapedHostname'";
            error_log("Executing database query: " . $sql);
            
            $result = $db->query($sql);
            if (!empty($result) && isset($result[0]['ccap_name'])) {
                $ccapHostname = $result[0]['ccap_name'];
                error_log("Found corresponding CCAP hostname: " . $ccapHostname . " for ABR/DBR/CBR device: " . $hostname);
                return $ccapHostname;
            } else {
                error_log("No CCAP mapping found for ABR/DBR/CBR device: " . $hostname);
                return $hostname; // Return original if no mapping found
            }
        } catch (Exception $dbException) {
            error_log("Database error looking up ABR/DBR/CBR alias: " . $dbException->getMessage());
            return $hostname; // Return original on error
        }
    }
    
    public function getDeviceByIP($ipAddress) {
        try {
            error_log("Fetching devices from Netshot for IP lookup: " . $ipAddress);
            
            // Get only INPRODUCTION devices
            $devices = $this->getDevicesInGroup(null, true);
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices for IP: " . $ipAddress);
            
            foreach ($devices as $device) {
                if (isset($device['mgmtIp']) && $device['mgmtIp'] === $ipAddress) {
                    // Format the response consistently and ensure hostname is uppercase
                    $result = [
                        'id' => $device['id'] ?? null,
                        'name' => strtoupper($device['name'] ?? ''),
                        'ip' => $device['mgmtIp'] ?? $ipAddress,
                        'model' => $device['family'] ?? null,
                        'vendor' => $device['domain'] ?? null,
                        'status' => $device['status'] ?? null,
                        'software_version' => $device['softwareVersion'] ?? null,
                        'last_check' => $device['lastCheck'] ?? null
                    ];
                    
                    return $result;
                }
            }
            
            // Device not found
            return null;
        } catch (Exception $e) {
            error_log("Error in getDeviceByIP: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get device hostnames by IP address
     * 
     * @param string $ipAddress The IP address to search for
     * @return array List of hostnames associated with this IP
     */
    public function getDeviceNamesByIP($ipAddress) {
        try {
            
            error_log("Looking up hostnames for IP: " . $ipAddress);
            
            // Get only INPRODUCTION devices
            $devices = $this->getDevicesInGroup(null, true);
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices for hostnames with IP: " . $ipAddress);
            $hostnames = [];
            
            foreach ($devices as $device) {
                if (isset($device['mgmtIp']) && $device['mgmtIp'] === $ipAddress) {
                    if (isset($device['name']) && !empty($device['name'])) {
                        $hostnames[] = strtoupper($device['name']);
                    }
                }
            }
            
            return $hostnames;
        } catch (Exception $e) {
            error_log("Error in getDeviceNamesByIP: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enrich device data from database with Netshot data
     * 
     * @param array $dbDevice Device data from database
     * @return array Enriched device data
     */
    public function enrichDeviceData($dbDevice) {
        if (!isset($dbDevice['hostname'])) {
            return $dbDevice;
        }
        
        // Get the hostname and check if it's an ABR/DBR/CBR pattern first
        $hostname = $dbDevice['hostname'];
        $originalHostname = $hostname;
        
        // Map ABR/DBR/CBR hostname to CCAP if applicable
        $hostname = $this->mapAbrToCcapHostname($hostname);
        if ($hostname !== $originalHostname) {
            error_log("Enriching device data: Hostname mapped from ABR/DBR/CBR $originalHostname to CCAP $hostname");
        }
        
        // Set validateFormat to false to maintain backward compatibility
        // This will still only search in INPRODUCTION devices
        $netshotDevice = $this->getDeviceByHostname($hostname, false);
        
        // Handle both null (not found) and false (invalid format)
        if ($netshotDevice === null || $netshotDevice === false) {
            return $dbDevice;
        }
        
        // Add Netshot data to device record
        $dbDevice['netshot_id'] = $netshotDevice['id'] ?? null;
        $dbDevice['netshot_status'] = $netshotDevice['status'] ?? null;
        $dbDevice['netshot_family'] = $netshotDevice['family'] ?? null;
        $dbDevice['netshot_software_version'] = $netshotDevice['softwareVersion'] ?? null;
        
        return $dbDevice;
    }
}
