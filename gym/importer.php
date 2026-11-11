<?php
declare(strict_types=1);

/**
 * Called from bootstrap.php. Runs the FIRST time any page is opened after
 * deploying, then never again (guarded by the exercises table itself).
 *
 * Two paths:
 *   1. Local catalogue dir already exists → import straight from disk.
 *   2. Not present → download the GitHub archive ZIP, extract just the
 *      *.json files inside catalogue/exercises/, import them.
 *
 * A file lock in data/ prevents two concurrent requests racing the import.
 */

function ensure_catalogue_imported(): void
{
    $pdo = get_db();

    // Fast path — already seeded.
    if ((int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn() > 0) {
        return;
    }

    $lock = fopen(dirname((require __DIR__ . '/config.php')['sqlite_path']) . '/import.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        // Another request is already importing; let it finish.
        if ($lock) fclose($lock);
        return;
    }

    try {
        // Re-check inside the lock (another request may have just finished).
        if ((int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn() > 0) {
            return;
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $cfg = require __DIR__ . '/config.php';
        $dir = $cfg['catalogue_dir'];

        if (!is_dir($dir) || !glob($dir . '/*.json')) {
            $dir = download_catalogue_zip($cfg['catalogue_zip']);
        }

        if (!$dir) {
            error_log('[openGym] Catalogue import failed — no JSONs and download failed.');
            return;
        }

        import_json_files($pdo, $dir);

    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* ------------------------------------------------------------------ */

function import_json_files(PDO $pdo, string $dir): void
{
    $files = glob(rtrim($dir, '/') . '/*.json');
    if (!$files) return;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO exercises
            (id, name, body_part, equipment, target, secondary_muscles,
             category, description, instructions, demo_url)
        VALUES
            (:id, :name, :bp, :eq, :target, :sec,
             :cat, :descr, :instr, :demo)
    SQL);

    foreach ($files as $file) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || empty($data['id']) || empty($data['name'])) continue;

        $id = (string) $data['id'];

        try {
            $stmt->execute([
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
            ]);
        } catch (Throwable $e) {
            // Duplicate or malformed row — skip.
        }
    }

    $pdo->commit();

    error_log('[openGym] Imported ' . count($files) . ' exercise files.');
}

/* ------------------------------------------------------------------ */

function download_catalogue_zip(string $url): ?string
{
    $tmpZip = sys_get_temp_dir() . '/opengym-catalogue-' . bin2hex(random_bytes(4)) . '.zip';
    $tmpDir = sys_get_temp_dir() . '/opengym-extract-'  . bin2hex(random_bytes(4));

    // ---- Download --------------------------------------------------------
    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fh = fopen($tmpZip, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_USERAGENT      => 'openGym-php-importer/1.0',
        ]);
        $ok = curl_exec($ch) !== false;
        curl_close($ch);
        fclose($fh);
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout' => 180,
            'header'  => "User-Agent: openGym-php-importer/1.0\r\n",
        ]]);
        $ok = @copy($url, $tmpZip, $ctx);
    }

    if (!$ok || !file_exists($tmpZip) || filesize($tmpZip) < 1024) {
        @unlink($tmpZip);
        return null;
    }

    // ---- Extract only JSONs under catalogue/exercises/ -------------------
    if (!class_exists('ZipArchive')) {
        error_log('[openGym] ZipArchive not available; cannot extract catalogue.');
        @unlink($tmpZip);
        return null;
    }

    @mkdir($tmpDir, 0775, true);
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        @unlink($tmpZip);
        return null;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        // Pattern: openGym-main/catalogue/exercises/<file>.json
        if (preg_match('#/catalogue/exercises/[^/]+\.json$#', $name)) {
            $base = basename($name);
            file_put_contents($tmpDir . '/' . $base, $zip->getFromIndex($i));
        }
    }

    $zip->close();
    @unlink($tmpZip);

    return $tmpDir;
}