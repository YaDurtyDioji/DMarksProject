<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';
render_header('Services');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
    csrf_check(); Services::delete((int)$_POST['id']); flash('Service deleted');
    header('Location: services_list.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reorder') {
    csrf_check(); Services::reorderImages($_POST['order'] ?? []);
    jexit(['ok' => 1]);
}
?>
<h1>Services</h1>
<p><a href="service_edit.php" class="btn btn-primary">+ New Service</a></p>
<table class="data"><tr><th>ID</th><th>Menu Title</th><th>Slug</th><th>Updated</th><th></th></tr>
<?php foreach (Services::all() as $s): ?>
<tr>
<td><?= (int)$s['id'] ?></td>
<td><?= h($s['menu_title']) ?></td>
<td><?= h($s['slug']) ?></td>
<td><?= h($s['updated_at']) ?></td>
<td>
  <a class="btn" href="service_edit.php?id=<?= (int)$s['id'] ?>">Edit</a>
  <form method="post" style="display:inline" onsubmit="return confirm('Delete service?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-danger">Delete</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>