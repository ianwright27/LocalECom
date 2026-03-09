<?php
/**
 * CustomerController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles customer management for business owners
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class CustomerControllerAdmin extends BaseController {
    
    /**
     * GET /api/v1/customers
     * List all customers for authenticated user's business
     */
    public function index() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        $search = $this->input('search');
        
        if ($search) {
            $sql = "SELECT * FROM customers 
                    WHERE business_id = ? 
                    AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?";
            
            $searchTerm = "%{$search}%";
            $customers = $this->db->query($sql, [
                $businessId,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $pagination['limit'],
                $pagination['offset']
            ]);
            
            $countSql = "SELECT COUNT(*) as count FROM customers 
                         WHERE business_id = ? 
                         AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $totalResult = $this->db->query($countSql, [$businessId, $searchTerm, $searchTerm, $searchTerm]);
            $total = $totalResult[0]['count'];
        } else {
            $total = $this->db->count('customers', ['business_id' => $businessId]);
            $customers = $this->db->findAll(
                'customers',
                ['business_id' => $businessId],
                'created_at DESC',
                $pagination['limit'],
                $pagination['offset']
            );
        }
        
        // Add order count for each customer
        foreach ($customers as &$customer) {
            $customer['order_count'] = $this->db->count('orders', ['customer_id' => $customer['id']]);
        }
        
        return $this->paginate($customers, $total, $pagination);
    }
    
    /**
     * GET /api/v1/customers/{id}
     * Get single customer with orders
     */
    public function show($id) {
        $this->requireAuth();
        
        $customer = $this->db->find('customers', $id);
        
        if (!$customer) {
            return $this->notFound('Customer not found');
        }
        
        // Verify customer belongs to business
        if ($customer['business_id'] != $this->getBusinessId()) {
            return $this->forbidden();
        }
        
        // Get customer orders
        $customer['orders'] = $this->db->findAll('orders', ['customer_id' => $id], 'created_at DESC');
        $customer['order_count'] = count($customer['orders']);
        
        // Calculate total spent
        $totalSql = "SELECT SUM(total) as total_spent FROM orders WHERE customer_id = ?";
        $totalResult = $this->db->query($totalSql, [$id]);
        $customer['total_spent'] = (float) ($totalResult[0]['total_spent'] ?? 0);
        
        return $this->success($customer, 'Customer retrieved successfully');
    }
    
    /**
     * GET /api/v1/customers/{id}/orders
     * Get customer's orders
     */
    public function orders($id) {
        $this->requireAuth();
        
        $customer = $this->db->find('customers', $id);
        
        if (!$customer || $customer['business_id'] != $this->getBusinessId()) {
            return $this->notFound('Customer not found');
        }
        
        $orders = $this->db->findAll('orders', ['customer_id' => $id], 'created_at DESC');
        
        return $this->success($orders, 'Customer orders retrieved successfully');
    }
    
    /**
     * GET /api/v1/customers/search
     * Search customers
     */
    public function search() {
        $this->requireAuth();
        
        $query = $this->input('q');
        
        if (!$query) {
            return $this->error('Search query required', 400);
        }
        
        $businessId = $this->getBusinessId();
        
        $sql = "SELECT * FROM customers 
                WHERE business_id = ? 
                AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)
                LIMIT 20";
        
        $searchTerm = "%{$query}%";
        $customers = $this->db->query($sql, [$businessId, $searchTerm, $searchTerm, $searchTerm]);
        
        return $this->success($customers, "Found " . count($customers) . " customers");
    }
}