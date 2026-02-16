<?php
/**
 * Paystack Configuration
 * 
 * Get your API keys from: https://dashboard.paystack.com/#/settings/developer
 * 
 * Test Mode Keys (for development):
 * - Use test keys to test payments without real money
 * - Test cards: https://paystack.com/docs/payments/test-payments/
 * 
 * Live Mode Keys (for production):
 * - Use live keys for real transactions
 * - Switch after thorough testing
 */

// PAYSTACK TEST KEYS (Replace with your own from Paystack Dashboard)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_0b3....90322'); // Public Key
define('PAYSTACK_SECRET_KEY', 'sk_test_243....7b700'); // Secret Key

// PAYSTACK LIVE KEYS (Use in production only)
// define('PAYSTACK_PUBLIC_KEY', 'pk_live_xxxxxxxxxxxxxxxxxxxx');
// define('PAYSTACK_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxxxxx');

// Webhook URL (Set this in Paystack Dashboard)
// http://localhost/wrightcommerce/public/webhooks/paystack
// OR your production URL: https://yourdomain.com/webhooks/paystack

// Payment Settings
define('PAYSTACK_CURRENCY', 'KES'); // Kenyan Shillings
define('PAYSTACK_CALLBACK_URL', 'http://localhost/wrightcommerce/public/payment-callback.php');

/**
 * SETUP INSTRUCTIONS:
 * 
 * 1. Create Paystack Account:
 *    - Go to https://paystack.com
 *    - Sign up for free account
 *    - Verify your email
 * 
 * 2. Get API Keys:
 *    - Login to Paystack Dashboard
 *    - Go to Settings → API Keys & Webhooks
 *    - Copy Public Key (pk_test_...)
 *    - Copy Secret Key (sk_test_...)
 *    - Paste them above
 * 
 * 3. Setup Webhook:
 *    - In Paystack Dashboard
 *    - Go to Settings → API Keys & Webhooks
 *    - Click "Add Webhook URL"
 *    - Add: http://localhost/wrightcommerce/public/webhooks/paystack
 *    - (Or your production URL)
 *    - Save
 * 
 * 4. Test Payment:
 *    - Use test card: 5061 0200 0000 0000 181
 *    - CVV: Any 3 digits
 *    - Expiry: Any future date
 *    - PIN: 1234
 *    - OTP: 123456
 * 
 * 5. Go Live (When Ready):
 *    - Complete Paystack KYC verification
 *    - Get live API keys
 *    - Replace test keys with live keys
 *    - Update webhook URL to production domain
 */