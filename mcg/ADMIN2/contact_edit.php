<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/ContactPage.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    ContactPage::update($_POST['content_html'] ?? '');
    flash('Saved');
    header('Location: contact_edit.php'); exit;
}
$p = ContactPage::get();
render_header('Contact Page');
?>
<h1>Edit Contact Page</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
<label>Content (HTML) – include address, phone, map, etc.</label>
<textarea name="content_html" rows="14"><?= h($p['content_html']) ?></textarea>
<button class="btn-primary">Save</button>
</form>
<?php render_footer(); ?>