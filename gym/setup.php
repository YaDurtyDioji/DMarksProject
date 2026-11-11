<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/importer.php';

/* ---------- version guard ------------------------------------------ */
if (!function_exists('catalogue_import_step')) {
    http_response_code(500);
    header('Content-Type: text/html');
    echo "<h1>Wrong importer.php</h1><p>First function should be <code>exercise_count()</code>. "
       . "Look for <code>OPENGYM_IMPORTER_VERSION</code> near the top.</p>";
    exit;
}

/* ---------- reset -------------------------------------------------- */
if (isset($_GET['reset'])) {
    $p = importer_paths();
    foreach (glob($p['cache'] . '/*.json') ?: [] as $f) @unlink($f);
    foreach (glob($p['cache'] . '/_pages/*.json') ?: [] as $f) @unlink($f);
    @unlink($p['list']);
    @rmdir($p['pages']);
    get_db()->exec('DELETE FROM exercises');
    header('Content-Type: application/json');
    echo json_encode(['reset' => true]);
    exit;
}

/* ---------- status only -------------------------------------------- */
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    echo json_encode(state(get_db(), importer_paths(), null));
    exit;
}

/* ---------- ajax step ---------------------------------------------- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    try {
        echo json_encode(catalogue_import_step(10.0));
    } catch (Throwable $e) {
        // Never send HTML to the fetch() client — it will surface as JSON.
        echo json_encode([
            'error' => get_class($e) . ': ' . $e->getMessage(),
            'where' => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Setting up openGym…</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#0f1115; color:#e6e8ec;
               display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .card { background:#181b22; border:1px solid #2a2f3a; border-radius:12px;
                padding:28px; width:480px; }
        h1 { font-size:1.1rem; margin:0 0 16px; }
        .bar { height:10px; background:#2a2f3a; border-radius:6px; overflow:hidden; }
        .fill { height:100%; width:0; background:#4f8cff; transition:width .4s; }
        .muted { color:#8b92a3; font-size:.85rem; margin-top:12px; white-space:pre-wrap; }
        .err { color:#ff9c9c; }
        .phase { display:inline-block; padding:2px 8px; background:#2a3348; border-radius:10px;
                 font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; margin-left:8px; }
    </style>
</head>
<body>
<main class="card">
    <h1>Importing exercise catalogue <span class="phase" id="phase">starting</span></h1>
    <div class="bar"><div class="fill" id="fill"></div></div>
    <p class="muted" id="msg">Starting…</p>
</main>
<script>
const fill  = document.getElementById('fill');
const msg   = document.getElementById('msg');
const phase = document.getElementById('phase');

async function step() {
    let res;
    try {
        res = await fetch('setup.php?ajax=1&_=' + Date.now(), {cache: 'no-store'});
    } catch (e) {
        msg.innerHTML = '<span class="err">Network issue: ' + esc(e.message) + ' — retrying in 3s…</span>';
        return setTimeout(step, 3000);
    }

    if (!res.ok) {
        const body = await res.text();
        msg.innerHTML = '<span class="err">HTTP ' + res.status + '\n' + esc(body.slice(0, 600)) + '</span>';
        return setTimeout(step, 3000);
    }

    let s;
    try { s = await res.json(); }
    catch (e) { msg.innerHTML = '<span class="err">Invalid JSON response — server sent HTML.\nCheck error log.</span>'; return; }

    if (s.error) {
        msg.innerHTML = '<span class="err">' + esc(s.error) + '</span>';
        return;
    }

    const total = s.total || 1;
    const pct   = Math.min(100, Math.round(((s.inserted || 0) / total) * 100));

    fill.style.width = pct + '%';
    phase.textContent = s.phase || 'working';
    msg.textContent =
        `listed: ${s.listed.toLocaleString()} · cached: ${s.cached.toLocaleString()} · ` +
        `imported: ${s.inserted.toLocaleString()} / ${s.total.toLocaleString()} (${pct}%)`;

    if (s.done) {
        fill.style.width = '100%';
        phase.textContent = 'done';
        msg.textContent = `Done — ${s.inserted.toLocaleString()} exercises imported. Redirecting…`;
        setTimeout(() => location.href = 'register.php', 1200);
        return;
    }
    setTimeout(step, 300);
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

step();
</script>
</body>
</html>