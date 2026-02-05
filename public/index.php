<?php
// Entry point
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once '../config/database.php';

echo json_encode([
    'message' => 'WrightCommerce API v1.0',
    'status' => 'online'
]);