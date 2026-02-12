<?php
/**
 * Database Insert Test Script
 * Place in: C:\xampp\htdocs\wrightcommerce\public\test-insert.php
 * Access via: http://localhost/wrightcommerce/public/test-insert.php
 * 
 * This will test the database insert directly and show the exact error
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../app/helpers/Database.php';

echo "<h1>Database Insert Test</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h2>Step 1: Database Connection</h2>";
    echo "<p style='color: green;'>✅ Connected to database: " . DB_NAME . "</p>";
    
    echo "<h2>Step 2: Check if products table exists</h2>";
    $tables = $db->query("SHOW TABLES LIKE 'products'");
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ Products table does NOT exist!</p>";
        echo "<p>Run the SQL script to create it.</p>";
        exit;
    }
    echo "<p style='color: green;'>✅ Products table exists</p>";
    
    echo "<h2>Step 3: Check products table structure</h2>";
    $columns = $db->query("DESCRIBE products");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Step 4: Test Direct Insert (Method 1 - Using Database Helper)</h2>";
    
    $testData = [
        'business_id' => 1,
        'name' => 'Test Product Direct',
        'description' => 'Test Description',
        'price' => 1000.00,
        'cost_price' => 800.00,
        'stock' => 10,
        'sku' => 'TEST-' . time(),
        'category' => 'Test',
        'status' => 'active'
    ];
    
    echo "<p>Attempting to insert:</p>";
    echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    try {
        $productId = $db->insert('products', $testData);
        echo "<p style='color: green;'>✅ SUCCESS! Product inserted with ID: {$productId}</p>";
        
        // Verify it was inserted
        $product = $db->find('products', $productId);
        echo "<p>Retrieved product:</p>";
        echo "<pre>" . json_encode($product, JSON_PRETTY_PRINT) . "</pre>";
        
        // Clean up
        $db->delete('products', $productId);
        echo "<p style='color: blue;'>ℹ Test product cleaned up (deleted)</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ INSERT FAILED!</p>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    }
    
    echo "<h2>Step 5: Test Direct PDO Insert (Method 2 - Raw SQL)</h2>";
    
    try {
        $pdo = $db->getConnection();
        $sql = "INSERT INTO products (business_id, name, price, stock, status, created_at, updated_at) 
                VALUES (1, 'Test Product Raw SQL', 1000, 10, 'active', NOW(), NOW())";
        $pdo->exec($sql);
        $insertId = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ SUCCESS! Raw SQL insert worked. ID: {$insertId}</p>";
        
        // Clean up
        $pdo->exec("DELETE FROM products WHERE id = {$insertId}");
        echo "<p style='color: blue;'>ℹ Test product cleaned up (deleted)</p>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ RAW SQL INSERT FAILED!</p>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>SQL State:</strong> " . $e->getCode() . "</p>";
    }
    
    echo "<h2>Step 6: Check Foreign Key Constraints</h2>";
    
    // Check if business_id = 1 exists
    $business = $db->find('businesses', 1);
    if ($business) {
        echo "<p style='color: green;'>✅ Business ID 1 exists</p>";
        echo "<pre>" . json_encode($business, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Business ID 1 does NOT exist!</p>";
        echo "<p>This could be why inserts are failing - foreign key constraint.</p>";
        
        // Show existing businesses
        $businesses = $db->findAll('businesses');
        echo "<p>Existing businesses:</p>";
        echo "<pre>" . json_encode($businesses, JSON_PRETTY_PRINT) . "</pre>";
    }
    
    echo "<h2>Conclusion</h2>";
    echo "<p>If all tests passed, the database is working correctly.</p>";
    echo "<p>If tests failed, check the error messages above.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ FATAL ERROR!</p>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/wrightcommerce/public/'>← Back to API</a></p>";
?>