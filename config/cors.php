<?php
/**
 * CORS Configuration
 * Allows React frontend to communicate with PHP backend
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\config\cors.php
 * Include at the top of public/index.php
 */

// Allow requests from React development server
$allowedOrigins = [
    'http://localhost:3000',  // React Admin
    'http://localhost:3001',  // React Storefront (if running on different port)
    'http://localhost:5173',  // Vite (alternative)
];

// Get origin from request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // In development, you might want to allow all origins
    // Comment this out in production!
    header("Access-Control-Allow-Origin: http://localhost:3000");
}

// Allow credentials (cookies, sessions)
header("Access-Control-Allow-Credentials: true");

// Allow methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}