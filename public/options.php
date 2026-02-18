<?php
/**
 * OPTIONS Handler
 * Handles CORS preflight requests
 */

header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: text/plain');
header('Content-Length: 0');

http_response_code(200);
exit;