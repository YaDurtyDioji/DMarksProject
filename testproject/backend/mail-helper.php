<?php
// backend/mail-helper.php
require_once __DIR__ . '/db.php';

/**
 * Send notification email to configured Admin Notification Email addresses
 */
function sendAdminNotificationEmail($pdo, $subject, $bodyHtml) {
    $adminEmailsRaw = getSetting($pdo, 'admin_notification_emails', '');
    if (empty($adminEmailsRaw)) return;

    $emails = array_map('trim', explode(',', $adminEmailsRaw));
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: barewithtea Portal <no-reply@barewithtea.co.za>\r\n";

    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            @mail($email, $subject, $bodyHtml, $headers);
        }
    }
}

/**
 * Dispatch per-function notifications based on Settings Matrix
 */
function triggerNotification($pdo, $eventType, $data) {
    $emailEnabled = getSetting($pdo, "notif_{$eventType}_email", '1') === '1';
    $waEnabled = getSetting($pdo, "notif_{$eventType}_wa", '1') === '1';
    $target = getSetting($pdo, "notif_{$eventType}_target", 'both');

    $clientName = $data['client_name'] ?? 'Client';
    $clientEmail = $data['client_email'] ?? '';
    $service = $data['service'] ?? '';
    $bDate = $data['booking_date'] ?? '';
    $bTime = $data['booking_time'] ?? '';

    // Admin Notification
    if ($emailEnabled && ($target === 'admin' || $target === 'both')) {
        $subject = "[ADMIN ALERT] " . ucfirst(str_replace('_', ' ', $eventType)) . ": {$clientName}";
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h3>barewithtea Admin Notification</h3>
            <p><strong>Event:</strong> " . htmlspecialchars(strtoupper($eventType)) . "</p>
            <p><strong>Client:</strong> " . htmlspecialchars($clientName) . " (" . htmlspecialchars($clientEmail) . ")</p>
            <p><strong>Service:</strong> " . htmlspecialchars($service) . "</p>
            <p><strong>Date & Time:</strong> " . htmlspecialchars($bDate) . " @ " . htmlspecialchars($bTime) . "</p>
            <p>Log in to the dashboard to view full details.</p>
        </body>
        </html>
        ";
        sendAdminNotificationEmail($pdo, $subject, $body);
    }

    return [
        'email_sent' => $emailEnabled,
        'wa_enabled' => $waEnabled
    ];
}

/**
 * Send booking completion email with invoice details and feedback link
 */
function sendCompletionInvoiceEmail($booking, $reviewUrl) {
    $to = $booking['client_email'];
    $subject = "Invoice & Thank You for visiting barewithtea. [Booking #" . $booking['id'] . "]";
    
    $subtotal = number_format((float)($booking['subtotal'] ?? 0), 2);
    $discountVal = number_format((float)($booking['discount_value'] ?? 0), 2);
    $discountType = $booking['discount_type'] ?? 'fixed';
    $discountLabel = $discountType === 'percent' ? "{$discountVal}%" : "R{$discountVal}";
    $total = number_format((float)($booking['total_amount'] ?? 0), 2);
    
    $body = "
    <html>
    <head>
      <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; background: #f8f9fa; margin: 0; padding: 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e0e0e0; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; color: #111; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .table th { background: #f4f4f4; text-transform: uppercase; font-size: 12px; }
        .total-row { font-weight: bold; font-size: 16px; color: #111; background: #fafafa; }
        .btn { display: inline-block; padding: 12px 24px; background: #111; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; text-align: center; }
        .footer { font-size: 12px; color: #777; margin-top: 25px; text-align: center; border-top: 1px solid #eee; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='header'>
          <h2>barewithtea.</h2>
          <p style='margin: 5px 0; font-size: 14px; color: #555;'>Official Invoice & Visit Summary</p>
        </div>
        <p>Dear <strong>" . htmlspecialchars($booking['client_name']) . "</strong>,</p>
        <p>Thank you for visiting barewithtea! Here is the detailed summary and official invoice for your appointment on <strong>" . htmlspecialchars($booking['booking_date']) . "</strong> at <strong>" . htmlspecialchars($booking['booking_time']) . "</strong>.</p>

        <table class='table'>
          <thead>
            <tr>
              <th>Service</th>
              <th>Date / Time</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>" . htmlspecialchars($booking['service']) . "</td>
              <td>" . htmlspecialchars($booking['booking_date']) . " @ " . htmlspecialchars($booking['booking_time']) . "</td>
              <td>R" . $subtotal . "</td>
            </tr>
            <tr>
              <td colspan='2' style='text-align: right;'><strong>Discount (" . $discountLabel . "):</strong></td>
              <td>-R" . number_format((float)($booking['subtotal'] - $booking['total_amount']), 2) . "</td>
            </tr>
            <tr class='total-row'>
              <td colspan='2' style='text-align: right;'><strong>Total Paid / Due:</strong></td>
              <td><strong>R" . $total . "</strong></td>
            </tr>
          </tbody>
        </table>

        <div style='text-align: center; margin-top: 25px;'>
          <p><strong>We value your experience!</strong> Please take 30 seconds to rate your treatment session and let us know how we did:</p>
          <a href='" . htmlspecialchars($reviewUrl) . "' class='btn'>Rate Your Experience & Review Us</a>
        </div>

        <div class='footer'>
          <p>barewithtea. Boutique Salon & Laser Clinic | Gauteng, South Africa</p>
          <p>Questions? Reply directly to this email or contact us on WhatsApp.</p>
        </div>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: barewithtea <no-reply@barewithtea.co.za>\r\n";

    @mail($to, $subject, $body, $headers);
}

/**
 * Send indemnity link dispatch email
 */
function sendIndemnityEmail($clientEmail, $clientName, $indemnityUrl) {
    $to = $clientEmail;
    $subject = "Action Required: Complete Your Indemnity Form - barewithtea";
    
    $body = "
    <html>
    <head>
      <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; background: #f8f9fa; padding: 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e0e0e0; padding: 25px; }
        .btn { display: inline-block; padding: 12px 24px; background: #111; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
      </style>
    </head>
    <body>
      <div class='card'>
        <h2>barewithtea.</h2>
        <p>Dear <strong>" . htmlspecialchars($clientName) . "</strong>,</p>
        <p>Before your upcoming appointment, please complete our online digital indemnity form. This ensures we have all necessary details for a safe and comfortable treatment session.</p>
        <p style='text-align: center;'>
          <a href='" . htmlspecialchars($indemnityUrl) . "' class='btn'>Complete Digital Indemnity Form</a>
        </p>
        <p>If you have already submitted this or are an existing client, thank you!</p>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: barewithtea <no-reply@barewithtea.co.za>\r\n";

    @mail($to, $subject, $body, $headers);
}
?>
