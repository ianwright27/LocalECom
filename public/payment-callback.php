<?php
/**
 * Payment Callback Handler
 * 
 * This page is called after Paystack payment
 * It verifies the payment and updates the order status
 * 
 * Place in: C:\xampp\htdocs\wrightcommerce\public\payment-callback.php
 */

session_start();
require_once '../config/database.php';
require_once '../config/paystack.php';
require_once '../app/helpers/Database.php';

$db = Database::getInstance();

$reference = $_GET['reference'] ?? '';
$orderId = $_GET['order_id'] ?? '';

if (!$reference || !$orderId) {
    die('Invalid payment reference');
}

// Verify payment with Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - WrightCommerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; padding: 50px 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .icon { font-size: 80px; margin-bottom: 20px; }
        .success { color: #2ecc71; }
        .error { color: #e74c3c; }
        h1 { margin-bottom: 20px; }
        .details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; }
        .btn { display: inline-block; padding: 12px 30px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($result['status'] && $result['data']['status'] === 'success'): 
            $data = $result['data'];
            
            // Update database
            try {
                $db->beginTransaction();
                
                // Get order
                $order = $db->find('orders', $orderId);
                
                // Create or update payment record
                $paymentData = [
                    'business_id' => $order['business_id'],
                    'order_id' => $orderId,
                    'reference' => $reference,
                    'amount' => $data['amount'] / 100, // Convert from kobo
                    'currency' => $data['currency'],
                    'payment_method' => 'paystack',
                    'status' => 'completed',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'metadata' => json_encode($data)
                ];
                
                // Check if payment exists
                $existingPayment = $db->find('payments', ['reference' => $reference]);
                
                if ($existingPayment) {
                    $db->update('payments', $existingPayment['id'], $paymentData);
                } else {
                    $db->insert('payments', $paymentData);
                }
                
                // Update order status
                $db->update('orders', $orderId, [
                    'payment_status' => 'paid',
                    'status' => 'processing'
                ]);
                
                $db->commit();
                
                // Update session
                $_SESSION['last_order'] = [
                    'id' => $orderId,
                    'order_number' => $order['order_number'],
                    'total' => $order['total'],
                    'status' => 'processing',
                    'payment_status' => 'paid'
                ];
        ?>
            <div class="icon success">✅</div>
            <h1>Payment Successful!</h1>
            <p>Your payment has been received and your order is being processed.</p>
            
            <div class="details">
                <p><strong>Order Number:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                <p><strong>Amount Paid:</strong> KES <?= number_format($data['amount'] / 100) ?></p>
                <p><strong>Payment Method:</strong> <?= ucfirst($data['channel']) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($reference) ?></p>
                <p><strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($data['paid_at'])) ?></p>
            </div>
            
            <p style="margin-top: 20px;">Thank you for your purchase!</p>
            <a href="shop.php" class="btn">Continue Shopping</a>
            
        <?php 
            } catch (Exception $e) {
                $db->rollback();
                error_log("Payment callback error: " . $e->getMessage());
        ?>
            <div class="icon error">❌</div>
            <h1>Processing Error</h1>
            <p>Payment was successful but there was an error updating your order.</p>
            <p>Reference: <?= htmlspecialchars($reference) ?></p>
            <p>Please contact support with this reference number.</p>
            <a href="shop.php" class="btn">Go Back</a>
        <?php } ?>
        
        <?php else: ?>
            <div class="icon error">❌</div>
            <h1>Payment Failed</h1>
            <p>Your payment could not be processed.</p>
            <p><?= htmlspecialchars($result['message'] ?? 'Please try again or contact support.') ?></p>
            <a href="shop.php?page=payment&order_id=<?= $orderId ?>" class="btn">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>