<?php
/**
 * ProductController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles all product-related operations:
 * - CRUD operations (Create, Read, Update, Delete)
 * - Image upload and management
 * - Search and filtering
 * - Low stock alerts
 * - Inventory tracking
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class ProductController extends BaseController {
    
    /**
     * GET /api/v1/products
     * List all products for authenticated user's business
     * 
     * Query Parameters:
     * - status: Filter by status (active, inactive, deleted)
     * - search: Search in name, description, SKU
     * - min_price: Minimum price filter
     * - max_price: Maximum price filter
     * - low_stock: Show only low stock items (stock < 10)
     * - page: Page number for pagination
     * - per_page: Items per page (default 20, max 100)
     */
    public function index() {
        // Require authentication
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        // Get pagination parameters
        $pagination = $this->getPagination(20);
        
        // Get filter parameters
        $status = $this->input('status', 'active');
        $search = $this->input('search');
        $minPrice = $this->input('min_price');
        $maxPrice = $this->input('max_price');
        $lowStock = $this->input('low_stock');
        
        // Build base conditions
        $conditions = ['business_id' => $businessId];
        
        // Add status filter
        if ($status) {
            $conditions['status'] = $status;
        }
        
        // For simple filters, use findAll
        if (!$search && !$minPrice && !$maxPrice && !$lowStock) {
            $total = $this->db->count('products', $conditions);
            $products = $this->db->findAll(
                'products',
                $conditions,
                'created_at DESC',
                $pagination['limit'],
                $pagination['offset']
            );
        } else {
            // Use custom query for complex filters
            $whereClauses = ["business_id = ?"];
            $params = [$businessId];
            
            if ($status) {
                $whereClauses[] = "status = ?";
                $params[] = $status;
            }
            
            if ($search) {
                $whereClauses[] = "(name LIKE ? OR description LIKE ? OR sku LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($minPrice) {
                $whereClauses[] = "price >= ?";
                $params[] = $minPrice;
            }
            
            if ($maxPrice) {
                $whereClauses[] = "price <= ?";
                $params[] = $maxPrice;
            }
            
            if ($lowStock) {
                $whereClauses[] = "stock < 10";
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as count FROM products WHERE {$whereClause}";
            $countResult = $this->db->query($countSql, $params);
            $total = $countResult[0]['count'];
            
            // Get products
            $sql = "SELECT * FROM products WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            $products = $this->db->query($sql, $params);
        }
        
        // Return paginated response
        return $this->paginate($products, $total, $pagination);
    }
    
    /**
     * GET /api/v1/products/{id}
     * Get single product by ID
     */
    public function show($id) {
        $this->requireAuth();
        
        // Verify ownership
        $this->requireOwnership('products', $id);
        
        $product = $this->db->find('products', $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        return $this->success($product, 'Product retrieved successfully');
    }
    
    /**
     * POST /api/v1/products
     * Create new product
     * 
     * Required fields:
     * - name: Product name
     * - price: Selling price
     * 
     * Optional fields:
     * - description: Product description
     * - cost_price: Cost price (for profit calculation)
     * - stock: Initial stock quantity (default 0)
     * - sku: Stock Keeping Unit / Product code
     * - image: Product image file (multipart/form-data)
     * - category: Product category
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
        
        // Get input data
        $data = [
            'business_id' => $this->getBusinessId(),
            'name' => $this->sanitize($this->input('name')),
            'description' => $this->sanitize($this->input('description', '')),
            'price' => (float) $this->input('price'),
            'cost_price' => (float) $this->input('cost_price', 0),
            'stock' => (int) $this->input('stock', 0),
            'sku' => $this->input('sku'),
            'category' => $this->sanitize($this->input('category', '')),
            'status' => 'active'
        ];
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->uploadFile(
                'image',
                'products',
                ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                5242880 // 5MB max
            );
            
            if ($imagePath) {
                $data['image'] = $imagePath;
            } else {
                return $this->error('Failed to upload image', 400, $this->errors);
            }
        }
        
        try {
            $productId = $this->db->insert('products', $data);
            
            // Log activity
            $this->log("Product created: ID {$productId}, Name: {$data['name']}, By User: {$this->getUserId()}");
            
            // Get the created product
            $product = $this->db->find('products', $productId);
            
            return $this->created($product, 'Product created successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to create product: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to create product. Please try again.');
        }
    }
    
    /**
     * PUT /api/v1/products/{id}
     * Update existing product
     * 
     * All fields are optional - only send what you want to update
     */
    public function update($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        // Get existing product
        $existingProduct = $this->db->find('products', $id);
        
        if (!$existingProduct) {
            return $this->notFound('Product not found');
        }
        
        // Validate input (all optional)
        $this->validate([
            'name' => 'min:3|max:255',
            'price' => 'numeric|min:0',
            'cost_price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
            'status' => 'in:active,inactive,deleted'
        ]);
        
        // Get only the fields that were sent
        $data = $this->only([
            'name',
            'description',
            'price',
            'cost_price',
            'stock',
            'sku',
            'category',
            'status'
        ]);
        
        // Remove empty values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Sanitize text fields if present
        if (isset($data['name'])) {
            $data['name'] = $this->sanitize($data['name']);
        }
        if (isset($data['description'])) {
            $data['description'] = $this->sanitize($data['description']);
        }
        if (isset($data['category'])) {
            $data['category'] = $this->sanitize($data['category']);
        }
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->uploadFile(
                'image',
                'products',
                ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                5242880 // 5MB max
            );
            
            if ($imagePath) {
                // Delete old image if exists
                if ($existingProduct['image']) {
                    $this->deleteFile($existingProduct['image']);
                }
                $data['image'] = $imagePath;
            }
        }
        
        // If no data to update
        if (empty($data)) {
            return $this->error('No data provided for update', 400);
        }
        
        try {
            $this->db->update('products', $id, $data);
            
            $this->log("Product updated: ID {$id}, By User: {$this->getUserId()}");
            
            // Get updated product
            $product = $this->db->find('products', $id);
            
            return $this->success($product, 'Product updated successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to update product {$id}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to update product. Please try again.');
        }
    }
    
    /**
     * DELETE /api/v1/products/{id}
     * Delete product (soft delete - sets status to 'deleted')
     */
    public function destroy($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        $product = $this->db->find('products', $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        try {
            // Soft delete - just update status
            $this->db->update('products', $id, ['status' => 'deleted']);
            
            $this->log("Product soft deleted: ID {$id}, Name: {$product['name']}, By User: {$this->getUserId()}");
            
            return $this->success(null, 'Product deleted successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to delete product {$id}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to delete product. Please try again.');
        }
    }
    
    /**
     * POST /api/v1/products/{id}/restore
     * Restore a soft-deleted product
     */
    public function restore($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        $product = $this->db->find('products', $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        if ($product['status'] !== 'deleted') {
            return $this->error('Product is not deleted', 400);
        }
        
        try {
            $this->db->update('products', $id, ['status' => 'active']);
            
            $this->log("Product restored: ID {$id}, Name: {$product['name']}, By User: {$this->getUserId()}");
            
            return $this->success(null, 'Product restored successfully');
            
        } catch (Exception $e) {
            return $this->serverError('Failed to restore product');
        }
    }
    
    /**
     * DELETE /api/v1/products/{id}/permanent
     * Permanently delete product from database
     * WARNING: This cannot be undone!
     */
    public function permanentDelete($id) {
        $this->requireAuth();
        $this->requireRole('owner'); // Only owner can permanently delete
        $this->requireOwnership('products', $id);
        
        $product = $this->db->find('products', $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        try {
            // Delete product image if exists
            if ($product['image']) {
                $this->deleteFile($product['image']);
            }
            
            // Permanently delete from database
            $this->db->delete('products', $id);
            
            $this->log("Product permanently deleted: ID {$id}, Name: {$product['name']}, By User: {$this->getUserId()}", 'warning');
            
            return $this->success(null, 'Product permanently deleted');
            
        } catch (Exception $e) {
            $this->log("Failed to permanently delete product {$id}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to delete product');
        }
    }
    
    /**
     * GET /api/v1/products/search
     * Search products by name, description, or SKU
     * 
     * Query Parameters:
     * - q: Search query (required)
     * - limit: Max results to return (default 10)
     */
    public function search() {
        $this->requireAuth();
        
        $query = $this->input('q');
        $limit = min(50, (int) $this->input('limit', 10));
        
        if (!$query) {
            return $this->error('Search query is required', 400);
        }
        
        $businessId = $this->getBusinessId();
        
        $sql = "SELECT * FROM products 
                WHERE business_id = ? 
                AND status = 'active'
                AND (name LIKE ? OR description LIKE ? OR sku LIKE ?)
                ORDER BY name ASC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        $products = $this->db->query($sql, [
            $businessId,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $limit
        ]);
        
        return $this->success($products, "Found " . count($products) . " products");
    }
    
    /**
     * GET /api/v1/products/low-stock
     * Get products with low stock (stock < 10)
     * 
     * Query Parameters:
     * - threshold: Stock threshold (default 10)
     */
    public function lowStock() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $threshold = (int) $this->input('threshold', 10);
        
        $sql = "SELECT * FROM products 
                WHERE business_id = ? 
                AND status = 'active'
                AND stock < ?
                ORDER BY stock ASC";
        
        $products = $this->db->query($sql, [$businessId, $threshold]);
        
        return $this->success([
            'products' => $products,
            'count' => count($products),
            'threshold' => $threshold
        ], "Found " . count($products) . " low stock items");
    }
    
    /**
     * GET /api/v1/products/out-of-stock
     * Get products that are completely out of stock
     */
    public function outOfStock() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $sql = "SELECT * FROM products 
                WHERE business_id = ? 
                AND status = 'active'
                AND stock = 0
                ORDER BY name ASC";
        
        $products = $this->db->query($sql, [$businessId]);
        
        return $this->success([
            'products' => $products,
            'count' => count($products)
        ], "Found " . count($products) . " out of stock items");
    }
    
    /**
     * POST /api/v1/products/{id}/adjust-stock
     * Adjust product stock (add or subtract)
     * 
     * Body:
     * - adjustment: Number to add (positive) or subtract (negative)
     * - reason: Reason for adjustment (optional)
     */
    public function adjustStock($id) {
        $this->requireAuth();
        $this->requireOwnership('products', $id);
        
        $this->validate([
            'adjustment' => 'required|integer'
        ]);
        
        $product = $this->db->find('products', $id);
        
        if (!$product) {
            return $this->notFound('Product not found');
        }
        
        $adjustment = (int) $this->input('adjustment');
        $reason = $this->sanitize($this->input('reason', 'Manual adjustment'));
        
        $newStock = $product['stock'] + $adjustment;
        
        // Don't allow negative stock
        if ($newStock < 0) {
            return $this->error('Adjustment would result in negative stock', 400);
        }
        
        try {
            $this->db->update('products', $id, ['stock' => $newStock]);
            
            $this->log("Stock adjusted for product {$id}: {$product['stock']} → {$newStock} (Adjustment: {$adjustment}). Reason: {$reason}");
            
            return $this->success([
                'old_stock' => $product['stock'],
                'new_stock' => $newStock,
                'adjustment' => $adjustment
            ], 'Stock adjusted successfully');
            
        } catch (Exception $e) {
            return $this->serverError('Failed to adjust stock');
        }
    }
    
    /**
     * GET /api/v1/products/categories
     * Get all unique product categories for the business
     */
    public function categories() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $sql = "SELECT DISTINCT category FROM products 
                WHERE business_id = ? 
                AND category IS NOT NULL 
                AND category != ''
                AND status = 'active'
                ORDER BY category ASC";
        
        $results = $this->db->query($sql, [$businessId]);
        
        // Extract just the category names
        $categories = array_map(function($row) {
            return $row['category'];
        }, $results);
        
        return $this->success($categories, 'Categories retrieved successfully');
    }
    
    /**
     * GET /api/v1/products/stats
     * Get product statistics for the business
     */
    public function stats() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $stats = [
            'total_products' => $this->db->count('products', [
                'business_id' => $businessId,
                'status' => 'active'
            ]),
            'out_of_stock' => $this->db->count('products', [
                'business_id' => $businessId,
                'status' => 'active',
                'stock' => 0
            ]),
            'inactive_products' => $this->db->count('products', [
                'business_id' => $businessId,
                'status' => 'inactive'
            ])
        ];
        
        // Get low stock count (stock < 10)
        $lowStockSql = "SELECT COUNT(*) as count FROM products 
                        WHERE business_id = ? AND status = 'active' AND stock < 10 AND stock > 0";
        $lowStockResult = $this->db->query($lowStockSql, [$businessId]);
        $stats['low_stock'] = $lowStockResult[0]['count'];
        
        // Get total inventory value
        $valueSql = "SELECT SUM(stock * price) as total_value, SUM(stock * cost_price) as total_cost 
                     FROM products 
                     WHERE business_id = ? AND status = 'active'";
        $valueResult = $this->db->query($valueSql, [$businessId]);
        $stats['total_inventory_value'] = (float) ($valueResult[0]['total_value'] ?? 0);
        $stats['total_inventory_cost'] = (float) ($valueResult[0]['total_cost'] ?? 0);
        
        // Calculate potential profit
        $stats['potential_profit'] = $stats['total_inventory_value'] - $stats['total_inventory_cost'];
        
        return $this->success($stats, 'Product statistics retrieved successfully');
    }
    
    /**
     * POST /api/v1/products/bulk-update-status
     * Bulk update status for multiple products
     * 
     * Body:
     * - product_ids: Array of product IDs
     * - status: New status (active, inactive, deleted)
     */
    public function bulkUpdateStatus() {
        $this->requireAuth();
        
        $this->validate([
            'status' => 'required|in:active,inactive,deleted'
        ]);
        
        $productIds = $this->input('product_ids', []);
        $status = $this->input('status');
        
        if (empty($productIds) || !is_array($productIds)) {
            return $this->error('Product IDs are required and must be an array', 400);
        }
        
        $businessId = $this->getBusinessId();
        $updated = 0;
        
        try {
            $this->db->beginTransaction();
            
            foreach ($productIds as $productId) {
                // Verify ownership
                $product = $this->db->find('products', $productId);
                if ($product && $product['business_id'] == $businessId) {
                    $this->db->update('products', $productId, ['status' => $status]);
                    $updated++;
                }
            }
            
            $this->db->commit();
            
            $this->log("Bulk status update: {$updated} products set to '{$status}' by user {$this->getUserId()}");
            
            return $this->success([
                'updated_count' => $updated,
                'status' => $status
            ], "{$updated} products updated successfully");
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Bulk update failed: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to update products');
        }
    }
}