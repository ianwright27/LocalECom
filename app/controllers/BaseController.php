<?php
/**
 * BaseController Class
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * This is the base controller that all other controllers extend.
 * Provides common functionality like JSON responses, input validation,
 * authentication checking, and error handling.
 * 
 * All controllers should extend this class to inherit these features.
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class BaseController {
    
    /**
     * Database instance
     * @var Database
     */
    protected $db;
    
    /**
     * Current authenticated user
     * @var array|null
     */
    protected $currentUser = null;
    
    /**
     * Current business (from authenticated user)
     * @var array|null
     */
    protected $currentBusiness = null;
    
    /**
     * Request data (POST/PUT/DELETE body or GET params)
     * @var array
     */
    protected $request = [];
    
    /**
     * Validation errors
     * @var array
     */
    protected $errors = [];
    
    /**
     * Constructor - Initialize database and request data
     */
    public function __construct() {
        // Get database instance
        $this->db = Database::getInstance();
        
        // Get request data based on method
        $this->request = $this->getRequestData();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Load current user from session if exists
        $this->loadCurrentUser();
    }
    
    /**
     * Get request data based on HTTP method
     * 
     * @return array Request data
     */
    private function getRequestData() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            return $_GET;
        } else if ($method === 'POST') {
            // Check if content type is JSON
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                return json_decode($input, true) ?? [];
            }
            return $_POST;
        } else if (in_array($method, ['PUT', 'DELETE', 'PATCH'])) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Load current authenticated user from session
     */
    private function loadCurrentUser() {
        if (isset($_SESSION['user_id'])) {
            $this->currentUser = $this->db->find('users', $_SESSION['user_id']);
            
            // Load user's business if business_id exists
            if ($this->currentUser && isset($this->currentUser['business_id'])) {
                $this->currentBusiness = $this->db->find('businesses', $this->currentUser['business_id']);
            }
        }
    }
    
    // ============================================
    // JSON RESPONSE METHODS
    // ============================================

     /**
     * Get JSON input from request body
     * Returns associative array or empty array if invalid
     */
    protected function getJsonInput()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * Send a successful JSON response
     * 
     * Usage:
     * return $this->success(['products' => $products], 'Products retrieved successfully');
     * 
     * @param mixed $data Data to return
     * @param string $message Optional success message
     * @param int $statusCode HTTP status code (default 200)
     */
    protected function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send an error JSON response
     * 
     * Usage:
     * return $this->error('Product not found', 404);
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default 400)
     * @param array $errors Optional validation errors
     */
    protected function error($message = 'An error occurred', $statusCode = 400, $errors = []) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send a created response (201)
     * 
     * Usage:
     * return $this->created($productId, 'Product created successfully');
     * 
     * @param mixed $data Created resource data
     * @param string $message Success message
     */
    protected function created($data = null, $message = 'Resource created successfully') {
        return $this->success($data, $message, 201);
    }
    
    /**
     * Send a validation error response (422)
     * 
     * Usage:
     * return $this->validationError($this->errors);
     * 
     * @param array $errors Validation errors
     */
    protected function validationError($errors = []) {
        return $this->error('Validation failed', 422, $errors);
    }
    
    /**
     * Send a not found response (404)
     * 
     * Usage:
     * return $this->notFound('Product not found');
     * 
     * @param string $message Not found message
     */
    protected function notFound($message = 'Resource not found') {
        return $this->error($message, 404);
    }
    
    /**
     * Send an unauthorized response (401)
     * 
     * Usage:
     * return $this->unauthorized('Invalid credentials');
     * 
     * @param string $message Unauthorized message
     */
    protected function unauthorized($message = 'Unauthorized') {
        return $this->error($message, 401);
    }
    
    /**
     * Send a forbidden response (403)
     * 
     * Usage:
     * return $this->forbidden('You do not have permission to access this resource');
     * 
     * @param string $message Forbidden message
     */
    protected function forbidden($message = 'Forbidden') {
        return $this->error($message, 403);
    }
    
    /**
     * Send a server error response (500)
     * 
     * Usage:
     * return $this->serverError('Database connection failed');
     * 
     * @param string $message Error message
     */
    protected function serverError($message = 'Internal server error') {
        return $this->error($message, 500);
    }
    
    // ============================================
    // AUTHENTICATION & AUTHORIZATION
    // ============================================
    
    /**
     * Check if user is authenticated
     * If not, send 401 response and exit
     * 
     * Usage in controller:
     * $this->requireAuth();
     * 
     * @return bool True if authenticated
     */
    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->unauthorized('Authentication required');
        }
        return true;
    }
    
    /**
     * Check if user is authenticated (without exiting)
     * 
     * @return bool True if authenticated
     */
    protected function isAuthenticated() {
        return $this->currentUser !== null;
    }
    
    /**
     * Check if current user has a specific role
     * 
     * Usage:
     * if ($this->hasRole('admin')) { ... }
     * 
     * @param string $role Role to check
     * @return bool True if user has role
     */
    protected function hasRole($role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return $this->currentUser['role'] === $role;
    }
    
    /**
     * Require user to have a specific role
     * If not, send 403 response and exit
     * 
     * Usage:
     * $this->requireRole('admin');
     * 
     * @param string $role Required role
     */
    protected function requireRole($role) {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            $this->forbidden('Insufficient permissions');
        }
    }
    
    /**
     * Check if user owns a resource (belongs to their business)
     * 
     * Usage:
     * $this->requireOwnership('products', $productId);
     * 
     * @param string $table Table name
     * @param int $resourceId Resource ID
     * @param string $column Business ID column name (default: business_id)
     * @return bool True if user owns resource
     */
    protected function requireOwnership($table, $resourceId, $column = 'business_id') {
        $this->requireAuth();
        
        $resource = $this->db->find($table, $resourceId);
        
        if (!$resource) {
            $this->notFound('Resource not found');
        }
        
        if ($resource[$column] != $this->currentBusiness['id']) {
            $this->forbidden('You do not have permission to access this resource');
        }
        
        return true;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null User ID or null
     */
    protected function getUserId() {
        return $this->currentUser['id'] ?? null;
    }
    
    /**
     * Get current business ID
     * 
     * @return int|null Business ID or null
     */
    protected function getBusinessId() {
        return $this->currentBusiness['id'] ?? null;
    }
    
    // ============================================
    // INPUT VALIDATION
    // ============================================
    
    /**
     * Validate request data against rules
     * 
     * Usage:
     * $this->validate([
     *     'name' => 'required|min:3',
     *     'email' => 'required|email',
     *     'price' => 'required|numeric|min:0'
     * ]);
     * 
     * @param array $rules Validation rules
     * @return bool True if valid, false otherwise
     */
    protected function validate($rules) {
        $this->errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            
            foreach ($rules as $rule) {
                // Parse rule with parameters (e.g., min:3)
                $params = [];
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramString) = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }
                
                // Apply validation rule
                $this->applyRule($field, $rule, $params);
            }
        }
        
        // If there are errors, send validation error response
        if (!empty($this->errors)) {
            $this->validationError($this->errors);
        }
        
        return true;
    }
    
    /**
     * Apply a single validation rule
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $params Rule parameters
     */
    private function applyRule($field, $rule, $params = []) {
        $value = $this->input($field);
        
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, ucfirst($field) . ' is required');
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid email');
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, ucfirst($field) . ' must be a number');
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, ucfirst($field) . ' must be an integer');
                }
                break;
                
            case 'min':
                $min = $params[0] ?? 0;
                if (is_numeric($value) && $value < $min) {
                    $this->addError($field, ucfirst($field) . " must be at least {$min}");
                } else if (is_string($value) && strlen($value) < $min) {
                    $this->addError($field, ucfirst($field) . " must be at least {$min} characters");
                }
                break;
                
            case 'max':
                $max = $params[0] ?? 0;
                if (is_numeric($value) && $value > $max) {
                    $this->addError($field, ucfirst($field) . " must not exceed {$max}");
                } else if (is_string($value) && strlen($value) > $max) {
                    $this->addError($field, ucfirst($field) . " must not exceed {$max} characters");
                }
                break;
                
            case 'unique':
                // Format: unique:table,column
                $table = $params[0] ?? '';
                $column = $params[1] ?? $field;
                if (!empty($value) && $this->db->exists($table, [$column => $value])) {
                    $this->addError($field, ucfirst($field) . ' already exists');
                }
                break;
                
            case 'exists':
                // Format: exists:table,column
                $table = $params[0] ?? '';
                $column = $params[1] ?? 'id';
                if (!empty($value) && !$this->db->exists($table, [$column => $value])) {
                    $this->addError($field, ucfirst($field) . ' does not exist');
                }
                break;
                
            case 'in':
                // Format: in:value1,value2,value3
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, ucfirst($field) . ' must be one of: ' . implode(', ', $params));
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid URL');
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid date');
                }
                break;
                
            case 'phone':
                // Kenya phone format (254XXXXXXXXX or 07XXXXXXXX or +254XXXXXXXXX)
                if (!empty($value)) {
                    $cleaned = preg_replace('/[^0-9]/', '', $value);
                    if (!preg_match('/^(254|07|7)[0-9]{8,9}$/', $cleaned)) {
                        $this->addError($field, ucfirst($field) . ' must be a valid phone number');
                    }
                }
                break;
        }
    }
    
    /**
     * Add a validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    // ============================================
    // INPUT HELPERS
    // ============================================
    
    /**
     * Get input value from request
     * 
     * Usage:
     * $name = $this->input('name');
     * $price = $this->input('price', 0); // with default
     * 
     * @param string $key Input key
     * @param mixed $default Default value if not found
     * @return mixed Input value
     */
    protected function input($key, $default = null) {
        return $this->request[$key] ?? $default;
    }
    
    /**
     * Get all input data
     * 
     * @return array All input data
     */
    protected function all() {
        return $this->request;
    }
    
    /**
     * Get only specified input fields
     * 
     * Usage:
     * $data = $this->only(['name', 'email', 'phone']);
     * 
     * @param array $keys Keys to retrieve
     * @return array Filtered input data
     */
    protected function only($keys) {
        $data = [];
        foreach ($keys as $key) {
            if (isset($this->request[$key])) {
                $data[$key] = $this->request[$key];
            }
        }
        return $data;
    }
    
    /**
     * Check if input has a key
     * 
     * @param string $key Input key
     * @return bool True if exists
     */
    protected function has($key) {
        return isset($this->request[$key]);
    }
    
    /**
     * Sanitize input to prevent XSS
     * 
     * @param string $input Input to sanitize
     * @return string Sanitized input
     */
    protected function sanitize($input) {
        return $this->db->sanitize($input);
    }
    
    // ============================================
    // PAGINATION HELPERS
    // ============================================
    
    /**
     * Get pagination parameters from request
     * 
     * Usage:
     * $pagination = $this->getPagination();
     * $products = $this->db->findAll('products', [], 'id DESC', $pagination['limit'], $pagination['offset']);
     * 
     * @param int $defaultPerPage Default items per page
     * @return array Pagination parameters (page, per_page, offset, limit)
     */
    protected function getPagination($defaultPerPage = 20) {
        $page = max(1, (int) $this->input('page', 1));
        $perPage = min(100, max(1, (int) $this->input('per_page', $defaultPerPage)));
        $offset = ($page - 1) * $perPage;
        
        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'limit' => $perPage
        ];
    }
    
    /**
     * Create paginated response
     * 
     * Usage:
     * return $this->paginate($products, $total, $pagination);
     * 
     * @param array $items Items for current page
     * @param int $total Total number of items
     * @param array $pagination Pagination parameters
     * @return void Sends JSON response
     */
    protected function paginate($items, $total, $pagination) {
        $lastPage = ceil($total / $pagination['per_page']);
        
        $this->success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $pagination['per_page'],
                'current_page' => $pagination['page'],
                'last_page' => $lastPage,
                'from' => $pagination['offset'] + 1,
                'to' => min($pagination['offset'] + $pagination['per_page'], $total)
            ]
        ]);
    }
    
    // ============================================
    // FILE UPLOAD HELPERS
    // ============================================
    
    /**
     * Handle file upload
     * 
     * Usage:
     * $filename = $this->uploadFile('image', 'products');
     * 
     * @param string $field Form field name
     * @param string $folder Destination folder (relative to public/uploads/)
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Max file size in bytes (default 5MB)
     * @return string|false Uploaded filename or false on failure
     */
    protected function uploadFile($field, $folder = 'general', $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $maxSize = 5242880) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $file = $_FILES[$field];
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $this->addError($field, 'File size must not exceed ' . ($maxSize / 1024 / 1024) . 'MB');
            return false;
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $this->addError($field, 'Invalid file type');
            return false;
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../../public/uploads/' . $folder;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $destination = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $folder . '/' . $filename;
        }
        
        return false;
    }
    
    /**
     * Delete uploaded file
     * 
     * @param string $filepath File path relative to public/uploads/
     * @return bool True if deleted successfully
     */
    protected function deleteFile($filepath) {
        $fullPath = __DIR__ . '/../../public/uploads/' . $filepath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    // ============================================
    // UTILITY METHODS
    // ============================================
    
    /**
     * Log message to file
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    protected function log($message, $level = 'info') {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Generate random string
     * 
     * @param int $length Length of string
     * @return string Random string
     */
    protected function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Format price for display (Kenya Shillings)
     * 
     * @param float $amount Amount
     * @return string Formatted price
     */
    protected function formatPrice($amount) {
        return 'KES ' . number_format($amount, 2);
    }
    
    /**
     * Format phone number to E.164 format (254XXXXXXXXX)
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    protected function formatPhone($phone) {
        // Remove non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert to 254 format
        if (substr($cleaned, 0, 1) === '0') {
            $cleaned = '254' . substr($cleaned, 1);
        } else if (substr($cleaned, 0, 3) !== '254') {
            $cleaned = '254' . $cleaned;
        }
        
        return $cleaned;
    }
}