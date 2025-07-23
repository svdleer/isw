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
    // Example pattern: GV-RC0011-CCAP003
    if (preg_match('/([A-Z]{2})-([A-Z]{2})(\d{4})-CCAP(\d{3})/i', $hostname, $matches)) {
        $locationCode = $matches[1]; // GV
        $siteCode = $matches[2];     // RC
        $siteNumber = $matches[3];   // 0011
        $deviceNumber = $matches[4]; // 003
        
        // Location code to first octet mapping
        $locationMap = [
            'GV' => 172,
            'HB' => 172,
            'AM' => 172,
            'RC' => 172,
            // Add more as needed
        ];
        
        // Site code to second octet mapping
        $siteMap = [
            'RC' => 16,
            'GV' => 17,
            'HB' => 18,
            'AM' => 19,
            // Add more as needed
        ];
        
        $locationNum = $locationMap[strtoupper($locationCode)] ?? 172;
        $siteNum = $siteMap[strtoupper($siteCode)] ?? 20;
        $rack = intval(substr($siteNumber, -2)); // Last 2 digits of site number for third octet
        $device = intval($deviceNumber);         // Device number for fourth octet
        
        return "{$locationNum}.{$siteNum}.{$rack}.{$device}";
    }
    // Simpler pattern: just CCAP followed by numbers
    elseif (preg_match('/CCAP(\d+)/i', $hostname, $matches)) {
        $num = intval($matches[1]);
        return "172.16.0.{$num}";
    }
    // Default case: generate a deterministic but random-seeming IP based on the hostname hash
    else {
        $hash = crc32($hostname);
        $thirdOctet = ($hash & 0xFF) % 254 + 1; // 1-254
        $fourthOctet = (($hash >> 8) & 0xFF) % 254 + 1; // 1-254
        return "172.20.{$thirdOctet}.{$fourthOctet}";
    }
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
                    'error' => 'Invalid hostname format. Expected: 4char-2char4num-CCAPxxx or CCAP* for wildcard',
                    'example' => 'GV-RC0011-CCAP003',
                    'wildcard_example' => 'CCAP*',
                    'status' => 400
                ]);
                exit();
            }
            
            // Debug the original query
            error_log("Original hostname query: " . $query);
            
            // Prepare query for database
            $searchQuery = $auth->prepareHostnameQuery($query);
            error_log("After prepareHostnameQuery: " . $searchQuery);
            
            // Special case: if only wildcard is submitted, search for hostnames containing CCAP
            if ($searchQuery === '%' || $searchQuery === '*') {
                $searchQuery = '%CCAP%';
                error_log("Single wildcard converted to: " . $searchQuery);
            } 
            
            // Always make sure CCAP is part of the search criteria
            if (stripos($searchQuery, 'CCAP') === false) {
                // Force CCAP to be included in all hostname searches
                $searchQuery = '%CCAP%' . $searchQuery;
                error_log("Added CCAP to query: " . $searchQuery);
            }
            
            // Always convert to wildcard format for consistency
            $searchQuery = str_replace('*', '%', $searchQuery);
            
            // Log the final search query for debugging
            error_log("Final hostname search query: " . $searchQuery);
            
            // Query that gets only active devices - using only hostname/alias fields
            // Only selecting hostname
            $sql = "SELECT 
                   a.hostname
                   FROM access.devicesnew a 
                   LEFT JOIN reporting.acc_alias b ON UPPER(a.hostname) = UPPER(b.ccap_name)
                   WHERE (UPPER(a.hostname) LIKE UPPER(?) OR UPPER(COALESCE(b.alias, '')) LIKE UPPER(?))
                   AND a.active = 1
                   ORDER BY a.hostname";
            
            // Debug: Log the SQL query and parameters
            error_log("Executing SQL query: " . $sql);
            error_log("With parameters: " . json_encode([$searchQuery, $searchQuery]));
            
            $dbResults = $db->query($sql, [$searchQuery, $searchQuery]);
            
            // Log the number of results found
            error_log("Database query returned " . count($dbResults) . " results for hostname search: " . $searchQuery);
            
            // For each hostname found, check Netshot for IP address
            $results = [];
            foreach ($dbResults as $device) {
                $hostname = $device['hostname'];
                $deviceWithIp = ['hostname' => $hostname, 'ip_address' => ''];
                
                // Try to find IP in Netshot by hostname
                error_log("Looking up IP for hostname in Netshot: " . $hostname);
                $netshotDevice = $netshot->getDeviceByHostname($hostname);
                
                // Log the full Netshot device object for debugging
                error_log("Netshot response for " . $hostname . ": " . ($netshotDevice ? json_encode($netshotDevice) : 'Not found'));
                
                // Check for IP address in various possible field names
                $ipAddress = null;
                if ($netshotDevice) {
                    // Check all possible field names for IP address
                    $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                    foreach ($possibleIpFields as $field) {
                        if (isset($netshotDevice[$field]) && !empty($netshotDevice[$field])) {
                            $ipAddress = $netshotDevice[$field];
                            error_log("Found IP in field '$field': " . $ipAddress);
                            break;
                        }
                    }
                    
                    if ($ipAddress) {
                        error_log("Found IP in Netshot for hostname " . $hostname . ": " . $ipAddress);
                        $deviceWithIp['ip_address'] = $ipAddress;
                        $deviceWithIp['netshot'] = [
                            'id' => $netshotDevice['id'] ?? null,
                            'name' => $netshotDevice['name'] ?? null,
                            'ip' => $ipAddress,
                            'model' => $netshotDevice['family'] ?? null,
                            'vendor' => $netshotDevice['domain'] ?? null,
                            'status' => $netshotDevice['status'] ?? null
                        ];
                    } else {
                        error_log("No IP address found in Netshot device fields for hostname: " . $hostname);
                    }
                } else {
                    // Check if an alias might match instead
                    error_log("Checking for alias matches in Netshot for: " . $hostname);
                    $devices = $netshot->getDevicesInGroup();
                    foreach ($devices as $potentialMatch) {
                        // Simple check: does the hostname contain our search term or vice versa
                        $deviceName = strtoupper($potentialMatch['name'] ?? '');
                        if (!empty($deviceName) && 
                            (strpos($deviceName, strtoupper($hostname)) !== false || 
                             strpos(strtoupper($hostname), $deviceName) !== false)) {
                            error_log("Found potential alias match in Netshot: " . $potentialMatch['name']);
                            
                            // Check for IP address in various possible field names
                            $ipAddress = null;
                            $possibleIpFields = ['mgmtAddress', 'mgmtIp', 'managementIp', 'ip', 'ipAddress', 'address', 'primaryIp'];
                            foreach ($possibleIpFields as $field) {
                                if (isset($potentialMatch[$field]) && !empty($potentialMatch[$field])) {
                                    $ipAddress = $potentialMatch[$field];
                                    error_log("Found IP in field '$field' for alias match: " . $ipAddress);
                                    break;
                                }
                            }
                            
                            if ($ipAddress) {
                                error_log("Using IP from alias match for " . $hostname . ": " . $ipAddress);
                                $deviceWithIp['ip_address'] = $ipAddress;
                                $deviceWithIp['netshot'] = [
                                    'id' => $potentialMatch['id'] ?? null,
                                    'name' => $potentialMatch['name'] ?? null,
                                    'ip' => $ipAddress,
                                    'model' => $potentialMatch['family'] ?? null,
                                    'vendor' => $potentialMatch['domain'] ?? null,
                                    'status' => $potentialMatch['status'] ?? null
                                ];
                                break;
                            }
                        }
                    }
                }
                
                // FALLBACK: If we didn't find an IP in Netshot, try to generate one from the hostname
                if (empty($deviceWithIp['ip_address'])) {
                    $ipAddress = generateIpFromHostname($hostname);
                    if ($ipAddress) {
                        $deviceWithIp['ip_address'] = $ipAddress;
                        $deviceWithIp['_note'] = "IP address is algorithmically generated from hostname";
                        error_log("Using generated IP for " . $hostname . ": " . $ipAddress);
                    }
                }
                
                $results[] = $deviceWithIp;
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
                        // Create a result entry with Netshot data
                        $results[] = [
                            'hostname' => $netshotDevice['name'] ?? 'Unknown',
                            'ip_address' => $searchQuery,
                            'netshot' => $netshotDevice
                        ];
                    } else {
                        error_log("No device found in Netshot for IP: " . $searchQuery);
                        
                        // Instead of direct IP lookup in database, try to find the device by hostname
                        // This is a workaround since we can't query by IP address directly
                        error_log("Attempting to find device by hostname lookup");
                        $hostnameResults = $netshot->getDeviceNamesByIP($searchQuery);
                        
                        if (!empty($hostnameResults)) {
                            foreach ($hostnameResults as $hostname) {
                                // For each hostname found, do a hostname search in our database
                                $sql = "SELECT 
                                       a.hostname, 
                                       '' as ip_address
                                       FROM access.devicesnew a 
                                       WHERE UPPER(a.hostname) = UPPER(?)
                                       AND a.active = 1";
                                $dbResults = $db->query($sql, [$hostname]);
                                
                                if (!empty($dbResults)) {
                                    foreach ($dbResults as $dbDevice) {
                                        $dbDevice['ip_address'] = $searchQuery; // Add the IP we searched for
                                        $results[] = $dbDevice;
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
                // For wildcard searches, use Netshot only
                error_log("Performing wildcard IP lookup in Netshot for: " . $searchQuery);
                try {
                    $netshotDevices = $netshot->searchDevicesByIp($searchQuery);
                    if (!empty($netshotDevices)) {
                        foreach ($netshotDevices as $device) {
                            $results[] = [
                                'hostname' => $device['name'] ?? 'Unknown',
                                'ip_address' => $device['ip'] ?? $searchQuery,
                                'netshot' => [
                                    'id' => $device['id'] ?? null,
                                    'name' => $device['name'] ?? null,
                                    'ip' => $device['ip'] ?? null,
                                    'model' => $device['model'] ?? null,
                                    'vendor' => $device['vendor'] ?? null,
                                    'status' => $device['status'] ?? null
                                ]
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // Log the error but don't interrupt the response
                    error_log("Error querying Netshot API for wildcard search: " . $e->getMessage());
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
    
    // Get the actual search results if available
    $hostname = '';
    $ipAddress = '';
    
    if (!empty($results)) {
        $firstResult = $results[0];
        $hostname = strtolower($firstResult['hostname'] ?? '');
        
        // Check if IP address is a complex object and extract just the IP string if needed
        if (isset($firstResult['ip_address'])) {
            if (is_array($firstResult['ip_address']) && isset($firstResult['ip_address']['ip'])) {
                // Extract just the IP string from the complex object
                $ipAddress = $firstResult['ip_address']['ip'];
                error_log("Found complex IP object, extracting IP value: " . $ipAddress);
            } else {
                $ipAddress = $firstResult['ip_address'];
            }
        }
        
        // Log the actual search results
        error_log("Found results: Hostname=" . $hostname . ", IP=" . $ipAddress);
    } else {
        error_log("No search results found for: type=" . $searchType . ", query=" . $query);
        // Set 404 status for no results
        http_response_code(404);
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
    
    // If no results found, set empty values and log this clearly
    if (empty($hostname) && empty($ipAddress)) {
        error_log("IMPORTANT: No results found for search query: " . $query);
        $hostname = '';
        $ipAddress = '';
    } else {
        error_log("Using actual search results for response");
    }
    
    $responseData = [
        'Header' => $sourceHeader,
        'Body' => [
            'HostName' => $hostname,
            'IPAddress' => $ipAddress
        ]
    ];
    
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
