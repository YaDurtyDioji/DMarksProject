<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/BulkEmail.php';
$id = (int)($_GET['id'] ?? 0);
$e = $id ? BulkEmail::get($id) : ['subject'=>'','body_html'=>'','buttons'=>[]];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $buttons = [];
        foreach (($_POST['btn_text'] ?? []) as $i => $t) {
            if (trim($t) !== '') $buttons[] = ['text'=>trim($t), 'href'=>trim($_POST['btn_href'][$i] ?? '')];
        }
        if ($id) BulkEmail::update($id, $_POST['subject'], $_POST['body_html'], $buttons);
        else $id = BulkEmail::create($_POST['subject'], $_POST['body_html'], $buttons);
        flash('Saved');
    } catch (Exception $ex) { flash('Error: '.$ex->getMessage()); }
    header('Location: bulk_email_edit.php?id='.$id); exit;
}
render_header('Bulk Email');
$btns = array_merge($e['buttons'] ?? [], [['text'=>'','href'=>''],['text'=>'','href'=>'']]);
?>
<h1><?= $id ? 'Edit' : 'New' ?> Bulk Email</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
<label>Subject</label><input name="subject" value="<?= h($e['subject']) ?>" required>
<label>Body (HTML)</label><textarea name="body_html" rows="12"><?= h($e['body_html']) ?></textarea>
<label>Buttons</label>
<?php foreach ($btns as $b): ?>
<div class="row"><input name="btn_text[]" placeholder="Button text" value="<?= h($b['text']) ?>"><input name="btn_href[]" placeholder="https://link" value="<?= h($b['href']) ?>"></div>
<?php endforeach; ?>
<button class="btn-primary">Save Campaign</button>
</form>
<?php render_footer(); ?>