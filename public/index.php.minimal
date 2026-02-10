<?php
/**
 * ULTRA SIMPLE TEST - NO ROUTER CLASS
 * This bypasses Router entirely to test basic routing
 * Replace your index.php with this temporarily
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Calculate what URI we're trying to match
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Remove base path /wrightcommerce/public
$basePath = '/wrightcommerce/public';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Normalize
$requestUri = '/' . ltrim($requestUri, '/');
if ($requestUri === '') {
    $requestUri = '/';
}

// Show debug info
$debug = [
    'raw_request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'calculated_uri' => $requestUri,
    'base_path_used' => $basePath
];

// Simple routing
if ($requestUri === '/') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'app' => 'WrightCommerce API',
        'version' => '1.0',
        'status' => 'online',
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'ROOT ROUTE WORKS! ✅',
        'debug' => $debug
    ], JSON_PRETTY_PRINT);
    
} else if ($requestUri === '/test-db') {
    require_once '../config/database.php';
    require_once '../app/helpers/Database.php';
    
    try {
        $db = Database::getInstance();
        $result = $db->query("SELECT 1");
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'database' => DB_NAME,
            'debug' => $debug
        ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
            'debug' => $debug
        ], JSON_PRETTY_PRINT);
    }
    
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Not Found',
        'message' => 'Endpoint not found',
        'you_requested' => $requestUri,
        'available_routes' => ['/', '/test-db'],
        'debug' => $debug
    ], JSON_PRETTY_PRINT);
}
?>