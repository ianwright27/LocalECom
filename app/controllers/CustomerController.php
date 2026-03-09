<?php
/**
 * Customer Controller - UPDATED
 * Handles customer authentication and profile management
 */

require_once __DIR__ . '/BaseController.php';
require_once '../app/helpers/Response.php';

class CustomerController extends BaseController
{
    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    /**
     * Register new customer
     * POST /api/v1/customers/register
     */
    public function register()
    {
        try {
            $data = $this->getJsonInput();
            
            // Validate input
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                Response::error('Name, email and password are required', 400);
                return;
            }

            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];
            $phone = isset($data['phone']) ? trim($data['phone']) : '';

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email address', 400);
                return;
            }

            // Check if email already exists
            if ($this->db->exists('customers', ['email' => $email])) {
                Response::error('Email already registered', 409);
                return;
            }

            // Get business_id
            $businessId = $_ENV['BUSINESS_ID'] ?? 1;

            // Create customer
            $customerId = $this->db->insert('customers', [
                'business_id' => $businessId,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => str_replace([' ', '-', '+'], '', $phone),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$customerId) {
                Response::error('Failed to create customer', 500);
                return;
            }

            // Get created customer
            $customer = $this->db->find('customers', $customerId);
            unset($customer['password']);

            // Generate token
            $token = $this->generateToken($customerId);

            Response::success([
                'customer' => $customer,
                'token' => $token,
            ], 'Registration successful', 201);

        } catch (\Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            Response::error('Registration failed', 500);
        }
    }

    /**
     * Login customer
     * POST /api/v1/customers/login
     */
    public function login()
    {
        try {
            $data = $this->getJsonInput();
            
            // Validate input
            if (!isset($data['email']) || !isset($data['password'])) {
                Response::error('Email and password are required', 400);
                return;
            }

            $email = trim($data['email']);
            $password = $data['password'];

            // Find customer by email
            $customer = $this->db->find('customers', ['email' => $email]);

            if (!$customer) {
                Response::error('Invalid email or password', 401);
                return;
            }

            // Verify password
            if (!password_verify($password, $customer['password'])) {
                Response::error('Invalid email or password', 401);
                return;
            }

            // Remove password from response
            unset($customer['password']);

            // Generate token
            $token = $this->generateToken($customer['id']);

            Response::success([
                'customer' => $customer,
                'token' => $token,
            ], 'Login successful');

        } catch (\Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            Response::error('Login failed', 500);
        }
    }

    /**
     * Get customer profile
     * GET /api/v1/customers/profile
     */
    public function profile()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::unauthorized('Please login to access your profile');
                return;
            }

            $customer = $this->db->find('customers', $customerId);

            if (!$customer) {
                Response::notFound('Customer not found');
                return;
            }

            unset($customer['password']);

            Response::success($customer);

        } catch (\Exception $e) {
            error_log('Profile error: ' . $e->getMessage());
            Response::error('Failed to get profile', 500);
        }
    }

    /**
     * Update customer profile
     * PUT /api/v1/customers/profile
     */
    public function updateProfile()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::unauthorized('Please login to update profile');
                return;
            }

            $data = $this->getJsonInput();
            
            $updates = [];
            
            if (isset($data['name'])) {
                $updates['name'] = trim($data['name']);
            }
            
            if (isset($data['email'])) {
                $email = trim($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Response::error('Invalid email address', 400);
                    return;
                }
                // Check if email is taken by another customer
                $existing = $this->db->find('customers', ['email' => $email]);
                if ($existing && $existing['id'] != $customerId) {
                    Response::error('Email already in use', 409);
                    return;
                }
                $updates['email'] = $email;
            }
            
            if (isset($data['phone'])) {
                $updates['phone'] = str_replace([' ', '-', '+'], '', $data['phone']);
            }
            
            if (isset($data['address'])) {
                $updates['address'] = trim($data['address']);
            }

            if (empty($updates)) {
                Response::error('No fields to update', 400);
                return;
            }

            $updated = $this->db->update('customers', $customerId, $updates);

            if (!$updated) {
                Response::error('Failed to update profile', 500);
                return;
            }

            $customer = $this->db->find('customers', $customerId);
            unset($customer['password']);

            Response::success($customer, 'Profile updated successfully');

        } catch (\Exception $e) {
            error_log('Update profile error: ' . $e->getMessage());
            Response::error('Failed to update profile', 500);
        }
    }

    /**
     * Change customer password
     * PUT /api/v1/customers/password
     */
    public function changePassword()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::unauthorized('Please login to change password');
                return;
            }

            $data = $this->getJsonInput();
            
            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                Response::error('Current password and new password are required', 400);
                return;
            }

            // Validate new password length
            if (strlen($data['new_password']) < 6) {
                Response::error('New password must be at least 6 characters', 400);
                return;
            }

            // Get customer
            $customer = $this->db->find('customers', $customerId);

            if (!$customer) {
                Response::notFound('Customer not found');
                return;
            }

            // Verify current password
            if (!password_verify($data['current_password'], $customer['password'])) {
                Response::error('Current password is incorrect', 401);
                return;
            }

            // Update password
            $updated = $this->db->update('customers', $customerId, [
                'password' => password_hash($data['new_password'], PASSWORD_DEFAULT),
            ]);

            if (!$updated) {
                Response::error('Failed to update password', 500);
                return;
            }

            Response::success(null, 'Password updated successfully');

        } catch (\Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            Response::error('Failed to change password', 500);
        }
    }

    /**
     * Get customer orders
     * GET /api/v1/customers/orders
     */
    public function orders()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::unauthorized('Please login to view orders');
                return;
            }

            $orders = $this->db->findAll('orders', ['customer_id' => $customerId], 'created_at DESC');

            Response::success($orders);

        } catch (\Exception $e) {
            error_log('Get orders error: ' . $e->getMessage());
            Response::error('Failed to get orders', 500);
        }
    }
    
    /**
     * Get single customer order
     * GET /api/v1/customers/orders/{id}
     */
    public function getOrder($id)  // ← was $orderId, must match {id} in route
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::unauthorized('Please login to view order');
                return;
            }

            $order = $this->db->find('orders', $id);

            if (!$order) {
                Response::notFound('Order not found');
                return;
            }

            // Verify order belongs to customer
            if ($order['customer_id'] != $customerId) {
                Response::forbidden('You do not have access to this order');
                return;
            }

            // Get order items
            $items = $this->db->query(
                "SELECT oi.*, p.name as product_name, p.image 
                FROM order_items oi 
                LEFT JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?",
                [$id]
            );

            $order['items'] = $items;

            Response::success($order);

        } catch (\Exception $e) {
            error_log('Get order error: ' . $e->getMessage());
            Response::error('Failed to get order', 500);
        }
    }

    /**
     * Logout customer
     * POST /api/v1/customers/logout
     */
    public function logout()
    {
        // For token-based auth, logout is handled on frontend by removing token
        // This endpoint is just for consistency
        Response::success(null, 'Logged out successfully');
    }

    /**
     * Generate simple token for customer
     */
    private function generateToken($customerId)
    {
        $data = $customerId . ':' . time() . ':' . bin2hex(random_bytes(16));
        return base64_encode($data);
    }

    /**
     * Get authenticated customer ID from Bearer token
     */
    private function getAuthenticatedCustomerId()
    {
        // Check Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) >= 3) {
                return (int)$parts[0];
            }
        } catch (\Exception $e) {
            error_log('Token decode error: ' . $e->getMessage());
        }

        return null;
    }
}