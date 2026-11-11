<?php
require_once __DIR__ . '/bootstrap.php';
$user     = require_login();
$routines = list_routines((int) $user['id']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf" content="<?= csrf_token() ?>">
    <title>Saved routines — openGym PHP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="app-header">
    <h1>🏋️ openGym <span>PHP</span></h1>
    <nav>
        <a href="index.php">Builder</a>
        <a href="routines.php" class="active">Routines</a>
        <span class="user-chip">
            <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?>
            · <a href="logout.php">Sign out</a>
        </span>
    </nav>
</header>

<main class="routines-page">
    <h2>Your routines</h2>

    <?php if (empty($routines)): ?>
        <p class="muted">No routines yet. <a href="index.php">Build one →</a></p>
    <?php else: ?>
        <table class="routines-table">
            <thead>
            <tr><th>#</th><th>Name</th><th>Exercises</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($routines as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= (int) $r['exercise_count'] ?></td>
                    <td><?= htmlspecialchars((string) $r['created_at']) ?></td>
                    <td class="actions">
                        <a class="btn-secondary small"
                           href="index.php?load=<?= (int) $r['id'] ?>">Edit</a>
                        <button class="btn-danger small delete-routine"
                                data-id="<?= (int) $r['id'] ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
document.querySelectorAll('.delete-routine').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this routine?')) return;
        const id = btn.dataset.id;
        await fetch(`api.php?action=delete-routine&id=${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
        });
        btn.closest('tr').remove();
    });
});
</script>
</body>
</html>