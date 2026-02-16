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
            $action = $_POST['action'] ?? '';
            
            // Handle custom WhatsApp template test
            if ($action === 'test_custom_template') {
                $templateName = $_POST['template_name'] ?? '';
                $testPhone = $_POST['test_phone'] ?? '';
                $languageCode = $_POST['language_code'] ?? 'en_US';
                $hasParameters = isset($_POST['has_parameters']) && $_POST['has_parameters'] === 'yes';
                
                // Build parameters array only if template has parameters
                $parameters = [];
                if ($hasParameters) {
                    for ($i = 1; $i <= 4; $i++) {
                        $paramValue = $_POST["param_$i"] ?? '';
                        if (!empty($paramValue)) {
                            $parameters[] = [
                                'type' => 'text',
                                'text' => $paramValue
                            ];
                        }
                    }
                }
                
                echo "<div class='result'>";
                echo "<h3>🧪 Custom Template Test Results:</h3>";
                echo "<p><strong>Template:</strong> $templateName</p>";
                echo "<p><strong>Phone:</strong> $testPhone</p>";
                echo "<p><strong>Language:</strong> $languageCode</p>";
                echo "<p><strong>Has Parameters:</strong> " . ($hasParameters ? 'Yes' : 'No') . "</p>";
                if ($hasParameters) {
                    echo "<p><strong>Parameters:</strong> " . count($parameters) . " parameter(s)</p>";
                }
                
                try {
                    require_once '../app/services/WhatsAppProvider.php';
                    $whatsapp = new WhatsAppProvider();
                    
                    $result = $whatsapp->sendTemplate(
                        $testPhone,
                        $templateName,
                        $parameters, // Empty array if no parameters
                        $languageCode
                    );
                    
                    if ($result['success']) {
                        echo "<div class='success' style='margin-top: 15px;'>";
                        echo "<strong>✅ WhatsApp Message Sent Successfully!</strong><br>";
                        echo "Message ID: " . ($result['message_id'] ?? 'N/A') . "<br>";
                        echo "Sent to: " . ($result['phone'] ?? $testPhone);
                        echo "</div>";
                    } else {
                        echo "<div class='error' style='margin-top: 15px;'>";
                        echo "<strong>❌ Failed to Send WhatsApp Message</strong><br>";
                        echo "Error: " . ($result['error'] ?? 'Unknown error') . "<br>";
                        if (isset($result['error_code'])) {
                            echo "Error Code: " . $result['error_code'] . "<br>";
                        }
                        if (isset($result['http_code'])) {
                            echo "HTTP Code: " . $result['http_code'] . "<br>";
                        }
                        echo "</div>";
                        
                        // Show full response for debugging
                        echo "<div style='margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid #e74c3c;'>";
                        echo "<strong>🔍 Full API Response (for debugging):</strong>";
                        echo "<pre style='margin-top: 10px; font-size: 12px; overflow-x: auto;'>" . print_r($result, true) . "</pre>";
                        echo "</div>";
                        
                        // Provide helpful error messages
                        echo "<div class='warning' style='margin-top: 15px;'>";
                        echo "<strong>💡 Common Issues:</strong><ul style='margin: 10px 0; padding-left: 20px;'>";
                        echo "<li>Template name doesn't match exactly (case-sensitive)</li>";
                        echo "<li>Template not approved by Meta yet</li>";
                        echo "<li>Wrong 'Has Parameters' setting (check if your template has {{1}}, {{2}}, etc.)</li>";
                        echo "<li>Wrong number of parameters (must match template exactly)</li>";
                        echo "<li>Phone number format incorrect</li>";
                        echo "<li>WhatsApp credentials not configured in config/whatsapp.php</li>";
                        echo "<li>Access token expired (regenerate from Meta dashboard)</li>";
                        echo "<li>Phone Number ID incorrect</li>";
                        echo "<li>Check XAMPP error logs: C:\\xampp\\apache\\logs\\error.log</li>";
                        echo "</ul></div>";
                    }
                    
                    echo "<div style='margin-top: 15px; padding: 15px; background: #e3f2fd; border-radius: 4px; border-left: 3px solid #2196F3;'>";
                    echo "<strong>📋 Request Details:</strong>";
                    echo "<pre style='margin-top: 10px; font-size: 12px;'>";
                    echo "Template: $templateName\n";
                    echo "Phone: $testPhone\n";
                    echo "Language: $languageCode\n";
                    echo "Parameters: " . ($hasParameters ? count($parameters) : '0') . "\n";
                    if (!empty($parameters)) {
                        echo "Parameter Values:\n";
                        foreach ($parameters as $i => $param) {
                            echo "  [" . ($i + 1) . "] " . $param['text'] . "\n";
                        }
                    }
                    echo "</pre>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error' style='margin-top: 15px;'>";
                    echo "<strong>❌ Exception:</strong> " . $e->getMessage();
                    echo "</div>";
                }
                
                echo "</div>";
                
            } else {
                // Original notification test logic
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
        
        <!-- Test Custom WhatsApp Template -->
        <div class="channel" style="border-left-color: #128C7E; background: #f0fff4;">
            <h3>🧪 Test Custom WhatsApp Template</h3>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                Test a specific WhatsApp template you created in your Meta Business dashboard
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_custom_template">
                
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" name="template_name" required placeholder="e.g., order_confirmation">
                    <small style="color: #666; font-size: 12px;">Exact template name from Meta dashboard (must be approved)</small>
                </div>
                
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="test_phone" required placeholder="0712345678 or +254712345678">
                </div>
                
                <div class="form-group">
                    <label>Language Code</label>
                    <input type="text" name="language_code" value="en_US" placeholder="en_US">
                    <small style="color: #666; font-size: 12px;">Template language: en_US, en_GB, es, fr, etc. (use underscore format)</small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer; background: #fff3cd; padding: 15px; border-radius: 4px;">
                        <input type="checkbox" name="has_parameters" value="yes" id="hasParams" style="width: 20px; height: 20px; margin-right: 10px;" onchange="toggleParameters()">
                        <span><strong>My template has parameters ({{1}}, {{2}}, etc.)</strong></span>
                    </label>
                    <small style="color: #666; font-size: 12px;">Check this ONLY if your template includes variables like {{1}}, {{2}}, {{3}}</small>
                </div>
                
                <div id="parametersSection" style="display: none;">
                    <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 15px; border-left: 3px solid #ffc107;">
                        <strong>📝 Template Parameters:</strong>
                        <p style="font-size: 13px; margin-top: 5px; color: #666;">
                            Enter values for each variable in your template
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>Parameter 1 - {{1}} (Customer Name)</label>
                        <input type="text" name="param_1" placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label>Parameter 2 - {{2}} (Order Number)</label>
                        <input type="text" name="param_2" placeholder="ORD-20260215-ABC123">
                    </div>
                    
                    <div class="form-group">
                        <label>Parameter 3 - {{3}} (Amount/Total)</label>
                        <input type="text" name="param_3" placeholder="KES 5,000">
                    </div>
                    
                    <div class="form-group">
                        <label>Parameter 4 - {{4}} (Optional)</label>
                        <input type="text" name="param_4" placeholder="Additional info">
                    </div>
                </div>
                
                <script>
                function toggleParameters() {
                    const checkbox = document.getElementById('hasParams');
                    const section = document.getElementById('parametersSection');
                    section.style.display = checkbox.checked ? 'block' : 'none';
                }
                </script>
                
                <button type="submit" class="btn" style="background: #25D366; padding: 12px 30px;">
                    🚀 Send Custom WhatsApp Template
                </button>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 4px; border-left: 3px solid #2196F3;">
                <strong>💡 Example Template Structure:</strong>
                <p style="margin-top: 10px; font-size: 13px; font-family: monospace; background: white; padding: 10px; border-radius: 4px;">
                    Hi {{1}}, your order {{2}} for {{3}} has been received! We'll notify you when it's ready.
                </p>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    {{1}} = Parameter 1 (Customer Name)<br>
                    {{2}} = Parameter 2 (Order Number)<br>
                    {{3}} = Parameter 3 (Amount)
                </p>
            </div>
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