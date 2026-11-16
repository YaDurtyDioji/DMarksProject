<?php
// admin-view.php — private log viewer. NOT linked from any public page.
// Access: /stuff/admin-view.php?key=CHANGE_ME_TO_A_LONG_RANDOM_STRING

define('ADMIN_KEY', 'CHANGE_ME_TO_A_LONG_RANDOM_STRING_2026');

if (!isset($_GET['key']) || !hash_equals(ADMIN_KEY, $_GET['key'])) {
    http_response_code(404);
    exit('Not found.');
}

$file = __DIR__ . '/TEST/test.txt';
$rows = [];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lines) as $line) {
        $d = json_decode($line, true);
        if (is_array($d)) $rows[] = $d;
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Visitor Logs — Private</title>
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#0f172a;color:#e2e8f0;font-family:system-ui;padding:2rem;}
  table{width:100%;border-collapse:collapse;font-size:.85rem;}
  th{background:#1e293b;color:#38bdf8;text-align:left;padding:.5rem;}
  td{padding:.45rem;border-bottom:1px solid #334155;vertical-align:top;}
  tr:hover{background:#1e293b;}
  .ip{color:#22d3ee;font-weight:600;font-family:monospace;}
  .ua{color:#94a3b8;font-size:.78rem;max-width:400px;overflow-wrap:anywhere;}
  .ts{color:#fbbf24;white-space:nowrap;font-family:monospace;}
  h1{color:#38bdf8;}
  .stat{color:#38bdf8;font-weight:700;font-size:1.3rem;}
</style>
</head>
<body>
<div class="container-fluid">
  <h1>🔒 Visitor Log — <span class="stat"><?= count($rows) ?></span> entries</h1>
  <p class="text-secondary small">Private admin view. Not indexed.</p>

  <?php if (!$rows): ?>
    <p>No entries yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Timestamp</th><th>IP</th><th>Forwarded</th><th>Page</th>
        <th>Screen</th><th>Timezone</th><th>Language</th><th>User Agent</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= count($rows) - $i ?></td>
          <td class="ts"><?= htmlspecialchars($r['ts'] ?? $r['serverTime'] ?? '—') ?></td>
          <td class="ip"><?= htmlspecialchars($r['ip'] ?? '—') ?></td>
          <td class="ua"><?= htmlspecialchars($r['forwarded'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['page'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['screen'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['timezone'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['language'] ?? '—') ?></td>
          <td class="ua"><?= htmlspecialchars($r['userAgent'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>