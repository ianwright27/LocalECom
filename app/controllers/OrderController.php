<?php
/**
 * OrderController - FINAL WITH NOTIFICATIONS + PROPER DATA FETCHING
 * Properly fetches customer and payment data for OrderDetails page
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/NotificationService.php';

class OrderController extends BaseController {
    
    private $notificationService;
    
    public function __construct() {
        parent::__construct();
        $this->notificationService = new NotificationService($this->db);
    }
    
    /**
     * POST /api/v1/orders
     * Create new order + SEND NOTIFICATION
     */
    public function store() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $this->validate([
            'customer_name' => 'required|min:2',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|phone',
            'items' => 'required|array'
        ]);
        
        $customerData = [
            'name' => $this->sanitize($this->input('customer_name')),
            'email' => $this->input('customer_email'),
            'phone' => $this->input('customer_phone'),
            'address' => $this->sanitize($this->input('customer_address', ''))
        ];
        
        $items = $this->input('items');
        $notes = $this->input('notes', '');
        $shippingAddress = $this->sanitize($this->input('shipping_address', $customerData['address'])); // ✅ Get shipping address
        
        if (empty($items)) {
            return $this->error('Order must contain at least one item', 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Get or create customer
            $customer = $this->db->find('customers', [
                'business_id' => $businessId,
                'email' => $customerData['email']
            ]);
            
            if (!$customer) {
                $customerId = $this->db->insert('customers', array_merge(
                    $customerData,
                    ['business_id' => $businessId]
                ));
            } else {
                $customerId = $customer['id'];
                $this->db->update('customers', $customerId, $customerData);
            }
            
            // Calculate total and validate stock
            $total = 0;
            $orderItems = [];
            
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = (int) $item['quantity'];
                
                $product = $this->db->find('products', $productId);
                
                if (!$product) {
                    throw new Exception("Product ID {$productId} not found");
                }
                
                if ($product['business_id'] != $businessId) {
                    throw new Exception("Product does not belong to your business");
                }
                
                if ($product['stock'] < $quantity) {
                    throw new Exception("Insufficient stock for {$product['name']}");
                }
                
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
            
            // Create order with shipping_address
            $orderId = $this->db->insert('orders', [
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address' => $shippingAddress, // ✅ Store shipping address
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
            
            $this->log("Order created: {$orderNumber}, Total: KES {$total}");
            
            // Get complete order
            $order = $this->db->find('orders', $orderId);
            $order['items'] = $this->db->findAll('order_items', ['order_id' => $orderId]);
            $order['customer'] = $this->db->find('customers', $customerId);
            
            // ✅ SEND NEW ORDER NOTIFICATION
            try {
                $this->notificationService->send(
                    $businessId,
                    'order_placed',
                    [
                        'name' => $customerData['name'],
                        'email' => $customerData['email'],
                        'phone' => $customerData['phone']
                    ],
                    [
                        'name' => $customerData['name'],
                        'order_number' => $orderNumber,
                        'total' => number_format($total),
                        'items_count' => count($orderItems)
                    ]
                ); 
            } catch (Exception $e) {
                $this->log("Notification failed: " . $e->getMessage(), 'warning');
            }
            
            return $this->created($order, 'Order placed successfully!');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Order creation failed: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to create order: ' . $e->getMessage());
        }
    }
    
    /**
     * POST /api/v1/orders/{id}/status
     * Update order status + SEND NOTIFICATION
     */
    public function updateStatus($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $this->validate([
            'status' => 'required|in:pending,processing,completed,cancelled,shipped'
        ]);
        
        $newStatus = $this->input('status');
        $order = $this->db->find('orders', $id);
        $oldStatus = $order['status'];
        
        try {
            $this->db->update('orders', $id, ['status' => $newStatus]);
            
            $order = $this->db->find('orders', $id);
            $order['customer'] = $this->db->find('customers', $order['customer_id']); 
            $order['items'] = $this->db->findAll('order_items', ['order_id' => $id]);
            
            $this->log("Order {$order['order_number']} status: {$oldStatus} → {$newStatus}");
            
            // ✅ SEND STATUS UPDATE NOTIFICATION
            try {
                $business = $this->db->find('businesses', $order['business_id']);
                
                $notificationEventName = ''; 
                switch ($newStatus) {
                    case 'pending':
                        $notificationEventName = 'order_placed';
                        break;
                    case 'processing':
                        $notificationEventName = 'order_processing';
                        break;
                    case 'shipped':
                        $notificationEventName = 'order_shipped';
                        break;
                    case 'completed':
                        $notificationEventName = 'order_completed';
                        break;
                    case 'cancelled':
                        $notificationEventName = 'order_cancelled';
                        break;
                    default:
                        $notificationEventName = 'order_status_updated';
                }

                $this->notificationService->send(
                    $business['id'],
                    $notificationEventName,
                    [
                        'name' => $order['customer']['name'],
                        'email' => $order['customer']['email'],
                        'phone' => $order['customer']['phone']
                    ],
                    [
                        'name' => $order['customer']['name'],
                        'order_number' => $order['order_number'],
                        'total' => number_format($order['total']),
                        'items_count' => count($order['items'])
                    ]
                );
            } catch (Exception $e) {
                $this->log("Notification failed: " . $e->getMessage(), 'warning');
            }
            
            return $this->success($order, 'Order status updated successfully');
            
        } catch (Exception $e) {
            return $this->serverError('Failed to update order status');
        }
    }
    
    public function index() {
        $this->requireAuth();
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        $status = $this->input('status');
        $paymentStatus = $this->input('payment_status');
        $dateFrom = $this->input('date_from');
        $dateTo = $this->input('date_to');
        
        $conditions = ['business_id' => $businessId];
        if ($status) $conditions['status'] = $status;
        if ($paymentStatus) $conditions['payment_status'] = $paymentStatus;
        
        if (!$dateFrom && !$dateTo) {
            $total = $this->db->count('orders', $conditions);
            $orders = $this->db->findAll('orders', $conditions, 'created_at DESC', $pagination['limit'], $pagination['offset']);
        } else {
            $whereClauses = ["business_id = ?"];
            $params = [$businessId];
            if ($status) { $whereClauses[] = "status = ?"; $params[] = $status; }
            if ($paymentStatus) { $whereClauses[] = "payment_status = ?"; $params[] = $paymentStatus; }
            if ($dateFrom) { $whereClauses[] = "DATE(created_at) >= ?"; $params[] = $dateFrom; }
            if ($dateTo) { $whereClauses[] = "DATE(created_at) <= ?"; $params[] = $dateTo; }
            
            $whereClause = implode(' AND ', $whereClauses);
            $countSql = "SELECT COUNT(*) as count FROM orders WHERE {$whereClause}";
            $total = $this->db->query($countSql, $params)[0]['count'];
            
            $sql = "SELECT * FROM orders WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            $orders = $this->db->query($sql, $params);
        }
        
        foreach ($orders as &$order) {
            if ($order['customer_id']) {
                $customer = $this->db->find('customers', $order['customer_id']);
                $order['customer_name'] = $customer['name'] ?? null;
                $order['customer_email'] = $customer['email'] ?? null;
                $order['customer_phone'] = $customer['phone'] ?? null;
            }
            $order['items'] = $this->db->findAll('order_items', ['order_id' => $order['id']]);
        }
        
        return $this->paginate($orders, $total, $pagination);
    }
    
    /**
     * GET /api/v1/orders/{id}
     * Get single order with customer and payment data
     */
    public function show($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $order = $this->db->find('orders', $id);
        if (!$order) return $this->notFound('Order not found');
        
        // Get order items with product images
        $order['items'] = $this->db->findAll('order_items', ['order_id' => $id]);
        foreach ($order['items'] as &$item) {
            $product = $this->db->find('products', $item['product_id']);
            $item['product_image'] = $product['image'] ?? null;
        }
        
        // ✅ Get customer data from customers table
        if ($order['customer_id']) {
            $customer = $this->db->find('customers', $order['customer_id']);
            if ($customer) {
                $order['customer_name'] = $customer['name'];
                $order['customer_email'] = $customer['email'];
                $order['customer_phone'] = $customer['phone'];
                $order['customer'] = $customer; // Full customer object
            }
        }
        
        // ✅ Get payment method from payments table
        $payment = $this->db->find('payments', ['order_id' => $id]);
        if ($payment) {
            $order['payment_method'] = $payment['payment_method'];
            $order['payment_reference'] = $payment['reference'] ?? null;
            $order['payment'] = $payment; // Full payment object
        }
        
        return $this->success($order, 'Order retrieved successfully');
    }
    
    public function search() {
        $this->requireAuth();
        $query = $this->input('q');
        $limit = min(50, (int) $this->input('limit', 10));
        
        if (!$query) return $this->error('Search query required', 400);
        
        $businessId = $this->getBusinessId();
        $sql = "SELECT o.* FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.id 
                WHERE o.business_id = ? AND (o.order_number LIKE ? OR c.name LIKE ? OR c.email LIKE ?) 
                ORDER BY o.created_at DESC LIMIT ?";
        
        $searchTerm = "%{$query}%";
        $orders = $this->db->query($sql, [$businessId, $searchTerm, $searchTerm, $searchTerm, $limit]);
        
        foreach ($orders as &$order) {
            if ($order['customer_id']) {
                $customer = $this->db->find('customers', $order['customer_id']);
                $order['customer_name'] = $customer['name'] ?? null;
            }
        }
        
        return $this->success(['items' => $orders, 'count' => count($orders)]);
    }
    
    public function stats() {
        $this->requireAuth();
        $businessId = $this->getBusinessId();
        
        $stats = [
            'total_orders' => $this->db->count('orders', ['business_id' => $businessId]),
            'pending' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'pending']),
            'processing' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'processing']),
            'completed' => $this->db->count('orders', ['business_id' => $businessId, 'status' => 'completed']),
        ];
        
        $revenueSql = "SELECT SUM(total) as total_revenue FROM orders WHERE business_id = ?";
        $stats['total_revenue'] = (float) ($this->db->query($revenueSql, [$businessId])[0]['total_revenue'] ?? 0);
        
        return $this->success($stats);
    }
    
    public function destroy($id) {
        $this->requireAuth();
        $this->requireOwnership('orders', $id);
        
        $order = $this->db->find('orders', $id);
        if (!$order) return $this->notFound('Order not found');
        if ($order['status'] === 'completed') return $this->error('Cannot cancel completed order', 400);
        
        try {
            $this->db->beginTransaction();
            $this->db->update('orders', $id, ['status' => 'cancelled']);
            
            $items = $this->db->findAll('order_items', ['order_id' => $id]);
            foreach ($items as $item) {
                $product = $this->db->find('products', $item['product_id']);
                if ($product) {
                    $this->db->update('products', $item['product_id'], ['stock' => $product['stock'] + $item['quantity']]);
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
}