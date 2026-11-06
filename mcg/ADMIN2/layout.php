<?php
require_once __DIR__ . '/helpers.php';
require_login();

function render_header($title) {
    $isSuper = ($_SESSION['role'] ?? '') === 'super_admin';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.h($title).' – Admin2</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
    echo '</head><body>';
    echo '<nav class="topnav"><a href="dashboard.php" class="brand">ADMIN2</a>';
    echo '<a href="services_list.php">Services</a>';
    echo '<a href="gallery_list.php">Gallery</a>';
    echo '<a href="testimonials_edit.php">Testimonials</a>';
    echo '<a href="about_edit.php">About</a>';
    echo '<a href="contact_edit.php">Contact Page</a>';
    echo '<a href="messages_list.php">Messages</a>';
    echo '<a href="recipients_list.php">Recipients</a>';
    echo '<a href="bulk_emails_list.php">Bulk Email</a>';
    echo '<a href="history.php">History</a>';
    if ($isSuper) echo '<a href="users.php">Users</a>';
    echo '<span class="spacer"></span>';
    echo '<span class="user">'.h($_SESSION['username'] ?? '').' ('.h($_SESSION['role'] ?? '').')</span>';
    echo '<a href="logout.php" class="btn-small">Logout</a></nav>';
    echo '<main class="container">';
    if ($m = get_flash()) echo '<div class="flash">'.h($m).'</div>';
}
function render_footer() { echo '</main></body></html>'; }