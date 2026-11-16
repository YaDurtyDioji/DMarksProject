<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Recipients.php';
$cfg = require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'new') { Recipients::create($_POST['name'], $_POST['email'], $_POST['phone']); flash('Added'); }
        if ($act === 'upd') { Recipients::update((int)$_POST['id'], $_POST['name'], $_POST['email'], $_POST['phone']); flash('Updated'); }
        if ($act === 'del') { Recipients::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'api') { $n = Recipients::importFromApi($cfg['recipients_api_url']); flash("Imported $n"); }
        if ($act === 'csv') { $n = Recipients::importCsv('file'); flash("Imported $n"); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: recipients_list.php'); exit;
}
render_header('Recipients');
?>
<h1>Recipients</h1>
<details class="panel"><summary>+ Add manually</summary>
<form method="post" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="new">
<input name="name" placeholder="Name"><input name="email" type="email" placeholder="Email" required><input name="phone" placeholder="Phone">
<button>Add</button></form></details>
<details class="panel"><summary>Import</summary>
<form method="post" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="api">
<button>Import from API (<?= h($cfg['recipients_api_url'] ?: 'not configured') ?>)</button></form>
<form method="post" enctype="multipart/form-data" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="csv">
<input type="file" name="file" accept=".csv" required><button>Import CSV (name,email,phone)</button></form>
</details>
<table class="data"><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th></th></tr>
<?php foreach (Recipients::all() as $r): ?>
<tr>
<td><?= (int)$r['id'] ?></td>
<td colspan="3">
<form method="post" class="grid" style="grid-template-columns:1fr 1fr 1fr auto">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="upd"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input name="name" value="<?= h($r['name']) ?>"><input name="email" value="<?= h($r['email']) ?>"><input name="phone" value="<?= h($r['phone']) ?>">
<button class="btn-small">Save</button></form>
</td>
<td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn-danger">Del</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>