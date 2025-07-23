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

try {
    // Initialize classes
    $db = new Database();
    $auth = new ApiAuth($db); // Pass database connection for API key validation
    
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
    
    if (!$credentials || !$auth->validateBasicAuth($credentials['username'], $credentials['password'])) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid or missing authentication credentials',
            'status' => 401,
            'authentication' => 'HTTP Basic Authentication required'
        ]);
        exit();
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
            
            // Prepare query for database
            $searchQuery = $auth->prepareHostnameQuery($query);
            
            // Special case: if only wildcard is submitted, search for hostnames containing CCAP
            if ($searchQuery === '%' || $searchQuery === '*') {
                $searchQuery = '%CCAP%';
            } 
            // If CCAP is not in the query, ensure it's included in the search
            elseif (stripos($searchQuery, 'CCAP') === false) {
                if (strpos($searchQuery, '%') !== false) {
                    // It's already a wildcard query, append CCAP condition
                    $searchQuery = '%CCAP%' . $searchQuery;
                } else {
                    // Convert to wildcard query with CCAP
                    $searchQuery = '%CCAP%' . $searchQuery . '%';
                }
            }
            
            // Always convert to wildcard format for consistency
            $searchQuery = str_replace('*', '%', $searchQuery);
            
            // Query that gets only active devices 
            $sql = "SELECT a.hostname, a.ipaddress as ip_address, a.description, 
                   a.created_at, a.updated_at, a.location
                   FROM access.devicesnew a 
                   LEFT JOIN reporting.acc_alias b ON UPPER(a.hostname) = UPPER(b.ccap_name)
                   WHERE (UPPER(a.hostname) LIKE UPPER(?) OR UPPER(b.alias) LIKE UPPER(?))
                   AND a.active = 1
                   ORDER BY a.hostname";
            
            $results = $db->query($sql, [$searchQuery, $searchQuery]);
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
            
            // Prepare query for database
            $searchQuery = $auth->prepareIpQuery($query);
            
            // SQL query for IP search - only return active devices
            if (strpos($searchQuery, '%') !== false) {
                // Wildcard search
                $sql = "SELECT a.hostname, a.ipaddress as ip_address, a.description,
                       a.created_at, a.updated_at, a.location
                       FROM access.devicesnew a
                       WHERE a.ipaddress LIKE ? 
                       AND a.active = 1
                       ORDER BY a.ipaddress";
                $results = $db->query($sql, [$searchQuery]);
            } else {
                // Exact search
                $sql = "SELECT a.hostname, a.ipaddress as ip_address, a.description,
                       a.created_at, a.updated_at, a.location
                       FROM access.devicesnew a
                       WHERE a.ipaddress = ? 
                       AND a.active = 1
                       ORDER BY a.ipaddress";
                $results = $db->query($sql, [$searchQuery]);
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
    
    // Prepare response data
    $responseData = [
        'status' => 200,
        'search_type' => $searchType,
        'query' => $query,
        'count' => count($results),
        'data' => $results
    ];
    
    if (isset($isJsonRequest) && $isJsonRequest) {
        // For JSON requests, include original request data in the response
        $responseData['request'] = [
            'header' => $data['Header'] ?? null,
            'body' => $data['Body'] ?? null
        ];
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error: ' . $e->getMessage(),
        'status' => 500
    ]);
}
