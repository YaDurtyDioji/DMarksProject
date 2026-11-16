<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/AboutPage.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'save') { AboutPage::update($_POST['content_html'], $_POST['video_url'] ?? ''); flash('Saved'); }
        if ($act === 'media_add') { AboutPage::addMedia('file'); flash('Media added'); }
        if ($act === 'media_del') { AboutPage::deleteMedia((int)$_POST['id']); flash('Media deleted'); }
        if ($act === 'media_reorder') { AboutPage::reorderMedia($_POST['order'] ?? []); jexit(['ok'=>1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if ($act !== 'media_reorder') { header('Location: about_edit.php'); exit; }
}
$p = AboutPage::get();
render_header('About Page');
?>
<h1>Edit About Page</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="save">
<label>Content (HTML)</label>
<textarea name="content_html" rows="12"><?= h($p['content_html']) ?></textarea>
<label>Main Video URL</label>
<input name="video_url" value="<?= h($p['video_url']) ?>">
<button class="btn-primary">Save</button>
</form>

<h2>Images & Videos (drag to reorder)</h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="media_add">
<input type="file" name="file" accept="image/*,video/mp4,video/webm" required>
<button>Upload</button>
</form>
<div id="msort" class="img-grid">
<?php foreach (AboutPage::get()['media'] as $m): ?>
<div class="img-card" data-id="<?= (int)$m['id'] ?>">
  <?php if ($m['media_type'] === 'video'): ?><video src="<?= h(upload_url($m['file_path'])) ?>" controls></video>
  <?php else: ?><img src="<?= h(upload_url($m['file_path'])) ?>"><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="media_del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('msort'), {animation:150, onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#msort .img-card')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','media_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('about_edit.php', {method:'POST', body:fd});
}});
</script>
<?php render_footer(); ?>