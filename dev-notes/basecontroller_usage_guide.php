<?php
/**
 * BASECONTROLLER - USAGE GUIDE & EXAMPLES
 * WrightCommerce Platform
 * 
 * This file contains practical examples of how to use the BaseController class
 * in your controllers.
 */

// ============================================
// CREATING A CONTROLLER
// ============================================

// All controllers should extend BaseController
// File: app/controllers/ProductController.php


require_once __DIR__ . '/BaseController.php';

class ProductController extends BaseController {
    
    /**
     * GET /products
     * List all products for a business
     */
    public function index() {
        // Require authentication
        $this->requireAuth();
        
        // Get business ID from authenticated user
        $businessId = $this->getBusinessId();
        
        // Get pagination parameters
        $pagination = $this->getPagination(20); // 20 items per page
        
        // Get filters from query params
        $status = $this->input('status', 'active');
        
        // Get total count
        $total = $this->db->count('products', [
            'business_id' => $businessId,
            'status' => $status
        ]);
        
        // Get products
        $products = $this->db->findAll(
            'products',
            ['business_id' => $businessId, 'status' => $status],
            'created_at DESC',
            $pagination['limit'],
            $pagination['offset']
        );
        
        // Return paginated response
        return $this->paginate($products, $total, $pagination);
    }
    
    /**
     * GET /products/{id}
     * Show single product
     */
    public function show($id) {
        $this->requireAuth();
        
        // Check if product exists and belongs to user's business
        $this->requireOwnership('products', $id);
        
        $product = $this->db->find('products', $id);
        
        return $this->success($product, 'Product retrieved successfully');
    }
    
    /**
     * POST /products
     * Create new product
     */
    public function store() {
        $this->requireAuth();
        
        // Validate input
        $this->validate([
            'name' => 'required|min:3|max:255',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
            'sku' => 'unique:products,sku'
        ]);
        
        // Get only allowed fields
        $data = $this->only(['name', 'description', 'price', 'cost_price', 'stock', 'sku']);
        
        // Add business_id
        $data['business_id'] = $this->getBusinessId();
        $data['status'] = 'active';
        
        // Sanitize text inputs
        $data['name'] = $this->sanitize($data['name']);
        $data['description'] = $this->sanitize($data['description'] ?? '');
        
        // Handle image upload if present
        if (isset($_FILES['image'])) {
            $imagePath = $this->uploadFile('image', 'products');
            if ($imagePath) {
                $data['image'] = $imagePath;
            }
        }
        
        try {
            $productId = $this->db->insert('products', $data);
            
            $this->log("Product created: ID {$productId} by user {$this->getUserId()}");
            
            return $this->created(
                ['id' => $productId],
                'Product created successfully'
            );
        } catch (Exception $e) {
            $this->log("Failed to create product: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to create product');
        }
    }
    
    /**
     * PUT /products/{id}
     * Update product
     */
    public function update($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        // Validate input
        $this->validate([
            'name' => 'min:3|max:255',
            'price' => 'numeric|min:0',
            'cost_price' => 'numeric|min:0',
            'stock' => 'integer|min:0'
        ]);
        
        // Get input data
        $data = $this->only(['name', 'description', 'price', 'cost_price', 'stock', 'status']);
        
        // Remove empty values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Sanitize if name or description present
        if (isset($data['name'])) {
            $data['name'] = $this->sanitize($data['name']);
        }
        if (isset($data['description'])) {
            $data['description'] = $this->sanitize($data['description']);
        }
        
        try {
            $this->db->update('products', $id, $data);
            
            return $this->success(null, 'Product updated successfully');
        } catch (Exception $e) {
            return $this->serverError('Failed to update product');
        }
    }
    
    /**
     * DELETE /products/{id}
     * Delete product (soft delete)
     */
    public function destroy($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        try {
            // Soft delete - just update status
            $this->db->update('products', $id, ['status' => 'deleted']);
            
            return $this->success(null, 'Product deleted successfully');
        } catch (Exception $e) {
            return $this->serverError('Failed to delete product');
        }
    }
}
?>


// ============================================
// AUTHENTICATION CONTROLLER EXAMPLE
// ============================================

<?php
require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {
    
    /**
     * POST /auth/register
     * Register new user
     */
    public function register() {
        // Validate input
        $this->validate([
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'business_name' => 'required|min:3'
        ]);
        
        try {
            $this->db->beginTransaction();
            
            // Create business first
            $businessId = $this->db->insert('businesses', [
                'name' => $this->sanitize($this->input('business_name')),
                'email' => $this->input('email'),
                'settings' => json_encode([])
            ]);
            
            // Create user
            $userId = $this->db->insert('users', [
                'business_id' => $businessId,
                'name' => $this->sanitize($this->input('name')),
                'email' => $this->input('email'),
                'password' => password_hash($this->input('password'), PASSWORD_DEFAULT),
                'role' => 'owner'
            ]);
            
            $this->db->commit();
            
            // Log user in
            $_SESSION['user_id'] = $userId;
            
            return $this->created([
                'user_id' => $userId,
                'business_id' => $businessId
            ], 'Registration successful');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Registration failed: " . $e->getMessage(), 'error');
            return $this->serverError('Registration failed');
        }
    }
    
    /**
     * POST /auth/login
     * Login user
     */
    public function login() {
        // Validate input
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        $email = $this->input('email');
        $password = $this->input('password');
        
        // Find user
        $user = $this->db->find('users', ['email' => $email]);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return $this->unauthorized('Invalid credentials');
        }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        
        // Remove password from response
        unset($user['password']);
        
        return $this->success([
            'user' => $user,
            'token' => $this->generateRandomString(64) // For API token if needed
        ], 'Login successful');
    }
    
