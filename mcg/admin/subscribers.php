<?php
/**
 * Mailing list / subscribers.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
mcg_require_auth();

$subscribers = mcg_read_data('subscribers', []);
if (!is_array($subscribers)) {
    $subscribers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_require_post();
    mcg_check_csrf();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'add') {
        $result = mcg_add_subscriber($_POST['email'] ?? '', $_POST['name'] ?? '', 'manual');
        mcg_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Subscriber added.' : $result['error']);
        mcg_redirect('subscribers.php');
    }

    if ($action === 'import') {
        $raw = mcg_clean_multiline($_POST['emails'] ?? '', 20000);
        $lines = preg_split('/\r?\n/', $raw);
        $added = 0;
        $skipped = 0;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $name = '';
            $email = $line;
            if (preg_match('/^(.*?)<([^>]+)>$/', $line, $matches)) {
                $name = trim($matches[1]);
                $email = trim($matches[2]);
            }
            $result = mcg_add_subscriber($email, $name, 'import');
            if ($result['ok']) {
                $added++;
            } else {
                $skipped++;
            }
        }
        mcg_flash($added > 0 ? 'success' : 'error', $added . ' subscriber(s) added' . ($skipped > 0 ? ', ' . $skipped . ' skipped (invalid or duplicate).' : '.'));
        mcg_redirect('subscribers.php');
    }

    if ($action === 'toggle') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        foreach ($subscribers as $index => $subscriber) {
            if ((int) $subscriber['id'] === $id) {
                $subscribers[$index]['active'] = empty($subscriber['active']);
                break;
            }
        }
        mcg_write_data('subscribers', $subscribers);
        mcg_flash('success', 'Subscriber status updated.');
        mcg_redirect('subscribers.php');
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $remaining = [];
        foreach ($subscribers as $subscriber) {
            if ((int) $subscriber['id'] !== $id) {
                $remaining[] = $subscriber;
            }
        }
        mcg_write_data('subscribers', $remaining);
        mcg_flash('success', 'Subscriber removed.');
        mcg_redirect('subscribers.php');
    }
}

usort($subscribers, function ($a, $b) {
    return strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? ''));
});
$active = 0;
foreach ($subscribers as $subscriber) {
    if (!empty($subscriber['active'])) {
        $active++;
    }
}

mcg_admin_header('Subscribers', 'subscribers', 'Build the mailing list used by the bulk email composer.');
?>

<div class="page-head">
    <div>
        <p class="eyebrow">Audience</p>
        <h2>Subscribers</h2>
        <p><?= count($subscribers) ?> people · <?= $active ?> active. These addresses can be selected when composing a campaign.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="mail.php"><?= mcg_icon('mail') ?> Compose email</a>
    </div>
</div>

<div class="split-2">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Mailing list</h3>
                <p>Inactive subscribers are kept but excluded from campaigns.</p>
            </div>
        </div>
        <div class="panel-body flush">
            <?php if (!$subscribers): ?>
                <div class="table-empty">
                    <?= mcg_icon('subscribers') ?>
                    <p>No subscribers yet. Add one manually or paste a list on the right.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th>Source</th>
                                <th>Added</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <div class="cell-title">
                                            <strong><?= mcg_e($subscriber['name'] ?: '—') ?></strong>
                                            <span><?= mcg_e($subscriber['email']) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="pill pill-category"><?= mcg_e($subscriber['source'] ?? 'manual') ?></span></td>
                                    <td class="small muted"><?= mcg_e(mcg_format_date($subscriber['created'] ?? '')) ?></td>
                                    <td><?= mcg_status_pill(!empty($subscriber['active']) ? 'published' : 'draft') ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <form method="post" action="subscribers.php">
                                                <?= mcg_csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int) $subscriber['id'] ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit"><?= mcg_icon(!empty($subscriber['active']) ? 'x' : 'check') ?> <?= !empty($subscriber['active']) ? 'Pause' : 'Activate' ?></button>
                                            </form>
                                            <form method="post" action="subscribers.php" data-confirm="Remove this subscriber?">
                                                <?= mcg_csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $subscriber['id'] ?>">
                                                <button class="btn btn-danger btn-sm" type="submit"><?= mcg_icon('trash') ?></button>
                                            </form>
                                        </div>
                                    </td>
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
                <h3>Add a subscriber</h3>
            </div>
            <div class="panel-body">
                <form method="post" action="subscribers.php">
                    <?= mcg_csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="field">
                        <label for="subName">Name (optional)</label>
                        <input type="text" id="subName" name="name" maxlength="120" placeholder="First name">
                    </div>
                    <div class="field">
                        <label for="subEmail">Email address</label>
                        <input type="email" id="subEmail" name="email" required placeholder="name@example.com">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit"><?= mcg_icon('plus') ?> Add subscriber</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h3>Import a list</h3>
            </div>
            <div class="panel-body">
                <form method="post" action="subscribers.php">
                    <?= mcg_csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <div class="field">
                        <label for="emails">One per line</label>
                        <textarea id="emails" name="emails" placeholder="name@example.com&#10;Jane Doe &lt;jane@example.com&gt;" style="min-height: 150px;"></textarea>
                        <span class="field-help">Both plain email addresses and <span class="mono">Name &lt;email&gt;</span> are supported. Duplicates are skipped.</span>
                    </div>
                    <button class="btn btn-secondary btn-block" type="submit"><?= mcg_icon('upload') ?> Import list</button>
                </form>
            </div>
        </section>

        <section class="notice notice-warning">
            <p><strong>Consent.</strong> Only email people who have agreed to hear from the practice, and include a clear way to opt out. Bulk sending through PHP <span class="mono">mail()</span> should be paired with SPF/DKIM on the domain.</p>
        </section>
    </div>
</div>

<?php mcg_admin_footer(); ?>
