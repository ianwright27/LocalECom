<?php
/**
 * SMSProvider - Africa's Talking
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles SMS notifications via Africa's Talking API
 * 
 * Setup:
 * 1. Create account at https://africastalking.com
 * 2. Get API Key from https://account.africastalking.com/apps/sandbox
 * 3. Add credentials to config/africastalking.php
 * 4. Buy SMS credits (Production) or use sandbox (Testing)
 * 
 * Documentation: https://developers.africastalking.com/docs/sms/sending
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class SMSProvider {
    private $username;
    private $apiKey;
    private $environment; // 'sandbox' or 'production'
    private $baseUrl;
    
    public function __construct() {
        // Load config
        $configFile = __DIR__ . '/../../config/africastalking.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->username = AT_USERNAME ?? '';
            $this->apiKey = AT_API_KEY ?? '';
            $this->environment = AT_ENVIRONMENT ?? 'sandbox';
        } else {
            $this->username = '';
            $this->apiKey = '';
            $this->environment = 'sandbox';
        }
        
        // Set API URL based on environment
        if ($this->environment === 'production') {
            $this->baseUrl = 'https://api.africastalking.com/version1';
        } else {
            $this->baseUrl = 'https://api.sandbox.africastalking.com/version1';
        }
    }
    
    /**
     * Send SMS
     * 
     * @param string $to Phone number (format: +254712345678 or 0712345678)
     * @param string $message Message content (max 160 chars for single SMS)
     * @param string $from Sender ID (optional, default: AFRICASTKNG in sandbox)
     * @return array Result with success status and details
     */
    public function send($to, $message, $from = null) {
        // Validate inputs
        if (empty($this->username) || empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'Africa\'s Talking credentials not configured'
            ];
        }
        
        if (empty($to) || empty($message)) {
            return [
                'success' => false,
                'error' => 'Phone number and message are required'
            ];
        }
        
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        // Prepare request data
        $data = [
            'username' => $this->username,
            'to' => $to,
            'message' => $message
        ];
        
        // Add sender ID if provided (only works in production)
        if ($from && $this->environment === 'production') {
            $data['from'] = $from;
        }
        
        // Make API request
        $url = $this->baseUrl . '/messaging';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Parse response
        $result = json_decode($response, true);
        
        // Log for debugging
        error_log("Africa's Talking SMS Response: " . $response);
        
        // Check if successful
        if ($httpCode === 201 && isset($result['SMSMessageData']['Recipients'])) {
            $recipients = $result['SMSMessageData']['Recipients'];
            
            if (!empty($recipients) && $recipients[0]['status'] === 'Success') {
                return [
                    'success' => true,
                    'message_id' => $recipients[0]['messageId'] ?? null,
                    'status' => $recipients[0]['status'],
                    'cost' => $recipients[0]['cost'] ?? null,
                    'phone' => $to
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $recipients[0]['status'] ?? 'Unknown error',
                    'phone' => $to
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => $result['SMSMessageData']['Message'] ?? 'Failed to send SMS',
                'http_code' => $httpCode
            ];
        }
    }
    
    /**
     * Send bulk SMS to multiple recipients
     * 
     * @param array $recipients Array of phone numbers
     * @param string $message Message content
     * @param string $from Sender ID (optional)
     * @return array Results for each recipient
     */
    public function sendBulk($recipients, $message, $from = null) {
        if (empty($recipients) || !is_array($recipients)) {
            return [
                'success' => false,
                'error' => 'Recipients array is required'
            ];
        }
        
        // Format all phone numbers
        $formattedRecipients = array_map([$this, 'formatPhoneNumber'], $recipients);
        $to = implode(',', $formattedRecipients);
        
        // Prepare request data
        $data = [
            'username' => $this->username,
            'to' => $to,
            'message' => $message
        ];
        
        if ($from && $this->environment === 'production') {
            $data['from'] = $from;
        }
        
        // Make API request
        $url = $this->baseUrl . '/messaging';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 201 && isset($result['SMSMessageData']['Recipients'])) {
            return [
                'success' => true,
                'recipients' => $result['SMSMessageData']['Recipients'],
                'message' => $result['SMSMessageData']['Message']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['SMSMessageData']['Message'] ?? 'Failed to send bulk SMS'
            ];
        }
    }
    
    /**
     * Format phone number to international format
     * Converts Kenyan numbers to +254 format
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove spaces, dashes, and other characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with 0, replace with +254 (Kenya)
        if (substr($phone, 0, 1) === '0') {
            $phone = '+254' . substr($phone, 1);
        }
        
        // If starts with 254 without +, add +
        if (substr($phone, 0, 3) === '254' && substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        // If doesn't start with +, assume Kenya and add +254
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get SMS delivery status
     * (Premium SMS feature - requires callback URL setup)
     * 
     * @param string $messageId Message ID from send response
     * @return array Status information
     */
    public function getDeliveryStatus($messageId) {
        // Note: Delivery reports are sent to your callback URL
        // This is a placeholder for future implementation
        return [
            'success' => false,
            'error' => 'Delivery status check not implemented. Use callback URL for delivery reports.'
        ];
    }
    
    /**
     * Check account balance (Production only)
     * 
     * @return array Balance information
     */
    public function checkBalance() {
        if ($this->environment === 'sandbox') {
            return [
                'success' => true,
                'balance' => 'Sandbox mode - unlimited test credits',
                'currency' => 'USD'
            ];
        }
        
        $url = 'https://api.africastalking.com/version1/user?username=' . $this->username;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'apiKey: ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['UserData'])) {
            return [
                'success' => true,
                'balance' => $result['UserData']['balance'] ?? 'Unknown'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to fetch balance'
            ];
        }
    }
}