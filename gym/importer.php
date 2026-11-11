<?php
declare(strict_types=1);

/**
 * Zero-terminal catalogue seeding.
 *
 * Strategy:
 *   1. If openGym/catalogue/exercises/*.json already exists in the repo → import from disk.
 *   2. Otherwise ask the jsDelivr package API for the file tree (one small request),
 *      then download ONLY the exercise JSON files in parallel with curl_multi.
 *      No giant GitHub ZIP. No demo videos.
 *
 * Re-entrant: safe to refresh or retry. Existing exercise IDs are skipped.
 * Progress: browsers can refresh and it continues where it left off
 * because downloaded JSONs are cached to data/cache/*.json on disk.
 */

function ensure_catalogue_imported(): void
{
    $pdo = get_db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn() > 0) return;

    $cfg     = require __DIR__ . '/config.php';
    $dataDir = dirname($cfg['sqlite_path']);
    if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);

    $lock = @fopen($dataDir . '/import.lock', 'c');
    if (!$lock) { error_log('[openGym] cannot open lock in ' . $dataDir); return; }
    if (!flock($lock, LOCK_EX)) { fclose($lock); return; }

    try {
        if ((int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn() > 0) return;

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        // --- Path 1: local catalogue committed to the repo -------------
        $dir = $cfg['catalogue_dir'];
        if (is_dir($dir) && ($files = glob($dir . '/*.json'))) {
            error_log('[openGym] Importing from local catalogue: ' . count($files) . ' files');
            import_json_files($pdo, $files);
            return;
        }

        // --- Path 2: fetch from jsDelivr (JSON only, in parallel) ------
        import_from_jsdelivr($pdo, 'DuarteSantos8/openGym', 'main', $dataDir);

    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* ====================================================================== */

function import_json_files(PDO $pdo, array $files): void
{
    $pdo->beginTransaction();
    $stmt = insert_stmt($pdo);
    $ok = 0; $skip = 0;

    foreach ($files as $file) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || empty($data['id']) || empty($data['name'])) { $skip++; continue; }
        try { $stmt->execute(row_params($data)); $ok++; }
        catch (Throwable $e) { $skip++; }
    }

    $pdo->commit();
    error_log("[openGym] Import (local): $ok inserted, $skip skipped");
}

/* ====================================================================== */

function import_from_jsdelivr(PDO $pdo, string $repo, string $ref, string $dataDir): void
{
    $cacheDir = $dataDir . '/cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);

    // 1. File tree ---------------------------------------------------------
    $treeUrl = "https://data.jsdelivr.com/v1/packages/gh/{$repo}@{$ref}?structure=flat";
    $ctx = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'openGym-php/2']]);
    $raw = @file_get_contents($treeUrl, false, $ctx);
    if (!$raw) { error_log('[openGym] jsDelivr tree fetch failed'); return; }

    $tree = json_decode($raw, true);
    if (empty($tree['files'])) { error_log('[openGym] jsDelivr returned no files'); return; }

    $paths = [];
    foreach ($tree['files'] as $f) {
        if (preg_match('#^/catalogue/exercises/[^/]+\.json$#', $f['name'])) $paths[] = $f['name'];
    }
    if (!$paths) {
        // Some repo layouts put a single big JSON under catalogue/
        foreach ($tree['files'] as $f) {
            if (preg_match('#^/catalogue/[^/]+\.json$#', $f['name'])) $paths[] = $f['name'];
        }
    }
    if (!$paths) { error_log('[openGym] No catalogue JSONs in tree'); return; }

    error_log('[openGym] Found ' . count($paths) . ' catalogue JSON files');

    // 2. Already-imported IDs (skip download for those) --------------------
    $existing = array_flip(
        $pdo->query('SELECT id FROM exercises')->fetchAll(PDO::FETCH_COLUMN, 0)
    );

    // 3. Download, using on-disk cache so refreshes are incremental --------
    $urls = [];
    foreach ($paths as $p) {
        $cacheFile = $cacheDir . '/' . basename($p);
        if (is_file($cacheFile)) continue;                       // cached from a prior attempt
        $urls[$p] = "https://cdn.jsdelivr.net/gh/{$repo}@{$ref}{$p}";
    }

    if ($urls) {
        error_log('[openGym] Downloading ' . count($urls) . ' JSONs…');
        $bodies = fetch_parallel($urls, 40);

        foreach ($bodies as $path => $body) {
            if ($body !== null && $body !== '') {
                @file_put_contents($cacheDir . '/' . basename($path), $body);
            }
        }
    }

    // 4. Insert from cache ------------------------------------------------
    $files = glob($cacheDir . '/*.json') ?: [];
    error_log('[openGym] Inserting ' . count($files) . ' cached JSONs…');

    $pdo->beginTransaction();
    $stmt = insert_stmt($pdo);
    $ok = 0; $skip = 0;

    foreach ($files as $file) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || empty($data['id']) || empty($data['name'])) { $skip++; continue; }
        $id = (string) $data['id'];
        if (isset($existing[$id])) { $skip++; continue; }
        try { $stmt->execute(row_params($data)); $ok++; }
        catch (Throwable $e) { $skip++; }
    }

    $pdo->commit();
    error_log("[openGym] Import (jsDelivr): $ok inserted, $skip skipped");

    // Optional: clean the cache once we've got the full catalogue in the DB
    if ($ok > 1000) {
        foreach (glob($cacheDir . '/*.json') as $f) @unlink($f);
        @rmdir($cacheDir);
    }
}

/* ====================================================================== */

function insert_stmt(PDO $pdo): PDOStatement
{
    return $pdo->prepare(<<<SQL
        INSERT INTO exercises
            (id, name, body_part, equipment, target, secondary_muscles,
             category, description, instructions, demo_url)
        VALUES
            (:id, :name, :bp, :eq, :target, :sec,
             :cat, :descr, :instr, :demo)
    SQL);
}

function row_params(array $data): array
{
    $id = (string) $data['id'];
    return [
        ':id'    => $id,
        ':name'  => $data['name'],
        ':bp'    => $data['bodyPart']  ?? null,
        ':eq'    => $data['equipment'] ?? null,
        ':target'=> $data['target']    ?? null,
        ':sec'   => json_encode($data['secondaryMuscles'] ?? [], JSON_UNESCAPED_UNICODE),
        ':cat'   => $data['category']  ?? null,
        ':descr' => $data['description'] ?? null,
        ':instr' => json_encode($data['instructions'] ?? [], JSON_UNESCAPED_UNICODE),
        ':demo'  => "https://raw.githubusercontent.com/DuarteSantos8/openGym/main/catalogue/media/clip/{$id}.mp4",
    ];
}

/* ====================================================================== */

function fetch_parallel(array $urls, int $concurrency = 40): array
{
    $results = [];
    $mh = curl_multi_init();
    $active = [];
    $queue  = $urls;

    while ($queue || $active) {
        while (count($active) < $concurrency && $queue) {
            $key = array_key_first($queue);
            $url = $queue[$key]; unset($queue[$key]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT      => 'openGym-php/2',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[(int) $ch] = ['key' => $key, 'handle' => $ch];
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 0.5);
        } while ($running && $status === CURLM_OK);

        while ($info = curl_multi_info_read($mh)) {
            $ch  = $info['handle'];
            $key = $active[(int) $ch]['key'];
            $results[$key] = ($info['result'] === CURLE_OK)
                ? curl_multi_getcontent($ch)
                : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($active[(int) $ch]);
        }
    }

    curl_multi_close($mh);
    return $results;
}