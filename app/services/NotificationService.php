<?php
/**
 * NotificationService
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Central notification orchestrator
 * Routes notifications to enabled and paid-for channels only
 * 
 * Supported Channels:
 * - SMS (Africa's Talking)
 * - WhatsApp (Meta Cloud API)
 * - Email (Custom WrightCommerce)
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class NotificationService {
    private $db;
    private $smsProvider;
    private $whatsappProvider;
    private $emailProvider;
    
    public function __construct($db) {
        $this->db = $db;
        
        // Lazy load providers
        require_once __DIR__ . '/SMSProvider.php';
        require_once __DIR__ . '/WhatsAppProvider.php';
        require_once __DIR__ . '/EmailProvider.php';
        
        $this->smsProvider = new SMSProvider();
        $this->whatsappProvider = new WhatsAppProvider();
        $this->emailProvider = new EmailProvider();
    }
    
    /**
     * Send notification through enabled channels
     * 
     * @param int $businessId Business ID
     * @param string $event Event type (order_placed, payment_received, etc.)
     * @param array $recipient Recipient data [name, phone, email]
     * @param array $data Notification data
     * @param array $channels Optional: Force specific channels ['sms', 'whatsapp', 'email']
     */
    public function send($businessId, $event, $recipient, $data, $channels = null) {
        // Get business notification settings
        $settings = $this->getNotificationSettings($businessId);
        
        // Determine which channels to use
        if ($channels === null) {
            // Use all enabled channels
            $channels = [];
            if ($settings['sms_enabled']) $channels[] = 'sms';
            if ($settings['whatsapp_enabled']) $channels[] = 'whatsapp';
            if ($settings['email_enabled']) $channels[] = 'email';
        } else {
            // Filter requested channels by what's enabled
            $channels = array_filter($channels, function($channel) use ($settings) {
                return $settings[$channel . '_enabled'] ?? false;
            });
        }
        
        // If no channels enabled, log and return
        if (empty($channels)) {
            $this->log($businessId, $event, 'skipped', 'No channels enabled', $data);
            return [
                'success' => false,
                'message' => 'No notification channels enabled for this business'
            ];
        }
        
        $results = [];
        
        // Send through each enabled channel
        foreach ($channels as $channel) {
            $result = $this->sendToChannel($channel, $businessId, $event, $recipient, $data, $settings);
            $results[$channel] = $result;
        }
        
        return [
            'success' => true,
            'channels' => $results
        ];
    }
    
    /**
     * Send notification to specific channel
     */
    private function sendToChannel($channel, $businessId, $event, $recipient, $data, $settings) {
        try {
            switch ($channel) {
                case 'sms':
                    return $this->sendSMS($businessId, $event, $recipient, $data, $settings);
                    
                case 'whatsapp':
                    return $this->sendWhatsApp($businessId, $event, $recipient, $data, $settings);
                    
                case 'email':
                    return $this->sendEmail($businessId, $event, $recipient, $data, $settings);
                    
                default:
                    return ['success' => false, 'error' => 'Unknown channel'];
            }
        } catch (Exception $e) {
            $this->log($businessId, $event, 'failed', $e->getMessage(), $data);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS notification
     */
    private function sendSMS($businessId, $event, $recipient, $data, $settings) {
        if (empty($recipient['phone'])) {
            return ['success' => false, 'error' => 'No phone number'];
        }
        
        $message = $this->buildMessage($event, $data, 'sms');
        
        $result = $this->smsProvider->send(
            $recipient['phone'],
            $message,
            $settings['sms_sender_id'] ?? 'WrightComm'
        );
        
        $this->log($businessId, $event, $result['success'] ? 'sent' : 'failed', json_encode($result), $data);
        
        return $result;
    }
    
    /**
     * Send WhatsApp notification
     */
    private function sendWhatsApp($businessId, $event, $recipient, $data, $settings) {
        if (empty($recipient['phone'])) {
            return ['success' => false, 'error' => 'No phone number'];
        }
        
        // WhatsApp requires templates
        $template = $this->getWhatsAppTemplate($event);
        $parameters = $this->buildWhatsAppParameters($event, $data);
        
        $result = $this->whatsappProvider->sendTemplate(
            $recipient['phone'],
            $template,
            $parameters
        );
        
        $this->log($businessId, $event, $result['success'] ? 'sent' : 'failed', json_encode($result), $data);
        
        return $result;
    }
    
    /**
     * Send Email notification
     */
    private function sendEmail($businessId, $event, $recipient, $data, $settings) {
        if (empty($recipient['email'])) {
            return ['success' => false, 'error' => 'No email address'];
        }
        
        $subject = $this->getEmailSubject($event, $data);
        $body = $this->buildMessage($event, $data, 'email');
        
        $result = $this->emailProvider->send(
            $recipient['email'],
            $recipient['name'] ?? '',
            $subject,
            $body
        );
        
        $this->log($businessId, $event, $result['success'] ? 'sent' : 'failed', json_encode($result), $data);
        
        return $result;
    }
    
    /**
     * Get business notification settings
     */
    private function getNotificationSettings($businessId) {
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->getDefaultSettings();
        }
        
        $settings = json_decode($business['settings'] ?? '{}', true);
        $notifications = $settings['notifications'] ?? [];
        
        return [
            'sms_enabled' => $notifications['sms_enabled'] ?? false,
            'whatsapp_enabled' => $notifications['whatsapp_enabled'] ?? false,
            'email_enabled' => $notifications['email_enabled'] ?? true, // Email free by default
            'sms_sender_id' => $notifications['sms_sender_id'] ?? 'WrightComm',
        ];
    }
    
    /**
     * Default notification settings
     */
    private function getDefaultSettings() {
        return [
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'email_enabled' => true,
            'sms_sender_id' => 'WrightComm'
        ];
    }
    
    /**
     * Build notification message based on event
     */
    private function buildMessage($event, $data, $channel = 'sms') {
        $messages = [
            'order_placed' => [
                'sms' => "Hi {name}, your order {order_number} for KES {total} has been received! We'll notify you when it's ready.",
                'email' => "Dear {name},\n\nThank you for your order!\n\nOrder Number: {order_number}\nTotal: KES {total}\nStatus: Pending\n\nWe'll process your order and keep you updated.\n\nThank you,\nWrightCommerce"
            ],
            'payment_received' => [
                'sms' => "Payment received! KES {amount} for order {order_number}. Your order is being processed.",
                'email' => "Dear {name},\n\nPayment Confirmation\n\nWe've received your payment of KES {amount} for order {order_number}.\n\nYour order is now being processed and will be shipped soon.\n\nThank you,\nWrightCommerce"
            ],
            'order_processing' => [
                'sms' => "Good news! Order {order_number} is being processed. You'll receive updates as we prepare your items.",
                'email' => "Dear {name},\n\nOrder Update: Processing\n\nYour order {order_number} is now being processed.\n\nWe're preparing your items for shipment.\n\nThank you,\nWrightCommerce"
            ],
            'order_shipped' => [
                'sms' => "Your order {order_number} has been shipped! Track it with this reference: {tracking_number}",
                'email' => "Dear {name},\n\nOrder Shipped!\n\nYour order {order_number} has been shipped.\n\nTracking Number: {tracking_number}\n\nExpected Delivery: {delivery_date}\n\nThank you,\nWrightCommerce"
            ],
            'order_completed' => [
                'sms' => "Order {order_number} delivered! Thank you for shopping with us. Rate your experience: {rating_link}",
                'email' => "Dear {name},\n\nOrder Completed\n\nYour order {order_number} has been delivered.\n\nWe hope you love your purchase!\n\nRate your experience: {rating_link}\n\nThank you,\nWrightCommerce"
            ]
        ];
        
        $template = $messages[$event][$channel] ?? "Update for order {order_number}";
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Get WhatsApp template name for event
     */
    private function getWhatsAppTemplate($event) {
        $templates = [
            'order_placed' => 'hello_world', // Use your actual template name
            'payment_received' => 'hello_world',
            'order_processing' => 'hello_world',
            'order_shipped' => 'hello_world',
            'order_completed' => 'hello_world'
        ];
        
        return $templates[$event] ?? 'hello_world';
    }
    
    /**
     * Build WhatsApp template parameters
     */
    private function buildWhatsAppParameters($event, $data) {
        // WhatsApp templates require specific parameter format
        // This will vary based on your approved templates
        return [
            ['type' => 'text', 'text' => $data['name'] ?? ''],
            ['type' => 'text', 'text' => $data['order_number'] ?? ''],
            ['type' => 'text', 'text' => 'KES ' . number_format($data['total'] ?? 0)]
        ];
    }
    
    /**
     * Get email subject for event
     */
    private function getEmailSubject($event, $data) {
        $subjects = [
            'order_placed' => 'Order Confirmation - ' . ($data['order_number'] ?? ''),
            'payment_received' => 'Payment Received - ' . ($data['order_number'] ?? ''),
            'order_processing' => 'Order Being Processed - ' . ($data['order_number'] ?? ''),
            'order_shipped' => 'Order Shipped - ' . ($data['order_number'] ?? ''),
            'order_completed' => 'Order Delivered - ' . ($data['order_number'] ?? '')
        ];
        
        return $subjects[$event] ?? 'Order Update';
    }
    
    /**
     * Log notification attempt
     */
    private function log($businessId, $event, $status, $response, $data) {
        try {
            $this->db->insert('notification_logs', [
                'business_id' => $businessId,
                'event' => $event,
                'status' => $status,
                'response' => $response,
                'data' => json_encode($data)
            ]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
}