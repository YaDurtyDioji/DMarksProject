<?php
/**
 * Bulk email composer, preview and delivery.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/mailer.php';
mcg_require_auth();

$gallery = mcg_read_data('gallery', ['categories' => [], 'photos' => []]);
$photos = isset($gallery['photos']) && is_array($gallery['photos']) ? $gallery['photos'] : [];
$settings = mcg_settings();
$form = [
    'subject' => 'News from MCG Biokinetics',
    'heading' => 'Movement, restored.',
    'body' => "A short update from the practice.\n\nWrite your message here. Line breaks are preserved, and every email is wrapped in the MCG Biokinetics brand.",
    'image' => '',
    'button_label' => 'Book an appointment',
    'button_url' => $settings['booking_url'],
    'include_socials' => true,
    'include_subscribers' => true,
    'include_contacts' => false,
    'manual_emails' => '',
];
$errors = [];
$previewHtml = '';
$sentResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : 'preview';

    $form['subject'] = mcg_clean_text($_POST['subject'] ?? '', 180);
    $form['heading'] = mcg_clean_text($_POST['heading'] ?? '', 180);
    $form['body'] = mcg_clean_multiline($_POST['body'] ?? '', 12000);
    $form['image'] = isset($_POST['image']) ? (string) $_POST['image'] : '';
    $form['button_label'] = mcg_clean_text($_POST['button_label'] ?? 'Book an appointment', 60);
    $form['button_url'] = trim((string) ($_POST['button_url'] ?? ''));
    $form['include_socials'] = isset($_POST['include_socials']);
    $form['include_subscribers'] = isset($_POST['include_subscribers']);
    $form['include_contacts'] = isset($_POST['include_contacts']);
    $form['manual_emails'] = mcg_clean_multiline($_POST['manual_emails'] ?? '', 8000);

    if (!empty($_FILES['campaign_image']['name'])) {
        $upload = mcg_handle_image_upload($_FILES['campaign_image'], 'campaign');
        if ($upload['ok']) {
            $form['image'] = $upload['path'];
        } else {
            $errors[] = $upload['error'];
        }
    }
    if ($form['image'] !== '' && strpos($form['image'], 'admin/uploads/') !== 0 && strpos($form['image'], 'images/') !== 0) {
        $form['image'] = '';
    }
    if ($form['button_url'] !== '' && !mcg_web_url($form['button_url'])) {
        $errors[] = 'The button URL must be a full http(s) URL.';
        $form['button_url'] = $settings['booking_url'];
    }

    $recipients = mcg_collect_recipients($form['include_subscribers'], $form['include_contacts'], $form['manual_emails']);
    $maxRecipients = max(50, min(1000, (int) $settings['mail_max_recipients']));
    $campaign = [
        'subject' => $form['subject'],
        'heading' => $form['heading'],
        'body' => $form['body'],
        'image' => $form['image'],
        'button_label' => $form['button_label'],
        'button_url' => $form['button_url'],
        'include_socials' => $form['include_socials'],
    ];
    $previewHtml = mcg_email_template($campaign);

    if ($action === 'send') {
        if ($form['subject'] === '' || $form['body'] === '') {
            $errors[] = 'A subject and message body are required.';
        }
        if (!$recipients) {
            $errors[] = 'Choose at least one recipient.';
        }
        if (count($recipients) > $maxRecipients) {
            $errors[] = 'This campaign has ' . count($recipients) . ' recipients; the limit is ' . $maxRecipients . '.';
        }
        if (!$errors) {
            $limit = mcg_rate_limit('campaign:' . mcg_ip_hash(), 3, 1800);
            if (!$limit['ok']) {
                $errors[] = 'Bulk sending is rate limited. Try again in ' . max(1, (int) ceil($limit['retry'] / 60)) . ' minute(s).';
            } else {
                $sentResult = mcg_send_campaign($campaign, $recipients);
                mcg_flash('success', 'Campaign sent to ' . $sentResult['sent'] . ' recipient(s); ' . $sentResult['failed'] . ' failed.');
                mcg_redirect('mail.php?sent=1');
            }
        }
    } elseif ($action === 'test') {
        if ($form['subject'] === '' || $form['body'] === '') {
            $errors[] = 'Add a subject and message before sending a test.';
        }
        $adminEmail = $settings['admin_email'];
        if (!mcg_valid_email($adminEmail)) {
            $errors[] = 'Add a valid admin email in Settings first.';
        }
        if (!$errors) {
            $result = mcg_send_campaign($campaign, [$adminEmail]);
            mcg_flash($result['sent'] ? 'success' : 'error', $result['sent'] ? 'Test email sent to ' . $adminEmail . '.' : 'The test email could not be sent. Check the server mail configuration.');
            mcg_redirect('mail.php');
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            mcg_flash('error', $error);
        }
    }
}

if (isset($_GET['sent'])) {
    mcg_flash('success', 'Campaign finished. Check the delivery log below.');
}

/* Build a preview for the initial page view if no POST preview was rendered. */
if ($previewHtml === '') {
    $previewHtml = mcg_email_template([
        'subject' => $form['subject'],
        'heading' => $form['heading'],
        'body' => $form['body'],
        'image' => $form['image'],
        'button_label' => $form['button_label'],
        'button_url' => $form['button_url'],
        'include_socials' => $form['include_socials'],
    ]);
}

