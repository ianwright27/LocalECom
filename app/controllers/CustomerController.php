<?php
/**
 * Customer Controller
 * Handles customer authentication and profile management
 */

require_once __DIR__ . '/BaseController.php';
require_once '../app/helpers/Response.php';

class CustomerController extends BaseController
{
    // private $db;

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

            // Get business_id (from config or first active product)
            $businessId = $_ENV['BUSINESS_ID'] ?? 1;
            if (!$businessId) {
                $products = $this->db->findAll('products', ['status' => 'active'], 'id ASC', 1);
                $businessId = $products[0]['business_id'] ?? 1;
            }

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
            unset($customer['password']); // Don't send password back

            // Generate token (simple implementation)
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
     * Requires authentication
     */
    public function profile()
    {
        try {
            // Get customer ID from token
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::error('Unauthorized', 401);
                return;
            }

            $customer = $this->db->find('customers', $customerId);

            if (!$customer) {
                Response::error('Customer not found', 404);
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
     * Requires authentication
     */
    public function updateProfile()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::error('Unauthorized', 401);
                return;
            }

            $data = $this->getJsonInput();
            
            $updates = [];
            
            if (isset($data['name'])) {
                $updates['name'] = trim($data['name']);
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
     * Get customer orders
     * GET /api/v1/customers/orders
     * Requires authentication
     */
    public function orders()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                Response::error('Unauthorized', 401);
                return;
            }

            $orders = $this->db->findAll('orders', ['customer_id' => $customerId], 'created_at DESC');

            Response::success([
                'items' => $orders,
                'total' => count($orders),
            ]);

        } catch (\Exception $e) {
            error_log('Get orders error: ' . $e->getMessage());
            Response::error('Failed to get orders', 500);
        }
    }

    /**
     * Generate simple token for customer
     * In production, use JWT or similar
     */
    private function generateToken($customerId)
    {
        // Simple token: base64(customer_id:timestamp:random)
        $data = $customerId . ':' . time() . ':' . bin2hex(random_bytes(16));
        return base64_encode($data);
    }

    /**
     * Get authenticated customer ID from token
     * Returns customer ID or null
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
            // Decode token
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