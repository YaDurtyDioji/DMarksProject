<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Gallery.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'cat_new') { Gallery::createCategory($_POST['name']); flash('Category added'); }
        if ($act === 'cat_rename') { Gallery::renameCategory((int)$_POST['id'], $_POST['name']); flash('Renamed'); }
        if ($act === 'cat_del') { Gallery::deleteCategory((int)$_POST['id']); flash('Category deleted'); }
        if ($act === 'cat_reorder') { Gallery::reorderCategories($_POST['order'] ?? []); jexit(['ok' => 1]); }
        if ($act === 'item_new') { Gallery::addItem((int)$_POST['cat_id'], $_POST['caption'] ?? '', 'file'); flash('Item uploaded'); }
        if ($act === 'item_caption') { Gallery::updateItem((int)$_POST['id'], $_POST['caption'] ?? ''); flash('Caption updated'); }
        if ($act === 'item_del') { Gallery::deleteItem((int)$_POST['id']); flash('Item deleted'); }
        if ($act === 'item_reorder') { Gallery::reorderItems($_POST['order'] ?? []); jexit(['ok' => 1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if (!in_array($act, ['cat_reorder','item_reorder'])) { header('Location: gallery_list.php'); exit; }
}
render_header('Gallery');
$cats = Gallery::categories();
?>
<h1>Gallery</h1>

<details class="panel"><summary>+ Add Category</summary>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_new">
<input name="name" placeholder="Category name" required> <button>Add</button></form>
</details>

<h2>Categories (drag to reorder)</h2>
<div id="catsort">
<?php foreach ($cats as $c): ?>
<div class="cat-row" data-id="<?= (int)$c['id'] ?>">
  <span class="handle">☰</span>
  <form method="post" style="display:flex;gap:6px;align-items:center;flex:1">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_rename"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <input name="name" value="<?= h($c['name']) ?>" style="flex:1"><button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete category and all items?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn-danger">Del</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('catsort'), {animation:150, handle:'.handle', onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#catsort .cat-row')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','cat_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('gallery_list.php', {method:'POST', body:fd});
}});
</script>

<?php foreach ($cats as $c): ?>
<h2>📁 <?= h($c['name']) ?></h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_new"><input type="hidden" name="cat_id" value="<?= (int)$c['id'] ?>">
<input type="file" name="file" accept="image/*,video/mp4,video/webm" required>
<input name="caption" placeholder="Caption">
<button>Upload</button>
</form>
<div class="img-grid" id="sort-<?= (int)$c['id'] ?>">
<?php foreach (Gallery::items($c['id']) as $it): ?>
<div class="img-card" data-id="<?= (int)$it['id'] ?>">
  <?php if ($it['media_type'] === 'video'): ?>
    <video src="<?= h(upload_url($it['file_path'])) ?>" controls></video>
  <?php else: ?>
    <img src="<?= h(upload_url($it['file_path'])) ?>" alt="">
  <?php endif; ?>
  <form method="post" style="display:flex;gap:4px;margin-top:4px">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_caption"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
    <input name="caption" value="<?= h($it['caption']) ?>" placeholder="Caption" style="width:100%"><button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_del"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('sort-<?= (int)$c['id'] ?>'), {animation:150, onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#sort-<?= (int)$c['id'] ?> .img-card')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','item_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('gallery_list.php', {method:'POST', body:fd});
}});
</script>
<?php endforeach; ?>
<?php render_footer(); ?>