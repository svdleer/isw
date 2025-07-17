<?php
/**
 * CMDB REST API
 * 
 * Search endpoints:
 * GET /api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=your-key
 * GET /api/search?type=hostname&q=CCAP*&api_key=your-key
 * GET /api/search?type=ip&q=192.168.1.100&api_key=your-key
 * GET /api/search?type=ip&q=192.168.1.*&api_key=your-key
 */

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/ApiAuth.php';

try {
    // Initialize classes
    $db = new Database();
    $auth = new ApiAuth($db); // Pass database connection for API key validation
    
    // Get API parameters
    $apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
    $searchType = $_GET['type'] ?? null;
    $query = $_GET['q'] ?? null;
    
    // Validate API key
    if (!$apiKey || !$auth->validateApiKey($apiKey)) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid or missing API key',
            'status' => 401
        ]);
        exit();
    }
    
    // Validate required parameters
    if (!$searchType || !$query) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required parameters: type and q',
            'status' => 400,
            'usage' => [
                'hostname_search' => '/api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=your-key',
                'hostname_wildcard' => '/api/search?type=hostname&q=CCAP*&api_key=your-key',
                'ip_search' => '/api/search?type=ip&q=192.168.1.100&api_key=your-key',
                'ip_wildcard' => '/api/search?type=ip&q=192.168.1.*&api_key=your-key'
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
            
            // SQL query for hostname search
            if (strpos($searchQuery, '%') !== false) {
                // Wildcard search
                $sql = "SELECT id, hostname, ip_address, description, created_at, updated_at 
                        FROM devices 
                        WHERE hostname LIKE ? 
                        ORDER BY hostname";
                $results = $db->query($sql, [$searchQuery]);
            } else {
                // Exact search
                $sql = "SELECT id, hostname, ip_address, description, created_at, updated_at 
                        FROM devices 
                        WHERE hostname = ? 
                        ORDER BY hostname";
                $results = $db->query($sql, [$searchQuery]);
            }
            break;
            
        case 'ip':
            // Validate IP address format
            if (!$auth->validateIpAddress($query)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid IP address format',
                    'example' => '192.168.1.100',
                    'wildcard_example' => '192.168.1.*',
                    'status' => 400
                ]);
                exit();
            }
            
            // Prepare query for database
            $searchQuery = $auth->prepareIpQuery($query);
            
            // SQL query for IP search
            if (strpos($searchQuery, '%') !== false) {
                // Wildcard search
                $sql = "SELECT id, hostname, ip_address, description, created_at, updated_at 
                        FROM devices 
                        WHERE ip_address LIKE ? 
                        ORDER BY ip_address";
                $results = $db->query($sql, [$searchQuery]);
            } else {
                // Exact search
                $sql = "SELECT id, hostname, ip_address, description, created_at, updated_at 
                        FROM devices 
                        WHERE ip_address = ? 
                        ORDER BY ip_address";
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
    echo json_encode([
        'status' => 200,
        'search_type' => $searchType,
        'query' => $query,
        'count' => count($results),
        'data' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error: ' . $e->getMessage(),
        'status' => 500
    ]);
}
