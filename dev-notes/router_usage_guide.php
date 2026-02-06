<?php
/**
 * ROUTER - USAGE GUIDE & EXAMPLES
 * WrightCommerce Platform
 * 
 * This file contains practical examples of how to use the Router class
 * to build your API endpoints.
 */

// ============================================
// BASIC SETUP
// ============================================

// File: public/index.php (Your main entry point)


// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for API
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include dependencies
require_once '../config/database.php';
require_once '../app/helpers/Database.php';
require_once '../app/helpers/Router.php';

// Create router instance
$router = new Router();

// Set base path if your API is in a subdirectory
// $router->setBasePath('/api/v1');

// ============================================
// BASIC ROUTING EXAMPLES
// ============================================

// Example 1: Simple GET route
$router->get('/', function() {
    echo json_encode([
        'message' => 'WrightCommerce API v1.0',
        'status' => 'online',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

// Example 2: GET route with controller
$router->get('/products', 'ProductController@index');

// Example 3: GET route with URL parameter
$router->get('/products/{id}', 'ProductController@show');

// Example 4: POST route
$router->post('/products', 'ProductController@store');

// Example 5: PUT route for updates
$router->put('/products/{id}', 'ProductController@update');

// Example 6: DELETE route
$router->delete('/products/{id}', 'ProductController@destroy');


// ============================================
// URL PARAMETERS EXAMPLES
// ============================================

// Example 1: Single parameter
$router->get('/users/{id}', function($id) {
    echo json_encode(['user_id' => $id]);
});

// Example 2: Multiple parameters
$router->get('/businesses/{businessId}/products/{productId}', function($businessId, $productId) {
    echo json_encode([
        'business_id' => $businessId,
        'product_id' => $productId
    ]);
});

// Example 3: Access parameters in controller
$router->get('/orders/{id}', 'OrderController@show');
// In OrderController:
// public function show($id) {
//     // $id is automatically passed
// }


// ============================================
// RESTFUL RESOURCE ROUTING
// ============================================

// Create all RESTful routes at once
$router->resource('products', 'ProductController');

// This automatically creates:
// GET    /products           -> ProductController@index    (list all)
// GET    /products/{id}      -> ProductController@show     (show one)
// POST   /products           -> ProductController@store    (create)
// PUT    /products/{id}      -> ProductController@update   (update)
// DELETE /products/{id}      -> ProductController@destroy  (delete)

// You can create multiple resources:
$router->resource('products', 'ProductController');
$router->resource('orders', 'OrderController');
$router->resource('customers', 'CustomerController');


// ============================================
// ROUTE GROUPS (API Versioning)
// ============================================

// Example 1: Group routes with common prefix
$router->group('/api/v1', function($router) {
    $router->get('/products', 'ProductController@index');
    $router->get('/orders', 'OrderController@index');
    $router->get('/customers', 'CustomerController@index');
});
// Routes become: /api/v1/products, /api/v1/orders, /api/v1/customers

// Example 2: Nested groups
$router->group('/api', function($router) {
    $router->group('/v1', function($router) {
        $router->resource('products', 'ProductController');
        $router->resource('orders', 'OrderController');
    });
    
    $router->group('/v2', function($router) {
        $router->resource('products', 'ProductControllerV2');
    });
});


// ============================================
// MIDDLEWARE (Authentication, CORS, etc.)
// ============================================

// Example 1: Simple authentication middleware
$router->middleware(function() {
    // Skip auth for public routes
    $publicRoutes = ['/', '/login', '/register'];
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if (in_array($currentPath, $publicRoutes)) {
        return; // Allow access
    }
    
    // Check for authorization header
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Token required']);
        exit;
    }
    
    // Verify token (simplified example)
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    // Validate token here...
});

// Example 2: CORS middleware
$router->middleware(function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
});

// Example 3: Rate limiting middleware
$router->middleware(function() {
    session_start();
    
    $key = 'api_calls_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limit = 100; // 100 requests per hour
    $window = 3600; // 1 hour
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window expired
    if (time() - $data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return;
    }
    
    // Check limit
    if ($data['count'] >= $limit) {
        http_response_code(429);
        echo json_encode(['error' => 'Too Many Requests']);
        exit;
    }
    
    $_SESSION[$key]['count']++;
});


// ============================================
// CUSTOM 404 HANDLER
// ============================================

$router->setNotFoundHandler(function() {
    http_response_code(404);
    echo json_encode([
        'error' => 'Not Found',
        'message' => 'The API endpoint you requested does not exist',
        'docs' => 'https://docs.wrightcommerce.com'
    ]);
});


// ============================================
// REAL-WORLD ROUTING EXAMPLES
// ============================================

/**
 * EXAMPLE 1: Complete Product API Routes
 */
// List all products for a business
$router->get('/businesses/{businessId}/products', 'ProductController@index');

// Get single product
$router->get('/products/{id}', 'ProductController@show');

// Create new product
$router->post('/products', 'ProductController@store');

// Update product
$router->put('/products/{id}', 'ProductController@update');

// Delete product
$router->delete('/products/{id}', 'ProductController@destroy');

// Search products
$router->get('/products/search', 'ProductController@search');

// Low stock products
$router->get('/products/low-stock', 'ProductController@lowStock');


/**
 * EXAMPLE 2: Order Management Routes
 */
// All orders for a business
$router->get('/businesses/{businessId}/orders', 'OrderController@index');

// Single order
$router->get('/orders/{id}', 'OrderController@show');

// Create order
$router->post('/orders', 'OrderController@store');

// Update order status
$router->patch('/orders/{id}/status', 'OrderController@updateStatus');

// Cancel order
$router->post('/orders/{id}/cancel', 'OrderController@cancel');

// Order statistics
$router->get('/businesses/{businessId}/orders/stats', 'OrderController@statistics');


/**
 * EXAMPLE 3: Authentication Routes
 */
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/logout', 'AuthController@logout');
$router->post('/auth/forgot-password', 'AuthController@forgotPassword');
$router->post('/auth/reset-password', 'AuthController@resetPassword');
$router->get('/auth/me', 'AuthController@me');


/**
 * EXAMPLE 4: M-PESA Payment Routes
 */
// Initiate STK Push
$router->post('/payments/mpesa/stk-push', 'MpesaController@stkPush');

// M-PESA callback
$router->post('/payments/mpesa/callback', 'MpesaController@callback');

// Check payment status
$router->get('/payments/{id}/status', 'PaymentController@status');

// Payment history
$router->get('/businesses/{businessId}/payments', 'PaymentController@index');


/**
 * EXAMPLE 5: Customer Management Routes
 */
$router->resource('customers', 'CustomerController');

// Customer orders
$router->get('/customers/{id}/orders', 'CustomerController@orders');

// Customer statistics
$router->get('/customers/{id}/stats', 'CustomerController@statistics');


/**
 * EXAMPLE 6: Dashboard/Analytics Routes
 */
$router->get('/dashboard/stats', 'DashboardController@stats');
$router->get('/dashboard/sales', 'DashboardController@sales');
$router->get('/dashboard/top-products', 'DashboardController@topProducts');
$router->get('/dashboard/recent-orders', 'DashboardController@recentOrders');


/**
 * EXAMPLE 7: Webhook Routes
 */
$router->post('/webhooks/mpesa', 'WebhookController@mpesa');
$router->post('/webhooks/whatsapp', 'WebhookController@whatsapp');
$router->any('/webhooks/test', 'WebhookController@test'); // Any HTTP method


// ============================================
// DISPATCH THE ROUTER
// ============================================

// This must be the last line in your index.php
try {
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}

?>


// ============================================
// COMPLETE WORKING EXAMPLE: public/index.php
// ============================================

<?php
/**
 * WrightCommerce API Entry Point
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once '../config/database.php';
require_once '../app/helpers/Database.php';
require_once '../app/helpers/Router.php';

// Create router
$router = new Router();

// ============================================
// PUBLIC ROUTES (No authentication required)
// ============================================

$router->get('/', function() {
    echo json_encode([
        'app' => 'WrightCommerce API',
        'version' => '1.0',
        'status' => 'online',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

// Authentication
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/login', 'AuthController@login');

// ============================================
// API V1 ROUTES (Protected)
// ============================================

$router->group('/api/v1', function($router) {
    
    // Products
    $router->resource('products', 'ProductController');
    $router->get('/products/search', 'ProductController@search');
    
    // Orders
    $router->resource('orders', 'OrderController');
    $router->patch('/orders/{id}/status', 'OrderController@updateStatus');
    
    // Customers
    $router->resource('customers', 'CustomerController');
    
    // Payments
    $router->post('/payments/mpesa/stk-push', 'MpesaController@stkPush');
    $router->get('/payments/{id}/status', 'PaymentController@status');
    
    // Dashboard
    $router->get('/dashboard/stats', 'DashboardController@stats');
});

// Webhooks (outside API group, special handling)
$router->post('/webhooks/mpesa', 'WebhookController@mpesa');

// Custom 404
$router->setNotFoundHandler(function() {
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found',
        'available_endpoints' => [
            'GET /' => 'API info',
            'POST /auth/login' => 'Login',
            'GET /api/v1/products' => 'List products',
            'POST /api/v1/orders' => 'Create order'
        ]
    ]);
});

// Dispatch
try {
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


// ============================================
// GETTING REQUEST DATA IN CONTROLLERS
// ============================================

// Example Controller: ProductController.php
<?php
class ProductController {
    
    // GET /products
    public function index() {
        $db = Database::getInstance();
        
        // Get query parameters
        $businessId = $_GET['business_id'] ?? null;
        $status = $_GET['status'] ?? 'active';
        
        $products = $db->findAll('products', [
            'business_id' => $businessId,
            'status' => $status
        ]);
        
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
    }
    
    // GET /products/{id}
    public function show($id) {
        $db = Database::getInstance();
        $product = $db->find('products', $id);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $product
        ]);
    }
    
    // POST /products
    public function store() {
        $db = Database::getInstance();
        
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Or use router helper (if you passed router to controller)
        // $data = $router->getJsonInput();
        
        // Validate
        if (empty($data['name']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and price required']);
            return;
        }
        
        // Create product
        $productId = $db->insert('products', [
            'business_id' => $data['business_id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'] ?? 0,
            'status' => 'active'
        ]);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Product created',
            'product_id' => $productId
        ]);
    }
    
    // PUT /products/{id}
    public function update($id) {
        $db = Database::getInstance();
        
        // Check if product exists
        if (!$db->exists('products', ['id' => $id])) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }
        
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Update
        $db->update('products', $id, $data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated'
        ]);
    }
    
    // DELETE /products/{id}
    public function destroy($id) {
        $db = Database::getInstance();
        
        // Soft delete (recommended)
        $db->update('products', $id, ['status' => 'deleted']);
        
        // Or hard delete
        // $db->delete('products', $id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted'
        ]);
    }
}
?>


// ============================================
// TESTING YOUR ROUTES
// ============================================

// Use tools like:
// 1. Postman
// 2. cURL
// 3. Thunder Client (VS Code extension)

// cURL Examples:

// GET request
curl http://localhost/wrightcommerce/public/api/v1/products

// POST request
curl -X POST http://localhost/wrightcommerce/public/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{"name":"iPhone 15","price":120000,"stock":10}'

// PUT request
curl -X PUT http://localhost/wrightcommerce/public/api/v1/products/5 \
  -H "Content-Type: application/json" \
  -d '{"price":125000}'

// DELETE request
curl -X DELETE http://localhost/wrightcommerce/public/api/v1/products/5

?>