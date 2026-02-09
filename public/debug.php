<?php
/**
 * Router Debug Script
 * Place this temporarily in public/debug.php to troubleshoot routing issues
 * DELETE THIS FILE after debugging!
 */

echo "<h1>WrightCommerce Router Debug</h1>";

echo "<h2>Server Information</h2>";
echo "<pre>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'NOT SET') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n";
echo "</pre>";

echo "<h2>Detected Base Path</h2>";
echo "<pre>";
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = dirname($scriptName);
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}
echo "Base Path: '{$basePath}'\n";
echo "</pre>";

echo "<h2>Test URLs</h2>";
echo "<ul>";
echo "<li><a href='/wrightcommerce/public/'>Root endpoint (/)</a></li>";
echo "<li><a href='/wrightcommerce/public/test-db'>Database test (/test-db)</a></li>";
echo "<li><a href='/wrightcommerce/public/api/v1/products'>Products endpoint (/api/v1/products)</a></li>";
echo "</ul>";

echo "<h2>Files Check</h2>";
echo "<pre>";
$files = [
    '../config/database.php' => 'Database Config',
    '../app/helpers/Database.php' => 'Database Helper',
    '../app/helpers/Router.php' => 'Router Helper',
    '../app/controllers/BaseController.php' => 'Base Controller',
    '../app/controllers/ProductController.php' => 'Product Controller',
    'index.php' => 'Main Index'
];

foreach ($files as $file => $name) {
    $exists = file_exists($file);
    $status = $exists ? '✅ EXISTS' : '❌ MISSING';
    echo "{$status} - {$name} ({$file})\n";
}
echo "</pre>";

echo "<h2>Quick Fix</h2>";
echo "<p>If the root endpoint (/) is not working, try this:</p>";
echo "<ol>";
echo "<li>Make sure you've replaced the old index.php with the new one</li>";
echo "<li>Make sure you've updated Router.php with the auto-detect code</li>";
echo "<li>Clear your browser cache or use Incognito mode</li>";
echo "<li>Restart Apache in XAMPP</li>";
echo "</ol>";
?>