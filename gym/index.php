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
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-lightning-charge-fill text-warning"></i>
            <span class="brand-gradient">openGym</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <button id="theme-toggle" class="btn btn-outline-secondary btn-sm" type="button" title="Toggle Theme">
                <i class="bi bi-moon-stars-fill"></i>
            </button>
            
            <label class="text-secondary small d-flex align-items-center gap-1 mb-0">
                Weight (kg)
                <input type="number" id="user-weight" class="form-control form-control-sm" style="width: 70px;" value="75">
            </label>

            <span class="vr"></span>
            <a class="btn btn-outline-secondary btn-sm" href="logout.php">Sign out</a>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 px-3">
    <div class="row g-3">
        <!-- Catalogue Panel -->
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body-tertiary py-3">
                    <span class="fw-semibold">Exercise catalogue</span>
                </div>
                <div class="card-body pb-2 d-flex flex-column gap-2">
                    <input id="search" type="search" class="form-control form-control-sm" placeholder="Search exercises…">
                    <select id="filter-bodypart" class="form-select form-select-sm">
                        <option value="">All body parts</option>
                        <?php foreach ($filters['bodyParts'] as $bp): ?>
                            <option value="<?= htmlspecialchars($bp) ?>"><?= htmlspecialchars(ucfirst($bp)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filter-category" class="form-select form-select-sm">
                        <option value="">All categories (Yoga, Sports...)</option>
                        <?php foreach ($filters['categories'] as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="result-count" class="text-secondary small mb-0"></p>
                </div>
                <div id="exercise-list" class="px-2 pb-2"></div>
            </div>
        </div>

        <!-- Builder Panel -->
        <div class="col-lg-8 col-xl-9">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body-tertiary py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <input id="routine-name" type="text" class="form-control form-control-sm" value="My workout" placeholder="Routine name">
                        </div>
                        <div class="col-md-6 d-flex gap-2 justify-content-md-end">
                            <select id="load-routine" class="form-select form-select-sm" style="width: auto;">
                                <option value="">— New routine —</option>
                            </select>
                            <button id="btn-save" class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Save</button>
                            <button id="btn-delete" class="btn btn-outline-danger btn-sm" hidden><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <p class="text-secondary small mb-3">
                        Drag exercises here. To make a <strong>Superset</strong>, drag one exercise onto the card of another. Use the <i class="bi bi-grip-vertical"></i> handle to reorder.
                    </p>
                    <!-- Drop zone container -->
                    <div id="routine-list" class="routine-list p-2">
                        <div id="empty-state" class="empty-state">
                            <i class="bi bi-plus-circle"></i>
                            <div class="fw-semibold">Drag exercises here</div>
                            <div class="small text-muted">Drag one exercise onto another to create a superset</div>
                        </div>
                        <!-- Blocks will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
<!-- Hidden staging area for drag-and-drop -->
<div id="staging-area" style="display:none;"></div>
</body>
</html>