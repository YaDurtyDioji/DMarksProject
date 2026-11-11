<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';

$id = (int)($_GET['id'] ?? 0);
$svc = $id ? Services::get($id) : ['menu_title'=>'','slug'=>'','content_html'=>'','video_url'=>'','buttons'=>[],'images'=>[],'sort_order'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? 'save';
    try {
        if ($act === 'save') {
            $data = [
                'menu_title' => $_POST['menu_title'],
                'slug' => preg_replace('/[^a-z0-9-]+/i', '-', strtolower($_POST['slug'] ?: $_POST['menu_title'])),
                'content_html' => $_POST['content_html'],
                'video_url' => $_POST['video_url'] ?? '',
                'buttons' => array_values(array_filter(array_map(fn($t,$h) => ['text'=>trim($t),'href'=>trim($h)], $_POST['btn_text'] ?? [], $_POST['btn_href'] ?? []), fn($b) => $b['text'] !== '')),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($id) Services::update($id, $data); else $id = Services::create($data);
            flash('Saved');
            header('Location: service_edit.php?id='.$id); exit;
        } elseif ($act === 'add_img') {
            Services::addImage($id, 'image', $_POST['alt'] ?? '');
            flash('Image added'); header('Location: service_edit.php?id='.$id); exit;
        } elseif ($act === 'del_img') {
            Services::deleteImage((int)$_POST['img_id']);
            flash('Image deleted'); header('Location: service_edit.php?id='.$id); exit;
        }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: service_edit.php?id='.$id); exit;
}

render_header('Edit Service');
$btns = array_merge($svc['buttons'] ?? [], [['text'=>'','href'=>''], ['text'=>'','href'=>'']]);
?>
<h1><?= $id ? 'Edit' : 'New' ?> Service</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="save">
<label>Menu Title</label>
<input name="menu_title" value="<?= h($svc['menu_title']) ?>" required>
<label>Slug (URL)</label>
<input name="slug" value="<?= h($svc['slug']) ?>" placeholder="auto">
<label>Content (HTML allowed)</label>
<textarea name="content_html" rows="10"><?= h($svc['content_html']) ?></textarea>
<label>Video URL (YouTube embed or mp4 URL)</label>
<input name="video_url" value="<?= h($svc['video_url']) ?>">
<label>Buttons</label>
<div id="buttons">
<?php foreach ($btns as $b): ?>
<div class="row"><input name="btn_text[]" placeholder="Button text" value="<?= h($b['text']) ?>"><input name="btn_href[]" placeholder="https://link" value="<?= h($b['href']) ?>"></div>
<?php endforeach; ?>
</div>
<label>Sort Order</label><input name="sort_order" type="number" value="<?= (int)$svc['sort_order'] ?>">
<button class="btn btn-primary">Save</button>
</form>

<?php if ($id): ?>
<h2>Images (drag to reorder)</h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="add_img">
<input type="file" name="image" accept="image/*,video/mp4,video/webm" required>
<input name="alt" placeholder="Alt text">
<button>Upload</button>
</form>
<div id="imgsort" class="img-grid">
<?php foreach (Services::images($id) as $img): ?>
<div class="img-card" data-id="<?= (int)$img['id'] ?>">
  <img src="<?= h(upload_url($img['image_path'])) ?>" alt="">
  <div><?= h($img['alt_text']) ?></div>
  <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del_img"><input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('imgsort'), {
  animation: 150,
  onEnd: function() {
    var ids = Array.from(document.querySelectorAll('#imgsort .img-card')).map(e => +e.dataset.id);
    var fd = new FormData();
    fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','reorder');
    ids.forEach((id,i) => fd.append('order[]', id));
    fetch('service_edit.php?id=<?= $id ?>', {method:'POST', body:fd});
  }
});
</script>
<?php endif; ?>
<?php render_footer(); ?>