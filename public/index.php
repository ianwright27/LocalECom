<?php
/**
 * WrightCommerce API - Main Entry Point
 * options.php handles OPTIONS, this handles all other requests
 */

// TEMPORARY DEBUG - force CORS headers before anything else
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    // comment/update lines below when production changes in the near future
    'https://wrightcommerce.vercel.app', 
    'https://wrightcommerce-store.vercel.app', 
];
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');



// ============================================
// CORS headers for actual requests (not OPTIONS)
// ============================================

// REMOVE any existing CORS headers first
header_remove('Access-Control-Allow-Origin');
header_remove('Access-Control-Allow-Credentials');

// CORS Headers - MUST BE FIRST
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    // comment/update lines below when production changes in the near future
    'https://wrightcommerce.vercel.app', 
    'https://wrightcommerce-store.vercel.app', 
];

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");  // SPECIFIC, NOT *
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS preflight (shouldn't reach here, but just in case)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// Load cors.php for session only
// ============================================
require_once __DIR__ . '/../config/cors.php';

// ============================================
// Start session if not started
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// Error reporting
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// Load dependencies
// ============================================
require_once '../config/database.php';
require_once '../app/helpers/Database.php';
require_once '../app/helpers/Router.php';

// Load controllers
require_once '../app/controllers/BaseController.php';
require_once '../app/controllers/ProductController.php';
require_once '../app/controllers/AuthController.php';
require_once '../app/controllers/OrderController.php';
require_once '../app/controllers/CustomerController.php';
require_once '../app/controllers/CustomerControllerAdmin.php';
require_once '../app/controllers/PaymentsController.php';
require_once '../app/controllers/PaystackController.php';
require_once '../app/controllers/BusinessController.php';

$router = new Router();

// Public routes
$router->get('/', function() {
    header('Content-Type: application/json');
    echo json_encode(['app' => 'WrightCommerce API', 'version' => '1.0', 'status' => 'online']);
});

$router->get('/test-db', function() {
    header('Content-Type: application/json');
    try {
        $db = Database::getInstance();
        $result = $db->query("SELECT 1");
        echo json_encode(['success' => true, 'message' => 'Database OK']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// Auth routes
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');
$router->put('/auth/profile', 'AuthController@updateProfile');
$router->post('/auth/profile', 'AuthController@updateProfile');  // ✅ Also accept POST
$router->put('/auth/change-password', 'AuthController@changePassword');
$router->post('/auth/change-password', 'AuthController@changePassword');  // ✅ Also accept POST
$router->get('/auth/check', 'AuthController@check');

// API v1
$router->group('/api/v1', function($router) {
    
    // PRODUCTS
    $router->get('/products/search', 'ProductController@search');
    $router->get('/products/categories', 'ProductController@categories');
    $router->get('/products/stats', 'ProductController@stats');
    $router->get('/products', 'ProductController@index');
    $router->get('/products/{id}', 'ProductController@show');
    $router->post('/products', 'ProductController@store');
    $router->put('/products/{id}', 'ProductController@update');
    $router->post('/products/{id}', 'ProductController@update');  // ✅ Also accept POST
    $router->delete('/products/{id}', 'ProductController@destroy');
    $router->post('/products/{id}/delete', 'ProductController@destroy');  // ✅ Also accept POST
    
    // ORDERS
    $router->get('/orders/search', 'OrderController@search');
    $router->get('/orders/stats', 'OrderController@stats');
    $router->get('/orders', 'OrderController@index');
    $router->get('/orders/{id}', 'OrderController@show');
    $router->post('/orders', 'OrderController@store');
    $router->put('/orders/{id}/status', 'OrderController@updateStatus');
    $router->post('/orders/{id}/status', 'OrderController@updateStatus');  // ✅ Also accept POST
    $router->delete('/orders/{id}', 'OrderController@destroy');
    $router->post('/orders/{id}/delete', 'OrderController@destroy');  // ✅ Also accept POST
    
    // CUSTOMERS

    // ADMIN CUSTOMERS (business owner managing their customers)
    $router->get('/customers/search', 'CustomerControllerAdmin@search');
    $router->get('/customers', 'CustomerControllerAdmin@index');
    $router->get('/customers/{id}/orders', 'CustomerControllerAdmin@orders');
    $router->get('/customers/{id}', 'CustomerControllerAdmin@show');

    // STOREFRONT CUSTOMERS (existing - do not touch)
    $router->post('/customers/register', 'CustomerController@register');
    $router->post('/customers/login', 'CustomerController@login');
    $router->get('/customers/profile', 'CustomerController@profile');
    $router->put('/customers/profile', 'CustomerController@updateProfile');
    $router->post('/customers/profile', 'CustomerController@updateProfile');
    $router->put('/customers/password', 'CustomerController@changePassword');
    $router->post('/customers/password', 'CustomerController@changePassword');
    $router->get('/customers/orders', 'CustomerController@orders');
    $router->get('/customers/orders/{id}', 'CustomerController@getOrder');
    $router->post('/customers/logout', 'CustomerController@logout');

    // PAYMENTS
    $router->get('/payments/stats', 'PaymentsController@stats');
    $router->get('/payments/search', 'PaymentsController@search');
    $router->post('/payments/paystack/initialize', 'PaystackController@initialize');
    $router->get('/payments/paystack/verify/{reference}', 'PaystackController@verify');
    $router->get('/payments/paystack/public-key', 'PaystackController@getPublicKey');
    $router->get('/payments', 'PaymentsController@index');
    $router->get('/payments/{id}', 'PaymentsController@show');
    
    // BUSINESS
    $router->get('/business/profile', 'BusinessController@profile');
    $router->put('/business/profile', 'BusinessController@updateProfile');
    $router->post('/business/profile', 'BusinessController@updateProfile');  // ✅ Also accept POST
    $router->get('/business/settings', 'BusinessController@settings');
    $router->put('/business/settings', 'BusinessController@updateSettings');
    $router->post('/business/settings', 'BusinessController@updateSettings');  // ✅ Also accept POST
    $router->put('/business/notifications', 'BusinessController@updateNotifications');
    $router->post('/business/notifications', 'BusinessController@updateNotifications');  // ✅ Also accept POST
});

// WEBHOOKS
$router->post('/webhooks/paystack', 'PaystackController@webhook');

// 404 handler
$router->setNotFoundHandler(function() {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint Not Found',
        'path' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
});

// Dispatch
try {
    $router->dispatch();
} catch (Exception $e) {
    error_log("Router Error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'details' => ($_SERVER['SERVER_NAME'] === 'localhost') ? $e->getMessage() : null
    ]);
}