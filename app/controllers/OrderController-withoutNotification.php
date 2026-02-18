<?php
/**
 * OrderController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles all order-related operations:
 * - Order creation (from storefront)
 * - Order listing and filtering
 * - Order status updates
 * - Order details with items
 * - Order statistics
 * 
 * @author WrightCommerce Team
 * @version 1.1 - FIXED: search condition bug, date parameter names aligned with React
 */

require_once __DIR__ . '/BaseController.php';

class OrderController extends BaseController {
    
    /**
     * GET /api/v1/orders
     * List all orders for authenticated user's business
     * 
     * Query Parameters:
     * - status: Filter by order status
     * - payment_status: Filter by payment status
     * - customer_id: Filter by customer
     * - date_from: Filter from date (matches React frontend)
     * - date_to: Filter to date (matches React frontend)
     * - page: Page number
     * - per_page: Items per page
     */
    public function index() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        // Get filters
        $status = $this->input('status');
        $paymentStatus = $this->input('payment_status');
        $customerId = $this->input('customer_id');
        $dateFrom = $this->input('date_from'); // ✅ Matches React: ?date_from=2026-01-01
        $dateTo = $this->input('date_to');     // ✅ Matches React: ?date_to=2026-12-31

        // Build base conditions for simple path
        $conditions = ['business_id' => $businessId];
        
        if ($status) {
            $conditions['status'] = $status;
        }
        if ($paymentStatus) {
            $conditions['payment_status'] = $paymentStatus;
        }
        if ($customerId) {
            $conditions['customer_id'] = $customerId;
        }
        // ✅ NOTE: 'search' is NOT added here - it's not a column,
        // search has its own dedicated route: GET /api/v1/orders/search

        // For date filters, use custom SQL
        if ($dateFrom || $dateTo) {
            $whereClauses = ["business_id = ?"];
            $params = [$businessId];

            if ($status) {
                $whereClauses[] = "status = ?";
                $params[] = $status;
            }
            if ($paymentStatus) {
                $whereClauses[] = "payment_status = ?";
                $params[] = $paymentStatus;
            }
            if ($customerId) {
                $whereClauses[] = "customer_id = ?";
                $params[] = $customerId;
            }
            if ($dateFrom) {
                $whereClauses[] = "DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $whereClauses[] = "DATE(created_at) <= ?";
                $params[] = $dateTo;
            }

            $whereClause = implode(' AND ', $whereClauses);

            // Get total count
            $countSql = "SELECT COUNT(*) as count FROM orders WHERE {$whereClause}";
            $countResult = $this->db->query($countSql, $params);
            $total = $countResult[0]['count'];

            // Get orders
            $sql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            $orders = $this->db->query($sql, $params);

        } else {
            // Simple query without date filters
            $total = $this->db->count('orders', $conditions);
            $orders = $this->db->findAll(
                'orders',
                $conditions,
                'created_at DESC',
                $pagination['limit'],
                $pagination['offset']
            );
        }
        
        // Enrich with customer info and item count
        foreach ($orders as &$order) {
            $customer = $this->db->find('customers', $order['customer_id']);
            $order['customer_name'] = $customer['name'] ?? 'N/A';
            $order['customer_email'] = $customer['email'] ?? 'N/A';
            $order['customer_phone'] = $customer['phone'] ?? 'N/A';
            
            // Get items
            $items = $this->db->findAll('order_items', ['order_id' => $order['id']]);
            $order['items'] = $items;
            $order['item_count'] = count($items);
        }
        
