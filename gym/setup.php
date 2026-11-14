<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/importer.php';

if (!function_exists('catalogue_import_step')) {
    http_response_code(500);
    header('Content-Type: text/html');
    echo "<h1>Wrong importer.php</h1><p>First function should be <code>exercise_count()</code>.</p>";
    exit;
}

if (isset($_GET['reset'])) {
    $p = importer_paths();
    foreach (glob($p['cache'] . '/*.json') ?: [] as $f) @unlink($f);
    foreach (glob($p['cache'] . '/_pages/*.json') ?: [] as $f) @unlink($f);
    @unlink($p['list']); @rmdir($p['pages']);
    get_db()->exec('DELETE FROM exercises');
    header('Content-Type: application/json');
    echo json_encode(['reset' => true]);
    exit;
}

if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    echo json_encode(state(get_db(), importer_paths(), null));
    exit;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(catalogue_import_step(10.0));
    } catch (Throwable $e) {
        echo json_encode([
            'error' => get_class($e) . ': ' . $e->getMessage(),
            'where' => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
    exit;
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setting up openGym…</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <div class="text-center mb-4">
                    <div class="setup-icon mb-3"><i class="bi bi-database-fill-gear"></i></div>
                    <h1 class="h3 fw-bold mb-1">Setting up openGym</h1>
                    <p class="text-secondary small mb-0">Importing the exercise catalogue — this runs once</p>
                </div>

                <div class="card shadow-lg border-secondary-subtle">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-secondary small text-uppercase" style="letter-spacing: .05em;">Status</span>
                            <span class="badge bg-primary-subtle text-primary-emphasis text-uppercase small" id="phase">starting</span>
                        </div>

                        <div class="progress mb-3" role="progressbar" style="height: 10px;">
                            <div id="fill" class="progress-bar progress-bar-striped progress-bar-animated"
                                 style="width: 0%;"></div>
                        </div>

                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="text-secondary small text-uppercase" style="letter-spacing: .05em;">Listed</div>
                                <div class="fw-semibold fs-5" id="listed">0</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary small text-uppercase" style="letter-spacing: .05em;">Cached</div>
                                <div class="fw-semibold fs-5" id="cached">0</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary small text-uppercase" style="letter-spacing: .05em;">Imported</div>
                                <div class="fw-semibold fs-5" id="inserted">0</div>
                            </div>
                        </div>

                        <div id="msg" class="text-secondary small text-center mt-3 mb-0">
                            Preparing…
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

<script>
const fill      = document.getElementById('fill');
const phaseEl   = document.getElementById('phase');
const listedEl  = document.getElementById('listed');
const cachedEl  = document.getElementById('cached');
const insertedEl= document.getElementById('inserted');
const msg       = document.getElementById('msg');

async function step() {
    let res;
    try {
        res = await fetch('setup.php?ajax=1&_=' + Date.now(), {cache: 'no-store'});
    } catch (e) {
        msg.innerHTML = `<span class="text-danger">Network issue — retrying…</span>`;
        return setTimeout(step, 3000);
    }

    if (!res.ok) {
        const body = await res.text();
        msg.innerHTML = `<span class="text-danger">HTTP ${res.status} — retrying…</span>`;
        console.error(body);
        return setTimeout(step, 3000);
    }

    let s;
    try { s = await res.json(); }
    catch { msg.innerHTML = '<span class="text-danger">Invalid JSON response.</span>'; return; }

    if (s.error) {
        msg.innerHTML = `<span class="text-danger">${escape(s.error)}</span>`;
        return;
    }

    const total = s.total || 1;
    const pct   = Math.min(100, Math.round(((s.inserted || 0) / total) * 100));

    fill.style.width = pct + '%';
    phaseEl.textContent = s.phase || 'working';
    listedEl.textContent = (s.listed || 0).toLocaleString();
    cachedEl.textContent = (s.cached || 0).toLocaleString();
    insertedEl.textContent = (s.inserted || 0).toLocaleString();
    msg.textContent = `Imported ${(s.inserted || 0).toLocaleString()} of ${(s.total || 0).toLocaleString()} (${pct}%)`;

    if (s.done) {
        fill.style.width = '100%';
        phaseEl.textContent = 'done';
        phaseEl.className = 'badge bg-success-subtle text-success-emphasis text-uppercase small';
        msg.innerHTML = `<span class="text-success">
            <i class="bi bi-check-circle-fill"></i> Complete — redirecting…
        </span>`;
        setTimeout(() => location.href = 'register.php', 1200);
        return;
    }
    setTimeout(step, 300);
}

function escape(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

step();
</script>
</body>
</html>