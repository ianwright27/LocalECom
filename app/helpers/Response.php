<?php
/**
 * Response Helper Class - CORS FIXED
 * Handles consistent JSON response formatting for API requests
 */

class Response
{
    /**
     * Send a success response
     * 
     * @param mixed $data The data to return
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code (default: 200)
     */
    public static function success($data = null, $message = null, $statusCode = 200)
    {
        self::setHeaders($statusCode);
        
        $response = [
            'status' => 'success',
            'statusCode' => $statusCode,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default: 400)
     * @param mixed $errors Optional additional error details
     */
    public static function error($message, $statusCode = 400, $errors = null)
    {
        self::setHeaders($statusCode);
        
        $response = [
            'status' => 'error',
            'statusCode' => $statusCode,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Set HTTP headers for JSON response - CORS FIXED
     * 
     * @param int $statusCode HTTP status code
     */
    private static function setHeaders($statusCode)
    {
        // Only set headers if not already sent
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            
            // CORS - Use specific origin for credentials support
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = [
                'http://localhost:3000',  // Storefront
                'http://localhost:3001',  // Admin
                'http://localhost:3002',  // Additional port if needed
            ];
            
            // Check if origin is allowed
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Credentials: true');
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Send a validation error response
     * 
     * @param array $errors Associative array of field => error message
     * @param int $statusCode HTTP status code (default: 422)
     */
    public static function validationError($errors, $statusCode = 422)
    {
        self::setHeaders($statusCode);
        
        $response = [
            'status' => 'validation_error',
            'statusCode' => $statusCode,
            'message' => 'Validation failed',
            'errors' => $errors,
        ];

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an unauthorized response
     * 
     * @param string $message Custom message (default: 'Unauthorized')
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    /**
     * Send a forbidden response
     * 
     * @param string $message Custom message (default: 'Forbidden')
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }

    /**
     * Send a not found response
     * 
     * @param string $message Custom message (default: 'Not found')
     */
    public static function notFound($message = 'Not found')
    {
        self::error($message, 404);
    }

    /**
     * Send a server error response
     * 
     * @param string $message Custom message (default: 'Internal server error')
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }
}