<?php
/**
 * PaystackController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles Paystack payment integration for M-PESA and card payments
 * 
 * Setup:
 * 1. Get API keys from https://dashboard.paystack.com/#/settings/developer
 * 2. Add to config/paystack.php:
 *    - PAYSTACK_PUBLIC_KEY (for frontend)
 *    - PAYSTACK_SECRET_KEY (for backend)
 * 3. Set webhook URL in Paystack dashboard
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class PaystackController extends BaseController {
    
    private $secretKey;
    private $publicKey;
    private $baseUrl = 'https://api.paystack.co';
    
    public function __construct() {
        parent::__construct();
        
        // Load Paystack config
        $configFile = __DIR__ . '/../../config/paystack.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->secretKey = PAYSTACK_SECRET_KEY ?? '';
            $this->publicKey = PAYSTACK_PUBLIC_KEY ?? '';
        } else {
            $this->secretKey = '';
            $this->publicKey = '';
        }
    }
    
    /**
     * POST /api/v1/payments/paystack/initialize
     * Initialize Paystack payment
     * 
     * Required:
     * - order_id: Order ID to pay for
     * - email: Customer email
     * - amount: Amount in kobo (multiply by 100)
     */
    public function initialize() {
        $this->validate([
            'order_id' => 'required|exists:orders,id',
            'email' => 'required|email',
            'amount' => 'required|numeric'
        ]);
        
        $orderId = $this->input('order_id');
        $email = $this->input('email');
        $amount = $this->input('amount'); // Already in kobo
        
        // Get order details
        $order = $this->db->find('orders', $orderId);
        
        if (!$order) {
            return $this->notFound('Order not found');
        }
        
        if ($order['payment_status'] === 'paid') {
            return $this->error('Order already paid', 400);
        }
        
        // Prepare payment data
        $reference = 'WC-' . $order['order_number'] . '-' . time();
        
        $postData = [
            'email' => $email,
            'amount' => $amount, // Amount in kobo (KES * 100)
            'currency' => 'KES',
            'reference' => $reference,
            'callback_url' => $this->input('callback_url', 'http://localhost/wrightcommerce/public/payment-callback.php'),
            'metadata' => [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'customer_name' => $this->input('customer_name', '')
            ]
        ];
        
        // Call Paystack API
        $response = $this->makePaystackRequest('/transaction/initialize', 'POST', $postData);
        
        if ($response['status']) {
            // Save payment record
            $paymentId = $this->db->insert('payments', [
                'business_id' => $order['business_id'],
                'order_id' => $orderId,
                'reference' => $reference,
                'amount' => $order['total'],
                'currency' => 'KES',
                'payment_method' => 'paystack',
                'status' => 'pending',
                'metadata' => json_encode($response['data'])
            ]);
            
            $this->log("Payment initialized for Order {$order['order_number']}: Reference {$reference}");
            
            return $this->success([
                'payment_id' => $paymentId,
                'reference' => $reference,
                'authorization_url' => $response['data']['authorization_url'],
                'access_code' => $response['data']['access_code']
            ], 'Payment initialized successfully');
        } else {
            return $this->error('Failed to initialize payment: ' . ($response['message'] ?? 'Unknown error'), 500);
        }
    }
    
    /**
     * GET /api/v1/payments/paystack/verify/{reference}
     * Verify Paystack payment
     */
    public function verify($reference) {
        if (!$reference) {
            return $this->error('Payment reference required', 400);
        }
        
        // Call Paystack verify API
        $response = $this->makePaystackRequest("/transaction/verify/{$reference}", 'GET');
        
        if ($response['status'] && $response['data']['status'] === 'success') {
            $data = $response['data'];
            
            // Get payment record
            $payment = $this->db->find('payments', ['reference' => $reference]);
            
            if (!$payment) {
                return $this->notFound('Payment record not found');
            }
            
            // Get order
            $order = $this->db->find('orders', $payment['order_id']);
            
            try {
                $this->db->beginTransaction();
                
                // Update payment status
                $this->db->update('payments', $payment['id'], [
                    'status' => 'completed',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'metadata' => json_encode($data)
                ]);
                
                // Update order payment status
                $this->db->update('orders', $order['id'], [
                    'payment_status' => 'paid',
                    'status' => 'processing' // Move to processing after payment
                ]);
                
                $this->db->commit();
                
                $this->log("Payment verified for Order {$order['order_number']}: Reference {$reference}");
                
                return $this->success([
                    'order' => $order,
                    'payment' => $payment,
                    'transaction' => $data
                ], 'Payment verified successfully');
                
            } catch (Exception $e) {
                $this->db->rollback();
                $this->log("Payment verification failed: " . $e->getMessage(), 'error');
                return $this->serverError('Failed to update payment status');
            }
        } else {
            return $this->error('Payment verification failed', 400);
        }
    }
    
    /**
     * POST /webhooks/paystack
     * Handle Paystack webhook
     * 
     * This is called by Paystack when payment status changes
     */
    public function webhook() {
        // Get raw POST data
        $input = @file_get_contents("php://input");
        
        // Verify signature
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        
        if (!$signature || $signature !== hash_hmac('sha512', $input, $this->secretKey)) {
            $this->log("Invalid webhook signature", 'warning');
            return $this->error('Invalid signature', 401);
        }
        
        $event = json_decode($input, true);
        
        $this->log("Paystack webhook received: " . $event['event']);
        
        // Handle different events
        switch ($event['event']) {
            case 'charge.success':
                $this->handleSuccessfulCharge($event['data']);
                break;
                
            case 'charge.failed':
                $this->handleFailedCharge($event['data']);
                break;
                
            default:
                $this->log("Unhandled webhook event: {$event['event']}");
        }
        
        // Always return 200 to acknowledge receipt
        http_response_code(200);
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    /**
     * Handle successful charge webhook
     */
    private function handleSuccessfulCharge($data) {
        $reference = $data['reference'];
        
        // Find payment
        $payment = $this->db->find('payments', ['reference' => $reference]);
        
        if (!$payment) {
            $this->log("Payment not found for reference: {$reference}", 'error');
            return;
        }
        
        // Skip if already completed
        if ($payment['status'] === 'completed') {
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Update payment
            $this->db->update('payments', $payment['id'], [
                'status' => 'completed',
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode($data)
            ]);
            
            // Update order
            $this->db->update('orders', $payment['order_id'], [
                'payment_status' => 'paid',
                'status' => 'processing'
            ]);
            
            $this->db->commit();
            
            $order = $this->db->find('orders', $payment['order_id']);
            $this->log("Webhook: Payment completed for Order {$order['order_number']}");
            
            // TODO: Send SMS/Email notification to customer (Week 5)
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Webhook: Failed to process successful charge: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Handle failed charge webhook
     */
    private function handleFailedCharge($data) {
        $reference = $data['reference'];
        
        $payment = $this->db->find('payments', ['reference' => $reference]);
        
        if (!$payment) {
            return;
        }
        
        try {
            $this->db->update('payments', $payment['id'], [
                'status' => 'failed',
                'metadata' => json_encode($data)
            ]);
            
            $order = $this->db->find('orders', $payment['order_id']);
            $this->log("Webhook: Payment failed for Order {$order['order_number']}");
            
            // TODO: Send notification to customer (Week 5)
            
        } catch (Exception $e) {
            $this->log("Webhook: Failed to process failed charge: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * GET /api/v1/payments
     * List payments for business
     */
    public function index() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        $pagination = $this->getPagination(20);
        
        $total = $this->db->count('payments', ['business_id' => $businessId]);
        $payments = $this->db->findAll(
            'payments',
            ['business_id' => $businessId],
            'created_at DESC',
            $pagination['limit'],
            $pagination['offset']
        );
        
        // Enrich with order info
        foreach ($payments as &$payment) {
            $order = $this->db->find('orders', $payment['order_id']);
            $payment['order'] = $order;
        }
        
        return $this->paginate($payments, $total, $pagination);
    }
    
    /**
     * GET /api/v1/payments/{id}
     * Get single payment details
     */
    public function show($id) {
        $this->requireAuth();
        
        $payment = $this->db->find('payments', $id);
        
        if (!$payment) {
            return $this->notFound('Payment not found');
        }
        
        // Verify ownership
        if ($payment['business_id'] != $this->getBusinessId()) {
            return $this->forbidden();
        }
        
        // Get related order
        $payment['order'] = $this->db->find('orders', $payment['order_id']);
        
        return $this->success($payment, 'Payment retrieved successfully');
    }
    
    /**
     * Make request to Paystack API
     */
    private function makePaystackRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->log("Paystack API error ({$httpCode}): " . ($result['message'] ?? 'Unknown error'), 'error');
        }
        
        return $result;
    }
    
    /**
     * Get public key for frontend
     */
    public function getPublicKey() {
        return $this->success([
            'public_key' => $this->publicKey
        ], 'Public key retrieved');
    }
}