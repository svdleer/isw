<?php
/**
 * Simple router for the CMDB API
 * This file handles routing requests to appropriate endpoints
 */

// Get the request URI and remove query string
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove any trailing slashes and normalize path
$requestUri = rtrim($requestUri, '/');

// Basic routing
switch ($requestUri) {
    case '/api/search':
    case '/search':
        if ($requestMethod === 'GET' || $requestMethod === 'POST') {
            require_once __DIR__ . '/api/search.php';
        } else {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Method not allowed',
                'allowed_methods' => ['GET', 'POST'],
                'status' => 405
            ]);
        }
        break;
        
    case '/api/health':
    case '/health':
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ]);
        break;
        
    case '/docs':
    case '/api/docs':
    case '/swagger':
        header('Location: /isw/docs/');
        exit();
        break;
        
    case '':
    case '/':
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'ISW CMDB API',
            'version' => '1.0.0',
            'endpoints' => [
                'search_get' => '/api/search?type={hostname|ip}&q={query}',
                'search_post' => '/api/search (JSON body format)',
                'health' => '/api/health',
                'docs' => '/docs/',
                'admin' => '/admin/'
            ],
            'authentication' => 'HTTP Basic Authentication required (Authorization: Basic base64(username:password))',
            'examples' => [
                'hostname_exact' => '/api/search?type=hostname&q=GV-RC0011-CCAP003',
                'hostname_wildcard' => '/api/search?type=hostname&q=CCAP*',
                'ip_exact' => '/api/search?type=ip&q=192.168.1.100',
                'ip_wildcard' => '/api/search?type=ip&q=192.168.1.*',
                'post_hostname' => '{"Header":{"BusinessTransactionID":"1","SentTimestamp":"2023-11-10T09:20:00","SourceContext":{"host":"String","application":"String"}},"Body":{"HostName":"GV-RC0052-CCAP002"}}',
                'post_ip' => '{"Header":{"BusinessTransactionID":"1","SentTimestamp":"2023-11-10T09:20:00","SourceContext":{"host":"String","application":"String"}},"Body":{"IPAddress":"172.16.55.26"}}'
            ],
            'documentation' => '/docs/',
            'admin_panel' => '/admin/'
        ]);
        break;
        
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Endpoint not found',
            'status' => 404,
            'available_endpoints' => [
                '/api/search',
                '/api/health',
                '/docs/',
                '/admin/'
            ]
        ]);
        break;
}
