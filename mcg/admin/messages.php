<?php
/**
 * Contact inbox: read, filter, mark and delete messages saved by sendEmail.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
mcg_require_auth();

$messages = mcg_read_data('messages', []);
if (!is_array($messages)) {
    $messages = [];
}
$filter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
if (!in_array($filter, ['all', 'unread', 'read', 'spam'], true)) {
    $filter = 'all';
}
$query = isset($_GET['q']) ? mcg_clean_text($_GET['q'], 80) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $returnFilter = isset($_POST['return_filter']) ? (string) $_POST['return_filter'] : 'all';
    if (!in_array($returnFilter, ['all', 'unread', 'read', 'spam'], true)) {
        $returnFilter = 'all';
    }

    if ($action === 'delete') {
        $remaining = [];
        foreach ($messages as $message) {
            if ((int) $message['id'] !== $id) {
                $remaining[] = $message;
            }
        }
        mcg_write_data('messages', $remaining);
        mcg_flash('success', 'Message deleted.');
        mcg_redirect('messages.php?status=' . $returnFilter);
    } elseif ($action === 'mark_read' || $action === 'mark_unread' || $action === 'mark_spam' || $action === 'mark_ham') {
        $changes = [];
        if ($action === 'mark_read') {
            $changes = ['read' => true, 'status' => 'read'];
        } elseif ($action === 'mark_unread') {
            $changes = ['read' => false, 'status' => 'new'];
        } elseif ($action === 'mark_spam') {
            $changes = ['read' => true, 'status' => 'spam'];
        } else {
            $changes = ['read' => true, 'status' => 'read'];
        }
        mcg_update_message($id, $changes);
        mcg_flash('success', 'Message updated.');
        mcg_redirect('messages.php?status=' . $returnFilter . '&id=' . $id);
    }
}

$active = null;
$activeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($activeId > 0) {
    foreach ($messages as $message) {
        if ((int) $message['id'] === $activeId) {
            $active = $message;
            break;
        }
    }
    if ($active && ($active['status'] ?? 'new') === 'new') {
        $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
        if ($token !== '' && hash_equals(mcg_csrf_token(), $token)) {
            mcg_update_message($activeId, ['read' => true, 'status' => 'read']);
            $active['read'] = true;
            $active['status'] = 'read';
            foreach ($messages as $index => $message) {
                if ((int) $message['id'] === $activeId) {
                    $messages[$index]['read'] = true;
                    $messages[$index]['status'] = 'read';
                    break;
                }
            }
        }
    }
}

$counts = ['all' => count($messages), 'unread' => 0, 'read' => 0, 'spam' => 0];
foreach ($messages as $message) {
    $status = (string) ($message['status'] ?? 'new');
    if ($status === 'spam') {
        $counts['spam']++;
    } elseif ($status === 'read') {
        $counts['read']++;
    } else {
        $counts['unread']++;
    }
}

$filtered = [];
foreach ($messages as $message) {
    $status = (string) ($message['status'] ?? 'new');
    if ($filter === 'unread' && $status !== 'new') {
        continue;
    }
    if ($filter === 'read' && $status !== 'read') {
        continue;
    }
    if ($filter === 'spam' && $status !== 'spam') {
        continue;
    }
    if ($query !== '') {
        $haystack = strtolower(($message['name'] ?? '') . ' ' . ($message['email'] ?? '') . ' ' . ($message['message'] ?? ''));
        if (strpos($haystack, strtolower($query)) === false) {
            continue;
        }
    }
    $filtered[] = $message;
}
$filtered = array_reverse($filtered);
$token = mcg_csrf_token();
$filterLabel = ['all' => 'All messages', 'unread' => 'Unread', 'read' => 'Read', 'spam' => 'Spam'][$filter];

mcg_admin_header('Inbox', 'messages', 'Contact form submissions, spam scoring and rate-limit protection.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Enquiries</p>
        <h2>Contact inbox</h2>
        <p>Messages are stored as plain text, stripped of tags and control characters. Rate limits and spam scoring are applied at the point of submission.</p>
    </div>
    <div class="page-actions">
        <span class="pill pill-new"><?= $counts['unread'] ?> unread</span>
        <span class="pill pill-spam"><?= $counts['spam'] ?> spam</span>
    </div>
</div>

<div class="toolbar">
    <div class="filter-tabs">
        <a class="<?= $filter === 'all' ? 'is-active' : '' ?>" href="messages.php?status=all">All <span class="count"><?= $counts['all'] ?></span></a>
        <a class="<?= $filter === 'unread' ? 'is-active' : '' ?>" href="messages.php?status=unread">Unread <span class="count"><?= $counts['unread'] ?></span></a>
        <a class="<?= $filter === 'read' ? 'is-active' : '' ?>" href="messages.php?status=read">Read <span class="count"><?= $counts['read'] ?></span></a>
        <a class="<?= $filter === 'spam' ? 'is-active' : '' ?>" href="messages.php?status=spam">Spam <span class="count"><?= $counts['spam'] ?></span></a>
    </div>
    <form class="search-form" method="get" action="messages.php">
        <input type="hidden" name="status" value="<?= mcg_e($filter) ?>">
        <input type="search" name="q" value="<?= mcg_e($query) ?>" placeholder="Search name, email or text" aria-label="Search messages">
        <button class="btn btn-secondary btn-sm" type="submit"><?= mcg_icon('search') ?> Search</button>
        <?php if ($query !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="messages.php?status=<?= mcg_e($filter) ?>">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="message-layout">
    <div>
        <div class="message-list">
            <?php if (!$filtered): ?>
                <div class="table-empty">
                    <?= mcg_icon('inbox') ?>
                    <p><?= $query !== '' ? 'No messages match that search.' : 'No messages in ' . strtolower($filterLabel) . '.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($filtered as $message): ?>
                    <?php
                    $messageId = (int) $message['id'];
                    $isActive = $active && (int) $active['id'] === $messageId;
                    $isUnread = ($message['status'] ?? 'new') === 'new';
                    ?>
                    <a class="message-item <?= $isActive ? 'is-active' : '' ?> <?= $isUnread ? 'is-unread' : '' ?>"
                        href="messages.php?status=<?= mcg_e($filter) ?>&amp;id=<?= $messageId ?>&amp;token=<?= mcg_e($token) ?>">
                        <span class="message-item-top">
                            <span><?= $isUnread ? '● Unread' : '○ ' . ucfirst((string) ($message['status'] ?? 'read')) ?></span>
                            <span><?= mcg_e(mcg_format_date($message['created'] ?? '')) ?></span>
                        </span>
                        <p class="message-name"><?= mcg_e($message['name'] ?? 'Unknown') ?> · <?= mcg_e($message['email'] ?? '') ?></p>
                        <p class="message-excerpt"><?= mcg_e($message['message'] ?? '') ?></p>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <section class="panel">
        <?php if (!$active): ?>
            <div class="table-empty" style="padding: 80px 24px;">
                <?= mcg_icon('messages') ?>
                <p>Select a message to read it.</p>
                <p class="small muted">Messages are rate limited at submission and stored as sanitised plain text.</p>
            </div>
        <?php else: ?>
            <div class="panel-body">
                <div class="message-detail-head">
                    <div>
                        <div class="button-row" style="margin-bottom: 6px;">
                            <?= mcg_status_pill((string) ($active['status'] ?? 'new')) ?>
                            <?php if ((int) ($active['spam_score'] ?? 0) > 0): ?>
                                <span class="pill pill-category">Risk score <?= (int) $active['spam_score'] ?>/12</span>
                            <?php endif; ?>
                        </div>
                        <h2><?= mcg_e($active['name'] ?? 'Unknown') ?></h2>
                        <div class="message-meta">
                            <span><?= mcg_e($active['email'] ?? '') ?></span>
                            <span><?= mcg_e(mcg_format_date($active['created'] ?? '')) ?></span>
                            <?php if (!empty($active['ip_hash'])): ?>
                                <span class="mono">IP <?= mcg_e($active['ip_hash']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="button-row">
                        <a class="btn btn-primary btn-sm" href="mailto:<?= mcg_e($active['email'] ?? '') ?>?subject=<?= rawurlencode('Re: your enquiry to MCG Biokinetics') ?>"><?= mcg_icon('mail') ?> Reply</a>
                        <form method="post" action="messages.php">
                            <?= mcg_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $active['id'] ?>">
                            <input type="hidden" name="return_filter" value="<?= mcg_e($filter) ?>">
                            <input type="hidden" name="action" value="<?= ($active['status'] ?? '') === 'spam' ? 'mark_ham' : 'mark_spam' ?>">
                            <button class="btn btn-secondary btn-sm" type="submit"><?= mcg_icon('warning') ?> <?= ($active['status'] ?? '') === 'spam' ? 'Not spam' : 'Mark spam' ?></button>
                        </form>
                        <form method="post" action="messages.php" data-confirm="Delete this message permanently?">
                            <?= mcg_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $active['id'] ?>">
                            <input type="hidden" name="return_filter" value="<?= mcg_e($filter) ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn btn-danger btn-sm" type="submit"><?= mcg_icon('trash') ?> Delete</button>
                        </form>
                    </div>
                </div>

                <?php if (($active['status'] ?? '') === 'spam'): ?>
                    <div class="notice notice-danger" style="margin-bottom: 14px;">
                        <p>This message was flagged by the spam filter and was not emailed to the practice. Review it carefully before marking it as legitimate.</p>
                    </div>
                <?php endif; ?>

                <div class="message-body"><?= nl2br(mcg_e($active['message'] ?? ''), false) ?></div>

                <div class="message-side">
                    <div><strong>Spam reasons:</strong>
                        <?= !empty($active['spam_reasons']) && is_array($active['spam_reasons']) ? mcg_e(implode(', ', $active['spam_reasons'])) : 'None recorded' ?>
                    </div>
                    <div><strong>Email delivered:</strong> <?= !empty($active['email_sent']) ? 'Yes' : 'No / not recorded' ?></div>
                    <div><strong>User agent:</strong> <?= mcg_e($active['user_agent'] ?? '—') ?></div>
                    <div><strong>Rate limit:</strong> 3 per 10 minutes, 12 per day per IP (configured in the intake library).</div>
                </div>

                <div class="button-row" style="margin-top: 16px;">
                    <form method="post" action="messages.php">
                        <?= mcg_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $active['id'] ?>">
                        <input type="hidden" name="return_filter" value="<?= mcg_e($filter) ?>">
                        <input type="hidden" name="action" value="mark_unread">
                        <button class="btn btn-secondary btn-sm" type="submit"><?= mcg_icon('messages') ?> Mark unread</button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="messages.php?status=<?= mcg_e($filter) ?>">Close</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php mcg_admin_footer(); ?>
