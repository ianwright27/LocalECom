<?php
/**
 * WrightCommerce Storefront
 * Customer-facing shopping interface
 * 
 * Features:
 * - Browse products from all businesses
 * - Add to cart
 * - Checkout
 * - Order confirmation
 */

session_start();
require_once '../config/database.php';
require_once '../app/helpers/Database.php';

$db = Database::getInstance();

// Handle customer login
if (isset($_POST['customer_login'])) {
    $email = $_POST['login_email'];
    $password = $_POST['login_password'];
    
    $customer = $db->find('customers', ['email' => $email]);
    
    if ($customer && !empty($customer['password']) && password_verify($password, $customer['password'])) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_name'] = $customer['name'];
        $_SESSION['customer_email'] = $customer['email'];
        header('Location: shop.php');
        exit;
    } else {
        $loginError = 'Invalid email or password';
    }
}

// Handle customer registration
if (isset($_POST['customer_register'])) {
    $name = $_POST['reg_name'];
    $email = $_POST['reg_email'];
    $password = $_POST['reg_password'];
    $phone = $_POST['reg_phone'];
    
    // Check if email exists
    if ($db->exists('customers', ['email' => $email])) {
        $registerError = 'Email already registered';
    } else {
        try {
            // Get business_id from first active product (for marketplace)
            $products = $db->findAll('products', ['status' => 'active'], 'id ASC', 1);
            $businessId = $products[0]['business_id'] ?? 1;
            
            $customerId = $db->insert('customers', [
                'business_id' => $businessId,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => str_replace([' ', '-', '+'], '', $phone)
            ]);
            
            // Auto-login
            $_SESSION['customer_id'] = $customerId;
            $_SESSION['customer_name'] = $name;
            $_SESSION['customer_email'] = $email;
            
            header('Location: shop.php');
            exit;
        } catch (Exception $e) {
            $registerError = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Handle customer logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_email']);
    header('Location: shop.php');
    exit;
}

// Check if customer is logged in
$isCustomerLoggedIn = isset($_SESSION['customer_id']);

// Initialize cart if doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get page
$page = $_GET['page'] ?? 'products';
$productId = $_GET['id'] ?? null;

// Handle cart actions
$action = $_GET['action'] ?? null;

if ($action === 'add-to-cart' && $productId) {
    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = 0;
    }
    $_SESSION['cart'][$productId]++;
    header('Location: shop.php?page=cart&added=1');
    exit;
}

if ($action === 'update-cart' && isset($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $pid => $qty) {
        if ($qty > 0) {
            $_SESSION['cart'][$pid] = (int) $qty;
        } else {
            unset($_SESSION['cart'][$pid]);
        }
    }
    header('Location: shop.php?page=cart&updated=1');
    exit;
}

if ($action === 'remove-from-cart' && $productId) {
    unset($_SESSION['cart'][$productId]);
    header('Location: shop.php?page=cart&removed=1');
    exit;
}

if ($action === 'clear-cart') {
    $_SESSION['cart'] = [];
    header('Location: shop.php?page=cart&cleared=1');
    exit;
}

// Handle checkout
if ($action === 'place-order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $customerData = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address'] ?? ''
        ];
        
        // Create or get customer
        $customer = $db->find('customers', ['email' => $customerData['email']]);
        
        if (!$customer) {
            // Get business_id from first product in cart
            $firstProductId = array_key_first($_SESSION['cart']);
            $firstProduct = $db->find('products', $firstProductId);
            
            $customerId = $db->insert('customers', [
                'business_id' => $firstProduct['business_id'],
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'phone' => str_replace([' ', '-', '+'], '', $customerData['phone']),
                'address' => $customerData['address']
            ]);
        } else {
            $customerId = $customer['id'];
        }
        
        // Calculate total
        $total = 0;
        $businessId = null;
        $items = [];
        
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $product = $db->find('products', $pid);
            if (!$product) continue;
            
            if (!$businessId) {
                $businessId = $product['business_id'];
            }
            
            $itemTotal = $product['price'] * $qty;
            $total += $itemTotal;
            
            $items[] = [
                'product' => $product,
                'quantity' => $qty,
                'price' => $product['price'],
                'total' => $itemTotal
            ];
        }
        
        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Create order
        $orderId = $db->insert('orders', [
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'notes' => $_POST['notes'] ?? ''
        ]);
        
        // Create order items
        foreach ($items as $item) {
            $db->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $item['product']['id'],
                'product_name' => $item['product']['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['total']
            ]);
            
            // Update product stock
            $newStock = $item['product']['stock'] - $item['quantity'];
            $db->update('products', $item['product']['id'], [
                'stock' => max(0, $newStock)
            ]);
        }
        
        $db->commit();
        
        // Save order to session
        $_SESSION['last_order'] = [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid'
        ];
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        header('Location: shop.php?page=confirmation');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $checkoutError = 'Failed to place order: ' . $e->getMessage();
        error_log("Order creation error: " . $e->getMessage());
    }
}

// Get cart count
$cartCount = array_sum($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WrightCommerce Shop</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; }
        
        /* Header */
        .header { background: #2c3e50; color: white; padding: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .header h1 a { color: white; text-decoration: none; }
        .cart-icon { position: relative; background: #e67e22; padding: 10px 20px; border-radius: 4px; text-decoration: none; color: white; font-weight: 600; }
        .cart-badge { position: absolute; top: -8px; right: -8px; background: #e74c3c; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        
        /* Container */
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        /* Products Grid */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .product-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .product-image { width: 100%; height: 200px; object-fit: cover; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 48px; }
        .product-info { padding: 15px; }
        .product-name { font-size: 16px; font-weight: 600; margin-bottom: 5px; }
        .product-price { font-size: 20px; color: #e67e22; font-weight: 700; margin: 10px 0; }
        .product-stock { font-size: 12px; color: #666; margin-bottom: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; transition: 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-success:hover { background: #27ae60; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        
        /* Cart */
        .cart-table { width: 100%; background: white; border-radius: 8px; overflow: hidden; }
        .cart-table th, .cart-table td { padding: 15px; text-align: left; }
        .cart-table th { background: #f8f9fa; }
        .cart-summary { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; }
        
        /* Checkout Form */
        .checkout-form { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        
        /* Alerts */
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 8px; }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
        
        .text-center { text-align: center; }
        .mb-20 { margin-bottom: 20px; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <h1><a href="shop.php">🛍️ WrightCommerce Shop</a></h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <?php if ($isCustomerLoggedIn): ?>
                <span style="color: white;">👤 <?= htmlspecialchars($_SESSION['customer_name']) ?></span>
                <a href="?action=logout" style="color: white; text-decoration: none;">Logout</a>
            <?php else: ?>
                <a href="?page=login" style="color: white; text-decoration: none;">Login</a>
                <a href="?page=register" style="color: white; text-decoration: none; background: #e67e22; padding: 8px 15px; border-radius: 4px;">Register</a>
            <?php endif; ?>
            <a href="shop.php?page=cart" class="cart-icon">
                🛒 Cart
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<div class="container">
    <?php include 'shop-pages.php'; ?>
</div>

</body>
</html>