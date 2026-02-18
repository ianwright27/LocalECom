<?php
/**
 * BusinessController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles business profile and settings management
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class BusinessController extends BaseController {
    
    /**
     * GET /api/v1/business/profile
     * Get business profile for authenticated user
     */
    public function profile() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        if (!$businessId) {
            return $this->error('Business not found', 404);
        }
        
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->notFound('Business not found');
        }
        
        // Get notification preferences if they exist
        // They might be stored in a separate table or as JSON in the businesses table
        $notificationPreferences = null;
        
        // Check if there's a settings column with JSON data
        if (isset($business['settings'])) {
            $settings = json_decode($business['settings'], true);
            if (isset($settings['notification_preferences'])) {
                $notificationPreferences = $settings['notification_preferences'];
            }
        }
        
        // If no notification preferences found, return defaults
        if (!$notificationPreferences) {
            $notificationPreferences = [
                'email_new_order' => true,
                'email_order_status' => true,
                'email_low_stock' => true,
                'sms_new_order' => false,
                'sms_order_status' => false,
                'whatsapp_new_order' => false,
                'whatsapp_order_status' => false,
            ];
        }
        
        $business['notification_preferences'] = $notificationPreferences;
        
        return $this->success($business, 'Business profile retrieved successfully');
    }
    
    /**
     * PUT /api/v1/business/profile
     * Update business profile
     */
    public function updateProfile() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        if (!$businessId) {
            return $this->error('Business not found', 404);
        }
        
        // Get current business
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->notFound('Business not found');
        }
        
        // Validate input (all optional)
        $this->validate([
            'name' => 'min:3|max:255',
            'email' => 'email',
            'phone' => 'phone',
        ]);
        
        // Get only the fields that were sent
        $data = $this->only([
            'name',
            'email',
            'phone',
            'address',
            'city',
            'country',
            'website',
            'description'
        ]);
        
        // Remove empty values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Sanitize text fields
        if (isset($data['name'])) {
            $data['name'] = $this->sanitize($data['name']);
        }
        if (isset($data['address'])) {
            $data['address'] = $this->sanitize($data['address']);
        }
        if (isset($data['city'])) {
            $data['city'] = $this->sanitize($data['city']);
        }
        if (isset($data['description'])) {
            $data['description'] = $this->sanitize($data['description']);
        }
        
        if (empty($data)) {
            return $this->error('No data provided for update', 400);
        }
        
        try {
            $this->db->update('businesses', $businessId, $data);
            
            $this->log("Business profile updated: Business ID {$businessId}");
            
            // Get updated business
            $updatedBusiness = $this->db->find('businesses', $businessId);
            
            return $this->success($updatedBusiness, 'Business profile updated successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to update business profile: " . $businessId . $e->getMessage(), 'error');

            return $this->serverError('Failed to update business profile: '.$businessId);
        }
    }
    
    /**
     * PUT /api/v1/business/notifications
     * Update notification preferences
     */
    public function updateNotifications() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        if (!$businessId) {
            return $this->error('Business not found', 404);
        }
        
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->notFound('Business not found');
        }
        
        // Get notification preferences from request
        $notificationPreferences = [
            'email_new_order' => $this->input('email_new_order', false),
            'email_order_status' => $this->input('email_order_status', false),
            'email_low_stock' => $this->input('email_low_stock', false),
            'sms_new_order' => $this->input('sms_new_order', false),
            'sms_order_status' => $this->input('sms_order_status', false),
            'whatsapp_new_order' => $this->input('whatsapp_new_order', false),
            'whatsapp_order_status' => $this->input('whatsapp_order_status', false),
        ];

        //  ✅ Convert to NotificationService format
        $notificationsEnabled = [
            'sms_enabled' => $notificationPreferences['sms_new_order'] || $notificationPreferences['sms_order_status'],
            'whatsapp_enabled' => $notificationPreferences['whatsapp_new_order'] || $notificationPreferences['whatsapp_order_status'],
            'email_enabled' => $notificationPreferences['email_new_order'] || $notificationPreferences['email_order_status'] || $notificationPreferences['email_low_stock'],
        ];
        
        
        try {
            // Get existing settings or create new
            $settings = [];
            if (isset($business['settings']) && $business['settings']) {
                $settings = json_decode($business['settings'], true) ?? [];
            }
            
            // Update notification preferences
            // Store BOTH formats for compatibility
            $settings['notification_preferences'] = $notificationPreferences; // For React UI
            $settings['notifications'] = $notificationsEnabled; // For NotificationService
            
            // Save back to database
            $this->db->update('businesses', $businessId, [
                'settings' => json_encode($settings)
            ]);
            
            $this->log("Notification preferences updated: Business ID {$businessId}");
            
            return $this->success([
                'notification_preferences' => $notificationPreferences
            ], 'Notification preferences updated successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to update notification preferences: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to update notification preferences');
        }
    }
    
    /**
     * GET /api/v1/business/settings
     * Get all business settings
     */
    public function settings() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        if (!$businessId) {
            return $this->error('Business not found', 404);
        }
        
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->notFound('Business not found');
        }
        
        // Parse settings JSON
        $settings = [];
        if (isset($business['settings']) && $business['settings']) {
            $settings = json_decode($business['settings'], true) ?? [];
        }
        
        return $this->success($settings, 'Business settings retrieved successfully');
    }
    
    /**
     * PUT /api/v1/business/settings
     * Update business settings
     */
    public function updateSettings() {
        $this->requireAuth();
        
        $businessId = $this->getBusinessId();
        
        if (!$businessId) {
            return $this->error('Business not found', 404);
        }
        
        $business = $this->db->find('businesses', $businessId);
        
        if (!$business) {
            return $this->notFound('Business not found');
        }
        
        // Get new settings from request
        $newSettings = $this->input();
        
        try {
            // Get existing settings
            $settings = [];
            if (isset($business['settings']) && $business['settings']) {
                $settings = json_decode($business['settings'], true) ?? [];
            }
            
            // Merge with new settings
            $settings = array_merge($settings, $newSettings);
            
            // Save to database
            $this->db->update('businesses', $businessId, [
                'settings' => json_encode($settings)
            ]);
            
            $this->log("Business settings updated: Business ID {$businessId}");
            
            return $this->success($settings, 'Business settings updated successfully');
            
        } catch (Exception $e) {
            $this->log("Failed to update business settings: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to update business settings');
        }
    }
}