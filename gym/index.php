<?php
require_once __DIR__ . '/bootstrap.php';
$user    = require_login();
$filters = get_filter_options();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= csrf_token() ?>">
    <title>openGym PHP — Routine Builder</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
<header class="app-header">
    <h1>🏋️ openGym <span>PHP</span></h1>
    <nav>
        <a href="index.php" class="active">Builder</a>
        <a href="routines.php">Routines</a>
        <span class="user-chip">
            <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?>
            · <a href="logout.php">Sign out</a>
        </span>
    </nav>
</header>

<main class="builder">
    <aside class="catalogue-panel">
        <h2>Exercise catalogue</h2>

        <div class="filters">
            <input id="search" type="search" placeholder="Search exercises…" autocomplete="off">

            <label>Body part
                <select id="filter-bodypart">
                    <option value="">All</option>
                    <?php foreach ($filters['bodyParts'] as $bp): ?>
                        <option value="<?= htmlspecialchars($bp) ?>"><?= htmlspecialchars($bp) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Equipment
                <select id="filter-equipment">
                    <option value="">All</option>
                    <?php foreach ($filters['equipment'] as $eq): ?>
                        <option value="<?= htmlspecialchars($eq) ?>"><?= htmlspecialchars($eq) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <p id="result-count" class="muted"></p>
        <div id="exercise-list" class="exercise-list"></div>
    </aside>

    <section class="routine-panel">
        <div class="routine-toolbar">
            <label class="routine-name-label">Routine name
                <input id="routine-name" type="text" value="My workout" maxlength="120">
            </label>

            <label class="load-label">Load routine
                <select id="load-routine">
                    <option value="">— New routine —</option>
                </select>
            </label>

            <button id="btn-new" class="btn-secondary" type="button">New</button>
            <button id="btn-save" class="btn-primary" type="button">Save</button>
            <button id="btn-delete" class="btn-danger" type="button" hidden>Delete</button>
        </div>

        <p class="hint">Drag exercises from the left into this list. Drag inside to reorder.</p>

        <ul id="routine-list" class="routine-list">
            <li class="empty-state">No exercises yet — drag some in from the catalogue.</li>
        </ul>

        <div id="status" class="status" role="status"></div>
    </section>
</main>

<script src="assets/app.js"></script>
</body>
</html>