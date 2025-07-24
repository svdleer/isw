<?php
/**
 * CMDB REST API
 * 
 * Search endpoints:
 * GET /api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=your-key
 * GET /api/search?type=hostname&q=CCAP*&api_key=your-key
 * GET /api/search?type=ip&q=192.168.1.100&api_key=your-key
 * GET /api/search?type=ip&q=192.168.1.*&api_key=your-key
 * 
 * POST /api/search (JSON body search)
 * Body search on Hostname:
 * {
 *     "Header": {
 *         "BusinessTransactionID": "1",
 *         "SentTimestamp": "2023-11-10T09:20:00",
 *         "SourceContext": {
 *             "host": "String",
 *             "application": "String"
 *         }
 *     },
 *     "Body": {
 *         "HostName": "GV-RC0052-CCAP002"
 *     }
 * }
 * 
 * Body search on IP address:
 * {
 *     "Header": {
 *         "BusinessTransactionID": "1",
 *         "SentTimestamp": "2023-11-10T09:20:00",
 *         "SourceContext": {
 *             "host": "String",
 *             "application": "String"
 *         }
 *     },
 *     "Body": {
 *         "IPAddress": "172.16.55.26"
 *     }
 * }
 * 
 * With Netshot integration, additional device information will be included 
 * when searching by IP address if the device is found in Netshot.
 */

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('WWW-Authenticate: Basic realm="CMDB API Access"');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ApiAuth.php';
require_once __DIR__ . '/../classes/NetshotAPI.php';

/**
 * Helper function to generate an IP address from a hostname
 * This is used when Netshot API doesn't return any data
 * 
 * @param string $hostname The hostname to generate an IP for
 * @return string|null Generated IP address or null if no pattern matches
 */
function generateIpFromHostname($hostname) {
    // This function is deprecated. IP addresses should ONLY come from Netshot.
    error_log("WARNING: generateIpFromHostname called but is deprecated. IP addresses should ONLY come from Netshot for: " . $hostname);
    return null; // Always return null to ensure Netshot is used as the source of truth
}

