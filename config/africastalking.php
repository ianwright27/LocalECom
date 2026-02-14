<?php
/**
 * Africa's Talking Configuration
 * 
 * Get your credentials from: https://account.africastalking.com/apps
 * 
 * Sandbox (Testing):
 * - Free test credits
 * - Use for development
 * - Test numbers only
 * 
 * Production (Live):
 * - Buy SMS credits
 * - Real phone numbers
 * - Live messaging
 */

// Africa's Talking Credentials
define('AT_USERNAME', 'sandbox'); // Change to your username in production
define('AT_API_KEY', 'your_api_key_here'); // Get from dashboard
define('AT_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'

// Optional: Sender ID (Production only, requires approval)
define('AT_SENDER_ID', 'WRIGHTCOMM'); // Your approved sender ID

/**
 * SETUP INSTRUCTIONS:
 * 
 * 1. Create Account:
 *    - Go to https://africastalking.com
 *    - Sign up (free for students!)
 *    - Verify your email
 * 
 * 2. Get API Key:
 *    - Login to https://account.africastalking.com
 *    - Go to Apps (Sandbox or Production)
 *    - Copy API Key
 *    - Paste above
 * 
 * 3. Sandbox Testing:
 *    - Use AT_ENVIRONMENT = 'sandbox'
 *    - Test with any Kenyan number
 *    - Free test credits provided
 *    - SMS won't actually send (simulated)
 * 
 * 4. Go to Production:
 *    - Complete KYC verification
 *    - Buy SMS credits (very affordable!)
 *    - Change AT_ENVIRONMENT to 'production'
 *    - Use your production username
 *    - Apply for sender ID (optional)
 * 
 * 5. Pricing (Production):
 *    - Kenya: ~KES 0.50 per SMS
 *    - Bulk discounts available
 *    - Pay as you go
 * 
 * Documentation: https://developers.africastalking.com/docs/sms/overview
 */