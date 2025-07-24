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
    
    // Memory storage for hostname-to-IP mappings
    private $hostnameIpMap = [];
    private $aliasIpMap = [];
    private $mapInitialized = false;
    
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
            
        // Log the configuration being used
        error_log("NetshotAPI initialized with: URL=" . $this->apiUrl . ", Group=" . $this->group);
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
     * Build an index of devices by hostname and IP for faster lookups
     * 
     * @param array $devices Array of devices to index
     * @return array Indexed array with 'byHostname' and 'byIp' keys
     */
    private function buildDeviceIndex($devices) {
        $index = ['byHostname' => [], 'byIp' => []];
        
        foreach ($devices as $device) {
            // Index by hostname (case-insensitive)
            if (isset($device['name']) && !empty($device['name'])) {
                $hostname = strtoupper($device['name']);
                $index['byHostname'][$hostname] = $device;
            }
            
            // Index by IP address - handle nested mgmtAddress structure
            $deviceIp = null;
            
            // Check multiple possible IP field structures
            $possibleIpFields = ['mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
            
            // First, try simple fields
            foreach ($possibleIpFields as $field) {
                if (isset($device[$field]) && !empty($device[$field])) {
                    $deviceIp = $device[$field];
                    break;
                }
            }
            
            // If not found, check nested mgmtAddress structure
            if (!$deviceIp && isset($device['mgmtAddress'])) {
                if (is_array($device['mgmtAddress']) && isset($device['mgmtAddress']['ip'])) {
                    $deviceIp = $device['mgmtAddress']['ip'];
                }
            }
            
            // Add to IP index if we found an IP
            if ($deviceIp && !empty($deviceIp)) {
                // Store the flattened IP in the device for consistent access
                $device['mgmtIp'] = $deviceIp;
                $index['byIp'][$deviceIp] = $device;
            }
        }
        
        return $index;
    }

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
     * Get devices from a specific group (simplified - no caching)
     * 
     * @param int|string|null $groupParam Optional override for the group ID/name to fetch devices from
     * @param bool $onlyInProduction Whether to filter for only INPRODUCTION devices (default: true)
     * @param bool $useCache Ignored - no caching for simplicity
     * @return array Devices in the specified group
     */
    public function getDevicesInGroup($groupParam = null, $onlyInProduction = true, $useCache = false) {
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
                // Default to a reasonable group ID if all else fails, but first try to find available groups
                error_log("Group parameter '{$group}' is not numeric or valid string. Checking available groups...");
                
                // Try to get available groups to help with debugging
                $availableGroups = $this->getGroups();
                if (!empty($availableGroups)) {
                    error_log("Available Netshot groups: " . json_encode(array_map(function($g) {
                        return ['id' => $g['id'] ?? 'unknown', 'name' => $g['name'] ?? 'unknown'];
                    }, $availableGroups)));
                    
                    // Try to find a group that contains "ACCESS" in the name
                    foreach ($availableGroups as $availableGroup) {
                        if (isset($availableGroup['name']) && stripos($availableGroup['name'], 'ACCESS') !== false) {
                            $groupQueryParam = "group=" . $availableGroup['id'];
                            error_log("Found ACCESS group automatically: using group ID " . $availableGroup['id'] . " (name: " . $availableGroup['name'] . ")");
                            break;
                        }
                    }
                }
                
                // If we still don't have a group, use the fallback
                if (empty($groupQueryParam)) {
                    $groupQueryParam = "group=240"; // Fallback to default group ID
                    error_log("Using fallback group ID: 240 (this group appears to be empty - check your Netshot configuration)");
                }
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
            // Add timeout to prevent hanging
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                error_log("Netshot API error: HTTP {$httpCode} - " . curl_error($ch) . " - URL: {$url}");
                return [];
            }
            
            curl_close($ch);
            
            // Parse JSON with error handling
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg() . " - Response length: " . strlen($response));
                return [];
            }
            
            $result = $result ?: [];
            error_log("Retrieved " . count($result) . " raw devices from Netshot using {$groupQueryParam}");
            
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
     * Clear cache - clears memory mappings for fresh data
     * 
     * @param string|null $key The cache key (ignored for backward compatibility)
     * @return void
     */
    public function clearCache($key = null) {
        // Clear memory mappings
        $this->hostnameIpMap = [];
        $this->aliasIpMap = [];
        $this->mapInitialized = false;
        
        error_log("NetshotAPI clearCache called - memory mappings cleared");
    }
    
    /**
     * Force refresh of memory mappings from Netshot and MySQL
     * Useful when you know the data has changed and want to reload
     */
    public function refreshMemoryMappings() {
        error_log("NetshotAPI: Forcing refresh of memory mappings");
        $this->clearCache();
        $this->initializeMemoryMappings();
        error_log("NetshotAPI: Memory mappings refreshed - " . 
                  count($this->hostnameIpMap) . " hostnames, " . 
                  count($this->aliasIpMap) . " aliases");
    }
    
    /**
     * Initialize memory mappings by reading all hostnames from Netshot and alias mappings from MySQL
     * This creates an in-memory lookup table for fast hostname-to-IP resolution
     */
    private function initializeMemoryMappings() {
        if ($this->mapInitialized) {
            return; // Already initialized
        }
        
        error_log("NetshotAPI: Initializing memory mappings from Netshot and MySQL alias table");
        $startTime = microtime(true);
        
        // Step 1: Get all devices from Netshot with their hostnames and IP addresses
        try {
            $netshotDevices = $this->getDevicesInGroup(null, true, false);
            error_log("NetshotAPI: Retrieved " . count($netshotDevices) . " devices from Netshot");
            
            // Build hostname-to-IP mapping from Netshot data
            foreach ($netshotDevices as $device) {
                $hostname = strtoupper($device['name'] ?? '');
                $ipAddress = null;
                
                // Extract IP address from various possible fields
                $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                foreach ($possibleIpFields as $field) {
                    if (isset($device[$field]) && !empty($device[$field])) {
                        $fieldValue = $device[$field];
                        
                        // Handle case where IP is an object with 'ip' field
                        if (is_array($fieldValue) && isset($fieldValue['ip'])) {
                            $ipAddress = $fieldValue['ip'];
                        } else {
                            $ipAddress = $fieldValue;
                        }
                        break;
                    }
                }
                
                if ($hostname && $ipAddress) {
                    $this->hostnameIpMap[$hostname] = $ipAddress;
                }
            }
            
            error_log("NetshotAPI: Built hostname-to-IP map with " . count($this->hostnameIpMap) . " entries");
            
        } catch (Exception $e) {
            error_log("NetshotAPI: Error reading devices from Netshot: " . $e->getMessage());
        }
        
        // Step 2: Get alias mappings from MySQL and create alias-to-IP lookup
        try {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/Database.php';
            }
            
            $db = new Database();
            $sql = "SELECT alias, ccap_name FROM reporting.acc_alias";
            $aliasResults = $db->query($sql);
            
            error_log("NetshotAPI: Retrieved " . count($aliasResults) . " alias mappings from MySQL");
            
            // Create alias-to-IP mapping using the hostname-to-IP map
            foreach ($aliasResults as $aliasRow) {
                $alias = strtoupper($aliasRow['alias']); // Convert to uppercase for consistency
                $ccapHostname = strtoupper($aliasRow['ccap_name']); // Convert to uppercase for consistency
                
                // If we have the IP for this CCAP hostname, map it to the alias
                if (isset($this->hostnameIpMap[$ccapHostname])) {
                    $this->aliasIpMap[$alias] = [
                        'ip_address' => $this->hostnameIpMap[$ccapHostname],
                        'ccap_hostname' => $ccapHostname
                    ];
                }
            }
            
            error_log("NetshotAPI: Built alias-to-IP map with " . count($this->aliasIpMap) . " entries");
            
        } catch (Exception $e) {
            error_log("NetshotAPI: Error reading alias mappings from MySQL: " . $e->getMessage());
        }
        
        $this->mapInitialized = true;
        $duration = microtime(true) - $startTime;
        error_log("NetshotAPI: Memory mapping initialization completed in " . round($duration, 3) . " seconds");
    }
    
    /**
     * Lookup IP address from memory for a given hostname (direct CCAP or alias)
     * Supports wildcard searches with * or %
     * 
     * @param string $hostname The hostname to lookup (can include wildcards)
     * @return array|null Array with ip_address and optionally ccap_hostname, or null if not found
     */
    public function lookupIpFromMemory($hostname) {
        // Ensure mappings are initialized
        $this->initializeMemoryMappings();
        
        $upperHostname = strtoupper($hostname);
        
        // Check if it's a wildcard search
        $hasWildcard = (strpos($upperHostname, '*') !== false || strpos($upperHostname, '%') !== false);
        
        if ($hasWildcard) {
            // Handle wildcard search
            return $this->wildcardLookupInMemory($upperHostname);
        }
        
        // Exact hostname lookup
        // First check if it's a direct CCAP hostname
        if (isset($this->hostnameIpMap[$upperHostname])) {
            return [
                'ip_address' => $this->hostnameIpMap[$upperHostname],
                'is_alias' => false
            ];
        }
        
        // Then check if it's an alias
        if (isset($this->aliasIpMap[$upperHostname])) {
            return [
                'ip_address' => $this->aliasIpMap[$upperHostname]['ip_address'],
                'ccap_hostname' => $this->aliasIpMap[$upperHostname]['ccap_hostname'],
                'is_alias' => true
            ];
        }
        
        return null; // Not found in memory
    }
    
    /**
     * Perform wildcard search in memory maps
     * 
     * @param string $pattern Wildcard pattern (with * or %)
     * @return array Array of matching results
     */
    private function wildcardLookupInMemory($pattern) {
        // Convert wildcards to regex pattern
        $regexPattern = '/^' . str_replace(['*', '%'], ['.*', '.*'], preg_quote($pattern, '/')) . '$/i';
        
        $matches = [];
        
        // Search in direct CCAP hostnames
        foreach ($this->hostnameIpMap as $hostname => $ipAddress) {
            if (preg_match($regexPattern, $hostname)) {
                $matches[] = [
                    'hostname' => $hostname,
                    'ip_address' => $ipAddress,
                    'is_alias' => false
                ];
            }
        }
        
        // Search in aliases
        foreach ($this->aliasIpMap as $alias => $data) {
            if (preg_match($regexPattern, $alias)) {
                $matches[] = [
                    'hostname' => $alias,
                    'ip_address' => $data['ip_address'],
                    'ccap_hostname' => $data['ccap_hostname'],
                    'is_alias' => true
                ];
            }
        }
        
        error_log("NetshotAPI: Wildcard search '$pattern' found " . count($matches) . " matches");
        
        return $matches;
    }
    
    /**
     * Search hostnames by wildcard pattern using memory maps
     * 
     * @param string $pattern Wildcard pattern (with * or %)
     * @return array Array of matching hostnames with IP addresses
     */
    public function searchHostnamesByWildcard($pattern) {
        // Ensure mappings are initialized
        $this->initializeMemoryMappings();
        
        $upperPattern = strtoupper($pattern);
        
        // Check if it's a wildcard search
        $hasWildcard = (strpos($upperPattern, '*') !== false || strpos($upperPattern, '%') !== false);
        
        if (!$hasWildcard) {
            // Not a wildcard, use regular lookup
            $result = $this->lookupIpFromMemory($pattern);
            if ($result) {
                return [$result];
            }
            return [];
        }
        
        // Use the wildcard lookup
        $matches = $this->wildcardLookupInMemory($upperPattern);
        
        error_log("NetshotAPI: searchHostnamesByWildcard('$pattern') found " . count($matches) . " matches");
        
        return $matches;
    }
    
    /**
     * Search for a device by IP address (optimized)
     * 
     * @param string $ipPattern IP address pattern (can include wildcards as * or %)
     * @return array Matching devices
     */
    public function searchDevicesByIp($ipPattern) {
        try {
            // Convert SQL LIKE pattern to regex
            $regexPattern = $this->patternToRegex($ipPattern);
            
            // Get only INPRODUCTION devices - no caching
            $devices = $this->getDevicesInGroup(null, true, false);
            
            if (empty($devices)) {
                error_log("No devices available for IP pattern search: " . $ipPattern);
                return [];
            }
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices with IP pattern: " . $ipPattern . " (regex: " . $regexPattern . ")");
            
            // Filter devices by IP - use array_filter for better performance
            $results = [];
            foreach ($devices as $device) {
                if (!isset($device['mgmtIp'])) {
                    continue;
                }
                
                if (preg_match($regexPattern, $device['mgmtIp'])) {
                    $originalHostname = $device['name'] ?? '';
                    $displayHostname = strtoupper($originalHostname);
                    $aliasHostname = null;
                    
                    // Handle migration scenario: always determine the CCAP hostname and alias
                    if (preg_match('/(ABR|DBR|CBR)/i', $originalHostname)) {
                        // Netshot discovered device with alias name - map to CCAP
                        error_log("Found device with alias name in Netshot: $originalHostname - looking up target CCAP name");
                        $ccapHostname = $this->mapAbrToCcapHostname($originalHostname);
                        if ($ccapHostname !== strtoupper($originalHostname)) {
                            $aliasHostname = strtoupper($originalHostname); // Store the original as alias
                            $displayHostname = $ccapHostname; // Use CCAP as main hostname
                            error_log("Using target CCAP hostname $ccapHostname with alias $aliasHostname for consistency");
                        }
                    } else {
                        // Netshot discovered device with CCAP name - look up alias
                        $alias = $this->findAliasForCcapHostname($originalHostname);
                        if ($alias !== strtoupper($originalHostname)) {
                            $aliasHostname = $alias;
                            $displayHostname = strtoupper($originalHostname); // Keep CCAP as main
                            error_log("Found alias $alias for CCAP hostname $originalHostname");
                        }
                    }
                    
                    $result = [
                        'Id' => $device['id'] ?? null,
                        'Name' => $displayHostname, // Always the CCAP hostname
                        'IpAddress' => $device['mgmtIp'] ?? '',
                        'Model' => $device['family'] ?? null,
                        'Vendor' => $device['domain'] ?? null,
                        'Status' => $device['status'] ?? null
                    ];
                    
                    // Always add alias if one exists
                    if ($aliasHostname && $aliasHostname !== $displayHostname) {
                        $result['Alias'] = $aliasHostname;
                        error_log("Added Alias field: $aliasHostname for device $displayHostname");
                    }
                    
                    $results[] = $result;
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
     * Get device details by hostname (optimized with indexing)
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
            
            // Ensure hostname is always in uppercase
            $hostname = strtoupper($hostname);
            
            // Validate hostname format if required and not an ABR/DBR/CBR that was mapped
            if ($validateFormat) {
                // Compliant hostname regex: 2-4 letters, followed by -RC or -LC, followed by 0 and 3 digits, followed by -CCAP and digit 1-6 followed by 0 and another digit
                $hostnamePattern = '/^[a-zA-Z]{2,4}-(RC|LC)0\d{3}-CCAP[1-6]0[0-9]$/i';
                if (!preg_match($hostnamePattern, $hostname)) {
                    error_log("Invalid hostname format: " . $hostname . " - must match pattern: " . $hostnamePattern);
                    return false;
                }
            }
            
            // Get only INPRODUCTION devices - no caching
            $devices = $this->getDevicesInGroup(null, true, false);
            
            if (empty($devices)) {
                error_log("No devices available for hostname search: " . $hostname);
                return null;
            }
            
            // Build index for faster lookups
            $deviceIndex = $this->buildDeviceIndex($devices);
            
            error_log("Searching " . count($devices) . " Netshot INPRODUCTION devices for hostname: " . $hostname);
            
            // Try exact match first using index
            if (isset($deviceIndex['byHostname'][$hostname])) {
                $device = $deviceIndex['byHostname'][$hostname];
                error_log("Found exact hostname match in Netshot: " . $device['name']);
                
                // Log IP address fields found (reduced logging)
                $ipFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                foreach ($ipFields as $field) {
                    if (isset($device[$field]) && !empty($device[$field])) {
                        error_log("IP field '$field' contains: " . $device[$field]);
                        break; // Only log the first IP field found
                    }
                }
                
                return $device;
            }
            
            // If exact match failed, try fuzzy matching (only if needed)
            error_log("No exact match found, trying fuzzy matching for: " . $hostname);
            
            foreach ($deviceIndex['byHostname'] as $deviceName => $device) {
                // Check if one contains the other, or if there's a match after stripping non-alphanumeric chars
                $cleanDeviceName = preg_replace('/[^A-Z0-9]/', '', $deviceName);
                $cleanSearchName = preg_replace('/[^A-Z0-9]/', '', $hostname);
                
                if (strpos($deviceName, $hostname) !== false || 
                    strpos($hostname, $deviceName) !== false ||
                    strpos($cleanDeviceName, $cleanSearchName) !== false ||
                    strpos($cleanSearchName, $cleanDeviceName) !== false) {
                    
                    // Check for CCAP in both names as extra verification
                    if (strpos($deviceName, 'CCAP') !== false && strpos($hostname, 'CCAP') !== false) {
                        error_log("Found fuzzy hostname match: " . $device['name'] . " for query: " . $hostname);
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
        // First escape regex special characters
        $pattern = preg_quote($pattern, '/');
        
        // Then replace escaped wildcards with regex equivalents
        // % and * wildcards should match one or more characters
        $pattern = str_replace(['\\%', '\\*'], ['.*', '.*'], $pattern);
        
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
        // Check if this is a wildcard search - in that case we return as is (but in uppercase)
        if (strpos($hostname, '*') !== false || strpos($hostname, '%') !== false) {
            error_log("Wildcard detected in hostname: $hostname - skipping ABR/DBR/CBR mapping");
            // Convert wildcard pattern to uppercase but preserve wildcards
            $uppercaseHostname = strtoupper($hostname);
            return $uppercaseHostname;
        }
        
        // Additional pattern for adXX* or ahXX* format (where XX are numbers)
        $adAhPattern = '/^(ad|ah)\d{2}/i';
        if (preg_match($adAhPattern, $hostname)) {
            error_log("adXX/ahXX pattern detected in NetshotAPI: " . $hostname . " - skipping CCAP mapping");
            return strtoupper($hostname); // Return in uppercase
        }
        
        // Check for ABR/DBR/CBR pattern - more flexible to match various formats
        $abrPattern = '/^[a-zA-Z]{2}\d{2}(abr|dbr|cbr)\d{1,4}$/i';
        $alternativePattern = '/(abr|dbr|cbr)/i';
        
        if (!preg_match($abrPattern, $hostname) && !preg_match($alternativePattern, $hostname)) {
            // Not an ABR/DBR/CBR hostname, return as is but in uppercase
            return strtoupper($hostname);
        }
        
        error_log("ABR/DBR/CBR format detected: " . $hostname . ". Looking up corresponding CCAP device.");
        
        try {
            // Try to load the Database class
            if (!class_exists('Database')) {
                require_once __DIR__ . '/Database.php';
            }
            
            // Create database connection
            $db = new Database();
            
            // Query for the CCAP name using case-insensitive comparison with UPPER()
            // Search both ccap_name and alias columns to handle any input type
            $escapedHostname = str_replace("'", "''", $hostname); // Simple SQL escape
            $sql = "SELECT UPPER(ccap_name) as ccap_name FROM reporting.acc_alias WHERE UPPER(ccap_name) = UPPER('$escapedHostname') OR UPPER(alias) = UPPER('$escapedHostname')";
            error_log("Executing database query: " . $sql);
            
            $result = $db->query($sql);
            if (!empty($result) && isset($result[0]['ccap_name'])) {
                $ccapHostname = $result[0]['ccap_name']; // Already uppercase from query
                error_log("Found corresponding CCAP hostname: " . $ccapHostname . " for ABR/DBR/CBR device: " . $hostname);
                return $ccapHostname;
            } else {
                error_log("No CCAP mapping found for ABR/DBR/CBR device: " . $hostname);
                return strtoupper($hostname); // Return original in uppercase if no mapping found
            }
        } catch (Exception $dbException) {
            error_log("Database error looking up ABR/DBR/CBR alias: " . $dbException->getMessage());
            return strtoupper($hostname); // Return original in uppercase on error
        }
    }
    
    /**
     * Helper method to find alias hostname for a CCAP hostname (reverse of mapAbrToCcapHostname)
     * Used when IP search returns a CCAP device but we want to show the user-friendly alias
     * 
     * @param string $ccapHostname The CCAP hostname to find an alias for
     * @return string The alias hostname if found, otherwise the original hostname
     */
    public function findAliasForCcapHostname($ccapHostname) {
        try {
            // Try to load the Database class
            if (!class_exists('Database')) {
                require_once __DIR__ . '/Database.php';
            }
            
            // Create database connection
            $db = new Database();
            
            // Use flexible query to find CCAP hostname regardless of input type
            $escapedHostname = str_replace("'", "''", $ccapHostname); // Simple SQL escape
            $sql = "SELECT UPPER(ccap_name) as hostname FROM reporting.acc_alias WHERE UPPER(ccap_name) = UPPER('$escapedHostname') OR UPPER(alias) = UPPER('$escapedHostname')";
            error_log("Looking up CCAP hostname for: $ccapHostname - Query: $sql");
            
            $result = $db->query($sql);
            if (!empty($result) && isset($result[0]['hostname'])) {
                $ccapName = $result[0]['hostname']; // Already uppercase from query
                
                // Now find the alias for this CCAP name
                $sql2 = "SELECT UPPER(alias) as alias FROM reporting.acc_alias WHERE UPPER(ccap_name) = UPPER('$ccapName')";
                error_log("Looking up alias for CCAP hostname: $ccapName - Query: $sql2");
                
                $result2 = $db->query($sql2);
                if (!empty($result2) && isset($result2[0]['alias'])) {
                    $aliasHostname = $result2[0]['alias']; // Already uppercase from query
                    error_log("Found alias hostname: $aliasHostname for CCAP device: $ccapName");
                    return $aliasHostname;
                } else {
                    error_log("No alias found for CCAP device: $ccapName - returning CCAP name");
                    return $ccapName;
                }
            }
            
            error_log("No CCAP mapping found for: $ccapHostname");
            return strtoupper($ccapHostname); // Return original in uppercase if no mapping found
            
        } catch (Exception $dbException) {
            error_log("Database error looking up alias for CCAP: " . $dbException->getMessage());
            return strtoupper($ccapHostname); // Return original in uppercase on error
        }
    }

    public function getDeviceByIP($ipAddress) {
        try {
            error_log("Fetching device from Netshot for IP lookup: " . $ipAddress);
            
            // Get only INPRODUCTION devices - no caching
            $devices = $this->getDevicesInGroup(null, true, false);
            
            if (empty($devices)) {
                error_log("No devices available for IP search: " . $ipAddress);
                return null;
            }
            
            // Build index for faster IP lookups
            $deviceIndex = $this->buildDeviceIndex($devices);
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices for IP: " . $ipAddress);
            
            // Use index for O(1) lookup instead of O(n) iteration
            if (isset($deviceIndex['byIp'][$ipAddress])) {
                $device = $deviceIndex['byIp'][$ipAddress];
                
                $originalHostname = $device['name'] ?? '';
                $displayHostname = strtoupper($originalHostname);
                $aliasHostname = null;
                
                // Handle migration scenario: always determine the CCAP hostname and alias
                if (preg_match('/(ABR|DBR|CBR)/i', $originalHostname)) {
                    // Netshot discovered device with alias name - map to CCAP
                    error_log("Found device with alias name in Netshot for IP $ipAddress: $originalHostname - looking up target CCAP name");
                    $ccapHostname = $this->mapAbrToCcapHostname($originalHostname);
                    if ($ccapHostname !== strtoupper($originalHostname)) {
                        $aliasHostname = strtoupper($originalHostname); // Store the original as alias
                        $displayHostname = $ccapHostname; // Use CCAP as main hostname
                        error_log("Using target CCAP hostname $ccapHostname with alias $aliasHostname for consistency");
                    }
                } else {
                    // Netshot discovered device with CCAP name - look up alias
                    $alias = $this->findAliasForCcapHostname($originalHostname);
                    if ($alias !== strtoupper($originalHostname)) {
                        $aliasHostname = $alias;
                        $displayHostname = strtoupper($originalHostname); // Keep CCAP as main
                        error_log("Found alias $alias for CCAP hostname $originalHostname");
                    }
                }
                
                // Format the response consistently and ensure hostname is uppercase
                $result = [
                    'Id' => $device['id'] ?? null,
                    'Name' => $displayHostname, // Always the CCAP hostname
                    'IpAddress' => $device['mgmtIp'] ?? $ipAddress,
                    'Model' => $device['family'] ?? null,
                    'Vendor' => $device['domain'] ?? null,
                    'Status' => $device['status'] ?? null,
                    'SoftwareVersion' => $device['softwareVersion'] ?? null,
                    'LastCheck' => $device['lastCheck'] ?? null
                ];
                
                // Always add alias if one exists
                if ($aliasHostname && $aliasHostname !== $displayHostname) {
                    $result['Alias'] = $aliasHostname;
                    error_log("Added Alias field: $aliasHostname for device $displayHostname");
                }
                
                return $result;
            }
            
            // Device not found
            error_log("No device found with IP: " . $ipAddress);
            return null;
        } catch (Exception $e) {
            error_log("Error in getDeviceByIP: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get device hostnames by IP address (optimized)
     * 
     * @param string $ipAddress The IP address to search for
     * @return array List of hostnames associated with this IP
     */
    public function getDeviceNamesByIP($ipAddress) {
        try {
            error_log("Looking up hostnames for IP: " . $ipAddress);
            
            // Get only INPRODUCTION devices - no caching
            $devices = $this->getDevicesInGroup(null, true, false);
            
            if (empty($devices)) {
                return [];
            }
            
            // Build index for faster lookups
            $deviceIndex = $this->buildDeviceIndex($devices);
            
            error_log("Searching " . count($devices) . " INPRODUCTION devices for hostnames with IP: " . $ipAddress);
            $hostnames = [];
            
            // Use index for faster lookup
            if (isset($deviceIndex['byIp'][$ipAddress])) {
                $device = $deviceIndex['byIp'][$ipAddress];
                if (isset($device['name']) && !empty($device['name'])) {
                    $hostnames[] = strtoupper($device['name']);
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
            error_log("enrichDeviceData: No hostname provided in device data");
            return $dbDevice;
        }
        
        $originalHostname = $dbDevice['hostname'];
        error_log("enrichDeviceData: Starting enrichment for hostname: $originalHostname");
        
        // Step 1: Try to get IP from memory lookup first (much faster)
        $memoryResult = $this->lookupIpFromMemory($originalHostname);
        
        // Handle both single result and wildcard results
        if ($memoryResult) {
            // Check if it's a wildcard result (array of matches)
            if (is_array($memoryResult) && isset($memoryResult[0])) {
                // Multiple matches from wildcard - use first match
                $memoryResult = $memoryResult[0];
                error_log("enrichDeviceData: Using first match from wildcard search for '$originalHostname'");
            }
            
            if (isset($memoryResult['ip_address'])) {
                error_log("enrichDeviceData: Found IP in memory for '$originalHostname': " . $memoryResult['ip_address']);
                
                // Add the IP address and preserve original hostname
                $dbDevice['ip_address'] = $memoryResult['ip_address'];
                $dbDevice['hostname'] = $originalHostname; // Always preserve original hostname
                
                if ($memoryResult['is_alias']) {
                    $dbDevice['ccap_hostname'] = $memoryResult['ccap_hostname'];
                    error_log("enrichDeviceData: Successfully resolved alias '$originalHostname' to IP via CCAP '{$memoryResult['ccap_hostname']}'");
                } else {
                    error_log("enrichDeviceData: Successfully resolved direct hostname '$originalHostname' to IP");
                }
                
                // Add Netshot metadata by looking up the CCAP device
                $ccapHostname = $memoryResult['is_alias'] ? $memoryResult['ccap_hostname'] : $originalHostname;
                $netshotDevice = $this->getDeviceByHostname($ccapHostname, false);
                
                if ($netshotDevice) {
                    $dbDevice['netshot_id'] = $netshotDevice['id'] ?? null;
                    $dbDevice['netshot_status'] = $netshotDevice['status'] ?? null;
                    $dbDevice['netshot_family'] = $netshotDevice['family'] ?? null;
                    $dbDevice['netshot_software_version'] = $netshotDevice['softwareVersion'] ?? null;
                    error_log("enrichDeviceData: Added Netshot metadata for '$originalHostname'");
                }
                
                return $dbDevice;
            }
        }
        
        // Step 2: Fallback to old method if not found in memory
        error_log("enrichDeviceData: Not found in memory, falling back to direct Netshot lookup for: $originalHostname");
        
        // Map ABR/DBR/CBR hostname to CCAP if applicable
        $hostname = $this->mapAbrToCcapHostname($originalHostname);
        $isAliasedDevice = ($hostname !== $originalHostname);
        
        if ($isAliasedDevice) {
            error_log("enrichDeviceData: Hostname mapped from alias $originalHostname to CCAP $hostname");
        }
        
        // Set validateFormat to false to maintain backward compatibility
        $netshotDevice = $this->getDeviceByHostname($hostname, false);
        
        error_log("enrichDeviceData: getDeviceByHostname result for '$hostname': " . 
                  ($netshotDevice ? 'Found device' : 'Not found'));
        
        // Handle both null (not found) and false (invalid format)
        if ($netshotDevice === null || $netshotDevice === false) {
            error_log("enrichDeviceData: Device not found in Netshot for hostname: $hostname");
            
            // Try fallback: search with original hostname if it was mapped
            if ($hostname !== $originalHostname) {
                error_log("enrichDeviceData: Trying fallback search with original hostname: $originalHostname");
                $netshotDevice = $this->getDeviceByHostname($originalHostname, false);
                
                if ($netshotDevice) {
                    error_log("enrichDeviceData: Found device with original hostname: $originalHostname");
                } else {
                    error_log("enrichDeviceData: Fallback search also failed for: $originalHostname");
                }
            }
            
            // If still not found, return original device without enrichment
            if (!$netshotDevice) {
                error_log("enrichDeviceData: No device found in Netshot, returning original device data");
                return $dbDevice;
            }
        }
        
        // Check for IP address in various possible field names
        $ipAddress = null;
        $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
        
        error_log("enrichDeviceData: Searching for IP address in Netshot device fields");
        foreach ($possibleIpFields as $field) {
            if (isset($netshotDevice[$field]) && !empty($netshotDevice[$field])) {
                $fieldValue = $netshotDevice[$field];
                
                // Handle case where IP is an object with 'ip' field
                if (is_array($fieldValue) && isset($fieldValue['ip'])) {
                    $ipAddress = $fieldValue['ip'];
                    error_log("enrichDeviceData: Found IP in complex field '$field': $ipAddress");
                } else {
                    $ipAddress = $fieldValue;
                    error_log("enrichDeviceData: Found IP in field '$field': $ipAddress");
                }
                break;
            }
        }
        
        if (!$ipAddress) {
            error_log("enrichDeviceData: No IP address found in any Netshot fields for hostname: $hostname");
            // Log all available fields for debugging
            $availableFields = array_keys($netshotDevice);
            error_log("enrichDeviceData: Available Netshot fields: " . implode(', ', $availableFields));
        }
        
        // Add Netshot data to device record
        $dbDevice['netshot_id'] = $netshotDevice['id'] ?? null;
        $dbDevice['netshot_status'] = $netshotDevice['status'] ?? null;
        $dbDevice['netshot_family'] = $netshotDevice['family'] ?? null;
        $dbDevice['netshot_software_version'] = $netshotDevice['softwareVersion'] ?? null;
        
        // For aliased devices, preserve the original alias hostname but add IP from CCAP
        if ($isAliasedDevice) {
            $dbDevice['hostname'] = $originalHostname; // Keep the alias name (ABR/DBR/CBR)
            $dbDevice['ccap_hostname'] = $hostname; // Store the CCAP hostname for reference
            error_log("enrichDeviceData: Preserving alias hostname '$originalHostname' while using CCAP '$hostname' for IP lookup");
        }
        
        // Add IP address if found
        if ($ipAddress) {
            $dbDevice['ip_address'] = $ipAddress;
            if ($isAliasedDevice) {
                error_log("enrichDeviceData: Successfully enriched alias '$originalHostname' with IP from CCAP '$hostname': $ipAddress");
            } else {
                error_log("enrichDeviceData: Successfully enriched hostname '$originalHostname' with IP: $ipAddress");
            }
        } else {
            if ($isAliasedDevice) {
                error_log("enrichDeviceData: No IP address available for alias '$originalHostname' (CCAP: '$hostname')");
            } else {
                error_log("enrichDeviceData: No IP address available for hostname: $originalHostname");
            }
        }
        
        return $dbDevice;
    }
}
