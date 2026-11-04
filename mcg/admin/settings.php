<?php
/**
 * Settings: sender identity, social links, booking URL, email limits and password.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
mcg_require_auth();

$user = mcg_current_user();
$settings = mcg_settings();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'settings') {
        $input = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
        $errors = [];
        if (($input['from_email'] ?? '') !== '' && !mcg_valid_email($input['from_email'])) {
            $errors[] = 'The sender email address is not valid.';
        }
        if (($input['admin_email'] ?? '') !== '' && !mcg_valid_email($input['admin_email'])) {
            $errors[] = 'The admin email address is not valid.';
        }
        foreach (['booking_url', 'instagram', 'facebook', 'whatsapp', 'linkedin', 'youtube', 'site_url'] as $urlKey) {
            $value = trim((string) ($input[$urlKey] ?? ''));
            if ($value !== '' && !mcg_web_url($value)) {
                $errors[] = 'Please enter a full http(s) URL for ' . $urlKey . '.';
            }
        }
        $input['mail_batch_size'] = (string) max(5, min(100, (int) ($input['mail_batch_size'] ?? 25)));
        $input['mail_max_recipients'] = (string) max(50, min(1000, (int) ($input['mail_max_recipients'] ?? 300)));
        if ($errors) {
            foreach ($errors as $error) {
                mcg_flash('error', $error);
            }
        } else {
            mcg_save_settings($input);
            mcg_flash('success', 'Settings saved.');
        }
        mcg_redirect('settings.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($new !== $confirm) {
            mcg_flash('error', 'The new passwords do not match.');
        } elseif ($user) {
            $result = mcg_update_password((int) $user['id'], $current, $new);
            mcg_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Password updated.' : $result['error']);
        }
        mcg_redirect('settings.php');
    }
}

$settings = mcg_settings();
$checks = [
    ['HTTPS', mcg_is_https(), 'Use HTTPS in production so credentials and messages are encrypted.'],
    ['GD extension', function_exists('imagecreatefromstring'), 'Required to sanitise uploaded images.'],
    ['PHP mail()', function_exists('mail'), 'Used for bulk campaigns and contact notifications.'],
    ['Data folder writable', is_writable(MCG_DATA_DIR), MCG_DATA_DIR],
    ['Uploads folder writable', is_writable(MCG_UPLOAD_DIR), MCG_UPLOAD_DIR],
    ['Data deny rules', is_file(MCG_DATA_DIR . '/.htaccess'), 'Apache .htaccess protection for JSON files.'],
    ['Admin account', count(mcg_users()) > 0, 'First-run setup is complete.'],
];

mcg_admin_header('Settings', 'settings', 'Identity, links, mail limits and account security.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Configuration</p>
        <h2>Settings</h2>
        <p>These values are used by the mail template, contact links and generated pages.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="api/reviews.php" target="_blank" rel="noopener"><?= mcg_icon('eye') ?> Reviews JSON</a>
    </div>
</div>

<div class="split-2">
    <form method="post" action="settings.php" class="panel">
        <?= mcg_csrf_field() ?>
        <input type="hidden" name="action" value="settings">
        <div class="panel-head">
            <div>
                <h3>Practice &amp; email identity</h3>
                <p>Used in the branded bulk email and contact links.</p>
            </div>
        </div>
        <div class="settings-section">
            <div class="form-grid">
                <div class="field">
                    <label for="site_name">Practice name</label>
                    <input type="text" id="site_name" name="settings[site_name]" value="<?= mcg_e($settings['site_name']) ?>" maxlength="160">
                </div>
                <div class="field">
                    <label for="site_url">Public site URL</label>
                    <input type="url" id="site_url" name="settings[site_url]" value="<?= mcg_e($settings['site_url']) ?>" placeholder="https://mcgbiokinetics.co.za">
                    <span class="field-help">Used to make email images absolute. Leave blank to derive from the request.</span>
                </div>
                <div class="field">
                    <label for="admin_email">Admin notification email</label>
                    <input type="email" id="admin_email" name="settings[admin_email]" value="<?= mcg_e($settings['admin_email']) ?>">
                </div>
                <div class="field">
                    <label for="from_email">Campaign sender email</label>
                    <input type="email" id="from_email" name="settings[from_email]" value="<?= mcg_e($settings['from_email']) ?>">
                </div>
                <div class="field">
                    <label for="from_name">Campaign sender name</label>
                    <input type="text" id="from_name" name="settings[from_name]" value="<?= mcg_e($settings['from_name']) ?>" maxlength="160">
                </div>
                <div class="field">
                    <label for="contact_phone">Contact phone</label>
                    <input type="text" id="contact_phone" name="settings[contact_phone]" value="<?= mcg_e($settings['contact_phone']) ?>" maxlength="80">
                </div>
                <div class="field span-2">
                    <label for="contact_address">Address</label>
                    <input type="text" id="contact_address" name="settings[contact_address]" value="<?= mcg_e($settings['contact_address']) ?>" maxlength="220">
                </div>
                <div class="field span-2">
                    <label for="gallery_intro">Gallery introduction</label>
                    <textarea id="gallery_intro" name="settings[gallery_intro]" maxlength="300" style="min-height: 78px;"><?= mcg_e($settings['gallery_intro']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3>Links</h3>
            <p>Buttons appear in every campaign email and are used by the booking CTA.</p>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="booking_url">Booking platform URL</label>
                    <input type="url" id="booking_url" name="settings[booking_url]" value="<?= mcg_e($settings['booking_url']) ?>">
                </div>
                <div class="field">
                    <label for="instagram">Instagram</label>
                    <input type="url" id="instagram" name="settings[instagram]" value="<?= mcg_e($settings['instagram']) ?>">
                </div>
                <div class="field">
                    <label for="facebook">Facebook</label>
                    <input type="url" id="facebook" name="settings[facebook]" value="<?= mcg_e($settings['facebook']) ?>">
                </div>
                <div class="field">
                    <label for="whatsapp">WhatsApp</label>
                    <input type="url" id="whatsapp" name="settings[whatsapp]" value="<?= mcg_e($settings['whatsapp']) ?>">
                </div>
                <div class="field">
                    <label for="linkedin">LinkedIn</label>
                    <input type="url" id="linkedin" name="settings[linkedin]" value="<?= mcg_e($settings['linkedin']) ?>">
                </div>
                <div class="field">
                    <label for="youtube">YouTube</label>
                    <input type="url" id="youtube" name="settings[youtube]" value="<?= mcg_e($settings['youtube']) ?>">
                </div>
            </div>
        </div>

        <div class="settings-section">
            <h3>Bulk mail limits</h3>
            <p>Delivery is intentionally bounded to protect the mail server and the domain’s reputation.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="mail_batch_size">Batch size</label>
                    <input type="number" id="mail_batch_size" name="settings[mail_batch_size]" value="<?= mcg_e($settings['mail_batch_size']) ?>" min="5" max="100">
                    <span class="field-help">Messages sent per mail loop step.</span>
                </div>
                <div class="field">
                    <label for="mail_max_recipients">Maximum recipients per campaign</label>
                    <input type="number" id="mail_max_recipients" name="settings[mail_max_recipients]" value="<?= mcg_e($settings['mail_max_recipients']) ?>" min="50" max="1000">
                </div>
            </div>
        </div>

        <div class="panel-foot">
            <button class="btn btn-primary" type="submit"><?= mcg_icon('check') ?> Save settings</button>
        </div>
    </form>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Security</h3>
                    <p>Checks for this installation.</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="security-list">
                    <?php foreach ($checks as $check): ?>
                        <div class="security-item <?= $check[1] ? '' : 'is-bad' ?>">
                            <?= mcg_icon($check[1] ? 'check' : 'warning') ?>
                            <div>
                                <strong><?= mcg_e($check[0]) ?></strong>
                                <span><?= mcg_e($check[2]) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Change password</h3>
                    <p>Signed in as <strong><?= mcg_e($user['username'] ?? '') ?></strong>.</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="post" action="settings.php">
                    <?= mcg_csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="field">
                        <label for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="10" required>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="10" required>
                    </div>
                    <button class="btn btn-secondary btn-block" type="submit"><?= mcg_icon('lock') ?> Update password</button>
                </form>
            </div>
        </section>

        <section class="notice">
            <p><strong>Deployment note.</strong> Request the site over HTTPS. If the site runs on Nginx, deny direct access to <span class="mono">/admin/data</span> and PHP execution in <span class="mono">/admin/uploads</span> in the server configuration; the bundled <span class="mono">.htaccess</span> files cover Apache.</p>
        </section>
    </div>
</div>

<?php mcg_admin_footer(); ?>
