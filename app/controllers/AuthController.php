<?php
/**
 * AuthController
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Handles all authentication-related operations:
 * - User registration (with automatic business creation)
 * - Login/Logout
 * - Password reset
 * - User profile management
 * - Session management
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {
    
    /**
     * POST /auth/register
     * Register a new user and create their business
     * 
     * Required fields:
     * - name: User's full name
     * - email: User's email (must be unique)
     * - password: Password (min 6 characters)
     * - business_name: Name of their business
     * 
     * Optional fields:
     * - phone: User's phone number
     * - business_phone: Business phone number
     */
    public function register() {
        // Validate input
        $this->validate([
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'business_name' => 'required|min:3|max:255',
            'phone' => 'phone'
        ]);
        
        $name = $this->sanitize($this->input('name'));
        $email = $this->input('email');
        $password = $this->input('password');
        $businessName = $this->sanitize($this->input('business_name'));
        $phone = $this->input('phone');
        $businessPhone = $this->input('business_phone');
        
        try {
            $this->db->beginTransaction();
            
            // Create business first
            $businessId = $this->db->insert('businesses', [
                'name' => $businessName,
                'email' => $email,
                'phone' => $businessPhone ? $this->formatPhone($businessPhone) : null,
                'settings' => json_encode([
                    'currency' => 'KES',
                    'timezone' => 'Africa/Nairobi',
                    'language' => 'en'
                ])
            ]);
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Create user account
            $userId = $this->db->insert('users', [
                'business_id' => $businessId,
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'phone' => $phone ? $this->formatPhone($phone) : null,
                'role' => 'owner' // First user is always the owner
            ]);
            
            $this->db->commit();
            
            // Auto-login after registration
            if (session_status() === PHP_SESSION_NONE) session_start();;
            $_SESSION['user_id'] = $userId;
            $_SESSION['business_id'] = $businessId;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'owner';
            
            // Log activity
            $this->log("New user registered: {$email}, Business: {$businessName}");
            
            // Get user data (without password)
            $user = $this->db->find('users', $userId);
            unset($user['password']);
            
            $business = $this->db->find('businesses', $businessId);
            
            return $this->created([
                'user' => $user,
                'business' => $business,
                'session' => [
                    'user_id' => $userId,
                    'business_id' => $businessId
                ]
            ], 'Registration successful! Welcome to WrightCommerce!');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("Registration failed for {$email}: " . $e->getMessage(), 'error');
            return $this->serverError('Registration failed. Please try again.');
        }
    }
    
    /**
     * POST /auth/login
     * Login user with email and password
     * 
     * Required fields:
     * - email: User's email
     * - password: User's password
     */
    public function login() {
        // Validate input
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        $email = $this->input('email');
        $password = $this->input('password');
        
        // Find user by email
        $user = $this->db->find('users', ['email' => $email]);
        
        if (!$user) {
            $this->log("Login attempt with non-existent email: {$email}", 'warning');
            return $this->unauthorized('Invalid email or password');
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->log("Failed login attempt for: {$email} (wrong password)", 'warning');
            return $this->unauthorized('Invalid email or password');
        }
        
        // Get business info
        $business = $this->db->find('businesses', $user['business_id']);
        
        // Create session
        if (session_status() === PHP_SESSION_NONE) session_start();;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['business_id'] = $user['business_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        // Log successful login
        $this->log("User logged in: {$email}");
        
        // Remove password from response
        unset($user['password']);
        
        return $this->success([
            'user' => $user,
            'business' => $business,
            'session' => [
                'user_id' => $user['id'],
                'business_id' => $user['business_id']
            ]
        ], 'Login successful! Welcome back!');
    }
    
    /**
     * POST /auth/logout
     * Logout current user
     */
    public function logout() {
        $this->requireAuth();
        
        $email = $_SESSION['user_email'] ?? 'unknown';
        
        // Destroy session
        if (session_status() === PHP_SESSION_NONE) session_start();;
        session_destroy();
        
        $this->log("User logged out: {$email}");
        
        return $this->success(null, 'Logout successful');
    }
    
    /**
     * GET /auth/me
     * Get current authenticated user's information
     */
    public function me() {
        $this->requireAuth();
        
        // Get fresh user data
        $user = $this->db->find('users', $this->getUserId());
        
        if (!$user) {
            return $this->unauthorized('Session expired. Please login again.');
        }
        
        // Remove password
        unset($user['password']);
        
        // Get business data
        $business = $this->db->find('businesses', $user['business_id']);
        
        return $this->success([
            'user' => $user,
            'business' => $business
        ], 'User data retrieved successfully');
    }
    
    /**
     * PUT /auth/profile
     * Update current user's profile
     * 
     * Optional fields:
     * - name: User's name
     * - phone: User's phone
     * - email: User's email (must be unique)
     */
    public function updateProfile() {
        $this->requireAuth();
        
        $userId = $this->getUserId();
        
        // Validate input
        $rules = [
            'name' => 'min:3|max:255',
            'phone' => 'phone'
        ];
        
        // Only validate email uniqueness if it's being changed
        $currentUser = $this->db->find('users', $userId);
        if ($this->has('email') && $this->input('email') !== $currentUser['email']) {
            $rules['email'] = 'email|unique:users,email';
        }
        
        $this->validate($rules);
        
        // Get data to update
        $data = $this->only(['name', 'email', 'phone']);
        
        // Remove empty values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        if (empty($data)) {
            return $this->error('No data provided for update', 400);
        }
        
        // Sanitize and format
        if (isset($data['name'])) {
            $data['name'] = $this->sanitize($data['name']);
        }
        if (isset($data['phone'])) {
            $data['phone'] = $this->formatPhone($data['phone']);
        }
        
        try {
            $this->db->update('users', $userId, $data);
            
            // Update session if email or name changed
            if (isset($data['email'])) {
                $_SESSION['user_email'] = $data['email'];
            }
            if (isset($data['name'])) {
                $_SESSION['user_name'] = $data['name'];
            }
            
            $this->log("User profile updated: User ID {$userId}");
            
            // Get updated user
            $user = $this->db->find('users', $userId);
            unset($user['password']);
            
            return $this->success($user, 'Profile updated successfully');
            
        } catch (Exception $e) {
            $this->log("Profile update failed for user {$userId}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to update profile');
        }
    }
    
    /**
     * POST /auth/change-password
     * Change current user's password
     * 
     * Required fields:
     * - current_password: Current password
     * - new_password: New password (min 6 characters)
     * - confirm_password: Confirm new password
     */
    public function changePassword() {
        $this->requireAuth();
        
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required'
        ]);
        
        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');
        $confirmPassword = $this->input('confirm_password');
        
        // Check if new password and confirm match
        if ($newPassword !== $confirmPassword) {
            return $this->error('New password and confirm password do not match', 400);
        }
        
        $userId = $this->getUserId();
        $user = $this->db->find('users', $userId);
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return $this->error('Current password is incorrect', 400);
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $this->db->update('users', $userId, [
                'password' => $hashedPassword
            ]);
            
            $this->log("Password changed for user: {$user['email']}");
            
            return $this->success(null, 'Password changed successfully');
            
        } catch (Exception $e) {
            $this->log("Password change failed for user {$userId}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to change password');
        }
    }
    
    /**
     * POST /auth/forgot-password
     * Request password reset (sends reset token)
     * 
     * Required fields:
     * - email: User's email
     * 
     * NOTE: In a real application, this would send an email with a reset link.
     * For now, it just generates a token and returns it.
     */
    public function forgotPassword() {
        $this->validate([
            'email' => 'required|email'
        ]);
        
        $email = $this->input('email');
        
        // Find user
        $user = $this->db->find('users', ['email' => $email]);
        
        if (!$user) {
            // For security, don't reveal if email exists or not
            return $this->success(null, 'If the email exists, a password reset link has been sent.');
        }
        
        // Generate reset token (valid for 1 hour)
        $resetToken = $this->generateRandomString(64);
        $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        try {
            // Store reset token in database
            // Note: You'll need to add reset_token and reset_token_expiry columns to users table
            $this->db->update('users', $user['id'], [
                'reset_token' => $resetToken,
                'reset_token_expiry' => $resetExpiry
            ]);
            
            $this->log("Password reset requested for: {$email}");
            
            // In production, you would send an email here with the reset link
            // For now, return the token (ONLY FOR DEVELOPMENT/TESTING!)
            return $this->success([
                'message' => 'Password reset token generated',
                'token' => $resetToken, // REMOVE THIS IN PRODUCTION!
                'expires_at' => $resetExpiry,
                'reset_url' => "http://localhost:3000/reset-password?token={$resetToken}" // Example URL
            ], 'If the email exists, a password reset link has been sent.');
            
        } catch (Exception $e) {
            $this->log("Password reset request failed for {$email}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to process password reset request');
        }
    }
    
    /**
     * POST /auth/reset-password
     * Reset password using token
     * 
     * Required fields:
     * - token: Reset token from email
     * - new_password: New password (min 6 characters)
     * - confirm_password: Confirm new password
     */
    public function resetPassword() {
        $this->validate([
            'token' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required'
        ]);
        
        $token = $this->input('token');
        $newPassword = $this->input('new_password');
        $confirmPassword = $this->input('confirm_password');
        
        // Check if passwords match
        if ($newPassword !== $confirmPassword) {
            return $this->error('New password and confirm password do not match', 400);
        }
        
        // Find user by reset token
        $user = $this->db->find('users', ['reset_token' => $token]);
        
        if (!$user) {
            return $this->error('Invalid or expired reset token', 400);
        }
        
        // Check if token has expired
        $expiry = strtotime($user['reset_token_expiry'] ?? '1970-01-01');
        if (time() > $expiry) {
            return $this->error('Reset token has expired. Please request a new one.', 400);
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            // Update password and clear reset token
            $this->db->update('users', $user['id'], [
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_token_expiry' => null
            ]);
            
            $this->log("Password reset completed for user: {$user['email']}");
            
            return $this->success(null, 'Password reset successful! You can now login with your new password.');
            
        } catch (Exception $e) {
            $this->log("Password reset failed for user {$user['id']}: " . $e->getMessage(), 'error');
            return $this->serverError('Failed to reset password');
        }
    }
    
    /**
     * GET /auth/check
     * Check if user is authenticated (useful for frontend)
     */
    public function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();;
        
        if (isset($_SESSION['user_id'])) {
            return $this->success([
                'authenticated' => true,
                'user_id' => $_SESSION['user_id'],
                'business_id' => $_SESSION['business_id'] ?? null
            ], 'User is authenticated');
        } else {
            return $this->success([
                'authenticated' => false
            ], 'User is not authenticated');
        }
    }
}