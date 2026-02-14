<?php
/**
 * Notification Test Script
 * 
 * Tests all notification channels
 * Place in: C:\xampp\htdocs\wrightcommerce\public\test-notifications.php
 * Access via: http://localhost/wrightcommerce/public/test-notifications.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../app/helpers/Database.php';
require_once '../app/services/NotificationService.php';

$db = Database::getInstance();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    die('Please <a href="admin.php">login</a> first');
}

$businessId = $_SESSION['business_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Testing - WrightCommerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 20px; color: #2c3e50; }
        .channel { background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #3498db; }
        .channel h3 { margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2980b9; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?page=notifications" class="back-link">← Back to Settings</a>
        
        <h1>🔔 Notification Testing</h1>
        
        <div class="warning" style="margin-bottom: 30px;">
            <strong>⚠️ Important:</strong> Make sure you have configured your API credentials in the config files before testing.
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $channel = $_POST['channel'] ?? '';
            $testPhone = $_POST['test_phone'] ?? '';
            $testEmail = $_POST['test_email'] ?? '';
            $testName = $_POST['test_name'] ?? 'Test User';
            
            $notification = new NotificationService($db);
            
            echo "<div class='result success'>";
            echo "<h3>Test Results:</h3>";
            
            $recipient = [
                'name' => $testName,
                'phone' => $testPhone,
                'email' => $testEmail
            ];
            
            $data = [
                'name' => $testName,
                'order_number' => 'TEST-' . time(),
                'total' => '5,000',
                'items_count' => '3',
                'amount' => '5,000',
                'payment_method' => 'M-PESA'
            ];
            
            // Force specific channel or use all enabled
            $channels = $channel ? [$channel] : null;
            
            $result = $notification->send(
                $businessId,
                'order_placed',
                $recipient,
                $data,
                $channels
            );
            
            echo "<pre>" . print_r($result, true) . "</pre>";
            echo "</div>";
        }
        ?>
        
        <!-- Test Email -->
        <div class="channel" style="border-left-color: #2ecc71;">
            <h3>📧 Test Email Notification</h3>
            <form method="POST">
                <input type="hidden" name="channel" value="email">
                <div class="form-group">
                    <label>Your Email</label>
                    <input type="email" name="test_email" required placeholder="test@example.com">
                </div>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="test_name" required placeholder="John Doe">
                </div>
                <button type="submit" class="btn">Send Test Email</button>
            </form>
        </div>
        
        <!-- Test SMS -->
        <div class="channel" style="border-left-color: #3498db;">
            <h3>📱 Test SMS Notification</h3>
            <form method="POST">
                <input type="hidden" name="channel" value="sms">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="test_phone" required placeholder="0712345678 or +254712345678">
                    <small style="color: #666;">Kenyan format: 07XX or +2547XX</small>
                </div>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="test_name" required placeholder="John Doe">
                </div>
                <button type="submit" class="btn">Send Test SMS</button>
            </form>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                ⚠️ Make sure you have configured Africa's Talking credentials in <code>config/africastalking.php</code>
            </p>
        </div>
        
        <!-- Test WhatsApp -->
        <div class="channel" style="border-left-color: #25D366;">
            <h3>💬 Test WhatsApp Notification</h3>
            <form method="POST">
                <input type="hidden" name="channel" value="whatsapp">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="test_phone" required placeholder="0712345678 or +254712345678">
                </div>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="test_name" required placeholder="John Doe">
                </div>
                <button type="submit" class="btn">Send Test WhatsApp</button>
            </form>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                ⚠️ Make sure you have configured Meta WhatsApp credentials and approved templates in <code>config/whatsapp.php</code>
            </p>
        </div>
        
        <!-- Test All Channels -->
        <div class="channel" style="border-left-color: #e67e22;">
            <h3>🚀 Test All Enabled Channels</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="test_email" required placeholder="test@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="test_phone" required placeholder="0712345678">
                </div>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="test_name" required placeholder="John Doe">
                </div>
                <button type="submit" class="btn" style="background: #e67e22;">Send to All Enabled Channels</button>
            </form>
            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                This will send notifications through all channels you have enabled in settings.
            </p>
        </div>
        
        <!-- Configuration Status -->
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">📋 Configuration Status</h3>
            
            <?php
            $business = $db->find('businesses', $businessId);
            $settings = json_decode($business['settings'] ?? '{}', true);
            $notifications = $settings['notifications'] ?? [];
            
            $smsEnabled = $notifications['sms_enabled'] ?? false;
            $whatsappEnabled = $notifications['whatsapp_enabled'] ?? false;
            $emailEnabled = $notifications['email_enabled'] ?? true;
            ?>
            
            <p>
                📧 Email: <strong><?= $emailEnabled ? '✅ Enabled' : '❌ Disabled' ?></strong>
            </p>
            <p>
                📱 SMS: <strong><?= $smsEnabled ? '✅ Enabled' : '❌ Disabled' ?></strong>
            </p>
            <p>
                💬 WhatsApp: <strong><?= $whatsappEnabled ? '✅ Enabled' : '❌ Disabled' ?></strong>
            </p>
            
            <p style="margin-top: 15px; font-size: 13px; color: #666;">
                <a href="admin.php?page=notifications">Change notification settings →</a>
            </p>
        </div>
    </div>
</body>
</html>