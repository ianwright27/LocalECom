<?php
/**
 * WhatsAppProvider - Meta WhatsApp Cloud API
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles WhatsApp notifications via Meta's official Cloud API
 * Template-based messaging only (for outbound messages)
 * 
 * Setup:
 * 1. Create Meta Business Account at https://business.facebook.com
 * 2. Create WhatsApp Business App at https://developers.facebook.com
 * 3. Get Phone Number ID and Access Token
 * 4. Create and get approval for message templates
 * 5. Add credentials to config/whatsapp.php
 * 
 * Documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

class WhatsAppProvider {
    private $phoneNumberId;
    private $accessToken;
    private $apiVersion = 'v18.0';
    private $baseUrl;
    
    public function __construct() {
        // Load config
        $configFile = __DIR__ . '/../../config/whatsapp.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->phoneNumberId = WHATSAPP_PHONE_NUMBER_ID ?? '';
            $this->accessToken = WHATSAPP_ACCESS_TOKEN ?? '';
        } else {
            $this->phoneNumberId = '';
            $this->accessToken = '';
        }
        
        $this->baseUrl = 'https://graph.facebook.com/' . $this->apiVersion;
    }
    
    /**
     * Send template message
     * 
     * @param string $to Recipient phone number (+254712345678)
     * @param string $templateName Template name (must be pre-approved)
     * @param array $parameters Template parameters
     * @param string $languageCode Template language (default: en_US)
     * @return array Result with success status
     */
    public function sendTemplate($to, $templateName, $parameters = [], $languageCode = 'en_US') {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            return [
                'success' => false,
                'error' => 'WhatsApp credentials not configured'
            ];
        }
        
        if (empty($to) || empty($templateName)) {
            return [
                'success' => false,
                'error' => 'Phone number and template name are required'
            ];
        }
        
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        // Build template components
        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters
            ];
        }
        
        // Prepare request payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];
        
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        
        // Make API request
        $url = $this->baseUrl . '/' . $this->phoneNumberId . '/messages';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log for debugging - IMPORTANT: Shows exact API response
        error_log("WhatsApp API Request URL: " . $url);
        error_log("WhatsApp API Request Payload: " . json_encode($payload));
        error_log("WhatsApp API HTTP Code: " . $httpCode);
        error_log("WhatsApp API Response: " . $response);
        if ($curlError) {
            error_log("WhatsApp cURL Error: " . $curlError);
        }
        
        // Check if successful
        if ($httpCode === 200 && isset($result['messages'])) {
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? null,
                'phone' => $to
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Failed to send WhatsApp message',
                'error_code' => $result['error']['code'] ?? null,
                'http_code' => $httpCode
            ];
        }
    }
    
    /**
     * Format phone number to WhatsApp format (no + or spaces)
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number (e.g., 254712345678)
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove + if present
        $phone = str_replace('+', '', $phone);
        
        // If starts with 0, replace with 254 (Kenya)
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If doesn't start with country code, assume Kenya
        if (strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get message status
     * (Use webhooks for real-time delivery status)
     * 
     * @param string $messageId Message ID from send response
     * @return array Status information
     */
    public function getMessageStatus($messageId) {
        // Note: Message status is best received via webhooks
        // This is a placeholder
        return [
            'success' => false,
            'error' => 'Message status check not implemented. Use webhooks for delivery reports.'
        ];
    }
    
    /**
     * Verify webhook signature
     * Used to validate incoming webhooks from Meta
     * 
     * @param string $payload Raw POST body
     * @param string $signature X-Hub-Signature-256 header
     * @return bool True if valid
     */
    public function verifyWebhookSignature($payload, $signature) {
        $appSecret = WHATSAPP_APP_SECRET ?? '';
        
        if (empty($appSecret)) {
            return false;
        }
        
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
}