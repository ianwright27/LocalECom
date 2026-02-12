<?php
/**
 * Sample Data Generator
 * Creates realistic test products for WrightCommerce
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\public\seed-products.php
 * Run once via: http://localhost/wrightcommerce/public/seed-products.php
 * 
 * This will create 20 test products across different categories
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../app/helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>WrightCommerce - Sample Data Generator</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .product { padding: 10px; margin: 5px 0; background: #f9f9f9; border-left: 3px solid #4CAF50; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn:hover { background: #45a049; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📦 WrightCommerce Sample Data Generator</h1>";

try {
    $db = Database::getInstance();
    
    echo "<p class='info'>Connected to database: " . DB_NAME . "</p>";
    
    // Check if user is logged in (has a business)
    session_start();
    $businessId = $_SESSION['business_id'] ?? null;
    
    if (!$businessId) {
        // Try to get first business
        $businesses = $db->findAll('businesses', [], 'id ASC', 1);
        if (empty($businesses)) {
            echo "<p class='error'>❌ No business found! Please register a user first.</p>";
            echo "<p><a href='/wrightcommerce/public/auth/register' class='btn'>Register Now</a></p>";
            exit;
        }
        $businessId = $businesses[0]['id'];
        echo "<p class='warning'>⚠️ Not logged in. Using Business ID: {$businessId}</p>";
    } else {
        echo "<p class='success'>✅ Using your business (ID: {$businessId})</p>";
    }
    
    // Sample products data - Kenyan market focused
    $sampleProducts = [
        // Electronics
        [
            'name' => 'iPhone 15 Pro Max',
            'description' => '256GB, Titanium Blue, Latest Apple flagship with A17 Pro chip',
            'price' => 185000,
            'cost_price' => 165000,
            'stock' => 8,
            'sku' => 'IP15-PM-256-BLU',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'Samsung Galaxy S24 Ultra',
            'description' => '512GB, Phantom Black, S Pen included, 200MP camera',
            'price' => 155000,
            'cost_price' => 135000,
            'stock' => 12,
            'sku' => 'SAM-S24U-512',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'MacBook Air M3',
            'description' => '13-inch, 16GB RAM, 512GB SSD, Midnight color',
            'price' => 210000,
            'cost_price' => 190000,
            'stock' => 5,
            'sku' => 'MAC-AIR-M3-512',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'Dell XPS 15',
            'description' => 'Intel i7, 16GB RAM, 1TB SSD, OLED Display',
            'price' => 180000,
            'cost_price' => 160000,
            'stock' => 6,
            'sku' => 'DELL-XPS15-I7',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'Sony WH-1000XM5 Headphones',
            'description' => 'Wireless, Noise Cancelling, Premium Sound Quality',
            'price' => 45000,
            'cost_price' => 38000,
            'stock' => 15,
            'sku' => 'SONY-WH1000XM5',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'iPad Pro 12.9" M2',
            'description' => '256GB, Space Gray, Apple Pencil compatible',
            'price' => 135000,
            'cost_price' => 120000,
            'stock' => 7,
            'sku' => 'IPAD-PRO-129-M2',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        
        // Home Appliances
        [
            'name' => 'LG 55" OLED Smart TV',
            'description' => '4K OLED, WebOS, HDR10, Perfect for living room',
            'price' => 125000,
            'cost_price' => 105000,
            'stock' => 4,
            'sku' => 'LG-OLED55-4K',
            'category' => 'Home Appliances',
            'status' => 'active'
        ],
        [
            'name' => 'Samsung Washing Machine',
            'description' => '8kg Front Load, Digital Inverter, Energy Efficient',
            'price' => 55000,
            'cost_price' => 45000,
            'stock' => 6,
            'sku' => 'SAM-WM-8KG-FL',
            'category' => 'Home Appliances',
            'status' => 'active'
        ],
        [
            'name' => 'Hisense Refrigerator',
            'description' => '350L Double Door, Frost Free, Low Power Consumption',
            'price' => 65000,
            'cost_price' => 52000,
            'stock' => 3,
            'sku' => 'HIS-REF-350L',
            'category' => 'Home Appliances',
            'status' => 'active'
        ],
        [
            'name' => 'Ramtons Microwave Oven',
            'description' => '25L, Digital Display, 900W, Made for Kenyan Kitchens',
            'price' => 12000,
            'cost_price' => 9500,
            'stock' => 20,
            'sku' => 'RAM-MW-25L',
            'category' => 'Home Appliances',
            'status' => 'active'
        ],
        
        // Fashion & Accessories
        [
            'name' => 'Nike Air Max 270',
            'description' => 'Running Shoes, Size 42, Black/White colorway',
            'price' => 15000,
            'cost_price' => 11000,
            'stock' => 25,
            'sku' => 'NIKE-AM270-BW-42',
            'category' => 'Fashion',
            'status' => 'active'
        ],
        [
            'name' => 'Adidas Backpack',
            'description' => 'Classic 3-Stripes, Laptop compartment, 30L capacity',
            'price' => 4500,
            'cost_price' => 3200,
            'stock' => 40,
            'sku' => 'ADI-BP-30L',
            'category' => 'Fashion',
            'status' => 'active'
        ],
        [
            'name' => 'Ray-Ban Aviator Sunglasses',
            'description' => 'Classic Gold Frame, UV Protection, Original',
            'price' => 18000,
            'cost_price' => 14000,
            'stock' => 12,
            'sku' => 'RB-AVI-GOLD',
            'category' => 'Fashion',
            'status' => 'active'
        ],
        
        // Office Supplies
        [
            'name' => 'HP LaserJet Printer',
            'description' => 'Wireless, Auto Duplex, 40ppm, Perfect for office',
            'price' => 35000,
            'cost_price' => 28000,
            'stock' => 8,
            'sku' => 'HP-LJ-W-40',
            'category' => 'Office Supplies',
            'status' => 'active'
        ],
        [
            'name' => 'Executive Office Chair',
            'description' => 'Ergonomic, Leather, Adjustable Height & Arms',
            'price' => 18000,
            'cost_price' => 14000,
            'stock' => 10,
            'sku' => 'CHAIR-EXEC-LTH',
            'category' => 'Office Supplies',
            'status' => 'active'
        ],
        [
            'name' => 'Standing Desk Adjustable',
            'description' => 'Electric Height Adjustment, 120x60cm, White',
            'price' => 45000,
            'cost_price' => 35000,
            'stock' => 5,
            'sku' => 'DESK-STAND-120',
            'category' => 'Office Supplies',
            'status' => 'active'
        ],
        
        // Low Stock Items (for testing alerts)
        [
            'name' => 'Wireless Mouse Logitech',
            'description' => 'MX Master 3S, Programmable buttons, Long battery',
            'price' => 8500,
            'cost_price' => 6500,
            'stock' => 3,
            'sku' => 'LOG-MX3S',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'USB-C Hub 7-in-1',
            'description' => 'HDMI, USB 3.0, SD Card Reader, Fast charging',
            'price' => 3500,
            'cost_price' => 2500,
            'stock' => 5,
            'sku' => 'HUB-USBC-7IN1',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        
        // Out of Stock (for testing)
        [
            'name' => 'PlayStation 5 Console',
            'description' => 'Disc Version, 825GB SSD, DualSense Controller',
            'price' => 75000,
            'cost_price' => 65000,
            'stock' => 0,
            'sku' => 'PS5-DISC-825',
            'category' => 'Electronics',
            'status' => 'active'
        ],
        [
            'name' => 'AirPods Pro 2nd Gen',
            'description' => 'Active Noise Cancellation, Wireless Charging Case',
            'price' => 32000,
            'cost_price' => 28000,
            'stock' => 0,
            'sku' => 'APP-PRO-2GEN',
            'category' => 'Electronics',
            'status' => 'active'
        ]
    ];
    
    echo "<h2>Creating Sample Products...</h2>";
    
    $created = 0;
    $skipped = 0;
    
    foreach ($sampleProducts as $product) {
        // Check if SKU already exists
        if ($db->exists('products', ['sku' => $product['sku']])) {
            echo "<p class='error'>⏭️ Skipped: {$product['name']} (SKU already exists)</p>";
            $skipped++;
            continue;
        }
        
        // Add business_id
        $product['business_id'] = $businessId;
        
        try {
            $productId = $db->insert('products', $product);
            echo "<div class='product'>";
            echo "<strong>✅ Created: {$product['name']}</strong><br>";
            echo "Price: KES " . number_format($product['price']) . " | Stock: {$product['stock']} | SKU: {$product['sku']}<br>";
            echo "ID: {$productId}";
            echo "</div>";
            $created++;
        } catch (Exception $e) {
            echo "<p class='error'>❌ Failed to create {$product['name']}: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<h2>Summary</h2>";
    echo "<p class='success'>✅ Successfully created: <strong>{$created} products</strong></p>";
    if ($skipped > 0) {
        echo "<p class='info'>⏭️ Skipped (already exist): <strong>{$skipped} products</strong></p>";
    }
    
    // Get statistics
    $stats = [
        'total' => $db->count('products', ['business_id' => $businessId, 'status' => 'active']),
        'out_of_stock' => $db->count('products', ['business_id' => $businessId, 'stock' => 0]),
    ];
    
    $lowStockSql = "SELECT COUNT(*) as count FROM products WHERE business_id = ? AND stock > 0 AND stock < 10";
    $lowStockResult = $db->query($lowStockSql, [$businessId]);
    $stats['low_stock'] = $lowStockResult[0]['count'];
    
    echo "<h2>Your Inventory Stats</h2>";
    echo "<p>📦 Total Products: <strong>{$stats['total']}</strong></p>";
    echo "<p>⚠️ Low Stock (< 10): <strong>{$stats['low_stock']}</strong></p>";
    echo "<p>🚫 Out of Stock: <strong>{$stats['out_of_stock']}</strong></p>";
    
    echo "<hr>";
    echo "<h2>What's Next?</h2>";
    echo "<p>✅ Sample data created successfully!</p>";
    echo "<p>Now you can test all product features:</p>";
    echo "<ul>
        <li>View all products: <a href='/wrightcommerce/public/api/v1/products'>/api/v1/products</a></li>
        <li>Low stock alerts: <a href='/wrightcommerce/public/api/v1/products/low-stock'>/api/v1/products/low-stock</a></li>
        <li>Out of stock: <a href='/wrightcommerce/public/api/v1/products/out-of-stock'>/api/v1/products/out-of-stock</a></li>
        <li>Product stats: <a href='/wrightcommerce/public/api/v1/products/stats'>/api/v1/products/stats</a></li>
        <li>Categories: <a href='/wrightcommerce/public/api/v1/products/categories'>/api/v1/products/categories</a></li>
    </ul>";
    
    echo "<a href='admin.php' class='btn'>🎨 Go to Admin Panel</a> ";
    echo "<a href='/wrightcommerce/public/' class='btn'>🏠 Back to API</a>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "
    </div>
</body>
</html>";
?>