$subscriberCount = mcg_subscriber_count();
$galleryPaths = [];
foreach ($photos as $photo) {
    if (!empty($photo['path'])) {
        $galleryPaths[] = (string) $photo['path'];
    }
}
$contactEmails = [];
foreach (mcg_read_data('messages', []) as $message) {
    if (($message['status'] ?? '') !== 'spam' && mcg_valid_email($message['email'] ?? '')) {
        $contactEmails[strtolower((string) $message['email'])] = true;
    }
}
$contactCount = count($contactEmails);
$log = mcg_read_data('mail-log', []);
$log = array_slice(array_reverse($log), 0, 8);
$configuredSocials = 0;
foreach (['instagram', 'facebook', 'whatsapp', 'linkedin', 'youtube'] as $key) {
    if (mcg_setting($key) !== '') {
        $configuredSocials++;
    }
}

mcg_admin_header('Bulk email', 'mail', 'Compose a branded campaign with an image, booking button and social links.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Communications</p>
        <h2>Bulk email</h2>
        <p>Plain-text content is safely wrapped in the MCG brand template. Images are linked, never attached, and only managed images can be selected.</p>
    </div>
    <div class="page-actions">
        <span class="pill pill-category"><?= $subscriberCount ?> subscribers</span>
        <span class="pill pill-category"><?= $contactCount ?> contact emails</span>
        <span class="pill pill-category"><?= $configuredSocials ?> social links</span>
    </div>
</div>

<div class="mail-layout">
    <form method="post" action="mail.php" enctype="multipart/form-data" class="panel">
        <?= mcg_csrf_field() ?>
        <div class="panel-head">
            <div>
                <h3>Compose campaign</h3>
                <p>All fields are plain text; the template handles the design.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="field">
                <label for="subject">Subject line</label>
                <input type="text" id="subject" name="subject" value="<?= mcg_e($form['subject']) ?>" maxlength="180" required>
            </div>
            <div class="field">
                <label for="heading">Email heading</label>
                <input type="text" id="heading" name="heading" value="<?= mcg_e($form['heading']) ?>" maxlength="180">
            </div>
            <div class="field">
                <label for="body">Message</label>
                <textarea id="body" name="body" maxlength="12000" style="min-height: 170px;" required><?= mcg_e($form['body']) ?></textarea>
                <span class="field-help">Line breaks are preserved. No HTML is accepted from this field.</span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="image">Campaign image</label>
                    <select id="image" name="image">
                        <option value="">No image</option>
                        <?php foreach ($photos as $photo): ?>
                            <?php $path = (string) ($photo['path'] ?? ''); ?>
                            <?php if ($path === '') { continue; } ?>
                            <option value="<?= mcg_e($path) ?>" <?= $form['image'] === $path ? 'selected' : '' ?>>
                                <?= mcg_e($photo['caption'] ?? $path) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($form['image'] !== '' && !in_array($form['image'], $galleryPaths, true)): ?>
                            <option value="<?= mcg_e($form['image']) ?>" selected>Uploaded image — <?= mcg_e(basename($form['image'])) ?></option>
                        <?php endif; ?>
                    </select>
                    <span class="field-help">Choose a gallery photograph, or upload a one-off image below.</span>
                </div>
                <div class="field">
                    <label for="campaign_image">Or upload an image</label>
                    <input type="file" id="campaign_image" name="campaign_image" accept="image/jpeg,image/png,image/webp">
                    <span class="field-help">Validated and re-encoded as JPEG. It is linked in the email, not attached.</span>
                </div>
                <div class="field">
                    <label for="button_label">Button label</label>
                    <input type="text" id="button_label" name="button_label" value="<?= mcg_e($form['button_label']) ?>" maxlength="60">
                </div>
                <div class="field">
                    <label for="button_url">Button URL</label>
                    <input type="url" id="button_url" name="button_url" value="<?= mcg_e($form['button_url']) ?>">
                </div>
            </div>

            <label class="check" style="margin: 12px 0;">
                <input type="checkbox" name="include_socials" value="1" <?= $form['include_socials'] ? 'checked' : '' ?>>
                Include social media buttons (<?= $configuredSocials ?> configured in Settings)
            </label>

            <hr>

            <p class="field-label">Recipients</p>
            <div class="checks" style="margin-bottom: 10px;">
                <label class="check">
                    <input type="checkbox" name="include_subscribers" value="1" <?= $form['include_subscribers'] ? 'checked' : '' ?>>
                    Active subscribers (<?= $subscriberCount ?>)
                </label>
                <label class="check">
                    <input type="checkbox" name="include_contacts" value="1" <?= $form['include_contacts'] ? 'checked' : '' ?>>
                    Contact form senders (<?= $contactCount ?>)
                </label>
            </div>
            <div class="field">
                <label for="manual_emails">Additional addresses</label>
                <textarea id="manual_emails" name="manual_emails" style="min-height: 82px;" placeholder="One email per line, or comma separated"><?= mcg_e($form['manual_emails']) ?></textarea>
                <span class="field-help">Duplicates and invalid addresses are removed automatically. Maximum <?= (int) $settings['mail_max_recipients'] ?> recipients per campaign.</span>
            </div>

            <div class="button-row" style="margin-top: 14px;">
                <button class="btn btn-primary" type="submit" name="action" value="send" data-confirm="Send this campaign now? This cannot be undone."><?= mcg_icon('mail') ?> Send campaign</button>
                <button class="btn btn-secondary" type="submit" name="action" value="preview"><?= mcg_icon('eye') ?> Refresh preview</button>
                <button class="btn btn-ghost" type="submit" name="action" value="test"><?= mcg_icon('check') ?> Send test to me</button>
            </div>
            <div class="notice" style="margin-top: 14px;">
                <p><strong>Delivery limits.</strong> 3 campaigns per 30 minutes, up to <?= (int) $settings['mail_max_recipients'] ?> recipients each. No attachments are permitted; the campaign image is linked.</p>
            </div>
        </div>
    </form>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Live preview</h3>
                    <p>The exact HTML that will be sent.</p>
                </div>
            </div>
            <div class="panel-body">
                <iframe class="mail-preview-frame" title="Email preview" sandbox srcdoc="<?= mcg_e($previewHtml) ?>"></iframe>
                <?php if ($form['image'] !== ''): ?>
                    <p class="field-help" style="margin-top: 8px;">Campaign image: <span class="mono"><?= mcg_e($form['image']) ?></span></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Recent campaigns</h3>
                    <p>Last eight delivery attempts.</p>
                </div>
            </div>
            <div class="panel-body flush">
                <?php if (!$log): ?>
                    <div class="table-empty" style="padding: 32px 18px;">
                        <?= mcg_icon('mail') ?>
                        <p>No campaigns have been sent yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data mail-log">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Sent</th>
                                    <th>Failed</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($log as $item): ?>
                                    <tr>
                                        <td><?= mcg_e(mcg_substr((string) ($item['subject'] ?? ''), 46)) ?></td>
                                        <td><?= (int) ($item['sent'] ?? 0) ?>/<?= (int) ($item['recipients'] ?? 0) ?></td>
                                        <td><?= (int) ($item['failed'] ?? 0) ?></td>
                                        <td class="muted"><?= mcg_e(mcg_format_date($item['created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php mcg_admin_footer(); ?>
