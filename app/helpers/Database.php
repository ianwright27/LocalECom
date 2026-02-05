<?php
/**
 * Database Helper Class
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * This class provides a secure, efficient way to interact with the MySQL database.
 * Uses singleton pattern to ensure only one database connection exists.
 * All queries use prepared statements to prevent SQL injection attacks.
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class Database {
    
    /**
     * Singleton instance of Database class
     * @var Database|null
     */
    private static $instance = null;
    
    /**
     * PDO database connection object
     * @var PDO|null
     */
    private $connection = null;
    
    /**
     * Database configuration
     * @var array
     */
    private $config = [
        'host' => DB_HOST,
        'dbname' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Fetch associative arrays by default
            PDO::ATTR_EMULATE_PREPARES => false,  // Use real prepared statements
            PDO::ATTR_PERSISTENT => false  // Don't use persistent connections (can be changed if needed)
        ]
    ];
    
    /**
     * Last executed statement (for debugging)
     * @var PDOStatement|null
     */
    private $lastStatement = null;
    
    /**
     * Error log file path
     * @var string
     */
    private $errorLogPath = '../logs/database_errors.log';
    
    /**
     * Private constructor to prevent direct instantiation
     * Use Database::getInstance() instead
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get singleton instance of Database class
     * 
     * Usage: $db = Database::getInstance();
     * 
     * @return Database The singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection using PDO
     * 
     * @throws PDOException If connection fails
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
        } catch (PDOException $e) {
            $this->logError("Database Connection Failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please contact support.");
        }
    }
    
    /**
     * Get the PDO connection object (use with caution)
     * 
     * @return PDO The PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a raw SQL query with optional parameters
     * Use this for SELECT queries that return multiple rows
     * 
     * Usage:
     * $results = $db->query("SELECT * FROM products WHERE business_id = ?", [$businessId]);
     * $results = $db->query("SELECT * FROM products WHERE price > :price", ['price' => 1000]);
     * 
     * @param string $sql The SQL query with placeholders (? or :named)
     * @param array $params Optional parameters to bind to the query
     * @return array Array of results (empty array if no results)
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $this->lastStatement = $stmt;
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }
    
    /**
     * Find a single record by ID or custom conditions
     * 
     * Usage:
     * $product = $db->find('products', 5);  // Find by ID
     * $user = $db->find('users', ['email' => 'test@example.com']);  // Find by condition
     * 
     * @param string $table Table name
     * @param int|array $condition ID (integer) or associative array of conditions
     * @return array|null Single row as associative array, or null if not found
     */
    public function find($table, $condition) {
        try {
            // If condition is an integer, treat it as ID
            if (is_numeric($condition)) {
                $sql = "SELECT * FROM {$table} WHERE id = ? LIMIT 1";
                $params = [$condition];
            } 
            // If condition is an array, build WHERE clause
            else if (is_array($condition)) {
                $whereClause = [];
                $params = [];
                foreach ($condition as $column => $value) {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $whereClause) . " LIMIT 1";
            } else {
                throw new Exception("Invalid condition type for find()");
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $this->lastStatement = $stmt;
            
            $result = $stmt->fetch();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            $this->logError("Find Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database find operation failed");
        }
    }
    
    /**
     * Find all records with optional conditions, ordering, and limit
     * 
     * Usage:
     * $products = $db->findAll('products');  // Get all products
     * $products = $db->findAll('products', ['business_id' => 1]);  // With conditions
     * $products = $db->findAll('products', ['status' => 'active'], 'created_at DESC', 10);  // With order and limit
     * 
     * @param string $table Table name
     * @param array $conditions Optional WHERE conditions as associative array
     * @param string $orderBy Optional ORDER BY clause (e.g., 'created_at DESC')
     * @param int $limit Optional LIMIT value
     * @param int $offset Optional OFFSET value
     * @return array Array of results (empty array if no results)
     */
    public function findAll($table, $conditions = [], $orderBy = null, $limit = null, $offset = null) {
        try {
            $sql = "SELECT * FROM {$table}";
            $params = [];
            
            // Add WHERE clause if conditions provided
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $column => $value) {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            // Add ORDER BY clause
            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy}";
            }
            
            // Add LIMIT and OFFSET
            if ($limit) {
                $sql .= " LIMIT {$limit}";
                if ($offset) {
                    $sql .= " OFFSET {$offset}";
                }
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $this->lastStatement = $stmt;
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("FindAll Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database findAll operation failed");
        }
    }
    
    /**
     * Insert a new record into the database
     * 
     * Usage:
     * $productId = $db->insert('products', [
     *     'business_id' => 1,
     *     'name' => 'iPhone 15',
     *     'price' => 120000,
     *     'stock' => 10
     * ]);
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @return int The ID of the inserted record
     */
    public function insert($table, $data) {
        try {
            // Automatically add created_at timestamp if column exists
            $data = $this->addTimestamps($table, $data, 'insert');
            
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($data));
            $this->lastStatement = $stmt;
            
            return (int) $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->logError("Insert Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database insert operation failed");
        }
    }
    
    /**
     * Update existing record(s) in the database
     * 
     * Usage:
     * $db->update('products', 5, ['price' => 125000, 'stock' => 8]);  // Update by ID
     * $db->update('products', ['sku' => 'IP15-BLK'], ['price' => 125000]);  // Update by condition
     * 
     * @param string $table Table name
     * @param int|array $condition ID (integer) or associative array of conditions
     * @param array $data Associative array of column => value pairs to update
     * @return int Number of affected rows
     */
    public function update($table, $condition, $data) {
        try {
            // Automatically add updated_at timestamp if column exists
            $data = $this->addTimestamps($table, $data, 'update');
            
            $setClause = [];
            $params = [];
            
            // Build SET clause
            foreach ($data as $column => $value) {
                $setClause[] = "{$column} = ?";
                $params[] = $value;
            }
            
            // Build WHERE clause
            if (is_numeric($condition)) {
                $whereClause = "id = ?";
                $params[] = $condition;
            } else if (is_array($condition)) {
                $whereParts = [];
                foreach ($condition as $column => $value) {
                    $whereParts[] = "{$column} = ?";
                    $params[] = $value;
                }
                $whereClause = implode(' AND ', $whereParts);
            } else {
                throw new Exception("Invalid condition type for update()");
            }
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$whereClause}";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $this->lastStatement = $stmt;
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("Update Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database update operation failed");
        }
    }
    
    /**
     * Delete record(s) from the database
     * 
     * Usage:
     * $db->delete('products', 5);  // Delete by ID
     * $db->delete('products', ['status' => 'deleted']);  // Delete by condition
     * 
     * @param string $table Table name
     * @param int|array $condition ID (integer) or associative array of conditions
     * @return int Number of deleted rows
     */
    public function delete($table, $condition) {
        try {
            $params = [];
            
            // Build WHERE clause
            if (is_numeric($condition)) {
                $whereClause = "id = ?";
                $params[] = $condition;
            } else if (is_array($condition)) {
                $whereParts = [];
                foreach ($condition as $column => $value) {
                    $whereParts[] = "{$column} = ?";
                    $params[] = $value;
                }
                $whereClause = implode(' AND ', $whereParts);
            } else {
                throw new Exception("Invalid condition type for delete()");
            }
            
            $sql = "DELETE FROM {$table} WHERE {$whereClause}";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $this->lastStatement = $stmt;
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("Delete Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database delete operation failed");
        }
    }
    
    /**
     * Count records in a table with optional conditions
     * 
     * Usage:
     * $totalProducts = $db->count('products');
     * $activeProducts = $db->count('products', ['status' => 'active']);
     * 
     * @param string $table Table name
     * @param array $conditions Optional WHERE conditions
     * @return int Count of records
     */
    public function count($table, $conditions = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table}";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $column => $value) {
                    $whereClause[] = "{$column} = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return (int) $result['count'];
        } catch (PDOException $e) {
            $this->logError("Count Error: " . $e->getMessage() . " | Table: " . $table);
            throw new Exception("Database count operation failed");
        }
    }
    
    /**
     * Begin a database transaction
     * Use with commit() and rollback()
     * 
     * Usage:
     * try {
     *     $db->beginTransaction();
     *     $db->insert('orders', $orderData);
     *     $db->insert('order_items', $itemData);
     *     $db->commit();
     * } catch (Exception $e) {
     *     $db->rollback();
     * }
     * 
     * @return bool True on success
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit a database transaction
     * 
     * @return bool True on success
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback a database transaction
     * 
     * @return bool True on success
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Check if a record exists
     * 
     * Usage:
     * if ($db->exists('users', ['email' => 'user@example.com'])) { ... }
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @return bool True if record exists, false otherwise
     */
    public function exists($table, $conditions) {
        return $this->count($table, $conditions) > 0;
    }
    
    /**
     * Get the last inserted ID
     * 
     * @return int Last insert ID
     */
    public function lastInsertId() {
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Get the number of rows affected by the last operation
     * 
     * @return int Number of affected rows
     */
    public function rowCount() {
        return $this->lastStatement ? $this->lastStatement->rowCount() : 0;
    }
    
    /**
     * Automatically add created_at and updated_at timestamps
     * Only adds if the columns exist in the table
     * 
     * @param string $table Table name
     * @param array $data Data array
     * @param string $operation 'insert' or 'update'
     * @return array Data with timestamps added
     */
    private function addTimestamps($table, $data, $operation) {
        $timestamp = date('Y-m-d H:i:s');
        
        // Check if table has timestamp columns (simple check)
        // In production, you might want to cache this information
        try {
            $columns = $this->query("SHOW COLUMNS FROM {$table}");
            $columnNames = array_column($columns, 'Field');
            
            if ($operation === 'insert' && in_array('created_at', $columnNames) && !isset($data['created_at'])) {
                $data['created_at'] = $timestamp;
            }
            
            if (in_array('updated_at', $columnNames) && !isset($data['updated_at'])) {
                $data['updated_at'] = $timestamp;
            }
        } catch (Exception $e) {
            // If checking columns fails, just continue without adding timestamps
        }
        
        return $data;
    }
    
    /**
     * Sanitize input to prevent XSS attacks
     * Use this for user input that will be displayed on pages
     * Note: Prepared statements already protect against SQL injection
     * 
     * @param string $input The input to sanitize
     * @return string Sanitized input
     */
    public function sanitize($input) {
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Log database errors to file
     * 
     * @param string $message Error message to log
     */
    private function logError($message) {
        $logDir = dirname($this->errorLogPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        @file_put_contents($this->errorLogPath, $logMessage, FILE_APPEND);
    }
    
    /**
     * Close database connection
     * Usually not needed as PHP closes connections automatically
     */
    public function close() {
        $this->connection = null;
    }
    
    /**
     * Destructor - clean up resources
     */
    public function __destruct() {
        $this->connection = null;
    }
}