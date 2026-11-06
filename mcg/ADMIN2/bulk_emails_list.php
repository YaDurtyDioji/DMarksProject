<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/BulkEmail.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'del') { BulkEmail::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'send') { $n = BulkEmail::send((int)$_POST['id']); flash("Sent to $n recipients"); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: bulk_emails_list.php'); exit;
}
render_header('Bulk Email');
?>
<h1>Bulk Emails</h1>
<p><a href="bulk_email_edit.php" class="btn btn-primary">+ New Campaign</a></p>
<table class="data"><tr><th>ID</th><th>Subject</th><th>Created</th><th>Sent</th><th></th></tr>
<?php foreach (BulkEmail::all() as $e): ?>
<tr>
<td><?= (int)$e['id'] ?></td>
<td><?= h($e['subject']) ?></td>
<td><?= h($e['created_at']) ?></td>
<td><?= $e['sent_at'] ? h($e['sent_at']) : '—' ?></td>
<td>
<a class="btn" href="bulk_email_edit.php?id=<?= (int)$e['id'] ?>">Edit</a>
<form method="post" style="display:inline" onsubmit="return confirm('Send now to ALL recipients?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="send"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn-primary">Send</button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn-danger">Del</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>