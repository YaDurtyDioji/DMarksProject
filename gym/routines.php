<?php
require_once __DIR__ . '/bootstrap.php';
$user     = require_login();
$routines = list_routines((int) $user['id']);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= csrf_token() ?>">
    <title>Routines — openGym PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-lightning-charge-fill text-warning"></i>
            <span class="brand-gradient">openGym</span>
            <span class="text-secondary fw-normal small ms-1">PHP</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <ul class="nav nav-pills d-none d-md-flex">
                <li class="nav-item"><a class="nav-link" href="index.php">Builder</a></li>
                <li class="nav-item"><a class="nav-link active" href="routines.php">Routines</a></li>
            </ul>
            <span class="vr d-none d-md-inline"></span>
            <span class="text-secondary small d-flex align-items-center gap-1">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline"><?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
            </span>
            <a class="btn btn-outline-secondary btn-sm" href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-sm-inline ms-1">Sign out</span>
            </a>
        </div>
    </div>
</nav>

<main class="container py-4">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Your routines</h1>
            <p class="text-secondary small mb-0"><?= count($routines) ?> routine<?= count($routines) === 1 ? '' : 's' ?> saved</p>
        </div>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New routine
        </a>
    </div>

    <?php if (empty($routines)): ?>
        <div class="card shadow-sm border-secondary-subtle">
            <div class="card-body empty-state">
                <i class="bi bi-journal-x"></i>
                <div class="fw-semibold">No routines yet</div>
                <div class="small mb-3">Build your first routine with drag-and-drop.</div>
                <a href="index.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Build one
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-secondary-subtle overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-group-divider">
                        <tr class="text-secondary small text-uppercase">
                            <th style="width: 60px;">#</th>
                            <th>Name</th>
                            <th style="width: 120px;">Exercises</th>
                            <th style="width: 200px;">Created</th>
                            <th style="width: 180px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($routines as $r): ?>
                        <tr>
                            <td class="text-secondary">#<?= (int) $r['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary-emphasis">
                                    <?= (int) $r['exercise_count'] ?> exercise<?= (int) $r['exercise_count'] === 1 ? '' : 's' ?>
                                </span>
                            </td>
                            <td class="text-secondary small"><?= htmlspecialchars((string) $r['created_at']) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="index.php?load=<?= (int) $r['id'] ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button class="btn btn-sm btn-outline-danger delete-routine"
                                        data-id="<?= (int) $r['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
document.querySelectorAll('.delete-routine').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this routine?')) return;
        btn.disabled = true;
        await fetch(`api.php?action=delete-routine&id=${btn.dataset.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
        });
        btn.closest('tr').remove();
    });
});
</script>
</body>
</html>