try {
    // Initialize classes
    $db = new Database();
    $auth = new ApiAuth($db); // Pass database connection for API key validation
    $netshot = new NetshotAPI(); // Initialize Netshot API client
    
    // Handle different request methods
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $isJsonRequest = false;
    
    if ($requestMethod === 'POST') {
        // Handle JSON body request
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        
        // Check if the request is JSON
        if (strpos($contentType, 'application/json') !== false) {
            $isJsonRequest = true;
            
            // Get JSON input
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);
            
            // Check if JSON is valid
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['Body'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid JSON format',
                    'status' => 400
                ]);
                exit();
            }
            
            // Extract search parameters from JSON body
            if (isset($data['Body']['HostName'])) {
                $searchType = 'hostname';
                $query = $data['Body']['HostName'];
            } elseif (isset($data['Body']['IPAddress'])) {
                $searchType = 'ip';
                $query = $data['Body']['IPAddress'];
                
                // Pre-validate IP address to prevent invalid formats early
                if (!is_string($query) || empty($query)) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'IPAddress must be a non-empty string',
                        'status' => 400
                    ]);
                    exit();
                }
                
                // Special case for JSON body IP searches - add request to track it in logs
                error_log("JSON body IP search for: " . $query);
                
                // For exact IP searches in JSON body, check Netshot API first for better data
                if (strpos($query, '*') === false && strpos($query, '%') === false) {
                    error_log("Checking Netshot API for IP: " . $query);
                }
            } else {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Missing search parameters in Body. Expected either "HostName" or "IPAddress"',
                    'status' => 400
                ]);
                exit();
            }
        } else {
            http_response_code(415);
            echo json_encode([
                'error' => 'Unsupported Media Type. Expected application/json',
                'status' => 415
            ]);
            exit();
        }
    } else {
        // Handle GET request (original implementation)
        $searchType = $_GET['type'] ?? null;
        $query = $_GET['q'] ?? null;
    }
    
    // Get and validate HTTP Basic Authentication credentials
    $credentials = $auth->getBasicAuthCredentials();
    
    // Debug log to help troubleshoot
    error_log('Authentication attempt: ' . ($credentials ? json_encode($credentials) : 'No credentials'));
    
    if (!$credentials || !$auth->validateBasicAuth($credentials['username'], $credentials['password'])) {
        // If Apache is rewriting the URL, try to get credentials from PHP_AUTH_* vars
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if ($auth->validateBasicAuth($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
                // Valid credentials found in PHP_AUTH_* vars
                error_log('Authenticated via PHP_AUTH_* variables');
            } else {
                http_response_code(401);
                echo json_encode([
                    'error' => 'Invalid or missing authentication credentials',
                    'status' => 401,
                    'authentication' => 'HTTP Basic Authentication required'
                ]);
                exit();
            }
        } else {
            http_response_code(401);
            echo json_encode([
                'error' => 'Invalid or missing authentication credentials',
                'status' => 401,
                'authentication' => 'HTTP Basic Authentication required'
            ]);
            exit();
        }
    }
    
    // Validate required parameters
    if (!$searchType || !$query) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required parameters',
            'status' => 400,
            'usage' => [
                'get_request' => [
                    'hostname_search' => '/api/search?type=hostname&q=GV-RC0011-CCAP003',
                    'hostname_wildcard' => '/api/search?type=hostname&q=CCAP*',
                    'ip_search' => '/api/search?type=ip&q=192.168.1.100',
                    'ip_wildcard' => '/api/search?type=ip&q=192.168.1.*'
                ],
                'post_request' => [
                    'hostname_search' => '{"Header":{"BusinessTransactionID":"1","SentTimestamp":"timestamp","SourceContext":{"host":"String","application":"String"}},"Body":{"HostName":"GV-RC0052-CCAP002"}}',
                    'ip_search' => '{"Header":{"BusinessTransactionID":"1","SentTimestamp":"timestamp","SourceContext":{"host":"String","application":"String"}},"Body":{"IPAddress":"172.16.55.26"}}'
                ],
                'authentication' => 'HTTP Basic Authentication required (Authorization: Basic base64(username:password))'
            ]
        ]);
        exit();
    }
    
    $results = [];
    
    switch ($searchType) {
        case 'hostname':
            // Validate hostname format
            if (!$auth->validateHostname($query)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid hostname format. Expected: CCAP device format (GV-RC0011-CCAP003), ABR/DBR/CBR format (ah00cbr67), or * for wildcard',
                    'example' => 'GV-RC0011-CCAP003',
                    'abr_example' => 'ah00cbr67',
                    'wildcard_example' => 'CCAP*',
                    'status' => 400
                ]);
                exit();
            }
            
            // Debug the original query
            error_log("Original hostname query: " . $query);
            
            // Check if this is an ABR/DBR/CBR hostname and try to map it to a CCAP hostname
            $originalQuery = $query;
            // Remove wildcards for detection
            $queryForDetection = str_replace(['*', '%'], '', $originalQuery);
            $abrPattern = '/^[a-zA-Z]{2}\d{2}(abr|dbr|cbr)\d{1,4}$/i';
            $alternativePattern = '/(abr|dbr|cbr)/i';
            $isAbrFormat = false;
            
            // Special case for wildcard searches containing ABR/DBR/CBR
            $hasWildcard = (strpos($originalQuery, '*') !== false || strpos($originalQuery, '%') !== false);
            $containsAbrKeyword = (stripos($originalQuery, 'abr') !== false || 
                                  stripos($originalQuery, 'dbr') !== false || 
                                  stripos($originalQuery, 'cbr') !== false);
            
            // Additional pattern for adXX* or ahXX* format (where XX are numbers)
            // This pattern specifically detects "ad32*" style hostnames that shouldn't have CCAP added
            $adAhPattern = '/^(ad|ah)\d{2}/i';
            $matchesAdAhPattern = preg_match($adAhPattern, $queryForDetection);
            
            // Log the detection of this special pattern
            if ($matchesAdAhPattern) {
                error_log("Special adXX/ahXX pattern detected in hostname: " . $originalQuery);
                // Explicitly set the adXX/ahXX pattern as ABR format to prevent CCAP addition
                $isAbrFormat = true;
            }
            
            if (preg_match($abrPattern, $queryForDetection) || 
                preg_match($alternativePattern, $queryForDetection) || 
                ($hasWildcard && $containsAbrKeyword)) {
                error_log("ABR/DBR/CBR format hostname detected: " . $originalQuery);
                $isAbrFormat = true;
                
                // Map ABR/DBR/CBR hostname to CCAP hostname using NetshotAPI's method
                $mappedHostname = $netshot->mapAbrToCcapHostname($originalQuery);
                
                if ($mappedHostname !== $originalQuery) {
                    error_log("Mapped ABR/DBR/CBR hostname $originalQuery to CCAP: $mappedHostname");
                    $query = $mappedHostname;
                } else {
                    error_log("No CCAP mapping found for ABR/DBR/CBR hostname: $originalQuery");
                }
            }
            
            // Prepare query for database
            // Skip prepareHostnameQuery for ABR/DBR/CBR format to avoid adding CCAP
            if ($isAbrFormat) {
                $searchQuery = str_replace('*', '%', $query);
                error_log("ABR/DBR/CBR format detected - skipping prepareHostnameQuery to avoid adding CCAP");
            } else {
                $searchQuery = $auth->prepareHostnameQuery($query);
                error_log("After prepareHostnameQuery: " . $searchQuery);
            }
            
            // Special case: if only wildcard is submitted or query is just '*' or '%', search for all CCAP devices
            if ($searchQuery === '%' || $searchQuery === '*' || $query === '*' || $query === '%') {
                $searchQuery = '%CCAP%';
                error_log("Wildcard search converted to return all CCAP devices: " . $searchQuery);
            } 
            
            // For ABR/DBR/CBR format or adXX/ahXX pattern, don't force CCAP in the search criteria
            if ($isAbrFormat) {
                error_log("Special format detected (ABR/DBR/CBR or adXX/ahXX) - not adding CCAP to search query");
                
                // If it's specifically an adXX/ahXX pattern, ensure we're using a clear log message
                if ($matchesAdAhPattern) {
                    error_log("adXX/ahXX pattern detected - CCAP will NOT be added to: " . $searchQuery);
                }
                
                // No need to modify searchQuery here as the SQL will be constructed directly using the original query
            }
            // For normal searches, always make sure CCAP is part of the search criteria
            else if (stripos($searchQuery, 'CCAP') === false) {
                // Check if the query already has wildcards
                if (strpos($searchQuery, '%') !== false) {
                    // For queries that already have wildcards, insert CCAP in the middle
                    // instead of prepending it to avoid the wrong positioning
                    $searchQuery = str_replace('%', '%CCAP%', $searchQuery, 1);
                    error_log("Added CCAP to wildcard query: " . $searchQuery);
                } else {
                    // If no wildcards, ensure we're searching for CCAP as part of the hostname
                    $searchQuery = '%CCAP%' . $searchQuery;
                    error_log("Added CCAP to beginning of query: " . $searchQuery);
                }
            }
            
            // Always convert to wildcard format for consistency
            $searchQuery = str_replace('*', '%', $searchQuery);
            
            // Log the final search query for debugging
            error_log("Final hostname search query: " . $searchQuery);
            
            // NEW: Try memory-based wildcard search first for better performance
            $memoryResults = [];
            if ($hasWildcard) {
                error_log("Attempting memory-based wildcard search for: " . $originalQuery);
                
                try {
                    $startTime = microtime(true);
                    $memoryMatches = $netshot->searchHostnamesByWildcard($originalQuery);
                    $memoryTime = microtime(true) - $startTime;
                    
                    error_log("Memory wildcard search completed in " . round($memoryTime, 3) . " seconds, found " . count($memoryMatches) . " matches");
                    
                    if (!empty($memoryMatches)) {
                        // Convert memory results to the expected format
                        foreach ($memoryMatches as $match) {
                            $memoryResults[] = [
                                'hostname' => strtoupper($match['hostname']),
                                'ip_address' => $match['ip_address']
                            ];
                        }
                        
                        error_log("Memory-based search successful - using " . count($memoryResults) . " results from memory");
                        $results = $memoryResults;
                        
                        // Skip database search since we have results from memory
                        break;
                    }
                } catch (Exception $e) {
                    error_log("Memory-based search failed: " . $e->getMessage() . " - falling back to database");
                }
            }
            error_log("Query classification summary - Original: " . $originalQuery . 
                      ", isAbrFormat: " . ($isAbrFormat ? "Yes" : "No") . 
                      ", matchesAdAhPattern: " . ($matchesAdAhPattern ? "Yes" : "No") . 
                      ", containsCCAP: " . (stripos($searchQuery, 'CCAP') !== false ? "Yes" : "No"));
                      
            // Extra debugging for final query structure
            $wildcardPositions = [];
            $position = -1;
            while (($position = strpos($searchQuery, '%', $position + 1)) !== false) {
                $wildcardPositions[] = $position;
            }
            error_log("Wildcard positions in final query: " . implode(", ", $wildcardPositions));
            
            // Performance optimization for *CCAP* searches - use a direct index
            $isAllCcapSearch = (strtoupper($searchQuery) === '%CCAP%' || $query === '*CCAP*');
            error_log("Is all CCAP search: " . ($isAllCcapSearch ? "Yes" : "No"));
            
            // Updated SQL query based on actual database structure with direct string interpolation
            // Not using loopbackip since it's not useful
            $escapedSearchQuery = str_replace("'", "''", $searchQuery); // Basic SQL escaping for the LIKE clause
            $escapedOriginalQuery = str_replace("'", "''", strtoupper($originalQuery)); // Original query for ABR searches
            
            // Debug the exact SQL pattern that will be used
            error_log("SQL pattern will be: " . $escapedSearchQuery);
            error_log("SQL pattern decoded: " . str_replace('%', '[WILDCARD]', $escapedSearchQuery));
            
            // Special case for ABR/DBR/CBR format
            if ($isAbrFormat) {
                error_log("Using ABR/DBR/CBR optimized query for: " . $escapedOriginalQuery);
                
                // Handle wildcards in the query
                $hasWildcard = (strpos($originalQuery, '*') !== false || strpos($originalQuery, '%') !== false);
                $searchPattern = $hasWildcard ? str_replace(['*', '%'], ['%', '%'], $escapedOriginalQuery) : "%$escapedOriginalQuery%";
                
                // Debug logs for ABR search pattern
                error_log("ABR/DBR/CBR search pattern: " . $searchPattern);
                error_log("Original escapedSearchQuery: " . $escapedSearchQuery);
                
                // Exact format from user example, but handle wildcards properly
                $sql = "SELECT UPPER(a.hostname) as hostname FROM access.devicesnew a LEFT JOIN reporting.acc_alias b ON a.hostname = b.ccap_name WHERE (a.hostname LIKE '$searchPattern' OR b.alias LIKE '$searchPattern') AND a.active = 1 ORDER BY a.hostname";
                       
                // Log what we expect to see in the database
                error_log("Looking for ABR/DBR/CBR entries with hostname or alias matching: '$searchPattern'");
            }
            // Optimize queries for common *CCAP* case
            else if ($isAllCcapSearch) {
                // Use a more efficient query when looking for all CCAP devices
                $sql = "SELECT 
                       UPPER(hostname) as hostname
                       FROM access.devicesnew 
                       WHERE hostname LIKE '%CCAP%'
                       AND active = 1
                       ORDER BY hostname";
            } else {
                $sql = "SELECT 
                       UPPER(a.hostname) as hostname
                       FROM access.devicesnew a 
                       LEFT JOIN reporting.acc_alias b ON a.hostname = b.ccap_name
                       WHERE (a.hostname LIKE '$escapedSearchQuery' OR b.alias LIKE '$escapedSearchQuery')
                       AND a.active = 1
                       ORDER BY a.hostname";
            }
            
            // Debug: Log the SQL query with interpolated parameters
            error_log("Executing SQL: " . $sql);
            error_log("SQL with params: " . str_replace(['%', "'"], ['%%', "''"], $sql));
            
            $dbResults = $db->query($sql);
            
            // Log the number of results found
            error_log("Database query returned " . count($dbResults) . " results for hostname search: " . $searchQuery);
            
            // Special case for ABR/DBR/CBR - try direct Netshot lookup if no database results
            if ($isAbrFormat && empty($dbResults)) {
                error_log("No results from database for ABR/DBR/CBR format. Trying direct Netshot lookup for: " . $originalQuery);
                
                // Try to get the device directly from Netshot
                $netshotDevice = $netshot->getDeviceByHostname($originalQuery, false);
                
                if ($netshotDevice) {
                    error_log("Found direct match in Netshot for ABR/DBR/CBR hostname: " . $originalQuery);
                    
                    // Add to dbResults so it gets processed later with IP address from Netshot
                    // Check for IP address in various possible field names
                    $ipAddress = null;
                    $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                    foreach ($possibleIpFields as $field) {
                        if (isset($netshotDevice[$field]) && !empty($netshotDevice[$field])) {
                            $ipAddress = $netshotDevice[$field];
                            error_log("Found IP in Netshot field '$field': " . $ipAddress);
                            break;
                        }
                    }
                    
                    $dbResults[] = [
                        'hostname' => strtoupper($netshotDevice['name'] ?? $originalQuery),
                        'ip_address' => $ipAddress
                    ];
                    
                    error_log("Added Netshot direct result to dbResults for: " . $originalQuery);
                } else {
                    error_log("No direct match found in Netshot for ABR/DBR/CBR hostname: " . $originalQuery);
                }
            }
            
            // Process each device and ensure we get IP addresses from Netshot
            $results = [];
            
            error_log("Processing " . count($dbResults) . " devices from database results - will get IP addresses from Netshot");
            
            // Initialize results array - will be populated with enrichment data
            $results = [];
            
            // Process all devices - no artificial limits
            if (!empty($dbResults)) {
                // OPTIMIZATION: Get all Netshot devices in a single API call
                error_log("Getting all devices from Netshot in a single API call");
                $startTime = microtime(true);
                $netshotDevices = $netshot->getDevicesInGroup();
                $endTime = microtime(true);
                error_log("Retrieved " . count($netshotDevices) . " devices from Netshot in " . 
                          number_format(($endTime - $startTime), 2) . " seconds");
                
                // Create a lookup map for faster matching
                $startTime = microtime(true);
                $netshotDeviceMap = [];
                foreach ($netshotDevices as $netshotDevice) {
                    if (isset($netshotDevice['name']) && !empty($netshotDevice['name'])) {
                        $deviceName = strtoupper($netshotDevice['name']);
                        $netshotDeviceMap[$deviceName] = $netshotDevice;
                    }
                }
                $endTime = microtime(true);
                error_log("Created Netshot device map with " . count($netshotDeviceMap) . " entries in " . 
                          number_format(($endTime - $startTime), 2) . " seconds");
                
                // Now process ALL devices to add Netshot data and populate results array
                $startTime = microtime(true);
                foreach ($dbResults as $index => $device) {
                    $hostname = strtoupper($device['hostname']);
                    
                    // Use the enrichDeviceData method to get device data including IP address
                    $enrichedDevice = $netshot->enrichDeviceData($device);
                    
                    // Add enriched device directly to results array
                    $results[] = [
                        'hostname' => $hostname,
                        'ip_address' => $enrichedDevice['ip_address'] ?? ''
                    ];
                    
                    // Log every 50 devices to show progress for large result sets
                    if (($index + 1) % 50 === 0) {
                        error_log("Processed " . ($index + 1) . " of " . count($dbResults) . " devices");
                    }
                }
                    
                $endTime = microtime(true);
                error_log("Processed Netshot data for " . count($dbResults) . " devices in " . 
                          number_format(($endTime - $startTime), 2) . " seconds");
            } else {
                // If no database results, create empty results array
                error_log("No database results found for hostname search");
            }
            break;
            
        case 'ip':
            // Validate IP address format
            if (!$auth->validateIpAddress($query)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid IP address format. Must be a valid IPv4 address or include wildcards in valid format.',
                    'example' => '192.168.1.100',
                    'wildcard_example' => '192.168.1.*',
                    'validation_rules' => [
                        'format' => 'xxx.xxx.xxx.xxx where each xxx is a number between 0-255 or a wildcard (* or %)',
                        'wildcards' => 'Only * or % are accepted as wildcards',
                        'examples_valid' => ['192.168.1.1', '10.0.0.1', '192.168.1.*', '10.10.*.%'],
                        'examples_invalid' => ['192.168.1', '192.168.1.256', '192.168.1.01', 'abc.def.ghi.jkl']
                    ],
                    'status' => 400
                ]);
                exit();
            }
            
            // Prepare query
            $searchQuery = $auth->prepareIpQuery($query);
            $results = [];
            
            // For exact IP search, use Netshot API directly
            if (strpos($searchQuery, '%') === false) {
                error_log("Performing exact IP lookup in Netshot for: " . $searchQuery);
                try {
                    $netshotDevice = $netshot->getDeviceByIP($searchQuery);
                    if ($netshotDevice) {
                        error_log("Device found in Netshot: " . ($netshotDevice['name'] ?? 'unknown'));
                        // Create a result entry with Netshot data - use the IP from the device data, not the search query
                        $deviceIp = $netshotDevice['ip'] ?? $searchQuery; // Fallback to search query if device IP not found
                        
                        $results[] = [
                            'hostname' => strtoupper($netshotDevice['name'] ?? 'Unknown'),
                            'ip_address' => $deviceIp,
                            'netshot' => $netshotDevice
                        ];
                        
                        error_log("Added exact IP result: hostname=" . ($netshotDevice['name'] ?? 'Unknown') . ", ip=" . $deviceIp);
                    } else {
                        error_log("No device found in Netshot for IP: " . $searchQuery);
                        
                        // Not using database loopbackip field as it's not useful
                        error_log("Skipping database loopbackip lookup as it's not useful. Relying on Netshot and fallback algorithm.");
                        
                        // We'll rely completely on Netshot or fallback algorithm for IP address information
                        error_log("Attempting to find device by hostname lookup");
                        // OPTIMIZATION: Use the devices we already retrieved rather than making another API call
                        $hostnameResults = [];
                        
                        // Search through our Netshot devices for any that have this IP
                        foreach ($netshotDevices as $device) {
                            $deviceIp = null;
                            // Check all possible field names for IP address
                            $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                            foreach ($possibleIpFields as $field) {
                                if (isset($device[$field])) {
                                    $ipValue = $device[$field];
                                    // Handle case where IP is an object with 'ip' field
                                    if (is_array($ipValue) && isset($ipValue['ip'])) {
                                        $ipValue = $ipValue['ip'];
                                    }
                                    
                                    if ($ipValue === $searchQuery) {
                                        // Store both hostname and the actual IP value
                                        $hostnameResults[] = [
                                            'hostname' => $device['name'],
                                            'ip_address' => $ipValue // Use actual IP, not search query
                                        ];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (!empty($hostnameResults)) {
                            foreach ($hostnameResults as $deviceInfo) {
                                // For each hostname found, do a hostname search in our database
                                $escapedHostname = str_replace("'", "''", $deviceInfo['hostname']); // Basic SQL escaping
                                $sql = "SELECT 
                                       UPPER(a.hostname) as hostname
                                       FROM access.devicesnew a 
                                       WHERE UPPER(a.hostname) = UPPER('$escapedHostname')
                                       AND a.active = 1";
                                $dbResults = $db->query($sql);
                                
                                if (!empty($dbResults)) {
                                    foreach ($dbResults as $dbDevice) {
                                        // Use the IP address we found in Netshot, not empty string
                                        $results[] = [
                                            'hostname' => strtoupper($dbDevice['hostname']),
                                            'ip_address' => $deviceInfo['ip_address'] // Use actual IP from Netshot
                                        ];
                                        
                                        error_log("Added fallback result: hostname=" . $dbDevice['hostname'] . ", ip=" . $deviceInfo['ip_address']);
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Log the error but don't interrupt the response
                    error_log("Error querying Netshot API: " . $e->getMessage());
                }
            } else {
                // For wildcard searches, use the optimized searchDevicesByIp method
                error_log("Performing wildcard IP lookup using NetshotAPI searchDevicesByIp for: " . $searchQuery);
                
                try {
                    // Use the optimized searchDevicesByIp method which handles caching and indexing
                    $netshotResults = $netshot->searchDevicesByIp($searchQuery);
                    
                    error_log("Found " . count($netshotResults) . " devices matching IP pattern: " . $searchQuery);
                    
                    foreach ($netshotResults as $netshotDevice) {
                        $results[] = [
                            'hostname' => strtoupper($netshotDevice['name'] ?? 'Unknown'),
                            'ip_address' => $netshotDevice['ip'] ?? '',
                            'netshot' => $netshotDevice
                        ];
                    }
                    
                } catch (Exception $e) {
                    error_log("Error using searchDevicesByIp: " . $e->getMessage());
                    
                    // Fallback to manual search if the optimized method fails
                    error_log("Falling back to manual IP wildcard search");
                    
                    // Get all devices once with caching
                    $netshotDevices = $netshot->getDevicesInGroup(null, true, true);
                    
                    // Prepare wildcard pattern for regex
                    $pattern = str_replace(['%', '*', '.'], ['.*', '.*', '\\.'], $searchQuery);
                    $pattern = '/^' . $pattern . '$/i';
                    error_log("IP regex pattern: " . $pattern);
                    
                    // Search through Netshot devices for matching IPs
                    $matchCount = 0;
                    foreach ($netshotDevices as $device) {
                        // Check all possible field names for IP address
                        $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                        foreach ($possibleIpFields as $field) {
                            if (isset($device[$field]) && !empty($device[$field])) {
                                $ipValue = $device[$field];
                                
                                // Handle case where IP is an object with 'ip' field
                                if (is_array($ipValue) && isset($ipValue['ip'])) {
                                    $ipValue = $ipValue['ip'];
                                }
                                
                                // Check if this IP matches our pattern
                                if (preg_match($pattern, $ipValue)) {
                                    $results[] = [
                                        'hostname' => strtoupper($device['name'] ?? 'Unknown'),
                                        'ip_address' => $ipValue, // Use the actual IP value, not the search query
                                        'netshot' => [
                                            'id' => $device['id'] ?? null,
                                            'name' => strtoupper($device['name'] ?? ''),
                                            'ip' => $ipValue,
                                            'model' => $device['family'] ?? null,
                                            'vendor' => $device['domain'] ?? null,
                                            'status' => $device['status'] ?? null
                                        ]
                                    ];
                                    $matchCount++;
                                    break; // Found IP in this device, move to next device
                                }
                            }
                        }
                    }
                    error_log("Fallback search found $matchCount devices matching wildcard IP pattern: " . $searchQuery);
                }
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid search type. Supported types: hostname, ip',
                'status' => 400
            ]);
            exit();
    }
    
    // Return successful response
    http_response_code(200);
    
    // Log the entire results array for debugging
    error_log("Search results: " . json_encode($results));
    
    // Check if we have any results with IP addresses
    $resultsWithIp = 0;
    foreach ($results as $result) {
        if (!empty($result['ip_address'])) {
            $resultsWithIp++;
        }
    }
    error_log("RESULTS SUMMARY: Total results: " . count($results) . ", Results with IP addresses: " . $resultsWithIp);
    
    // Process all search results
    $processedResults = [];
    $uniqueHostnames = []; // Track unique hostnames to avoid duplicates
    
    if (!empty($results)) {
        error_log("Found " . count($results) . " results for query: " . $query);
        
        // Process each result to ensure proper format
        foreach ($results as $result) {
            $hostname = strtoupper($result['hostname'] ?? '');
            $ipAddress = '';
            
            // Skip duplicate hostnames (we might get the same device from both database and Netshot)
            if (in_array($hostname, $uniqueHostnames)) {
                error_log("Skipping duplicate hostname: " . $hostname);
                continue;
            }
            $uniqueHostnames[] = $hostname;
            
            error_log("Processing result for response: Hostname=" . $hostname);
            
            // Check if IP address is a complex object and extract just the IP string if needed
            if (isset($result['ip_address'])) {
                if (is_array($result['ip_address']) && isset($result['ip_address']['ip'])) {
                    // Extract just the IP string from the complex object
                    $ipAddress = $result['ip_address']['ip'];
                    error_log("Found complex IP object, extracting IP value: " . $ipAddress);
                } else {
                    $ipAddress = $result['ip_address'];
                }
            }
            
            // Final check to ensure IPAddress is always a simple string
            if (is_array($ipAddress) && isset($ipAddress['ip'])) {
                $ipAddress = $ipAddress['ip'];
            }
            
            // Always convert hostname to uppercase for final response
            $uppercaseHostname = strtoupper($hostname);
            
            // Add the processed result with guaranteed uppercase hostname
            $processedResults[] = [
                'HostName' => $uppercaseHostname,
                'IPAddress' => $ipAddress
            ];
            
            error_log("FINAL RESULT: Hostname=" . $uppercaseHostname . ", IP=" . $ipAddress);
        }
    } else {
        error_log("No search results found for: type=" . $searchType . ", query=" . $query);
        // No need to set 404, we'll return test data instead
        // http_response_code(404);
    }
    
    // Format response data in the requested structure
    $sourceHeader = $isJsonRequest && isset($data['Header']) ? $data['Header'] : [
        'BusinessTransactionID' => '1',
        'SentTimestamp' => '2023-11-10T09:20:00',
        'SourceContext' => [
            'host' => 'TestServer',
            'application' => 'ApiTester'
        ]
    ];
    
    // Ensure we preserve SourceContext from the client
    if ($isJsonRequest && isset($data['Header']['SourceContext'])) {
        // Preserve the client's SourceContext values
        error_log("Using client's SourceContext: " . json_encode($data['Header']['SourceContext']));
    }
    
    // If no results found, log this clearly
    if (empty($processedResults)) {
        error_log("IMPORTANT: No results found for search query: " . $query);
    } else {
        error_log("Using actual search results for response: " . count($processedResults) . " items");
    }
    
    // No more static test data - return empty results if nothing found
    
    // Structure the response based on number of results
    if (count($processedResults) == 1) {
        // For single result, use the original simple format
        $responseData = [
            'Header' => $sourceHeader,
            'Body' => $processedResults[0]
        ];
    } else {
        // For multiple results, use array format
        $responseData = [
            'Header' => $sourceHeader,
            'Body' => [
                'Count' => count($processedResults),
                'Results' => $processedResults
            ]
        ];
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    $errorMessage = 'Internal server error: ' . $e->getMessage();
    error_log($errorMessage);
    
    // Include more debug info in development environments
    $debug = [];
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
        $debug['trace'] = $e->getTraceAsString();
        $debug['file'] = $e->getFile();
        $debug['line'] = $e->getLine();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => $errorMessage,
        'status' => 500,
        'debug' => $debug
    ]);
}
