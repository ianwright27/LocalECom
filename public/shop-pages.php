<?php
/**
 * Shop Pages - All in One File
 * Save this as: shop-pages.php in the public folder
 * Then include it in shop.php
 */

// This file is included by shop.php and has access to $db, $page, $productId, $cartCount

if ($page === 'login'): ?>
    <!-- CUSTOMER LOGIN -->
    <div class="checkout-form">
        <h2 class="text-center mb-20">Customer Login</h2>
        
        <?php if (isset($loginError)): ?>
            <div class="alert" style="background: #f8d7da; color: #721c24; border-left-color: #dc3545;">
                ❌ <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="login_email" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="login_password" required>
            </div>
            
            <button type="submit" name="customer_login" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        
        <p class="text-center" style="margin-top: 20px;">
            Don't have an account? <a href="?page=register">Register here</a>
        </p>
    </div>

<?php elseif ($page === 'register'): ?>
    <!-- CUSTOMER REGISTRATION -->
    <div class="checkout-form">
        <h2 class="text-center mb-20">Create Account</h2>
        
        <?php if (isset($registerError)): ?>
            <div class="alert" style="background: #f8d7da; color: #721c24; border-left-color: #dc3545;">
                ❌ <?= htmlspecialchars($registerError) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="reg_name" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="reg_email" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="reg_phone" required placeholder="0712345678">
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="reg_password" required minlength="6">
            </div>
            
            <button type="submit" name="customer_register" class="btn btn-success" style="width: 100%;">Create Account</button>
        </form>
        
        <p class="text-center" style="margin-top: 20px;">
            Already have an account? <a href="?page=login">Login here</a>
        </p>
    </div>

<?php elseif ($page === 'products'): ?>
    <!-- PRODUCTS LIST -->
    <h2 class="mb-20">Browse Products</h2>
    
    <?php
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT * FROM products WHERE status = 'active'";
    $params = [];
    
    if ($search) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $products = $db->query($sql, $params);
    ?>
    
    <!-- Search Bar -->
    <form method="GET" style="margin-bottom: 30px; background: white; padding: 20px; border-radius: 8px;">
        <input type="hidden" name="page" value="products">
        <div style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search || $category): ?>
                <a href="shop.php" class="btn">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <h3>No products found</h3>
            <p>Try adjusting your search</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php if (!empty($product['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" class="product-image">
                    <?php else: ?>
                        <div class="product-image">📦</div>
                    <?php endif; ?>
                    
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="product-price">KES <?= number_format($product['price']) ?></div>
                        <div class="product-stock">
                            <?php if ($product['stock'] == 0): ?>
                                <span style="color: #e74c3c;">Out of Stock</span>
                            <?php elseif ($product['stock'] < 10): ?>
                                <span style="color: #e67e22;">Only <?= $product['stock'] ?> left</span>
                            <?php else: ?>
                                <span style="color: #2ecc71;">In Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['stock'] > 0): ?>
                            <a href="?action=add-to-cart&id=<?= $product['id'] ?>" class="btn btn-success" style="width: 100%; text-align: center;">
                                🛒 Add to Cart
                            </a>
                        <?php else: ?>
                            <button class="btn" style="width: 100%; background: #ccc; cursor: not-allowed;" disabled>
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'cart'): ?>
    <!-- SHOPPING CART -->
    <h2 class="mb-20">Shopping Cart</h2>
    
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">✅ Product added to cart!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">✅ Cart updated!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['removed'])): ?>
        <div class="alert alert-info">Product removed from cart</div>
    <?php endif; ?>
    
    <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Add some products to get started</p>
            <a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Continue Shopping</a>
        </div>
    <?php else: ?>
        <form method="POST" action="?action=update-cart">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cartTotal = 0;
                    foreach ($_SESSION['cart'] as $pid => $qty):
                        $product = $db->find('products', $pid);
                        if (!$product) continue;
                        $itemTotal = $product['price'] * $qty;
                        $cartTotal += $itemTotal;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                <small style="color: #999;"><?= htmlspecialchars($product['category'] ?? '') ?></small>
                            </td>
                            <td>KES <?= number_format($product['price']) ?></td>
                            <td>
                                <input type="number" name="quantities[<?= $pid ?>]" value="<?= $qty ?>" min="1" max="<?= $product['stock'] ?>" style="width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            </td>
                            <td><strong>KES <?= number_format($itemTotal) ?></strong></td>
                            <td>
                                <a href="?action=remove-from-cart&id=<?= $pid ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this item?')">Remove</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="cart-summary">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button type="submit" class="btn btn-primary">Update Cart</button>
                        <a href="?action=clear-cart" class="btn btn-danger" onclick="return confirm('Clear entire cart?')">Clear Cart</a>
                        <a href="shop.php" class="btn">Continue Shopping</a>
                    </div>
                    <div style="text-align: right;">
                        <h3>Total: <span style="color: #e67e22;">KES <?= number_format($cartTotal) ?></span></h3>
                        <a href="shop.php?page=checkout" class="btn btn-success" style="margin-top: 10px;">Proceed to Checkout →</a>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

