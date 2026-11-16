<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Messages.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'del') { Messages::delete((int)$_POST['id']); flash('Deleted'); }
    if ($act === 'spam') { Messages::markSpam((int)$_POST['id'], 1); flash('Marked spam'); }
    if ($act === 'ham') { Messages::markSpam((int)$_POST['id'], 0); flash('Marked ham'); }
    header('Location: messages_list.php'); exit;
}
$showSpam = isset($_GET['spam']);
render_header('Messages');
?>
<h1>Contact Messages</h1>
<p><a href="messages_list.php" class="btn <?= $showSpam?'':'btn-primary' ?>">Inbox</a>
<a href="messages_list.php?spam=1" class="btn <?= $showSpam?'btn-primary':'' ?>">Spam</a></p>
<table class="data">
<tr><th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>IP</th><th>When</th><th></th></tr>
<?php foreach (Messages::all($showSpam) as $m): ?>
<tr>
<td><?= (int)$m['id'] ?></td>
<td><?= h($m['name']) ?></td>
<td><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></td>
<td><?= h($m['subject']) ?></td>
<td><small><?= nl2br(h($m['message'])) ?></small></td>
<td><?= h($m['ip']) ?></td>
<td><small><?= h($m['created_at']) ?></small></td>
<td>
<?php if ($showSpam): ?>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="ham"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-small">Not spam</button></form>
<?php else: ?>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="spam"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-small">Spam</button></form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-danger">Del</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>