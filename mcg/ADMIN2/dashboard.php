<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';
require_once __DIR__ . '/Gallery.php';
require_once __DIR__ . '/Testimonials.php';
require_once __DIR__ . '/Messages.php';
require_once __DIR__ . '/Recipients.php';

render_header('Dashboard');
?>
<h1>Dashboard</h1>
<p>Welcome, <?= h($_SESSION['username']) ?>.</p>
<div class="cards">
  <a href="services_list.php" class="card"><h3>Services</h3><p><?= count(Services::all()) ?> items</p></a>
  <a href="gallery_list.php" class="card"><h3>Gallery</h3><p><?= count(Gallery::categories()) ?> categories</p></a>
  <a href="testimonials_edit.php" class="card"><h3>Testimonials</h3><p><?= count(Testimonials::all()) ?> items</p></a>
  <a href="messages_list.php" class="card"><h3>Messages</h3><p><?= count(Messages::all()) ?> new</p></a>
  <a href="recipients_list.php" class="card"><h3>Recipients</h3><p><?= count(Recipients::all()) ?> emails</p></a>
  <a href="bulk_emails_list.php" class="card"><h3>Bulk Email</h3><p>Send campaigns</p></a>
</div>
<?php render_footer(); ?>