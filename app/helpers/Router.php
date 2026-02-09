<?php
/**
 * Router Class
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * This class handles all HTTP routing for the application.
 * Supports RESTful API routes with clean URL patterns.
 * Handles GET, POST, PUT, DELETE, PATCH methods.
 * Extracts URL parameters like /products/{id}
 * Routes requests to appropriate Controller@method
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class Router {
    
    /**
     * Array of registered routes
     * @var array
     */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => []
    ];
    
    /**
     * Array of middleware functions to run before routes
     * @var array
     */
    private $middleware = [];
    
    /**
     * Current request URI
     * @var string
     */
    private $requestUri;
    
    /**
     * Current request method
     * @var string
     */
    private $requestMethod;
    
    /**
     * Base path for the application (useful if app is in a subdirectory)
     * @var string
     */
    private $basePath = '';
    
    /**
     * Route parameters extracted from URL
     * @var array
     */
    private $params = [];
    
    /**
     * 404 Not Found handler
     * @var callable|null
     */
    private $notFoundHandler = null;
    
    /**
     * Constructor - Initialize router with current request
     */
    public function __construct() {
        // Auto-detect base path from script location
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        // If in root directory, basePath will be '/', we want empty string
        if ($basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }

        $this->basePath = $basePath;
        $this->requestUri = $this->getRequestUri();
        $this->requestMethod = $this->getRequestMethod();
    }
    

    /**
     * Set base path for application
     * Useful if app is not in document root
     * 
     * Example: $router->setBasePath('/api/v1');
     * 
     * @param string $basePath The base path
     */

    public function setBasePath($basePath) {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Get the current request URI
     * Removes query string and cleans the path
     * 
     * @return string Clean request URI
     */
    private function getRequestUri() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remove base path if set
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        // Ensure URI starts with /
        $uri = '/' . ltrim($uri, '/');
        
        // If URI is just the base path, return root
        if ($uri === '/' || $uri === '') {
            $uri = '/';
        }
        
        return $uri;
    }
    
    /**
     * Get the current request method
     * Supports method override via _method parameter for PUT/DELETE from forms
     * 
     * @return string Request method (GET, POST, PUT, DELETE, PATCH)
     */
    private function getRequestMethod() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Support method override for browsers that don't support PUT/DELETE
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        return strtoupper($method);
    }
    
    /**
     * Register a GET route
     * 
     * Usage:
     * $router->get('/products', 'ProductController@index');
     * $router->get('/products/{id}', 'ProductController@show');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function get($path, $handler) {
        return $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Register a POST route
     * 
     * Usage:
     * $router->post('/products', 'ProductController@store');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function post($path, $handler) {
        return $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Register a PUT route
     * 
     * Usage:
     * $router->put('/products/{id}', 'ProductController@update');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function put($path, $handler) {
        return $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Register a DELETE route
     * 
     * Usage:
     * $router->delete('/products/{id}', 'ProductController@destroy');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function delete($path, $handler) {
        return $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Register a PATCH route
     * 
     * Usage:
     * $router->patch('/products/{id}', 'ProductController@update');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function patch($path, $handler) {
        return $this->addRoute('PATCH', $path, $handler);
    }
    
    /**
     * Register a route that responds to any HTTP method
     * 
     * Usage:
     * $router->any('/webhook', 'WebhookController@handle');
     * 
     * @param string $path The URL path pattern
     * @param string|callable $handler Controller@method or callable function
     * @return Router Returns self for method chaining
     */
    public function any($path, $handler) {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }
    
    /**
     * Register a group of routes with a common prefix
     * 
     * Usage:
     * $router->group('/api/v1', function($router) {
     *     $router->get('/products', 'ProductController@index');
     *     $router->get('/orders', 'OrderController@index');
     * });
     * 
     * @param string $prefix Common path prefix
     * @param callable $callback Function that registers routes
     */
    public function group($prefix, $callback) {
        $originalBasePath = $this->basePath;
        $this->basePath = $originalBasePath . '/' . trim($prefix, '/');
        
        call_user_func($callback, $this);
        
        $this->basePath = $originalBasePath;
    }
    
    /**
     * Add a route to the routes array
     * 
     * @param string $method HTTP method
     * @param string $path URL path pattern
     * @param string|callable $handler Controller@method or callable
     * @return Router Returns self for method chaining
     */
    private function addRoute($method, $path, $handler) {
        // Normalize path
        $path = $this->basePath . '/' . trim($path, '/');
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        
        $this->routes[$method][$path] = $handler;
        return $this;
    }
    
    /**
     * Add middleware to be executed before routing
     * 
     * Usage:
     * $router->middleware(function() {
     *     // Check authentication
     *     if (!isset($_SESSION['user_id'])) {
     *         http_response_code(401);
     *         echo json_encode(['error' => 'Unauthorized']);
     *         exit;
     *     }
     * });
     * 
     * @param callable $callback Middleware function
     */
    public function middleware($callback) {
        $this->middleware[] = $callback;
    }
    
    /**
     * Set custom 404 handler
     * 
     * Usage:
     * $router->setNotFoundHandler(function() {
     *     http_response_code(404);
     *     echo json_encode(['error' => 'Endpoint not found']);
     * });
     * 
     * @param callable $handler 404 handler function
     */
    public function setNotFoundHandler($handler) {
        $this->notFoundHandler = $handler;
    }
    
    /**
     * Match current request against registered routes
     * 
     * @return array|null Returns route info if matched, null otherwise
     */
    private function matchRoute() {
        $method = $this->requestMethod;
        $uri = $this->requestUri;
        
        // Check for exact match first
        if (isset($this->routes[$method][$uri])) {
            return [
                'handler' => $this->routes[$method][$uri],
                'params' => []
            ];
        }
        
        // Check for pattern match (with parameters like {id})
        foreach ($this->routes[$method] as $route => $handler) {
            $pattern = $this->convertRouteToRegex($route);
            
            if (preg_match($pattern, $uri, $matches)) {
                // Remove the full match, keep only parameter values
                array_shift($matches);
                
                // Extract parameter names from route
                preg_match_all('/{([a-zA-Z0-9_]+)}/', $route, $paramNames);
                $paramNames = $paramNames[1];
                
                // Build params array
                $params = [];
                foreach ($paramNames as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }
                
                $this->params = $params;
                
                return [
                    'handler' => $handler,
                    'params' => $params
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Convert route pattern to regex for matching
     * Example: /products/{id} becomes /^\/products\/([^\/]+)$/
     * 
     * @param string $route Route pattern
     * @return string Regex pattern
     */
    private function convertRouteToRegex($route) {
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $route);
        
        // Replace {param} with regex capture group
        $pattern = preg_replace('/{[a-zA-Z0-9_]+}/', '([^\/]+)', $pattern);
        
        // Wrap in regex delimiters
        $pattern = '/^' . $pattern . '$/';
        
        return $pattern;
    }
    
    /**
     * Get route parameter by name
     * 
     * Usage in controller:
     * $id = $router->getParam('id');
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed Parameter value
     */
    public function getParam($name, $default = null) {
        return $this->params[$name] ?? $default;
    }
    
    /**
     * Get all route parameters
     * 
     * @return array All parameters
     */
    public function getParams() {
        return $this->params;
    }
    
    /**
     * Dispatch the router - match route and execute handler
     * This is the main method that runs the router
     * 
     * Usage:
     * $router->dispatch();
     */
    public function dispatch() {
        // Run middleware
        foreach ($this->middleware as $middleware) {
            call_user_func($middleware);
        }
        
        // Match route
        $match = $this->matchRoute();
        
        if ($match === null) {
            $this->handleNotFound();
            return;
        }
        
        $handler = $match['handler'];
        $params = $match['params'];
        
        // Execute handler
        if (is_callable($handler)) {
            // Handler is a closure/function
            call_user_func_array($handler, $params);
        } else if (is_string($handler) && strpos($handler, '@') !== false) {
            // Handler is Controller@method format
            $this->callControllerMethod($handler, $params);
        } else {
            throw new Exception("Invalid route handler");
        }
    }
    
    /**
     * Call a controller method
     * 
     * @param string $handler Controller@method string
     * @param array $params Route parameters
     */
    private function callControllerMethod($handler, $params) {
        list($controllerName, $method) = explode('@', $handler);
        
        // Build controller file path
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
        
        // Check if controller file exists
        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: {$controllerFile}");
        }
        
        // Include controller file
        require_once $controllerFile;
        
        // Check if controller class exists
        if (!class_exists($controllerName)) {
            throw new Exception("Controller class not found: {$controllerName}");
        }
        
        // Instantiate controller
        $controller = new $controllerName();
        
        // Check if method exists
        if (!method_exists($controller, $method)) {
            throw new Exception("Method {$method} not found in controller {$controllerName}");
        }
        
        // Call controller method with params
        call_user_func_array([$controller, $method], $params);
    }
    
    /**
     * Handle 404 Not Found
     */
    private function handleNotFound() {
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler);
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'The requested endpoint does not exist',
                'path' => $this->requestUri,
                'method' => $this->requestMethod
            ]);
        }
    }
    
    /**
     * Get request body as JSON
     * Useful for PUT/POST requests with JSON payload
     * 
     * Usage in controller:
     * $data = $router->getJsonInput();
     * 
     * @return array|null Decoded JSON data or null
     */
    public function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    /**
     * Get request data based on method
     * Returns $_POST for POST, JSON body for PUT/DELETE/PATCH
     * 
     * @return array Request data
     */
    public function getRequestData() {
        if ($this->requestMethod === 'POST') {
            return $_POST;
        } else if (in_array($this->requestMethod, ['PUT', 'DELETE', 'PATCH'])) {
            return $this->getJsonInput() ?? [];
        } else {
            return $_GET;
        }
    }
    
    /**
     * Create a RESTful resource routes
     * Automatically creates routes for: index, show, store, update, destroy
     * 
     * Usage:
     * $router->resource('products', 'ProductController');
     * 
     * This creates:
     * GET    /products           -> ProductController@index
     * GET    /products/{id}      -> ProductController@show
     * POST   /products           -> ProductController@store
     * PUT    /products/{id}      -> ProductController@update
     * DELETE /products/{id}      -> ProductController@destroy
     * 
     * @param string $resource Resource name (e.g., 'products')
     * @param string $controller Controller name (e.g., 'ProductController')
     */
    public function resource($resource, $controller) {
        $path = '/' . trim($resource, '/');
        
        $this->get($path, $controller . '@index');                    // List all
        $this->get($path . '/{id}', $controller . '@show');           // Show one
        $this->post($path, $controller . '@store');                   // Create
        $this->put($path . '/{id}', $controller . '@update');         // Update
        $this->delete($path . '/{id}', $controller . '@destroy');     // Delete
    }
    
    /**
     * Redirect to another URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code (301 or 302)
     */
    public function redirect($url, $statusCode = 302) {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    /**
     * Get all registered routes (useful for debugging)
     * 
     * @return array All routes
     */
    public function getRoutes() {
        return $this->routes;
    }
    
    /**
     * Print all registered routes (for debugging)
     */
    public function debugRoutes() {
        echo "<h2>Registered Routes</h2>";
        foreach ($this->routes as $method => $routes) {
            echo "<h3>{$method}</h3>";
            echo "<ul>";
            foreach ($routes as $path => $handler) {
                $handlerStr = is_callable($handler) ? 'Closure' : $handler;
                echo "<li><strong>{$path}</strong> → {$handlerStr}</li>";
            }
            echo "</ul>";
        }
    }
}