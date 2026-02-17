<?php
/**
 * CORS Configuration
 * Allows React frontend to communicate with PHP backend
 * WITH SESSION SUPPORT
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\config\cors.php
 */

// Get origin from request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Allowed origins
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3000',
];

// Check if origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    // Development fallback
    header("Access-Control-Allow-Origin: http://localhost:3000");
    header("Access-Control-Allow-Credentials: true");
}

// Allow methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}