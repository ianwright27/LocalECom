<?php
/**
 * Admin Pages - All in One File
 * Save this as: admin-pages.php in the public folder
 * Then include it in admin.php
 */

// This file is included by admin.php and has access to $db, $businessId, $page, $productId

if ($page === 'dashboard'): ?>
    <!-- DASHBOARD -->
    <div class="stats">
        <div class="stat-card blue">
            <h3><?= number_format($stats['total']) ?></h3>
            <p>Total Products</p>
        </div>
        <div class="stat-card green">
            <h3>KES <?= number_format($stats['value']) ?></h3>
            <p>Inventory Value</p>
        </div>
        <div class="stat-card orange">
            <h3><?= $stats['low_stock'] ?></h3>
            <p>Low Stock Items</p>
        </div>
        <div class="stat-card red">
            <h3><?= $stats['out_of_stock'] ?></h3>
            <p>Out of Stock</p>
        </div>
    </div>

    <div class="content-box">
        <h2 class="mb-20">Recent Products</h2>
        <?php
        $recentProducts = $db->findAll('products', ['business_id' => $businessId], 'created_at DESC', 10);
        
        if (empty($recentProducts)):
        ?>
            <p style="text-align: center; padding: 40px; color: #999;">
                No products yet. <a href="?page=add-product">Add your first product</a> or <a href="seed-products.php">generate sample data</a>
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentProducts as $product): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                            <small style="color: #999;"><?= htmlspecialchars($product['sku'] ?? 'No SKU') ?></small>
                        </td>
                        <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                        <td>KES <?= number_format($product['price']) ?></td>
                        <td>
                            <?php if ($product['stock'] == 0): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ($product['stock'] < 10): ?>
                                <span class="badge badge-warning"><?= $product['stock'] ?> left</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= $product['stock'] ?> in stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $product['status'] === 'active' ? 'success' : 'warning' ?>">
                                <?= ucfirst($product['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=edit-product&id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($page === 'products'): ?>
    <!-- ALL PRODUCTS -->
    <div class="content-box">
        <div class="text-right mb-20">
            <a href="?page=add-product" class="btn btn-success">➕ Add New Product</a>
        </div>
        
        <?php
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        $sql = "SELECT * FROM products WHERE business_id = ?";
        $params = [$businessId];
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR sku LIKE ?)";
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
        
        <!-- Search Form -->
        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="products">
            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" style="padding: 10px; width: 300px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search || $category): ?>
                <a href="?page=products" class="btn">Clear</a>
            <?php endif; ?>
        </form>
        
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Cost</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?php if (!empty($product['image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($product['image']) ?>" 
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 20px;">
                                📦
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                        <small style="color: #999;"><?= htmlspecialchars($product['sku'] ?? 'No SKU') ?></small>
                    </td>
                    <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                    <td>KES <?= number_format($product['price']) ?></td>
                    <td>KES <?= number_format($product['cost_price']) ?></td>
                    <td>
                        <?php if ($product['stock'] == 0): ?>
                            <span class="badge badge-danger">0</span>
                        <?php elseif ($product['stock'] < 10): ?>
                            <span class="badge badge-warning"><?= $product['stock'] ?></span>
                        <?php else: ?>
                            <?= $product['stock'] ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $product['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst($product['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="?page=edit-product&id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                        <a href="?page=products&action=delete&id=<?= $product['id'] ?>" 
                           class="btn btn-danger btn-sm" 
                           onclick="return confirm('Delete this product?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php
    // Handle delete
    if ($action === 'delete' && $productId) {
        $db->update('products', $productId, ['status' => 'deleted']);
        echo "<script>alert('Product deleted!'); window.location='?page=products';</script>";
    }
    ?>

<?php elseif ($page === 'low-stock'): ?>
    <!-- LOW STOCK ALERTS -->
    <div class="content-box">
        <?php
        $lowStockProducts = $db->query(
            "SELECT * FROM products WHERE business_id = ? AND stock > 0 AND stock < 10 AND status = 'active' ORDER BY stock ASC",
            [$businessId]
        );
        ?>
        
        <h2 class="mb-20">⚠️ Low Stock Alerts (< 10 units)</h2>
        
        <?php if (empty($lowStockProducts)): ?>
            <p style="text-align: center; padding: 40px; color: #999;">✅ No low stock items. Great job!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current Stock</th>
                        <th>Price</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockProducts as $product): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                            <small style="color: #999;"><?= htmlspecialchars($product['sku'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="badge badge-warning"><?= $product['stock'] ?> units left</span>
                        </td>
                        <td>KES <?= number_format($product['price']) ?></td>
                        <td><?= htmlspecialchars($product['category'] ?? '-') ?></td>
                        <td>
                            <a href="?page=edit-product&id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">Restock</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($page === 'categories'): ?>
    <!-- CATEGORIES -->
    <div class="content-box">
        <?php
        $categoriesResult = $db->query(
            "SELECT category, COUNT(*) as count, SUM(stock * price) as value 
             FROM products 
             WHERE business_id = ? AND category IS NOT NULL AND category != '' AND status = 'active'
             GROUP BY category 
             ORDER BY count DESC",
            [$businessId]
        );
        ?>
        
        <h2 class="mb-20">Product Categories</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Products</th>
                    <th>Total Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoriesResult as $cat): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cat['category']) ?></strong></td>
                    <td><?= $cat['count'] ?> products</td>
                    <td>KES <?= number_format($cat['value']) ?></td>
                    <td>
                        <a href="?page=products&category=<?= urlencode($cat['category']) ?>" class="btn btn-primary btn-sm">View Products</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'add-product' || $page === 'edit-product'): ?>
    <!-- ADD/EDIT PRODUCT FORM -->
    <?php
    $product = null;
    $isEdit = $page === 'edit-product';
    
    if ($isEdit && $productId) {
        $product = $db->find('products', $productId);
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Debug: Log that form was submitted
        error_log("Product form submitted. Files: " . print_r($_FILES, true));
        
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? '',
            'price' => (float) $_POST['price'],
            'cost_price' => (float) ($_POST['cost_price'] ?? 0),
            'stock' => (int) $_POST['stock'],
            'sku' => $_POST['sku'] ?? null,
            'category' => $_POST['category'] ?? '',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5242880; // 5MB
            
            $file = $_FILES['image'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (in_array($mimeType, $allowedTypes) && $file['size'] <= $maxSize) {
                // Create uploads directory if it doesn't exist
                // Use $_SERVER['DOCUMENT_ROOT'] for correct path
                $publicDir = $_SERVER['DOCUMENT_ROOT'] . '/wrightcommerce/public';
                $uploadDir = $publicDir . '/uploads/products';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $destination = $uploadDir . '/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old image if editing
                    if ($isEdit && $product && !empty($product['image'])) {
                        $oldImagePath = $publicDir . '/uploads/' . $product['image'];
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $data['image'] = 'products/' . $filename;
                } else {
                    echo "<script>alert('Failed to upload image. Check folder permissions.');</script>";
                }
            } else {
                echo "<script>alert('Invalid file type or file too large (max 5MB)');</script>";
            }
        }
        
        if ($isEdit && $product) {
            $db->update('products', $productId, $data);
            echo "<script>alert('Product updated!'); window.location='?page=products';</script>";
        } else {
            $data['business_id'] = $businessId;
            $db->insert('products', $data);
            echo "<script>alert('Product created!'); window.location='?page=products';</script>";
        }
    }
    ?>
    
    <div class="content-box">
        <h2 class="mb-20"><?= $isEdit ? 'Edit Product' : 'Add New Product' ?></h2>
        
        <form method="POST" enctype="multipart/form-data" style="max-width: 800px;">
            <div style="display: grid; grid-template-columns: 1fr 300px; gap: 30px;">
                <!-- Left Column: Form Fields -->
                <div>
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Selling Price (KES) *</label>
                            <input type="number" name="price" step="0.01" required value="<?= $product['price'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Cost Price (KES)</label>
                            <input type="number" name="cost_price" step="0.01" value="<?= $product['cost_price'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Stock Quantity *</label>
                            <input type="number" name="stock" required value="<?= $product['stock'] ?? 0 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>SKU</label>
                            <input type="text" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" placeholder="e.g., Electronics">
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Image Upload -->
                <div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <div style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; background: #f9f9f9;">
                            <?php if ($isEdit && !empty($product['image'])): ?>
                                <img id="imagePreview" src="uploads/<?= htmlspecialchars($product['image']) ?>" style="max-width: 100%; max-height: 200px; margin-bottom: 10px; border-radius: 4px;">
                                <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Current Image</p>
                            <?php else: ?>
                                <img id="imagePreview" src="" style="max-width: 100%; max-height: 200px; margin-bottom: 10px; border-radius: 4px; display: none;">
                                <p id="noImageText" style="color: #999; margin-bottom: 10px;">📷 No image uploaded</p>
                            <?php endif; ?>
                            
                            <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            <button type="button" onclick="document.getElementById('imageInput').click()" class="btn btn-primary btn-sm">
                                <?= $isEdit && !empty($product['image']) ? 'Change Image' : 'Upload Image' ?>
                            </button>
                            <p style="font-size: 11px; color: #999; margin-top: 10px;">Max 5MB • JPG, PNG, GIF, WebP</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-success"><?= $isEdit ? 'Update Product' : 'Create Product' ?></button>
                <a href="?page=products" class="btn">Cancel</a>
            </div>
        </form>
    </div>
    
    <script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const noImageText = document.getElementById('noImageText');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (noImageText) noImageText.style.display = 'none';
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

<?php elseif ($page === 'orders'): ?>
    <!-- ORDERS LIST -->
    <div class="content-box">
        <h2 class="mb-20">Orders</h2>
        
        <?php
        $statusFilter = $_GET['status'] ?? '';
        
        $sql = "SELECT * FROM orders WHERE business_id = ?";
        $params = [$businessId];
        
        if ($statusFilter) {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $orders = $db->query($sql, $params);
        ?>
        
        <!-- Status Filter -->
        <div style="margin-bottom: 20px; display: flex; gap: 10px;">
            <a href="?page=orders" class="btn <?= !$statusFilter ? 'btn-primary' : '' ?>">All</a>
            <a href="?page=orders&status=pending" class="btn <?= $statusFilter === 'pending' ? 'btn-primary' : '' ?>">Pending</a>
            <a href="?page=orders&status=processing" class="btn <?= $statusFilter === 'processing' ? 'btn-primary' : '' ?>">Processing</a>
            <a href="?page=orders&status=completed" class="btn <?= $statusFilter === 'completed' ? 'btn-primary' : '' ?>">Completed</a>
            <a href="?page=orders&status=cancelled" class="btn <?= $statusFilter === 'cancelled' ? 'btn-primary' : '' ?>">Cancelled</a>
        </div>
        
        <?php if (empty($orders)): ?>
            <p style="text-align: center; padding: 40px; color: #999;">No orders yet</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $customer = $db->find('customers', $order['customer_id']);
                        $itemCount = $db->count('order_items', ['order_id' => $order['id']]);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($customer['name']) ?><br>
                                <small style="color: #999;"><?= htmlspecialchars($customer['email']) ?></small>
                            </td>
                            <td><?= $itemCount ?> item(s)</td>
                            <td><strong>KES <?= number_format($order['total']) ?></strong></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'processing' => 'warning',
                                    'shipped' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $badgeClass = 'badge-' . ($statusColors[$order['status']] ?? 'warning');
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($order['payment_status']) ?: 'Pending' ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                            <td>
                                <a href="?page=order-details&id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($page === 'order-details'): ?>
    <!-- ORDER DETAILS -->
    <?php
    $order = $db->find('orders', $productId); // Using $productId as order id
    
    if (!$order || $order['business_id'] != $businessId) {
        echo "<div class='alert alert-error'>Order not found</div>";
    } else {
        $customer = $db->find('customers', $order['customer_id']);
        $items = $db->findAll('order_items', ['order_id' => $order['id']]);
        
        // Handle status update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            $newStatus = $_POST['status'];
            $db->update('orders', $order['id'], ['status' => $newStatus]);
            echo "<script>alert('Order status updated!'); window.location='?page=order-details&id={$order['id']}';</script>";
        }
    ?>
        <div class="content-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h2>Order <?= htmlspecialchars($order['order_number']) ?></h2>
                    <p style="color: #666;">Placed on <?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?></p>
                </div>
                <a href="?page=orders" class="btn">← Back to Orders</a>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Left Column: Order Items -->
                <div>
                    <h3 class="mb-20">Order Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td>KES <?= number_format($item['price']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><strong>KES <?= number_format($item['total']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td><strong style="font-size: 18px; color: #e67e22;">KES <?= number_format($order['total']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <?php if (!empty($order['notes'])): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                            <strong>Order Notes:</strong><br>
                            <?= nl2br(htmlspecialchars($order['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column: Customer & Status -->
                <div>
                    <!-- Customer Info -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 class="mb-20">Customer Information</h3>
                        <p><strong>Name:</strong><br><?= htmlspecialchars($customer['name']) ?></p>
                        <p><strong>Email:</strong><br><?= htmlspecialchars($customer['email']) ?></p>
                        <p><strong>Phone:</strong><br><?= htmlspecialchars($customer['phone']) ?></p>
                        <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($customer['address'] ?? 'N/A')) ?></p>
                    </div>
                    
                    <!-- Update Status -->
                    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                        <h3 class="mb-20">Update Order Status</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Order Status</label>
                                <select name="status" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Payment Status</label>
                                <p>
                                    <span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($order['payment_status']) ?>
                                    </span>
                                </p>
                                <small style="color: #666;">Payment status will be updated via M-PESA in Week 4</small>
                            </div>
                            
                            <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

<?php elseif ($page === 'customers'): ?>
    <!-- CUSTOMERS LIST -->
    <div class="content-box">
        <h2 class="mb-20">Customers</h2>
        
        <?php
        $customers = $db->findAll('customers', ['business_id' => $businessId], 'created_at DESC');
        ?>
        
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): 
                    $orderCount = $db->count('orders', ['customer_id' => $customer['id']]);
                    $totalResult = $db->query("SELECT SUM(total) as total FROM orders WHERE customer_id = ?", [$customer['id']]);
                    $totalSpent = $totalResult[0]['total'] ?? 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></td>
                        <td><?= $orderCount ?> order(s)</td>
                        <td><strong>KES <?= number_format($totalSpent) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>