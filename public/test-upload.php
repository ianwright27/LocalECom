<?php
/**
 * Image Upload Debug Script
 * Place in: C:\xampp\htdocs\wrightcommerce\public\test-upload.php
 * Access via: http://localhost/wrightcommerce/public/test-upload.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Image Upload Debug Test</h1>";

// Test 1: Check if uploads directory exists
echo "<h2>Test 1: Check Directories</h2>";
$publicDir = __DIR__;
$uploadsDir = $publicDir . '/uploads';
$productsDir = $uploadsDir . '/products';

echo "<p>Public directory: <code>{$publicDir}</code> - " . (is_dir($publicDir) ? '✅ Exists' : '❌ Not found') . "</p>";
echo "<p>Uploads directory: <code>{$uploadsDir}</code> - " . (is_dir($uploadsDir) ? '✅ Exists' : '❌ Not found') . "</p>";
echo "<p>Products directory: <code>{$productsDir}</code> - " . (is_dir($productsDir) ? '✅ Exists' : '❌ Not found') . "</p>";

// Test 2: Check write permissions
echo "<h2>Test 2: Check Permissions</h2>";
if (is_dir($uploadsDir)) {
    echo "<p>Uploads writable: " . (is_writable($uploadsDir) ? '✅ Yes' : '❌ No') . "</p>";
}
if (is_dir($productsDir)) {
    echo "<p>Products writable: " . (is_writable($productsDir) ? '✅ Yes' : '❌ No') . "</p>";
}

// Test 3: Test file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "<h2>Test 3: Upload Test Results</h2>";
    
    $file = $_FILES['test_image'];
    
    echo "<p><strong>File Info:</strong></p>";
    echo "<pre>" . print_r($file, true) . "</pre>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<p>✅ File uploaded to temp location: {$file['tmp_name']}</p>";
        
        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        echo "<p>MIME Type: {$mimeType}</p>";
        echo "<p>File Size: " . number_format($file['size']) . " bytes</p>";
        
        // Try to create directories if they don't exist
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            echo "<p>✅ Created uploads directory</p>";
        }
        if (!is_dir($productsDir)) {
            mkdir($productsDir, 0755, true);
            echo "<p>✅ Created products directory</p>";
        }
        
        // Generate filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $destination = $productsDir . '/' . $filename;
        
        echo "<p>Destination: <code>{$destination}</code></p>";
        
        // Try to move file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo "<p style='color: green;'><strong>✅ SUCCESS! File uploaded successfully!</strong></p>";
            echo "<p>File saved as: <code>products/{$filename}</code></p>";
            echo "<p><img src='uploads/products/{$filename}' style='max-width: 300px; border: 1px solid #ddd;'></p>";
        } else {
            echo "<p style='color: red;'><strong>❌ FAILED to move uploaded file!</strong></p>";
            echo "<p>Check folder permissions</p>";
        }
    } else {
        echo "<p style='color: red;'>Upload error code: {$file['error']}</p>";
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        echo "<p>" . ($errors[$file['error']] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<h2>Test 3: Upload a Test Image</h2>";
    ?>
    <form method="POST" enctype="multipart/form-data" style="border: 1px solid #ddd; padding: 20px; max-width: 400px;">
        <p>Select an image to test upload:</p>
        <input type="file" name="test_image" accept="image/*" required>
        <br><br>
        <button type="submit" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer;">Upload Test Image</button>
    </form>
    <?php
}

// Test 4: PHP configuration
echo "<h2>Test 4: PHP Upload Settings</h2>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";

echo "<hr>";
echo "<p><a href='admin.php'>← Back to Admin Panel</a></p>";
?>