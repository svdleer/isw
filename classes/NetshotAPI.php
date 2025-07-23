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
    private $cacheTime = 3600; // Cache results for 1 hour by default
    private $cacheDir;
    
    /**
     * Constructor
     * 
     * @param string $apiUrl The base URL for Netshot API
     * @param string $apiKey The API key for authentication
     */
    public function __construct($apiUrl = null, $apiKey = null) {
        // Load environment variables
        require_once __DIR__ . '/EnvLoader.php';
        EnvLoader::load();
        
        // Use provided values or fall back to environment variables
        $this->apiUrl = $apiUrl ?: $_ENV['NETSHOT_API_URL'] ?? 'https://netshot.oss.local/api';
        $this->apiKey = $apiKey ?: $_ENV['NETSHOT_API_KEY'] ?? 'UqRf6NkgvKru3rxRRrRKck1VoANQJvP2';
        
        // Set up cache directory
        $this->cacheDir = __DIR__ . '/../cache/netshot';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get devices from a specific group
     * 
     * @param int $groupId The ID of the group to fetch devices from
     * @return array Devices in the specified group
     */
    public function getDevicesInGroup($groupId = 207) {
        // Check cache first
        $cacheKey = "devices_group_{$groupId}";
        $cachedResult = $this->getFromCache($cacheKey);
        if ($cachedResult !== false) {
            return $cachedResult;
        }
        
        $url = "{$this->apiUrl}/devices?group={$groupId}";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Netshot-API-Key: ' . $this->apiKey,
                'Accept: application/json'
            ]);
            // Skip SSL verification in development (remove this in production)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                error_log("Netshot API error: HTTP {$httpCode} - " . curl_error($ch));
                return [];
            }
            
            curl_close($ch);
            
            $result = json_decode($response, true) ?: [];
            
            // Save to cache
            $this->saveToCache($cacheKey, $result);
            
            return $result;
        } catch (Exception $e) {
            error_log("Netshot API exception: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get data from cache
     * 
     * @param string $key The cache key
     * @return mixed The cached data or false if not found/expired
     */
    private function getFromCache($key) {
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = file_get_contents($cacheFile);
            $cache = json_decode($data, true);
            
            // Check if cache is still valid
            if ($cache['expires'] > time()) {
                return $cache['data'];
            }
        }
        
        return false;
    }
    
    /**
     * Save data to cache
     * 
     * @param string $key The cache key
     * @param mixed $data The data to cache
     * @return bool Success or failure
     */
    private function saveToCache($key, $data) {
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        
        $cache = [
            'expires' => time() + $this->cacheTime,
            'data' => $data
        ];
        
        return file_put_contents($cacheFile, json_encode($cache)) !== false;
    }
    
    /**
     * Clear the cache for a specific key or all cache
     * 
     * @param string|null $key The cache key (or null to clear all)
     * @return void
     */
    public function clearCache($key = null) {
        if ($key) {
            $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            $files = glob($this->cacheDir . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Search for a device by IP address
     * 
     * @param string $ipPattern IP address pattern (can include wildcards as * or %)
     * @return array Matching devices
     */
    public function searchDevicesByIp($ipPattern) {
        try {
            // Check cache first
            $cacheKey = "search_ip_" . $ipPattern;
            $cachedResult = $this->getFromCache($cacheKey);
            if ($cachedResult !== false) {
                error_log("Using cached IP wildcard search results for: " . $ipPattern);
                return $cachedResult;
            }
            
            // Convert SQL LIKE pattern to regex
            $regexPattern = $this->patternToRegex($ipPattern);
            $devices = $this->getDevicesInGroup();
            
            error_log("Searching devices with IP pattern: " . $ipPattern . " (regex: " . $regexPattern . ")");
            
            // Filter devices by IP
            $results = [];
            foreach ($devices as $device) {
                if (!isset($device['mgmtIp'])) {
                    continue;
                }
                
                if (preg_match($regexPattern, $device['mgmtIp'])) {
                    $results[] = [
                        'id' => $device['id'] ?? null,
                        'name' => $device['name'] ?? null,
                        'ip' => $device['mgmtIp'] ?? '',
                        'model' => $device['family'] ?? null,
                        'vendor' => $device['domain'] ?? null,
                        'status' => $device['status'] ?? null
                    ];
                }
            }
            
            error_log("Found " . count($results) . " matching devices for IP pattern: " . $ipPattern);
            
            // Save to cache
            $this->saveToCache($cacheKey, $results);
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
     * @return array|null Device details or null if not found
     */
    public function getDeviceByHostname($hostname) {
        try {
            // Check cache first
            $cacheKey = "device_hostname_" . strtolower($hostname);
            $cachedResult = $this->getFromCache($cacheKey);
            if ($cachedResult !== false) {
                error_log("Using cached Netshot data for hostname: " . $hostname);
                return $cachedResult;
            }
            
            $devices = $this->getDevicesInGroup();
            error_log("Searching " . count($devices) . " Netshot devices for hostname: " . $hostname);
            
            // Log a sample device to see the structure
            if (!empty($devices)) {
                $sampleDevice = $devices[0];
                error_log("Sample Netshot device structure: " . json_encode(array_keys($sampleDevice)));
                
                // Check for IP address fields in the sample device
                $ipFields = ['mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                foreach ($ipFields as $field) {
                    if (isset($sampleDevice[$field])) {
                        error_log("Found IP field in Netshot data: '$field' with value: " . $sampleDevice[$field]);
                    }
                }
            }
            
            // First try exact match
            foreach ($devices as $device) {
                if (isset($device['name']) && strtoupper($device['name']) === strtoupper($hostname)) {
                    error_log("Found exact hostname match in Netshot: " . $device['name']);
                    
                    // Log all fields in the device
                    foreach ($device as $key => $value) {
                        if (!is_array($value)) {
                            error_log("Device field '$key': " . $value);
                        } else {
                            error_log("Device field '$key': [array]");
                        }
                    }
                    
                    // Save to cache
                    $this->saveToCache($cacheKey, $device);
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
                $searchName = strtoupper($hostname);
                
                // Check if one contains the other
                if (strpos($deviceName, $searchName) !== false || strpos($searchName, $deviceName) !== false) {
                    // Check for CCAP in both names as extra verification
                    if (strpos($deviceName, 'CCAP') !== false && strpos($searchName, 'CCAP') !== false) {
                        error_log("Found fuzzy hostname match: " . $device['name'] . " for query: " . $hostname);
                        
                        // Check IP field
                        $ipFields = ['mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                        foreach ($ipFields as $field) {
                            if (isset($device[$field]) && !empty($device[$field])) {
                                error_log("IP field '$field' contains: " . $device[$field]);
                            }
                        }
                        
                        // Save to cache
                        $this->saveToCache($cacheKey, $device);
                        return $device;
                    }
                }
            }
            
            // No match found - cache null result to prevent repeated lookups
            error_log("No hostname match found in Netshot for: " . $hostname);
            $this->saveToCache($cacheKey, null);
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
    public function getDeviceByIP($ipAddress) {
        try {
            // Check cache first
            $cacheKey = "device_ip_" . $ipAddress;
            $cachedResult = $this->getFromCache($cacheKey);
            if ($cachedResult !== false) {
                error_log("Using cached Netshot data for IP: " . $ipAddress);
                return $cachedResult;
            }
            
            error_log("Fetching devices from Netshot for IP lookup: " . $ipAddress);
            $devices = $this->getDevicesInGroup();
            
            foreach ($devices as $device) {
                if (isset($device['mgmtIp']) && $device['mgmtIp'] === $ipAddress) {
                    // Format the response consistently
                    $result = [
                        'id' => $device['id'] ?? null,
                        'name' => $device['name'] ?? null,
                        'ip' => $device['mgmtIp'] ?? $ipAddress,
                        'model' => $device['family'] ?? null,
                        'vendor' => $device['domain'] ?? null,
                        'status' => $device['status'] ?? null,
                        'software_version' => $device['softwareVersion'] ?? null,
                        'last_check' => $device['lastCheck'] ?? null
                    ];
                    
                    // Save to cache
                    $this->saveToCache($cacheKey, $result);
                    return $result;
                }
            }
            
            // Device not found - cache null result to prevent repeated lookups
            $this->saveToCache($cacheKey, null);
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
            // Check cache first
            $cacheKey = "device_names_ip_" . $ipAddress;
            $cachedResult = $this->getFromCache($cacheKey);
            if ($cachedResult !== false) {
                error_log("Using cached hostname results for IP: " . $ipAddress);
                return $cachedResult;
            }
            
            error_log("Looking up hostnames for IP: " . $ipAddress);
            $devices = $this->getDevicesInGroup();
            $hostnames = [];
            
            foreach ($devices as $device) {
                if (isset($device['mgmtIp']) && $device['mgmtIp'] === $ipAddress) {
                    if (isset($device['name']) && !empty($device['name'])) {
                        $hostnames[] = $device['name'];
                    }
                }
            }
            
            // Save to cache
            $this->saveToCache($cacheKey, $hostnames);
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
        
        $netshotDevice = $this->getDeviceByHostname($dbDevice['hostname']);
        
        if (!$netshotDevice) {
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
