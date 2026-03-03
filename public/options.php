<?php
/**
 * OPTIONS Handler - CORS Preflight
 */

// REMOVE any existing CORS headers first
header_remove('Access-Control-Allow-Origin');
header_remove('Access-Control-Allow-Credentials');

// Get the origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Allowed origins
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
];

// Check and set specific origin (NOT *)
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

// Set other CORS headers
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');
header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours

// Return 200 OK
http_response_code(200);
exit;