        return $this->paginate($orders, $total, $pagination);
    }
    
    /**
     * GET /api/v1/orders/{id}
     * Get single order with full details (items, customer, payments)
     */
    public function show($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $order = $this->db->find('orders', $id);
        
        if (!$order) {
            return $this->notFound('Order not found');
        }
        
        // Get customer details
        $customer = $this->db->find('customers', $order['customer_id']);
        $order['customer_name'] = $customer['name'] ?? 'N/A';
        $order['customer_email'] = $customer['email'] ?? 'N/A';
        $order['customer_phone'] = $customer['phone'] ?? 'N/A';
        $order['shipping_address'] = $customer['address'] ?? '';
        
        // Get order items with product details
        $items = $this->db->findAll('order_items', ['order_id' => $id]);
        foreach ($items as &$item) {
            $product = $this->db->find('products', $item['product_id']);
            if ($product) {
                $item['image'] = $product['image'];
            }
        }
        $order['items'] = $items;
        
        // Get payments
        $payments = $this->db->findAll('payments', ['order_id' => $id]);
        $order['payments'] = $payments;
        if (!empty($payments)) {
            $order['payment_method'] = $payments[0]['payment_method'] ?? 'N/A';
        }
        
        return $this->success($order, 'Order retrieved successfully');
    }
    
    /**
     * POST /api/v1/orders
     * Create new order (from storefront)
     * 
     * Required:
     * - customer: {name, email, phone, address}
     * - items: [{product_id, quantity}]
     * 
     * Optional:
     * - notes: Order notes
     */
    public function store() {
        // Validate input
        $this->validate([
            'customer.name' => 'required|min:3',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|phone'
        ]);
        
        $customerData = $this->input('customer');
        $items = $this->input('items', []);
        $notes = $this->input('notes', '');
        
        if (empty($items)) {
            return $this->error('Order must have at least one item', 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Create or get customer
            $customer = $this->db->find('customers', ['email' => $customerData['email']]);
            
            if (!$customer) {
                // Create new customer
                $customerId = $this->db->insert('customers', [
                    'business_id' => 1,
                    'name' => $this->sanitize($customerData['name']),
                    'email' => $customerData['email'],
                    'phone' => $this->formatPhone($customerData['phone']),
                    'address' => $this->sanitize($customerData['address'] ?? '')
                ]);
            } else {
                $customerId = $customer['id'];
                
                // Update customer info if needed
                $this->db->update('customers', $customerId, [
                    'name' => $this->sanitize($customerData['name']),
                    'phone' => $this->formatPhone($customerData['phone']),
                    'address' => $this->sanitize($customerData['address'] ?? '')
                ]);
            }
            
            // Calculate total and get business_id from first product
            $total = 0;
            $businessId = null;
            $orderItems = [];
            
            foreach ($items as $item) {
                $product = $this->db->find('products', $item['product_id']);
                
                if (!$product) {
                    throw new Exception("Product {$item['product_id']} not found");
                }
                
                if (!$businessId) {
                    $businessId = $product['business_id'];
                }
                
                $quantity = (int) $item['quantity'];
                $itemTotal = $product['price'] * $quantity;
                $total += $itemTotal;
                
                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $product['price'],
                    'total' => $itemTotal
                ];
            }
            
            // Generate order number
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Create order
            $orderId = $this->db->insert('orders', [
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $this->sanitize($notes)
            ]);
            
            // Create order items and update stock
            foreach ($orderItems as $item) {
                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product']['id'],
                    'product_name' => $item['product']['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ]);
                
                // Update product stock
                $newStock = $item['product']['stock'] - $item['quantity'];
                $this->db->update('products', $item['product']['id'], [
                    'stock' => max(0, $newStock)
                ]);
            }
            
            $this->db->commit();
            
            $this->log("Order created: {$orderNumber}, Total: KES {$total}, Customer: {$customerData['email']}");
            
            // Get complete order details
            $order = $this->db->find('orders', $orderId);
            $order['items'] = $this->db->findAll('order_items', ['order_id' => $orderId]);
            $order['customer'] = $this->db->find('customers', $customerId);
            
            return $this->created($order, 'Order placed successfully!');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Order creation failed: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to create order: ' . $e->getMessage());
        }
    }
    
    /**
     * PATCH /api/v1/orders/{id}/status
     * Update order status
     * 
     * Body: {status: 'pending|processing|shipped|completed|cancelled'}
     */
    public function updateStatus($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $this->validate([
            'status' => 'required|in:pending,processing,completed,cancelled,shipped'
        ]);
        
        $status = $this->input('status');
        
        try {
            $this->db->update('orders', $id, ['status' => $status]);
            
            $order = $this->db->find('orders', $id);
            
            $this->log("Order {$order['order_number']} status updated to: {$status}");
            
            return $this->success($order, 'Order status updated successfully');
            
        } catch (Exception $e) {
            return $this->serverError('Failed to update order status');
        }
    }
    
    /**
     * POST /api/v1/orders/{id}/cancel
     * Cancel an order
     */
    public function cancel($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $order = $this->db->find('orders', $id);
        
        if (!$order) {
            return $this->notFound('Order not found');
        }
        
        if ($order['status'] === 'completed') {
            return $this->error('Cannot cancel completed order', 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Update order status
            $this->db->update('orders', $id, ['status' => 'cancelled']);
            
            // Restore product stock
            $items = $this->db->findAll('order_items', ['order_id' => $id]);
            foreach ($items as $item) {
                $product = $this->db->find('products', $item['product_id']);
                if ($product) {
                    $newStock = $product['stock'] + $item['quantity'];
                    $this->db->update('products', $item['product_id'], [
                        'stock' => $newStock
                    ]);
                }
            }
            
            $this->db->commit();
            
            $this->log("Order {$order['order_number']} cancelled");
            
            return $this->success(null, 'Order cancelled successfully');
            
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->serverError('Failed to cancel order');
        }
    }

    /**
     * GET /api/v1/orders/search
     * Search orders by order number or customer name/email
     * Route: GET /api/v1/orders/search?q=ORD-001
     */
    public function search() {
        $this->requireAuth();
        
        $query = $this->input('q');
        $limit = min(50, (int) $this->input('limit', 10));
        
        if (!$query) {
            return $this->error('Search query is required', 400);
        }
        
        $businessId = $this->getBusinessId();
        
        $sql = "SELECT o.* 
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.business_id = ? 
                AND (
                    o.order_number LIKE ? 
                    OR c.name LIKE ? 
                    OR c.email LIKE ?
                )
                ORDER BY o.created_at DESC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        $orders = $this->db->query($sql, [
            $businessId,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $limit
        ]);
        
        // Enrich with customer info
        foreach ($orders as &$order) {
            if ($order['customer_id']) {
                $customer = $this->db->find('customers', $order['customer_id']);
                $order['customer_name'] = $customer['name'] ?? 'N/A';
                $order['customer_email'] = $customer['email'] ?? 'N/A';
            }
            $items = $this->db->findAll('order_items', ['order_id' => $order['id']]);
            $order['items'] = $items;
        }
        
        return $this->success([
            'items' => $orders,
            'count' => count($orders)
        ], "Found " . count($orders) . " orders");
    }
    
    /**
     * GET /api/v1/orders/stats
     * Get order statistics for business
     */
    public function stats() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $stats = [
            'total_orders' => $this->db->count('orders', ['business_id' => $businessId]),
            'pending' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'pending']),
            'processing' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'processing']),
            'completed' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'completed']),
            'cancelled' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'cancelled']),
        ];
        
        // Get revenue stats
        $revenueSql = "SELECT 
                        SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid_revenue,
                        SUM(CASE WHEN payment_status = 'unpaid' THEN total ELSE 0 END) as unpaid_revenue,
                        SUM(total) as total_revenue
                       FROM orders 
                       WHERE business_id = ?";
        
        $revenueResult = $this->db->query($revenueSql, [$businessId]);
        $stats['paid_revenue'] = (float) ($revenueResult[0]['paid_revenue'] ?? 0);
        $stats['unpaid_revenue'] = (float) ($revenueResult[0]['unpaid_revenue'] ?? 0);
        $stats['total_revenue'] = (float) ($revenueResult[0]['total_revenue'] ?? 0);
        
        if ($stats['total_orders'] > 0) {
            $stats['average_order_value'] = $stats['total_revenue'] / $stats['total_orders'];
        } else {
            $stats['average_order_value'] = 0;
        }
        
        return $this->success($stats, 'Order statistics retrieved successfully');
    }
}