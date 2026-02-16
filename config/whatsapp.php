<?php
/**
 * WhatsApp Cloud API Configuration
 * 
 * Get your credentials from: https://developers.facebook.com
 * 
 * Requirements:
 * - Meta Business Account
 * - WhatsApp Business App
 * - Approved message templates
 * - Phone Number ID
 */

// WhatsApp Cloud API Credentials
// define('WHATSAPP_PHONE_NUMBER_ID', 'your_phone_number_id_here'); // From WhatsApp Business App
// define('WHATSAPP_ACCESS_TOKEN', 'your_access_token_here'); // From Meta App Dashboard
// define('WHATSAPP_APP_SECRET', 'your_app_secret_here'); // For webhook verification

define('WHATSAPP_PHONE_NUMBER_ID', '1062....4890'); // From WhatsApp Business App
define('WHATSAPP_ACCESS_TOKEN', 'EAAMtxSEP8A0BQn.....V8dI86aqoMPsggZDZD'); // From Meta App Dashboard
define('WHATSAPP_APP_SECRET', 'your_app_secret_here'); // For webhook verification

// Webhook Settings
define('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'your_webhook_verify_token_here'); // Create your own secure token

/**
 * SETUP INSTRUCTIONS:
 * 
 * 1. Create Meta Business Account:
 *    - Go to https://business.facebook.com
 *    - Create business account
 *    - Verify business details
 * 
 * 2. Create WhatsApp Business App:
 *    - Go to https://developers.facebook.com
 *    - Create new app
 *    - Add WhatsApp product
 *    - Complete setup wizard
 * 
 * 3. Get Phone Number ID:
 *    - In WhatsApp > API Setup
 *    - Copy Phone Number ID
 *    - Paste above
 * 
 * 4. Get Access Token:
 *    - In WhatsApp > API Setup
 *    - Generate temporary token (24 hours)
 *    - OR create system user for permanent token
 *    - Paste above
 * 
 * 5. Create Message Templates:
 *    - Go to WhatsApp > Message Templates
 *    - Create templates for:
 *      * order_confirmation
 *      * payment_confirmation
 *      * order_processing
 *      * order_shipped
 *      * order_delivered
 *    - Submit for approval (required!)
 *    - Wait for approval (usually 1-2 days)
 * 
 * 6. Setup Webhook (Optional but recommended):
 *    - In WhatsApp > Configuration
 *    - Add Callback URL: https://yourdomain.com/webhooks/whatsapp
 *    - Add Verify Token (create your own secure token)
 *    - Subscribe to message status updates
 * 
 * 7. Go Live:
 *    - Verify your business with Meta
 *    - Add payment method
 *    - Register official business phone number
 *    - Get full API access
 * 
 * Example Template (order_confirmation):
 * 
 * Name: order_confirmation
 * Category: TRANSACTIONAL
 * Language: English
 * 
 * Body:
 * Hi {{1}}, your order {{2}} for {{3}} has been received! 
 * We'll notify you when it's ready. Thank you for choosing WrightCommerce!
 * 
 * Parameters:
 * {{1}} = Customer name
 * {{2}} = Order number
 * {{3}} = Total amount (e.g., KES 5,000)
 * 
 * Documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
 */