<?php
/**
 * Branded bulk email rendering and delivery.
 *
 * No attachments are accepted: the campaign image is linked, never attached,
 * which removes the main virus-delivery vector. All text is plain text escaped
 * into HTML, and only local managed images can be selected.
 */

declare(strict_types=1);

function mcg_absolute_asset(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (strpos($relativePath, '..') !== false) {
        return '';
    }
    $parts = explode('/', $relativePath);
    $encoded = array_map(function ($part) {
        return rawurlencode($part);
    }, $parts);
    return mcg_absolute_url(implode('/', $encoded));
}

function mcg_email_paragraphs(string $text): string
{
    $parts = preg_split('/\n\s*\n/', trim($text));
    $out = '';
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $out .= '<p style="margin:0 0 16px;color:#335557;font-size:15px;line-height:1.75;">'
            . nl2br(mcg_e($part), false) . '</p>';
    }
    return $out;
}

function mcg_email_socials(bool $includeSocials): string
{
    if (!$includeSocials) {
        return '';
    }
    $networks = [
        'Instagram' => mcg_setting('instagram'),
        'Facebook' => mcg_setting('facebook'),
        'WhatsApp' => mcg_setting('whatsapp'),
        'LinkedIn' => mcg_setting('linkedin'),
        'YouTube' => mcg_setting('youtube'),
    ];
    $cells = '';
    foreach ($networks as $label => $url) {
        if ($url === '' || !mcg_web_url($url)) {
            continue;
        }
        $cells .= '<td style="padding:0 5px 8px 0;"><a href="' . mcg_e($url) . '" target="_blank" rel="noopener" style="display:inline-block;padding:8px 13px;border:1px solid #bad2d6;border-radius:999px;color:#115e62;font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;">' . mcg_e($label) . '</a></td>';
    }
    if ($cells === '') {
        return '';
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 0;"><tr>' . $cells . '</tr></table>';
}

function mcg_email_template(array $campaign): string
{
    $siteName = mcg_setting('site_name', 'MCG Biokinetics');
    $subject = (string) ($campaign['subject'] ?? '');
    $heading = (string) ($campaign['heading'] ?? $siteName);
    $body = (string) ($campaign['body'] ?? '');
    $image = (string) ($campaign['image'] ?? '');
    $buttonLabel = (string) ($campaign['button_label'] ?? 'Book an appointment');
    $buttonUrl = (string) ($campaign['button_url'] ?? mcg_setting('booking_url'));
    if ($buttonUrl === '' || !mcg_web_url($buttonUrl)) {
        $fallback = mcg_setting('booking_url');
        $buttonUrl = mcg_web_url($fallback) ? $fallback : '';
    }
    $includeSocials = !empty($campaign['include_socials']);
    $address = mcg_setting('contact_address');
    $phone = mcg_setting('contact_phone');
    $logo = mcg_absolute_asset('images/MCG BIOKINETICS LOGOtp.png');
    $imageHtml = '';
    if ($image !== '' && (strpos($image, 'admin/uploads/') === 0 || strpos($image, 'images/') === 0)) {
        $full = MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image);
        if (is_file($full)) {
            $imageHtml = '<tr><td style="padding:0 0 24px;"><img src="' . mcg_e(mcg_absolute_asset($image)) . '" alt="" width="600" style="display:block;width:100%;max-width:600px;height:auto;border-radius:4px;"></td></tr>';
        }
    }
    $socials = mcg_email_socials($includeSocials);
    $button = '';
    if ($buttonUrl !== '') {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 22px;"><tr><td style="border-radius:999px;background:#115e62;"><a href="' . mcg_e($buttonUrl) . '" target="_blank" rel="noopener" style="display:inline-block;padding:14px 24px;color:#ffffff;font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:.14em;text-transform:uppercase;text-decoration:none;">' . mcg_e($buttonLabel) . '</a></td></tr></table>';
    }

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . mcg_e($subject) . '</title>
</head>
<body style="margin:0;padding:0;background:#eef4f3;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . mcg_e($heading) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef4f3;padding:28px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden;">
<tr><td style="padding:22px 28px;background:#082f32;color:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="width:52px;"><img src="' . mcg_e($logo) . '" width="46" height="43" alt="" style="display:block;border-radius:50%;background:#bad2d6;"></td>
<td style="padding-left:12px;font-family:Georgia,serif;font-size:20px;color:#ffffff;">' . mcg_e($siteName) . '</td>
</tr></table>
</td></tr>
<tr><td style="padding:30px 30px 8px;">
<h1 style="margin:0 0 18px;font-family:Georgia,serif;font-size:34px;line-height:1.1;font-weight:400;color:#082f32;">' . mcg_e($heading) . '</h1>
</td></tr>
' . $imageHtml . '
<tr><td style="padding:0 30px 8px;">' . mcg_email_paragraphs($body) . '</td></tr>
<tr><td style="padding:4px 30px 8px;">' . $button . $socials . '</td></tr>
<tr><td style="padding:22px 30px;background:#f4f8f7;border-top:1px solid #e2eceb;font-family:Arial,sans-serif;font-size:11px;line-height:1.7;color:#5c7476;">
<p style="margin:0 0 4px;">' . mcg_e($address) . ' · ' . mcg_e($phone) . '</p>
<p style="margin:0 0 4px;">You are receiving this email from ' . mcg_e($siteName) . '.</p>
<p style="margin:0;"><a href="mailto:' . mcg_e(mcg_setting('admin_email')) . '?subject=Unsubscribe" style="color:#115e62;">Unsubscribe / manage preferences</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

function mcg_email_text(array $campaign): string
{
    $lines = [];
    $lines[] = (string) ($campaign['heading'] ?? '');
    $lines[] = '';
    $lines[] = (string) ($campaign['body'] ?? '');
    $lines[] = '';
    $buttonUrl = (string) ($campaign['button_url'] ?? mcg_setting('booking_url'));
    if ($buttonUrl !== '') {
        $lines[] = (string) ($campaign['button_label'] ?? 'Book an appointment') . ': ' . $buttonUrl;
    }
    $lines[] = '';
    $lines[] = mcg_setting('contact_address') . ' · ' . mcg_setting('contact_phone');
    $lines[] = 'You are receiving this email from ' . mcg_setting('site_name') . '.';
    return implode("\n", $lines);
}

function mcg_collect_recipients(bool $includeSubscribers, bool $includeContacts, string $manual): array
{
    $emails = [];
    if ($includeSubscribers) {
        foreach (mcg_read_data('subscribers', []) as $subscriber) {
            if (!empty($subscriber['active']) && mcg_valid_email($subscriber['email'] ?? '')) {
                $emails[strtolower((string) $subscriber['email'])] = (string) $subscriber['email'];
            }
        }
    }
    if ($includeContacts) {
        foreach (mcg_read_data('messages', []) as $message) {
            if (($message['status'] ?? '') === 'spam') {
                continue;
            }
            if (!empty($message['email']) && mcg_valid_email($message['email'])) {
                $emails[strtolower((string) $message['email'])] = (string) $message['email'];
            }
        }
    }
    $manual = str_replace([';', ','], "\n", $manual);
    foreach (preg_split('/\r?\n/', $manual) as $line) {
        $line = trim((string) $line);
        if ($line !== '' && mcg_valid_email($line)) {
            $emails[strtolower($line)] = $line;
        }
    }
    return array_values($emails);
}

function mcg_send_campaign(array $campaign, array $recipients): array
{
    if (!function_exists('mail')) {
        return ['sent' => 0, 'failed' => count($recipients), 'recipients' => count($recipients)];
    }
    $settings = mcg_settings();
    $fromEmail = mcg_valid_email($settings['from_email']) ? $settings['from_email'] : $settings['admin_email'];
    $fromName = $settings['from_name'] !== '' ? $settings['from_name'] : $settings['site_name'];
    $fromName = trim(str_replace(["\r", "\n"], ' ', $fromName));
    $subject = preg_replace('/[\r\n]+/', ' ', (string) ($campaign['subject'] ?? ''));
    $subject = mcg_substr(trim((string) $subject), 180);
    $html = mcg_email_template($campaign);
    $text = mcg_email_text($campaign);
    $batchSize = max(5, (int) ($settings['mail_batch_size'] ?? 25));

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: MCG Admin Portal';
    $headers[] = 'List-Unsubscribe: <mailto:' . $fromEmail . '?subject=Unsubscribe>';
    $headerString = implode("\r\n", $headers);

    $sent = 0;
    $failed = 0;
    $counter = 0;
    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }
    foreach ($recipients as $recipient) {
        if (!mcg_valid_email($recipient)) {
            $failed++;
            continue;
        }
        $ok = @mail($recipient, $subject, $html, $headerString);
        if ($ok) {
            $sent++;
        } else {
            $failed++;
        }
        $counter++;
        if ($counter % $batchSize === 0) {
            usleep(120000);
        }
    }

    $log = mcg_read_data('mail-log', []);
    $log[] = [
        'id' => mcg_next_id($log),
        'subject' => $subject,
        'heading' => (string) ($campaign['heading'] ?? ''),
        'recipients' => count($recipients),
        'sent' => $sent,
        'failed' => $failed,
        'image' => (string) ($campaign['image'] ?? ''),
        'created' => mcg_now(),
        'sent_by' => mcg_current_user() !== null ? (string) (mcg_current_user()['username'] ?? 'admin') : 'system',
    ];
    mcg_write_data('mail-log', $log);

    return ['sent' => $sent, 'failed' => $failed, 'recipients' => count($recipients)];
}
