<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Testimonials.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'new') { Testimonials::create($_POST['author'], $_POST['content'], 'image'); flash('Added'); }
        if ($act === 'upd') { Testimonials::update((int)$_POST['id'], $_POST['author'], $_POST['content'], 'image'); flash('Updated'); }
        if ($act === 'del') { Testimonials::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'order') { Testimonials::reorder($_POST['order'] ?? []); jexit(['ok'=>1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if ($act !== 'order') { header('Location: testimonials_edit.php'); exit; }
}
render_header('Testimonials');
?>
<h1>Testimonials</h1>
<details class="panel"><summary>+ New Testimonial</summary>
<form method="post" enctype="multipart/form-data" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="new">
<input name="author" placeholder="Author name" required>
<textarea name="content" placeholder="Testimonial text" required></textarea>
<input type="file" name="image" accept="image/*">
<button class="btn-primary">Add</button>
</form></details>

<div id="tsort" class="stack">
<?php foreach (Testimonials::all() as $t): ?>
<div class="card-edit" data-id="<?= (int)$t['id'] ?>">
  <span class="handle">☰</span>
  <form method="post" enctype="multipart/form-data" style="flex:1">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="upd"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <input name="author" value="<?= h($t['author']) ?>" placeholder="Author">
    <textarea name="content" rows="3"><?= h($t['content']) ?></textarea>
    <input type="file" name="image" accept="image/*">
    <?php if ($t['image_path']): ?><img src="<?= h(upload_url($t['image_path'])) ?>" style="max-height:60px"><?php endif; ?>
    <button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn-danger">Del</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('tsort'), {animation:150, handle:'.handle', onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#tsort .card-edit')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','order');
  ids.forEach(id => fd.append('order[]', id));
  fetch('testimonials_edit.php', {method:'POST', body:fd});
}});
</script>
<?php render_footer(); ?>