    /**
     * POST /auth/logout
     * Logout user
     */
    public function logout() {
        $this->requireAuth();
        
        session_destroy();
        
        return $this->success(null, 'Logout successful');
    }
    
    /**
     * GET /auth/me
     * Get current user info
     */
    public function me() {
        $this->requireAuth();
        
        $user = $this->currentUser;
        unset($user['password']);
        
        return $this->success([
            'user' => $user,
            'business' => $this->currentBusiness
        ]);
    }
}
?>


// ============================================
// ORDER CONTROLLER EXAMPLE
// ============================================

<?php
require_once __DIR__ . '/BaseController.php';

class OrderController extends BaseController {
    
    /**
     * GET /orders
     * List all orders
     */
    public function index() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        // Get filters
        $status = $this->input('status');
        $paymentStatus = $this->input('payment_status');
        
        // Build conditions
        $conditions = ['business_id' => $businessId];
        if ($status) {
            $conditions['status'] = $status;
        }
        if ($paymentStatus) {
            $conditions['payment_status'] = $paymentStatus;
        }
        
        // Get total and orders
        $total = $this->db->count('orders', $conditions);
        $orders = $this->db->findAll(
            'orders',
            $conditions,
            'created_at DESC',
            $pagination['limit'],
            $pagination['offset']
        );
        
        // Enrich with customer info
        foreach ($orders as &$order) {
            $customer = $this->db->find('customers', $order['customer_id']);
            $order['customer'] = $customer;
        }
        
        return $this->paginate($orders, $total, $pagination);
    }
    
    /**
     * GET /orders/{id}
     * Show single order with items
     */
    public function show($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $order = $this->db->find('orders', $id);
        
        // Get customer
        $order['customer'] = $this->db->find('customers', $order['customer_id']);
        
        // Get order items
        $order['items'] = $this->db->findAll('order_items', ['order_id' => $id]);
        
        // Get payments
        $order['payments'] = $this->db->findAll('payments', ['order_id' => $id]);
        
        return $this->success($order);
    }
    
    /**
     * POST /orders
     * Create new order
     */
    public function store() {
        $this->requireAuth();
        
        // Validate
        $this->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);
        
        $businessId = $this->getBusinessId();
        $customerId = $this->input('customer_id');
        $items = $this->input('items');
        
        try {
            $this->db->beginTransaction();
            
            // Calculate total
            $total = 0;
            foreach ($items as $item) {
                $product = $this->db->find('products', $item['product_id']);
                $total += $product['price'] * $item['quantity'];
            }
            
            // Create order
            $orderId = $this->db->insert('orders', [
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . time() . '-' . rand(1000, 9999),
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $this->input('notes', '')
            ]);
            
            // Create order items
            foreach ($items as $item) {
                $product = $this->db->find('products', $item['product_id']);
                
                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'quantity' => $item['quantity'],
                    'price' => $product['price'],
                    'total' => $product['price'] * $item['quantity']
                ]);
                
                // Update product stock
                $newStock = $product['stock'] - $item['quantity'];
                $this->db->update('products', $product['id'], [
                    'stock' => max(0, $newStock)
                ]);
            }
            
            $this->db->commit();
            
            $this->log("Order created: {$orderId}");
            
            return $this->created([
                'order_id' => $orderId,
                'order_number' => 'ORD-' . time() . '-' . rand(1000, 9999)
            ], 'Order created successfully');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Order creation failed: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to create order');
        }
    }
    
    /**
     * PATCH /orders/{id}/status
     * Update order status
     */
    public function updateStatus($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $this->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);
        
        $status = $this->input('status');
        
        $this->db->update('orders', $id, ['status' => $status]);
        
        return $this->success(null, 'Order status updated');
    }
}
?>


