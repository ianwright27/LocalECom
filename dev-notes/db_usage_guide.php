<?php
/**
 * DATABASE HELPER - USAGE GUIDE & EXAMPLES
 * WrightCommerce Platform
 * 
 * This file contains practical examples of how to use the Database helper class
 * in your controllers and models.
 */

// ============================================
// BASIC SETUP
// ============================================

// 1. Include the Database class in your file
require_once '../app/helpers/Database.php';
require_once '../config/database.php';

// 2. Get the database instance (Singleton pattern - only one connection)
$db = Database::getInstance();


// ============================================
// INSERT EXAMPLES
// ============================================

// Example 1: Insert a new product
$productId = $db->insert('products', [
    'business_id' => 1,
    'name' => 'Samsung Galaxy A54',
    'description' => '5G smartphone with 128GB storage',
    'price' => 45000,
    'cost_price' => 38000,
    'stock' => 15,
    'sku' => 'SAM-A54-BLK',
    'status' => 'active'
]);
// Returns: 5 (the new product ID)
// Note: created_at and updated_at are added automatically

// Example 2: Insert a new customer
$customerId = $db->insert('customers', [
    'business_id' => 1,
    'name' => 'John Kamau',
    'email' => 'john.kamau@example.com',
    'phone' => '254712345678',
    'address' => 'Nairobi, Kenya'
]);

// Example 3: Insert with transaction (for multiple related inserts)
try {
    $db->beginTransaction();
    
    // Create order
    $orderId = $db->insert('orders', [
        'business_id' => 1,
        'customer_id' => $customerId,
        'order_number' => 'ORD-' . time(),
        'total' => 90000,
        'status' => 'pending',
        'payment_status' => 'unpaid'
    ]);
    
    // Add order items
    $db->insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 5,
        'product_name' => 'Samsung Galaxy A54',
        'quantity' => 2,
        'price' => 45000,
        'total' => 90000
    ]);
    
    $db->commit();
    echo "Order created successfully!";
} catch (Exception $e) {
    $db->rollback();
    echo "Error: " . $e->getMessage();
}


// ============================================
// SELECT/FIND EXAMPLES
// ============================================

// Example 1: Find single product by ID
$product = $db->find('products', 5);
if ($product) {
    echo $product['name'];  // Output: Samsung Galaxy A54
    echo $product['price']; // Output: 45000
}

// Example 2: Find user by email
$user = $db->find('users', ['email' => 'admin@wrightcommerce.com']);
if ($user) {
    echo $user['name'];
}

// Example 3: Find order with multiple conditions
$order = $db->find('orders', [
    'business_id' => 1,
    'order_number' => 'ORD-1234567890'
]);

// Example 4: Get all products for a business
$products = $db->findAll('products', ['business_id' => 1]);
foreach ($products as $product) {
    echo $product['name'] . ' - KES ' . $product['price'] . '<br>';
}

// Example 5: Get active products, ordered by price, limit 10
$products = $db->findAll(
    'products',
    ['status' => 'active', 'business_id' => 1],
    'price DESC',
    10
);

// Example 6: Get latest 5 orders with pagination
$orders = $db->findAll(
    'orders',
    ['business_id' => 1],
    'created_at DESC',
    5,      // limit
    0       // offset (use 5, 10, 15... for pagination)
);

// Example 7: Custom query for complex needs
$results = $db->query(
    "SELECT o.*, c.name as customer_name 
     FROM orders o 
     JOIN customers c ON o.customer_id = c.id 
     WHERE o.business_id = ? AND o.status = ?",
    [1, 'completed']
);


// ============================================
// UPDATE EXAMPLES
// ============================================

// Example 1: Update product by ID
$rowsAffected = $db->update('products', 5, [
    'price' => 47000,
    'stock' => 12
]);
// updated_at is automatically set

// Example 2: Update by condition
$db->update('products', 
    ['sku' => 'SAM-A54-BLK'],  // condition
    ['status' => 'inactive']    // data to update
);

// Example 3: Update order status
$db->update('orders', $orderId, [
    'status' => 'processing',
    'payment_status' => 'paid'
]);

// Example 4: Update multiple orders
$db->update('orders',
    ['status' => 'pending', 'created_at' => date('Y-m-d')],
    ['status' => 'cancelled']
);


// ============================================
// DELETE EXAMPLES
// ============================================

// Example 1: Delete product by ID
$deleted = $db->delete('products', 5);
echo "Deleted {$deleted} row(s)";

// Example 2: Delete by condition
$db->delete('products', ['status' => 'deleted']);

// Example 3: Soft delete (update status instead of deleting)
// Recommended for important data like orders
$db->update('orders', 15, ['status' => 'deleted']);


// ============================================
// COUNT & EXISTS EXAMPLES
// ============================================

// Example 1: Count total products
$totalProducts = $db->count('products');
echo "Total products: {$totalProducts}";

// Example 2: Count active products for a business
$activeCount = $db->count('products', [
    'business_id' => 1,
    'status' => 'active'
]);

// Example 3: Check if email exists (for registration)
if ($db->exists('users', ['email' => 'test@example.com'])) {
    echo "Email already registered!";
}

// Example 4: Check if SKU is unique
if ($db->exists('products', ['sku' => 'IP15-BLK'])) {
    echo "SKU already exists!";
}


// ============================================
// REAL-WORLD USE CASES
// ============================================

/**
 * USE CASE 1: Product Controller - Create Product
 */
