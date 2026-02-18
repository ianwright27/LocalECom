<?php
/**
 * PaymentsController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles admin payment viewing, filtering, and statistics
 * This is separate from PaystackController which handles payment processing
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class PaymentsController extends BaseController {
    
    /**
     * GET /api/v1/payments
     * List all payments for authenticated user's business with filters
     * 
     * Query Parameters:
     * - status: Filter by payment status (pending, completed, failed, refunded)
     * - payment_method: Filter by method (paystack, mpesa, cash, bank)
     * - date_from: Filter from date
     * - date_to: Filter to date
     * - page: Page number
     * - per_page: Items per page
     */
    public function index() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        // Get filters
        $status = $this->input('status');
        $paymentMethod = $this->input('payment_method');
        $dateFrom = $this->input('date_from');
        $dateTo = $this->input('date_to');
        
        // Build base conditions
        $conditions = ['business_id' => $businessId];
        
        if ($status) {
            $conditions['status'] = $status;
        }
        if ($paymentMethod) {
            $conditions['payment_method'] = $paymentMethod;
        }
        
        // For simple filters (no date range), use findAll
        if (!$dateFrom && !$dateTo) {
            $total = $this->db->count('payments', $conditions);
            $payments = $this->db->findAll(
                'payments',
                $conditions,
                'created_at DESC',
                $pagination['limit'],
                $pagination['offset']
            );
        } else {
            // Use custom SQL for date filters
            $whereClauses = ["business_id = ?"];
            $params = [$businessId];
            
            if ($status) {
                $whereClauses[] = "status = ?";
                $params[] = $status;
            }
            if ($paymentMethod) {
                $whereClauses[] = "payment_method = ?";
                $params[] = $paymentMethod;
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
            $countSql = "SELECT COUNT(*) as count FROM payments WHERE {$whereClause}";
            $countResult = $this->db->query($countSql, $params);
            $total = $countResult[0]['count'];
            
            // Get payments
            $sql = "SELECT * FROM payments WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];
            $payments = $this->db->query($sql, $params);
        }
        
        // Enrich with order and customer info
        foreach ($payments as &$payment) {
            if ($payment['order_id']) {
                $order = $this->db->find('orders', $payment['order_id']);
                if ($order) {
                    $payment['order_number'] = $order['order_number'];
                    
                    // Get customer info
                    if ($order['customer_id']) {
                        $customer = $this->db->find('customers', $order['customer_id']);
                        if ($customer) {
                            $payment['customer_name'] = $customer['name'];
                            $payment['customer_phone'] = $customer['phone'];
                            $payment['customer_email'] = $customer['email'];
                        }
                    }
                }
            }
        }
        
        return $this->paginate($payments, $total, $pagination);
    }
    
    /**
     * GET /api/v1/payments/{id}
     * Get single payment details
     */
    public function show($id) {
        $this->requireAuth();
        $this->requireOwnership('payments', $id);
        
        $payment = $this->db->find('payments', $id);
        
        if (!$payment) {
            return $this->notFound('Payment not found');
        }
        
        // Get related order
        if ($payment['order_id']) {
            $order = $this->db->find('orders', $payment['order_id']);
            $payment['order'] = $order;
            
            // Get customer
            if ($order && $order['customer_id']) {
                $customer = $this->db->find('customers', $order['customer_id']);
                $payment['customer'] = $customer;
            }
        }
        
        return $this->success($payment, 'Payment retrieved successfully');
    }
    
    /**
     * GET /api/v1/payments/stats
     * Get payment statistics for business
     */
    public function stats() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        $stats = [
            'total_payments' => $this->db->count('payments', ['business_id' => $businessId]),
            'paid_count' => $this->db->count('payments', ['business_id' => $businessId, 'status' => 'completed']),
            'pending_count' => $this->db->count('payments', ['business_id' => $businessId, 'status' => 'pending']),
            'failed_count' => $this->db->count('payments', ['business_id' => $businessId, 'status' => 'failed']),
        ];
        
        // Get revenue stats
        $revenueSql = "SELECT 
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as paid_revenue,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue,
                        SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_revenue,
                        SUM(amount) as total_revenue
                       FROM payments 
                       WHERE business_id = ?";
        
        $revenueResult = $this->db->query($revenueSql, [$businessId]);
        $stats['paid_revenue'] = (float) ($revenueResult[0]['paid_revenue'] ?? 0);
        $stats['pending_revenue'] = (float) ($revenueResult[0]['pending_revenue'] ?? 0);
        $stats['failed_revenue'] = (float) ($revenueResult[0]['failed_revenue'] ?? 0);
        $stats['total_revenue'] = (float) ($revenueResult[0]['total_revenue'] ?? 0);
        
        // Also return unpaid_count for compatibility
        $stats['unpaid_count'] = $stats['pending_count'];
        $stats['unpaid_revenue'] = $stats['pending_revenue'];
        $stats['total_paid'] = $stats['paid_revenue'];
        $stats['total_unpaid'] = $stats['pending_revenue'];
        
        return $this->success($stats, 'Payment statistics retrieved successfully');
    }
    
    /**
     * GET /api/v1/payments/search
     * Search payments by reference or customer
     */
    public function search() {
        $this->requireAuth();
        
        $query = $this->input('q');
        $limit = min(50, (int) $this->input('limit', 10));
        
        if (!$query) {
            return $this->error('Search query is required', 400);
        }
        
        $businessId = $this->getBusinessId();
        
        // Search in payments and related orders/customers
        $sql = "SELECT p.* 
                FROM payments p
                LEFT JOIN orders o ON p.order_id = o.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE p.business_id = ? 
                AND (
                    p.reference LIKE ? 
                    OR p.transaction_id LIKE ?
                    OR o.order_number LIKE ?
                    OR c.name LIKE ? 
                    OR c.email LIKE ?
                )
                ORDER BY p.created_at DESC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        $payments = $this->db->query($sql, [
            $businessId,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $searchTerm,
            $limit
        ]);
        
        // Enrich with order and customer info
        foreach ($payments as &$payment) {
            if ($payment['order_id']) {
                $order = $this->db->find('orders', $payment['order_id']);
                if ($order) {
                    $payment['order_number'] = $order['order_number'];
                    
                    if ($order['customer_id']) {
                        $customer = $this->db->find('customers', $order['customer_id']);
                        if ($customer) {
                            $payment['customer_name'] = $customer['name'];
                            $payment['customer_phone'] = $customer['phone'];
                        }
                    }
                }
            }
        }
        
        return $this->success([
            'items' => $payments,
            'count' => count($payments)
        ], "Found " . count($payments) . " payments");
    }
}