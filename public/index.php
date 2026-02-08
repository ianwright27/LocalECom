<?php
/**
 * WrightCommerce API - Main Entry Point
 * 
 * This is the single entry point for all API requests.
 * All requests are routed through this file and handled by the Router.
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

// ============================================
// ERROR REPORTING (Change for production)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

// ============================================
// CORS HEADERS (Allow React app to access API)
// ============================================
header('Access-Control-Allow-Origin: *'); // In production, specify your React app's URL
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// LOAD DEPENDENCIES
// ============================================
require_once '../config/database.php';
require_once '../app/helpers/Database.php';
require_once '../app/helpers/Router.php';

// Load controllers (add more as you create them)
require_once '../app/controllers/BaseController.php';
require_once '../app/controllers/ProductController.php';
// require_once '../app/controllers/AuthController.php'; // Week 1
// require_once '../app/controllers/OrderController.php'; // Week 3
// require_once '../app/controllers/CustomerController.php'; // Week 3
// require_once '../app/controllers/PaymentController.php'; // Week 4
// require_once '../app/controllers/MpesaController.php'; // Week 4
// require_once '../app/controllers/DashboardController.php'; // Week 6

// ============================================
// INITIALIZE ROUTER
// ============================================
$router = new Router();

// ============================================
// PUBLIC ROUTES (No authentication required)
// ============================================

// API Info / Health Check
$router->get('/', function() {
    echo json_encode([
        'app' => 'WrightCommerce API',
        'version' => '1.0',
        'status' => 'online',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => 'Africa/Nairobi',
        'endpoints' => [
            'GET /' => 'API information',
            'POST /auth/register' => 'Register new user',
            'POST /auth/login' => 'Login user',
            'GET /api/v1/products' => 'List products (requires auth)',
            'POST /api/v1/products' => 'Create product (requires auth)'
        ]
    ]);
});

// Test database connection
$router->get('/test-db', function() {
    try {
        $db = Database::getInstance();
        $result = $db->query("SELECT 1");
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'database' => DB_NAME
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error' => $e->getMessage()
        ]);
    }
});

// ============================================
// AUTHENTICATION ROUTES
// ============================================
// TODO: Create AuthController in Week 1, Days 3-5
// Uncomment these when AuthController is ready

// $router->post('/auth/register', 'AuthController@register');
// $router->post('/auth/login', 'AuthController@login');
// $router->post('/auth/logout', 'AuthController@logout');
// $router->post('/auth/forgot-password', 'AuthController@forgotPassword');
// $router->post('/auth/reset-password', 'AuthController@resetPassword');
// $router->get('/auth/me', 'AuthController@me');

// ============================================
// API V1 ROUTES GROUP
// ============================================
$router->group('/api/v1', function($router) {
    
    // ============================================
    // PRODUCT ROUTES (Week 2)
    // ============================================
    
    // RESTful product routes
    $router->get('/products', 'ProductController@index');              // List all products
    $router->get('/products/{id}', 'ProductController@show');          // Get single product
    $router->post('/products', 'ProductController@store');             // Create product
    $router->put('/products/{id}', 'ProductController@update');        // Update product
    $router->delete('/products/{id}', 'ProductController@destroy');    // Delete product (soft)
    
    // Additional product routes
    $router->post('/products/{id}/restore', 'ProductController@restore');                    // Restore deleted product
    $router->delete('/products/{id}/permanent', 'ProductController@permanentDelete');        // Permanent delete
    $router->get('/products/search', 'ProductController@search');                            // Search products
    $router->get('/products/low-stock', 'ProductController@lowStock');                       // Low stock products
    $router->get('/products/out-of-stock', 'ProductController@outOfStock');                  // Out of stock products
    $router->post('/products/{id}/adjust-stock', 'ProductController@adjustStock');           // Adjust stock
    $router->get('/products/categories', 'ProductController@categories');                    // Get all categories
    $router->get('/products/stats', 'ProductController@stats');                              // Product statistics
    $router->post('/products/bulk-update-status', 'ProductController@bulkUpdateStatus');     // Bulk update status
    
    // ============================================
    // ORDER ROUTES (Week 3)
    // ============================================
    // TODO: Create OrderController in Week 3
    
    // $router->get('/orders', 'OrderController@index');                        // List all orders
    // $router->get('/orders/{id}', 'OrderController@show');                    // Get single order
    // $router->post('/orders', 'OrderController@store');                       // Create order
    // $router->put('/orders/{id}', 'OrderController@update');                  // Update order
    // $router->delete('/orders/{id}', 'OrderController@destroy');              // Cancel order
    // $router->patch('/orders/{id}/status', 'OrderController@updateStatus');   // Update order status
    // $router->get('/orders/stats', 'OrderController@stats');                  // Order statistics
    
    // ============================================
    // CUSTOMER ROUTES (Week 3)
    // ============================================
    // TODO: Create CustomerController in Week 3
    
    // $router->resource('customers', 'CustomerController');                    // RESTful routes
    // $router->get('/customers/{id}/orders', 'CustomerController@orders');     // Customer orders
    // $router->get('/customers/{id}/stats', 'CustomerController@stats');       // Customer stats
    // $router->get('/customers/search', 'CustomerController@search');          // Search customers
    
    // ============================================
    // PAYMENT ROUTES (Week 4)
    // ============================================
    // TODO: Create PaymentController and MpesaController in Week 4
    
    // M-PESA Routes
    // $router->post('/payments/mpesa/stk-push', 'MpesaController@stkPush');          // Initiate M-PESA payment
    // $router->post('/payments/mpesa/callback', 'MpesaController@callback');         // M-PESA callback
    // $router->post('/payments/mpesa/query', 'MpesaController@queryStatus');         // Query payment status
    
    // General Payment Routes
    // $router->get('/payments', 'PaymentController@index');                          // List payments
    // $router->get('/payments/{id}', 'PaymentController@show');                      // Get payment
    // $router->get('/payments/{id}/status', 'PaymentController@status');             // Check status
    // $router->post('/payments/{id}/verify', 'PaymentController@verify');            // Verify payment
    
    // ============================================
    // DASHBOARD/ANALYTICS ROUTES (Week 6)
    // ============================================
    // TODO: Create DashboardController in Week 6
    
    // $router->get('/dashboard/stats', 'DashboardController@stats');                 // Overview stats
    // $router->get('/dashboard/sales', 'DashboardController@sales');                 // Sales data
    // $router->get('/dashboard/revenue', 'DashboardController@revenue');             // Revenue data
    // $router->get('/dashboard/top-products', 'DashboardController@topProducts');    // Best sellers
    // $router->get('/dashboard/recent-orders', 'DashboardController@recentOrders');  // Recent orders
    
    // ============================================
    // BUSINESS SETTINGS ROUTES (Week 7)
    // ============================================
    // TODO: Create BusinessController in Week 7
    
    // $router->get('/business/profile', 'BusinessController@profile');               // Get business profile
    // $router->put('/business/profile', 'BusinessController@updateProfile');         // Update profile
    // $router->get('/business/settings', 'BusinessController@settings');             // Get settings
    // $router->put('/business/settings', 'BusinessController@updateSettings');       // Update settings
    
    // ============================================
    // USER MANAGEMENT ROUTES (Week 7)
    // ============================================
    
    // $router->get('/users', 'UserController@index');                                // List users
    // $router->post('/users', 'UserController@store');                               // Add user
    // $router->put('/users/{id}', 'UserController@update');                          // Update user
    // $router->delete('/users/{id}', 'UserController@destroy');                      // Remove user
});

// ============================================
// WEBHOOK ROUTES (Week 5)
// ============================================
// Webhooks should be outside the /api/v1 group
// These will be called by external services (M-PESA, WhatsApp, etc.)

// $router->post('/webhooks/mpesa', 'WebhookController@mpesa');
// $router->post('/webhooks/whatsapp', 'WebhookController@whatsapp');
// $router->post('/webhooks/sms', 'WebhookController@sms');

// ============================================
// CUSTOM 404 HANDLER
// ============================================
$router->setNotFoundHandler(function() {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint Not Found',
        'message' => 'The requested API endpoint does not exist',
        'documentation' => 'https://docs.wrightcommerce.com',
        'requested_path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ]);
});

// ============================================
// MIDDLEWARE (Optional - Add authentication check)
// ============================================
// Uncomment this when you have authentication set up

// $router->middleware(function() {
//     // List of public routes that don't need authentication
//     $publicRoutes = [
//         '/',
//         '/test-db',
//         '/auth/register',
//         '/auth/login',
//         '/auth/forgot-password',
//         '/auth/reset-password'
//     ];
//     
//     $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
//     
//     // Allow public routes
//     if (in_array($currentPath, $publicRoutes)) {
//         return;
//     }
//     
//     // Allow webhook routes (they have their own security)
//     if (strpos($currentPath, '/webhooks/') !== false) {
//         return;
//     }
//     
//     // Check for authentication
//     session_start();
//     if (!isset($_SESSION['user_id'])) {
//         http_response_code(401);
//         echo json_encode([
//             'success' => false,
//             'error' => 'Unauthorized',
//             'message' => 'Authentication required. Please login.'
//         ]);
//         exit;
//     }
// });

// ============================================
// DISPATCH ROUTER
// ============================================
try {
    $router->dispatch();
} catch (Exception $e) {
    // Log the error (in production, don't expose error details)
    error_log("Router Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An unexpected error occurred',
        // Only show error details in development
        'details' => ($_SERVER['SERVER_NAME'] === 'localhost') ? $e->getMessage() : null
    ]);
}