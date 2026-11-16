<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Logger.php';
render_header('History');
$tab = $_GET['tab'] ?? 'logins';
?>
<h1>History</h1>
<p><a href="?tab=logins" class="btn <?= $tab==='logins'?'btn-primary':'' ?>">Login Logs</a>
<a href="?tab=edits" class="btn <?= $tab==='edits'?'btn-primary':'' ?>">Edit Logs</a></p>
<?php if ($tab === 'logins'): ?>
<table class="data"><tr><th>ID</th><th>User</th><th>IP</th><th>Success</th><th>Agent</th><th>When</th></tr>
<?php foreach (Logger::loginLogs() as $l): ?>
<tr><td><?= (int)$l['id'] ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($l['ip']) ?></td><td><?= $l['success']?'✅':'❌' ?></td><td><small><?= h($l['user_agent']) ?></small></td><td><?= h($l['created_at']) ?></td></tr>
<?php endforeach; ?></table>
<?php else: ?>
<table class="data"><tr><th>ID</th><th>User</th><th>Table</th><th>Record</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
<?php foreach (Logger::editLogs() as $l): ?>
<tr><td><?= (int)$l['id'] ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($l['table_name']) ?></td><td><?= (int)$l['record_id'] ?></td><td><?= h($l['action']) ?></td><td><?= h($l['details']) ?></td><td><?= h($l['ip']) ?></td><td><?= h($l['created_at']) ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<?php render_footer(); ?>