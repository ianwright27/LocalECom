<?php
/**
 * Email Configuration
 * WrightCommerce Custom Email Provider
 * 
 * Free notification channel for all businesses
 * Uses PHP mail() function
 * 
 * For better deliverability in production, consider:
 * - SendGrid
 * - Mailgun
 * - AWS SES
 * - Postmark
 */

// Email From Settings
define('EMAIL_FROM_ADDRESS', 'noreply@wrightcommerce.com'); // Sender email
define('EMAIL_FROM_NAME', 'WrightCommerce'); // Sender name
define('EMAIL_REPLY_TO', 'support@wrightcommerce.com'); // Reply-to address

// Email Settings
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_ENABLE_HTML', true); // Send HTML emails by default

/**
 * SETUP INSTRUCTIONS:
 * 
 * 1. Local Development (XAMPP):
 *    - PHP mail() may not work on localhost
 *    - Install fake sendmail for testing:
 *      * Download: https://www.glob.com.au/sendmail/
 *      * Configure in php.ini
 *    - OR test email sending in production only
 * 
 * 2. Production Server:
 *    - Ensure server can send emails
 *    - Configure SPF, DKIM, DMARC records
 *    - Use authenticated SMTP if possible
 *    - Consider dedicated email service (better deliverability)
 * 
 * 3. Improve Deliverability:
 *    - Use company domain email (@yourcompany.com)
 *    - Set up SPF record for domain
 *    - Set up DKIM signing
 *    - Set up DMARC policy
 *    - Avoid spam trigger words
 *    - Include unsubscribe link (for marketing)
 * 
 * 4. Future: Integrate Email Service:
 *    - SendGrid: https://sendgrid.com (12k free emails/month)
 *    - Mailgun: https://www.mailgun.com (5k free emails/month)
 *    - AWS SES: https://aws.amazon.com/ses/ (62k free/month)
 *    - Postmark: https://postmarkapp.com (100 free/month)
 * 
 * Note: Current implementation is simple and free
 * Perfect for getting started and testing
 * Upgrade to dedicated service for better reliability
 */