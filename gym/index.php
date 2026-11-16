<?php
require_once __DIR__ . '/bootstrap.php';
$user    = require_login();
$filters = get_filter_options();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= csrf_token() ?>">
    <title>Builder — openGym PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css?v=3" rel="stylesheet">
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
                <li class="nav-item"><a class="nav-link active" href="index.php">Builder</a></li>
                <li class="nav-item"><a class="nav-link" href="routines.php">Routines</a></li>
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

<main class="container-fluid py-4 px-3">
    <div class="row g-3">

        <!-- ============ Catalogue ============ -->
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body-tertiary py-3 d-flex align-items-center gap-2">
                    <i class="bi bi-collection"></i>
                    <span class="fw-semibold">Exercise catalogue</span>
                </div>

                <div class="card-body pb-2 d-flex flex-column gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="search" type="search" class="form-control" placeholder="Search exercises…" autocomplete="off">
                    </div>
                    <select id="filter-bodypart" class="form-select form-select-sm">
                        <option value="">All body parts</option>
                        <?php foreach ($filters['bodyParts'] as $bp): ?>
                            <option value="<?= htmlspecialchars($bp) ?>"><?= htmlspecialchars(ucfirst($bp)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filter-equipment" class="form-select form-select-sm">
                        <option value="">All equipment</option>
                        <?php foreach ($filters['equipment'] as $eq): ?>
                            <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars(ucfirst($eq)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="result-count" class="text-secondary small mb-0"></p>
                </div>

                <div id="exercise-list" class="px-2 pb-2"></div>
            </div>
        </div>

        <!-- ============ Builder ============ -->
        <div class="col-lg-8 col-xl-9">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body-tertiary py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-pencil"></i></span>
                                <input id="routine-name" type="text" class="form-control" value="My workout" maxlength="120" placeholder="Routine name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="load-routine" class="form-select form-select-sm">
                                <option value="">— New routine —</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                            <button id="btn-new"    type="button" class="btn btn-outline-secondary btn-sm flex-grow-1 flex-md-grow-0">
                                <i class="bi bi-plus-lg"></i> New
                            </button>
                            <button id="btn-save"   type="button" class="btn btn-primary btn-sm flex-grow-1 flex-md-grow-0">
                                <i class="bi bi-check2"></i> Save
                            </button>
                            <button id="btn-delete" type="button" class="btn btn-outline-danger btn-sm flex-grow-1 flex-md-grow-0" hidden>
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <p class="text-secondary small mb-3">
                        <i class="bi bi-info-circle"></i>
                        Drag exercises from the catalogue into the list below. Drag within the list to reorder.
                    </p>

                    <div id="routine-list" class="routine-list">
                        <div class="empty-state">
                            <i class="bi bi-plus-circle"></i>
                            <div class="fw-semibold">Drag exercises here</div>
                            <div class="small">Pick from the catalogue on the left</div>
                        </div>
                    </div>

                    <div id="status" role="status"></div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Scripts must load synchronously at the bottom -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=3"></script>
</body>
</html>