// ============================================
// VALIDATION EXAMPLES
// ============================================

// Basic validation
$this->validate([
    'name' => 'required|min:3|max:255',
    'email' => 'required|email',
    'price' => 'required|numeric|min:0',
    'stock' => 'integer|min:0',
    'status' => 'in:active,inactive,deleted'
]);

// Unique validation
$this->validate([
    'email' => 'unique:users,email',
    'sku' => 'unique:products,sku'
]);

// Exists validation
$this->validate([
    'customer_id' => 'exists:customers,id',
    'product_id' => 'exists:products,id'
]);

// Phone validation (Kenya format)
$this->validate([
    'phone' => 'required|phone'
]);

// Date validation
$this->validate([
    'birth_date' => 'date',
    'delivery_date' => 'required|date'
]);

// URL validation
$this->validate([
    'website' => 'url'
]);


// ============================================
// RESPONSE EXAMPLES
// ============================================

// Success response
return $this->success($data, 'Operation successful');
// Output: {"success":true,"message":"Operation successful","data":{...}}

// Created response (201)
return $this->created(['id' => $id], 'Resource created');

// Not found response (404)
return $this->notFound('Product not found');

// Validation error (422)
return $this->validationError($errors);

// Unauthorized (401)
return $this->unauthorized('Please login');

// Forbidden (403)
return $this->forbidden('Insufficient permissions');

// Server error (500)
return $this->serverError('Something went wrong');


// ============================================
// AUTHENTICATION EXAMPLES
// ============================================

// Require authentication
public function index() {
    $this->requireAuth(); // Returns 401 if not authenticated
    // ... rest of code
}

// Check if authenticated (without exiting)
if ($this->isAuthenticated()) {
    // User is logged in
}

// Require specific role
$this->requireRole('admin'); // Returns 403 if not admin

// Check role without exiting
if ($this->hasRole('admin')) {
    // User is admin
}

// Check ownership
$this->requireOwnership('products', $productId); // Returns 403 if user doesn't own resource


// ============================================
// INPUT HANDLING EXAMPLES
// ============================================

// Get single input
$name = $this->input('name');
$price = $this->input('price', 0); // with default

// Get all input
$data = $this->all();

// Get specific fields only
$data = $this->only(['name', 'email', 'phone']);

// Check if input exists
if ($this->has('discount')) {
    // discount parameter was sent
}

// Sanitize input (prevent XSS)
$name = $this->sanitize($this->input('name'));


// ============================================
// PAGINATION EXAMPLES
// ============================================

// Get pagination parameters
$pagination = $this->getPagination(20); // 20 items per page
// Returns: ['page' => 1, 'per_page' => 20, 'offset' => 0, 'limit' => 20]

// Use in query
$products = $this->db->findAll(
    'products',
    [],
    'id DESC',
    $pagination['limit'],
    $pagination['offset']
);

// Return paginated response
$total = $this->db->count('products');
return $this->paginate($products, $total, $pagination);


// ============================================
// FILE UPLOAD EXAMPLES
// ============================================

// Upload image
$imagePath = $this->uploadFile('image', 'products');
if ($imagePath) {
    $data['image'] = $imagePath;
}

// Upload with custom settings
$docPath = $this->uploadFile(
    'document',
    'documents',
    ['application/pdf', 'application/msword'],
    10485760 // 10MB
);

// Delete file
$this->deleteFile('products/abc123.jpg');


// ============================================
// UTILITY EXAMPLES
// ============================================

// Logging
$this->log("Product created: ID {$productId}");
$this->log("Payment failed: " . $error, 'error');

// Generate random string (for tokens, etc)
$token = $this->generateRandomString(64);

// Format price
$formatted = $this->formatPrice(45000); // "KES 45,000.00"

// Format phone number
$phone = $this->formatPhone('0712345678'); // "254712345678"

?>