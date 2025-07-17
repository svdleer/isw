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
        if ($requestMethod === 'GET') {
            require_once __DIR__ . '/api/search.php';
        } else {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Method not allowed',
                'allowed_methods' => ['GET'],
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
                'search' => '/api/search?type={hostname|ip}&q={query}&api_key={key}',
                'health' => '/api/health',
                'docs' => '/docs/',
                'admin' => '/admin/'
            ],
            'examples' => [
                'hostname_exact' => '/api/search?type=hostname&q=GV-RC0011-CCAP003&api_key=your-key',
                'hostname_wildcard' => '/api/search?type=hostname&q=CCAP*&api_key=your-key',
                'ip_exact' => '/api/search?type=ip&q=192.168.1.100&api_key=your-key',
                'ip_wildcard' => '/api/search?type=ip&q=192.168.1.*&api_key=your-key'
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