function createProduct($data) {
    $db = Database::getInstance();
    
    // Validate data first
    if (empty($data['name']) || empty($data['price'])) {
        return ['success' => false, 'message' => 'Name and price required'];
    }
    
    // Check if SKU already exists
    if (!empty($data['sku']) && $db->exists('products', ['sku' => $data['sku']])) {
        return ['success' => false, 'message' => 'SKU already exists'];
    }
    
    try {
        $productId = $db->insert('products', [
            'business_id' => $data['business_id'],
            'name' => $db->sanitize($data['name']),
            'description' => $db->sanitize($data['description'] ?? ''),
            'price' => (float) $data['price'],
            'cost_price' => (float) ($data['cost_price'] ?? 0),
            'stock' => (int) ($data['stock'] ?? 0),
            'sku' => $data['sku'] ?? null,
            'status' => 'active'
        ]);
        
        return [
            'success' => true,
            'message' => 'Product created successfully',
            'product_id' => $productId
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to create product'];
    }
}

/**
 * USE CASE 2: Order Controller - Get Order with Items
 */
function getOrderWithItems($orderId) {
    $db = Database::getInstance();
    
    // Get order details
    $order = $db->find('orders', $orderId);
    if (!$order) {
        return null;
    }
    
    // Get customer info
    $customer = $db->find('customers', $order['customer_id']);
    $order['customer'] = $customer;
    
    // Get order items
    $order['items'] = $db->findAll('order_items', ['order_id' => $orderId]);
    
    return $order;
}

/**
 * USE CASE 3: Dashboard - Get Statistics
 */
function getDashboardStats($businessId) {
    $db = Database::getInstance();
    
    return [
        'total_products' => $db->count('products', ['business_id' => $businessId]),
        'active_products' => $db->count('products', [
            'business_id' => $businessId,
            'status' => 'active'
        ]),
        'total_orders' => $db->count('orders', ['business_id' => $businessId]),
        'pending_orders' => $db->count('orders', [
            'business_id' => $businessId,
            'status' => 'pending'
        ]),
        'total_customers' => $db->count('customers', ['business_id' => $businessId])
    ];
}

/**
 * USE CASE 4: M-PESA Payment - Process Payment
 */
function processMpesaPayment($orderId, $transactionData) {
    $db = Database::getInstance();
    
    try {
        $db->beginTransaction();
        
        // Insert payment record
        $paymentId = $db->insert('payments', [
            'order_id' => $orderId,
            'gateway' => 'mpesa',
            'transaction_id' => $transactionData['TransactionID'],
            'amount' => $transactionData['Amount'],
            'status' => 'completed',
            'metadata' => json_encode($transactionData)
        ]);
        
        // Update order payment status
        $db->update('orders', $orderId, [
            'payment_status' => 'paid',
            'status' => 'processing'
        ]);
        
        $db->commit();
        
        return ['success' => true, 'payment_id' => $paymentId];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * USE CASE 5: Authentication - Login User
 */
function loginUser($email, $password) {
    $db = Database::getInstance();
    
    // Find user by email
    $user = $db->find('users', ['email' => $email]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    // Verify password (assuming passwords are hashed with password_hash())
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    // Don't send password back to client
    unset($user['password']);
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

/**
 * USE CASE 6: Inventory - Update Stock After Order
 */
function updateInventoryAfterOrder($orderId) {
    $db = Database::getInstance();
    
    // Get order items
    $items = $db->findAll('order_items', ['order_id' => $orderId]);
    
    try {
        $db->beginTransaction();
        
        foreach ($items as $item) {
            // Get current product
            $product = $db->find('products', $item['product_id']);
            
            // Calculate new stock
            $newStock = $product['stock'] - $item['quantity'];
            
            // Update product stock
            $db->update('products', $item['product_id'], [
                'stock' => $newStock
            ]);
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


// ============================================
// SECURITY BEST PRACTICES
// ============================================

// ✅ GOOD - Using prepared statements (automatic with this class)
$products = $db->findAll('products', ['business_id' => $_GET['business_id']]);

// ✅ GOOD - Sanitizing output for display
$product = $db->find('products', 1);
echo $db->sanitize($product['name']);  // Prevents XSS

// ✅ GOOD - Using transactions for related operations
$db->beginTransaction();
// ... multiple database operations
$db->commit();

// ✅ GOOD - Validating input before insert/update
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $db->insert('users', ['email' => $email]);
}

// ❌ BAD - Never build raw SQL with user input
// $sql = "SELECT * FROM users WHERE email = '{$_POST['email']}'"; // SQL Injection risk!


// ============================================
// PERFORMANCE TIPS
// ============================================

// 1. Use findAll() with limit for pagination instead of loading all records
$page = 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$products = $db->findAll('products', [], 'id DESC', $perPage, $offset);

// 2. Use exists() instead of count() when you only need to check existence
if ($db->exists('users', ['email' => $email])) {
    // More efficient than: if ($db->count('users', ['email' => $email]) > 0)
}

// 3. Use transactions for multiple related operations
// Faster and ensures data consistency

// 4. For complex queries with JOINs, use query() method directly
$results = $db->query(
    "SELECT p.*, b.name as business_name 
     FROM products p 
     JOIN businesses b ON p.business_id = b.id 
     WHERE p.status = ?",
    ['active']
);


// ============================================
// ERROR HANDLING
// ============================================

// Always wrap database operations in try-catch
try {
    $productId = $db->insert('products', $data);
    echo "Success! Product ID: {$productId}";
} catch (Exception $e) {
    // Log error (errors are automatically logged to logs/database_errors.log)
    // Show user-friendly message
    echo "An error occurred. Please try again.";
    
    // For development, you can show the error:
    // echo $e->getMessage();
}

?>