<?php
/**
 * EmailProvider - Custom WrightCommerce
 * WrightCommerce - E-commerce Platform for East African Small Businesses
 * 
 * Custom email implementation for WrightCommerce
 * Free notification channel for all businesses
 * 
 * Features:
 * - WrightCommerce branding
 * - HTML and plain text support
 * - Transactional emails
 * 
 * Future: Can integrate with SendGrid, Mailgun, or AWS SES for better deliverability
 * 
 * @author WrightCommerce Team
 * @version 1.0
 */

require_once __DIR__ . '/../../config/email.php';

class EmailProvider {
    private $fromEmail;
    private $fromName;
    private $replyTo;
    
    public function __construct() {
        // Load config
        $configFile = __DIR__ . '/../../config/email.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->fromEmail = EMAIL_FROM_ADDRESS ?? 'noreply@wrightcommerce.com';
            $this->fromName = EMAIL_FROM_NAME ?? 'WrightCommerce';
            $this->replyTo = EMAIL_REPLY_TO ?? 'support@wrightcommerce.com';
        } else {
            $this->fromEmail = 'noreply@wrightcommerce.com';
            $this->fromName = 'WrightCommerce';
            $this->replyTo = 'support@wrightcommerce.com';
        }
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $body Email body (plain text or HTML)
     * @param bool $isHtml Whether body is HTML (default: true)
     * @return array Result with success status
     */
    public function send($to, $toName, $subject, $body, $isHtml = true) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid email address'
            ];
        }
        
        if (empty($subject) || empty($body)) {
            return [
                'success' => false,
                'error' => 'Subject and body are required'
            ];
        }
        
        // Build email body
        if ($isHtml) {
            $emailBody = $this->buildHtmlEmail($toName, $body, $subject);
            $contentType = 'text/html; charset=UTF-8';
        } else {
            $emailBody = $this->buildPlainTextEmail($toName, $body);
            $contentType = 'text/plain; charset=UTF-8';
        }
        
        // Build headers
        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->replyTo;
        $headers[] = 'Content-Type: ' . $contentType;
        $headers[] = 'X-Mailer: WrightCommerce/1.0';
        
        // Send email
        $result = mail($to, $subject, $emailBody, implode("\r\n", $headers));
        
        if ($result) {
            return [
                'success' => true,
                'email' => $to,
                'subject' => $subject
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to send email. Check PHP mail() configuration.',
                'email' => $to
            ];
        }
    }
    
    /**
     * Build HTML email with WrightCommerce branding
     * 
     * @param string $name Recipient name
     * @param string $content Email content
     * @param string $subject Email subject
     * @return string HTML email
     */
    private function buildHtmlEmail($name, $content, $subject) {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #2c3e50; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">📦 WrightCommerce</h1>
                            <p style="color: #ecf0f1; margin: 10px 0 0 0; font-size: 14px;">E-commerce for East African Businesses</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px 0; color: #333; font-size: 16px;">Hi ' . htmlspecialchars($name) . ',</p>
                            <div style="color: #555; font-size: 15px; line-height: 1.6;">
                                ' . nl2br(htmlspecialchars($content)) . '
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #ecf0f1;">
                            <p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
                                Thank you for choosing WrightCommerce
                            </p>
                            <p style="margin: 0; color: #999; font-size: 12px;">
                                © ' . date('Y') . ' WrightCommerce. All rights reserved.
                            </p>
                            <p style="margin: 10px 0 0 0; color: #999; font-size: 12px;">
                                If you have questions, contact us at <a href="mailto:' . $this->replyTo . '" style="color: #3498db;">' . $this->replyTo . '</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Build plain text email with WrightCommerce branding
     * 
     * @param string $name Recipient name
     * @param string $content Email content
     * @return string Plain text email
     */
    private function buildPlainTextEmail($name, $content) {
        return "
=====================================
📦 WRIGHTCOMMERCE
E-commerce for East African Businesses
=====================================

Hi {$name},

{$content}

-------------------------------------
Thank you for choosing WrightCommerce
© " . date('Y') . " WrightCommerce. All rights reserved.

Questions? Contact us at: {$this->replyTo}
";
    }
    
    /**
     * Send order confirmation email with rich formatting
     * 
     * @param string $to Recipient email
     * @param string $name Recipient name
     * @param array $orderData Order details
     * @return array Result
     */
    public function sendOrderConfirmation($to, $name, $orderData) {
        $subject = 'Order Confirmation - ' . $orderData['order_number'];
        
        $content = "
Thank you for your order!

Order Number: {$orderData['order_number']}
Total: KES " . number_format($orderData['total']) . "
Status: {$orderData['status']}

We'll process your order and keep you updated.

Items:
";
        
        foreach ($orderData['items'] as $item) {
            $content .= "- {$item['name']} x{$item['quantity']} - KES " . number_format($item['total']) . "\n";
        }
        
        return $this->send($to, $name, $subject, $content, true);
    }
}