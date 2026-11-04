<?php
/**
 * Admin layout and UI helpers.
 */

declare(strict_types=1);

function mcg_icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'pages' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M8 13h8M8 17h5"/>',
        'gallery' => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
        'reviews' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
        'messages' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'mail' => '<path d="m21 3-7.4 18-3.2-7.4L3 10.4z"/><path d="M10.4 13.6 15 9"/>',
        'subscribers' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3 3 0 0 1 0 6M17 14c2.4.4 4 2.4 4 5"/>',
        'settings' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2.2"/><circle cx="8" cy="17" r="2.2"/>',
        'logout' => '<path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4l11-11-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'up' => '<path d="m6 14 6-6 6 6"/>',
        'down' => '<path d="m6 10 6 6 6-6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5H5V5h5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'warning' => '<path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17h.01"/>',
        'inbox' => '<path d="M4 4h16l2 10v6H2v-6z"/><path d="M2 14h5l1.5 3h7L17 14h5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
    ];
    $path = isset($paths[$name]) ? $paths[$name] : $paths['dashboard'];
    return '<svg class="' . mcg_e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function mcg_admin_header(string $title, string $active = '', string $subtitle = ''): void
{
    $user = mcg_current_user();
    $username = $user && isset($user['username']) ? (string) $user['username'] : 'Admin';
    $unread = mcg_unread_count();
    $pendingReviews = mcg_review_pending_count();
    $flashes = mcg_take_flashes();
    $nav = [
        ['dashboard', 'index.php', 'Dashboard', 0],
        ['pages', 'pages.php', 'Pages', 0],
        ['gallery', 'gallery.php', 'Gallery', 0],
        ['reviews', 'reviews.php', 'Reviews', $pendingReviews],
        ['messages', 'messages.php', 'Inbox', $unread],
        ['mail', 'mail.php', 'Bulk email', 0],
        ['subscribers', 'subscribers.php', 'Subscribers', 0],
        ['settings', 'settings.php', 'Settings', 0],
    ];
    ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= mcg_e($title) ?> · MCG Admin</title>
    <link rel="icon" href="../images/MCG%20BIOKINETICS%20LOGOtp.png">
    <link rel="stylesheet" href="assets/admin.css">
</head>

<body class="admin-body">
    <a class="skip-link" href="#admin-main">Skip to content</a>

    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <a class="admin-brand" href="index.php">
                <span class="admin-brand-mark">
                    <img src="../images/MCG%20BIOKINETICS%20LOGOtp.png" alt="">
                </span>
                <span class="admin-brand-text">
                    <strong>MCG Biokinetics</strong>
                    <span>Admin portal</span>
                </span>
            </a>

            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($nav as $item): ?>
                    <a class="admin-nav-link <?= $active === $item[0] ? 'is-active' : '' ?>" href="<?= mcg_e($item[1]) ?>">
                        <?= mcg_icon($item[0]) ?>
                        <span><?= mcg_e($item[2]) ?></span>
                        <?php if ($item[3] > 0): ?>
                            <span class="nav-count"><?= (int) $item[3] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-sidebar-foot">
                <a class="admin-side-link" href="../index.html" target="_blank" rel="noopener">
                    <?= mcg_icon('external') ?> View website
                </a>
                <form method="post" action="logout.php" class="logout-form">
                    <?= mcg_csrf_field() ?>
                    <button class="admin-side-link" type="submit"><?= mcg_icon('logout') ?> Sign out</button>
                </form>
                <p class="admin-version">Portal v<?= mcg_e(MCG_ADMIN_VERSION) ?></p>
            </div>
        </aside>

        <div class="admin-overlay" data-sidebar-close></div>

        <div class="admin-main" id="admin-main">
            <header class="admin-topbar">
                <button class="icon-button mobile-only" type="button" data-sidebar-toggle aria-label="Open menu">
                    <?= mcg_icon('menu') ?>
                </button>
                <div class="topbar-title">
                    <h1><?= mcg_e($title) ?></h1>
                    <?php if ($subtitle !== ''): ?>
                        <p><?= mcg_e($subtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div class="topbar-user">
                    <span class="topbar-avatar"><?= mcg_e(strtoupper(substr($username, 0, 1))) ?></span>
                    <span class="topbar-user-text">
                        <strong><?= mcg_e($username) ?></strong>
                        <small>Signed in</small>
                    </span>
                </div>
            </header>

            <main class="admin-content">
                <?php if ($flashes): ?>
                    <div class="flash-stack">
                        <?php foreach ($flashes as $flash): ?>
                            <div class="flash flash-<?= mcg_e($flash['type']) ?>" role="status">
                                <span><?= mcg_icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'warning' : 'messages')) ?></span>
                                <p><?= mcg_e($flash['message']) ?></p>
                                <button type="button" class="flash-close" aria-label="Dismiss"><?= mcg_icon('x') ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
<?php
}

function mcg_admin_footer(): void
{
    ?>
            </main>
            <footer class="admin-footer">
                <p>MCG Biokinetics admin · <?= date('Y') ?> · PHP <?= mcg_e(PHP_VERSION) ?></p>
                <p>Content changes are written to the site instantly where applicable.</p>
            </footer>
        </div>
    </div>
    <script src="assets/admin.js" defer></script>
</body>

</html>
<?php
}

function mcg_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star ' . ($i <= $rating ? 'is-filled' : '') . '" aria-hidden="true">★</span>';
    }
    return '<span class="stars" aria-label="' . $rating . ' out of 5">' . $out . '</span>';
}

function mcg_status_pill(string $status): string
{
    $labels = [
        'published' => 'Published',
        'draft' => 'Draft',
        'new' => 'Unread',
        'read' => 'Read',
        'spam' => 'Spam',
    ];
    $label = isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    return '<span class="pill pill-' . mcg_e($status) . '">' . mcg_e($label) . '</span>';
}

function mcg_field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error">' . mcg_e($errors[$key]) . '</p>' : '';
}