<?php elseif ($page === 'checkout'): ?>
    <!-- CHECKOUT -->
    <h2 class="mb-20 text-center">Checkout</h2>
    
    <?php if (empty($_SESSION['cart'])): ?>
        <div class="alert alert-info">Your cart is empty. <a href="shop.php">Continue shopping</a></div>
    <?php elseif (!$isCustomerLoggedIn): ?>
        <div class="alert alert-info text-center">
            Please <a href="?page=login">login</a> or <a href="?page=register">register</a> to checkout
        </div>
    <?php else: 
        // Get customer details for pre-fill
        $customer = $db->find('customers', $_SESSION['customer_id']);
    ?>
        <?php if (isset($checkoutError)): ?>
            <div class="alert" style="background: #f8d7da; color: #721c24; border-left-color: #dc3545;">
                ❌ <?= htmlspecialchars($checkoutError) ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-form">
            <h3 class="mb-20">Your Information</h3>
            
            <form method="POST" action="?action=place-order">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($customer['name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($customer['email']) ?>" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" required value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Delivery Address *</label>
                    <textarea name="address" rows="3" required><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Order Notes (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Any special instructions?"></textarea>
                </div>
                
                <hr style="margin: 30px 0;">
                
                <h3 class="mb-20">Order Summary</h3>
                
                <?php
                $cartTotal = 0;
                foreach ($_SESSION['cart'] as $pid => $qty):
                    $product = $db->find('products', $pid);
                    if (!$product) continue;
                    $itemTotal = $product['price'] * $qty;
                    $cartTotal += $itemTotal;
                ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span><?= htmlspecialchars($product['name']) ?> × <?= $qty ?></span>
                        <span>KES <?= number_format($itemTotal) ?></span>
                    </div>
                <?php endforeach; ?>
                
                <hr style="margin: 20px 0;">
                
                <div style="display: flex; justify-content: space-between; font-size: 20px; font-weight: 700;">
                    <span>Total:</span>
                    <span style="color: #e67e22;">KES <?= number_format($cartTotal) ?></span>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 30px; padding: 15px; font-size: 16px;">
                    Place Order
                </button>
                
                <a href="shop.php?page=cart" class="btn" style="width: 100%; text-align: center; margin-top: 10px;">← Back to Cart</a>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'confirmation'): ?>
    <!-- ORDER CONFIRMATION -->
    <?php if (!isset($_SESSION['last_order'])): ?>
        <div class="alert alert-info">No recent order found. <a href="shop.php">Continue shopping</a></div>
    <?php else: 
        $order = $_SESSION['last_order'];
    ?>
        <div class="empty-state" style="background: white;">
            <div style="font-size: 80px; color: #2ecc71; margin-bottom: 20px;">✅</div>
            <h2>Order Placed Successfully!</h2>
            <p style="color: #666; margin: 20px 0;">Thank you for your order. We'll process it shortly.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px auto; max-width: 500px; text-align: left;">
                <h3 style="margin-bottom: 15px;">Order Details</h3>
                <p><strong>Order Number:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                <p><strong>Total:</strong> KES <?= number_format($order['total']) ?></p>
                <p><strong>Status:</strong> <span style="color: #e67e22;">Pending</span></p>
                <p><strong>Payment:</strong> <span style="color: #e74c3c;">Unpaid</span></p>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px auto; max-width: 500px;">
                <strong>📱 Next Steps:</strong><br>
                You'll receive an M-PESA payment request shortly (coming in Week 4).<br>
                For now, your order is confirmed and will be processed!
            </div>
            
            <a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Continue Shopping</a>
        </div>
    <?php endif; ?>

<?php endif; ?>