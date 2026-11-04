<?php
/**
 * Admin dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
mcg_require_auth();

$pages = mcg_read_data('pages', []);
$gallery = mcg_read_data('gallery', ['categories' => [], 'photos' => []]);
$reviews = mcg_read_data('reviews', []);
$messages = mcg_read_data('messages', []);
$subscribers = mcg_read_data('subscribers', []);
$mailLog = mcg_read_data('mail-log', []);

$published = 0;
foreach ($pages as $page) {
    if (($page['status'] ?? '') === 'published') {
        $published++;
    }
}
$photos = isset($gallery['photos']) && is_array($gallery['photos']) ? $gallery['photos'] : [];
$categories = isset($gallery['categories']) && is_array($gallery['categories']) ? $gallery['categories'] : [];
$unread = mcg_unread_count();
$pendingReviews = mcg_review_pending_count();
$activeSubscribers = mcg_subscriber_count();
$latestMessages = array_slice(array_reverse($messages), 0, 5);
$latestReviews = array_slice(array_reverse($reviews), 0, 4);
$latestCampaigns = array_slice(array_reverse($mailLog), 0, 3);
$user = mcg_current_user();
$displayName = $user && isset($user['username']) ? $user['username'] : 'there';

mcg_admin_header('Dashboard', 'dashboard', 'A snapshot of the practice website and incoming enquiries.');
?>

<section class="dashboard-hero">
    <div>
        <p class="eyebrow" style="color: var(--mist);">MCG Biokinetics · Admin</p>
        <h2>Good to see you, <?= mcg_e($displayName) ?>.</h2>
        <p>Everything that keeps the website current — pages, practice photography, patient reviews and the inbox — is here.</p>
    </div>
    <div class="button-row">
        <a class="btn btn-light" href="page-edit.php"><?= mcg_icon('plus') ?> New page</a>
        <a class="btn btn-secondary" href="mail.php"><?= mcg_icon('mail') ?> Compose email</a>
    </div>
</section>

<div class="quick-grid" style="margin-top: 16px;">
    <a class="quick-card" href="gallery.php"><?= mcg_icon('gallery') ?><span>Upload gallery photos</span><?= mcg_icon('arrow-right', 'icon arrow') ?></a>
    <a class="quick-card" href="reviews.php"><?= mcg_icon('reviews') ?><span>Add a client review</span><?= mcg_icon('arrow-right', 'icon arrow') ?></a>
    <a class="quick-card" href="messages.php"><?= mcg_icon('messages') ?><span>Open the contact inbox</span><?= mcg_icon('arrow-right', 'icon arrow') ?></a>
    <a class="quick-card" href="settings.php"><?= mcg_icon('settings') ?><span>Social &amp; email settings</span><?= mcg_icon('arrow-right', 'icon arrow') ?></a>
</div>

<div class="grid-4" style="margin-top: 18px;">
    <article class="stat-card">
        <span class="stat-label"><?= mcg_icon('pages') ?> Pages</span>
        <p class="stat-value"><?= count($pages) ?></p>
        <span class="stat-meta"><?= (int) $published ?> published · <?= count($pages) - $published ?> draft</span>
    </article>
    <article class="stat-card">
        <span class="stat-label"><?= mcg_icon('gallery') ?> Gallery</span>
        <p class="stat-value"><?= count($photos) ?></p>
        <span class="stat-meta"><?= count($categories) ?> categories · order editable</span>
    </article>
    <article class="stat-card">
        <span class="stat-label"><?= mcg_icon('messages') ?> Inbox</span>
        <p class="stat-value"><?= $unread ?></p>
        <span class="stat-meta"><?= count($messages) ?> total messages stored</span>
    </article>
    <article class="stat-card">
        <span class="stat-label"><?= mcg_icon('subscribers') ?> Subscribers</span>
        <p class="stat-value"><?= $activeSubscribers ?></p>
        <span class="stat-meta"><?= count($reviews) ?> reviews · <?= $pendingReviews ?> awaiting approval</span>
    </article>
</div>

<div class="split-2" style="margin-top: 18px;">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Latest enquiries</h3>
                <p>The five most recent contact form submissions.</p>
            </div>
            <a class="btn btn-secondary btn-sm" href="messages.php">Open inbox</a>
        </div>
        <div class="panel-body flush">
            <?php if (!$latestMessages): ?>
                <div class="table-empty">
                    <?= mcg_icon('inbox') ?>
                    <p>No messages yet. Enquiries sent through the contact form will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Message</th>
                                <th>Received</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestMessages as $message): ?>
                                <tr>
                                    <td>
                                        <div class="cell-title">
                                            <strong><?= mcg_e($message['name'] ?? 'Unknown') ?></strong>
                                            <span><?= mcg_e($message['email'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td><span class="message-snippet"><?= mcg_e($message['message'] ?? '') ?></span></td>
                                    <td class="small muted"><?= mcg_e(mcg_format_date($message['created'] ?? '')) ?></td>
                                    <td><?= mcg_status_pill((string) ($message['status'] ?? 'new')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>Client reviews</h3>
                    <p>Approve or edit what patients say.</p>
                </div>
                <a class="btn btn-secondary btn-sm" href="reviews.php">Manage</a>
            </div>
            <div class="panel-body">
                <?php if (!$latestReviews): ?>
                    <p class="muted small">No reviews yet.</p>
                <?php else: ?>
                    <div class="stack">
                        <?php foreach ($latestReviews as $review): ?>
                            <div>
                                <div class="button-row" style="justify-content: space-between;">
                                    <strong class="small"><?= mcg_e($review['name'] ?? 'Client') ?></strong>
                                    <?= mcg_status_pill(!empty($review['approved']) ? 'published' : 'draft') ?>
                                </div>
                                <p class="small muted" style="margin: 4px 0 0;"><?= mcg_e(mcg_substr((string) ($review['quote'] ?? ''), 110)) ?>…</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>System</h3>
                    <p>Quick health checks for this installation.</p>
                </div>
            </div>
            <div class="panel-body">
                <?php
                $checks = [
                    ['PHP 8.1+', version_compare(PHP_VERSION, '8.1.0', '>='), 'Running PHP ' . PHP_VERSION],
                    ['GD image processing', function_exists('imagecreatefromstring'), 'Required to sanitise uploads'],
                    ['Mail transport', function_exists('mail'), 'PHP mail() is available'],
                    ['Data folder writable', is_writable(MCG_DATA_DIR), MCG_DATA_DIR],
                    ['Uploads folder writable', is_writable(MCG_UPLOAD_DIR), MCG_UPLOAD_DIR],
                    ['Portal protected', is_file(MCG_DATA_DIR . '/.htaccess'), 'Apache data deny rules present'],
                ];
                ?>
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
    </div>
</div>

<section class="panel" style="margin-top: 18px;">
    <div class="panel-head">
        <div>
            <h3>Recent campaigns</h3>
            <p>Bulk email delivery history.</p>
        </div>
        <a class="btn btn-secondary btn-sm" href="mail.php">New campaign</a>
    </div>
    <div class="panel-body flush">
        <?php if (!$latestCampaigns): ?>
            <div class="table-empty">
                <?= mcg_icon('mail') ?>
                <p>No campaigns sent yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Recipients</th>
                            <th>Delivered</th>
                            <th>Failed</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestCampaigns as $campaign): ?>
                            <tr>
                                <td><strong><?= mcg_e($campaign['subject'] ?? '') ?></strong></td>
                                <td><?= (int) ($campaign['recipients'] ?? 0) ?></td>
                                <td><?= (int) ($campaign['sent'] ?? 0) ?></td>
                                <td><?= (int) ($campaign['failed'] ?? 0) ?></td>
                                <td class="small muted"><?= mcg_e(mcg_format_date($campaign['created'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php mcg_admin_footer(); ?>
