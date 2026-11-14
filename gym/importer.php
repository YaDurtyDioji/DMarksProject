<?php
declare(strict_types=1);

/**
 * openGym catalogue importer — v4
 *
 * Designed for 30 s / 32 MB hosts. Three resumable phases, each step
 * budgeted to ~10 s so a full request always fits inside max_execution_time.
 *
 *   Phase A  LIST     GitHub Contents API, one page per step, cached to disk.
 *   Phase B  FETCH    jsDelivr CDN, 15 concurrent, writes to cache on completion.
 *   Phase C  INSERT   reads cache dir, inserts into DB, idempotent by id.
 *
 * State is derived purely from what's on disk, so a refresh / crash / timeout
 * simply continues where it stopped. Nothing is ever re-downloaded.
 */

define('OPENGYM_IMPORTER_VERSION', '4.0');

const OG_LIST_URL = 'https://api.github.com/repos/DuarteSantos8/openGym/contents/catalogue/exercises';
const OG_CDN_BASE = 'https://cdn.jsdelivr.net/gh/DuarteSantos8/openGym@main/catalogue/exercises/';
const OG_PER_PAGE = 1000;

/* ====================================================================== */
function exercise_count(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
}

function importer_paths(): array
{
    $cfg     = require __DIR__ . '/config.php';
    $dataDir = dirname($cfg['sqlite_path']);
    $cache   = $dataDir . '/cache';
    if (!is_dir($cache)) @mkdir($cache, 0775, true);

    return [
        'data'  => $dataDir,
        'cache' => $cache,
        'list'  => $cache . '/_list.txt',       // newline-delimited filenames
        'pages' => $cache . '/_pages',          // raw GitHub pages
    ];
}

/* ====================================================================== */
function catalogue_import_step(float $budget = 10.0): array
{
    @ini_set('memory_limit', '64M');
    $deadline = microtime(true) + $budget;

    $pdo = get_db();
    $p   = importer_paths();
    if (!is_dir($p['pages'])) @mkdir($p['pages'], 0775, true);

    /* ---- local bundle wins ------------------------------------------- */
    $cfg      = require __DIR__ . '/config.php';
    $localDir = $cfg['catalogue_dir'];
    if (exercise_count($pdo) === 0 && is_dir($localDir) && ($f = glob($localDir . '/*.json'))) {
        import_from_files($pdo, $f, $deadline);
        return state($pdo, $p, null);
    }

    /* ---- Phase A — list (until _list.txt exists & is non-empty) ----- */
    if (!is_file($p['list']) || filesize($p['list']) === 0) {
        $err = build_list($p, $deadline);
        if ($err) return state($pdo, $p, $err);
        if (!is_file($p['list'])) return state($pdo, $p, null);   // more pages to fetch
    }

    $names = file($p['list'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    if (!$names) return state($pdo, $p, 'Catalogue listing is empty — GitHub API may have failed.');

    /* ---- Phase B — download missing files --------------------------- */
    $missing = [];
    foreach ($names as $n) {
        if (!is_file($p['cache'] . '/' . $n)) $missing[] = $n;
    }
    if ($missing && microtime(true) < $deadline) {
        fetch_to_disk($missing, $p['cache'], 15, $deadline);
    }

    /* ---- Phase C — insert from cache -------------------------------- */
    if (microtime(true) < $deadline) {
        $cached = array_values(array_filter(
            glob($p['cache'] . '/*.json') ?: [],
            fn($f) => substr(basename($f), 0, 1) !== '_'
        ));
        if ($cached) import_from_files($pdo, $cached, $deadline);
    }

    $st = state($pdo, $p, null);
    $st['total'] = count($names);
    $st['done']  = ($st['inserted'] >= $st['total']) && $st['total'] > 0;
    return $st;
}

/* ====================================================================== */
/** Fetch one page of the GitHub Contents API. Streamed via regex → low mem. */
function build_list(array $p, float $deadline): ?string
{
    $page = count(glob($p['pages'] . '/page_*.json') ?: []) + 1;
    if ($page > 10) {                    // should never happen
        finalize_list($p);
        return null;
    }

    $url = OG_LIST_URL . '?ref=main&per_page=' . OG_PER_PAGE . '&page=' . $page;
    $body = http_get($url, 20);
    if ($body === null) {
        return 'GitHub API request failed (page ' . $page . '). Could be network or rate limit.';
    }

    // Save raw page for resumability, and extract filenames WITHOUT json_decode
    @file_put_contents($p['pages'] . '/page_' . $page . '.json', $body);

    $rows = substr_count($body, '"name"');
    // Append this page's filenames to the running list
    preg_match_all('/"name"\s*:\s*"([^"]+\.json)"/', $body, $m);
    if (!empty($m[1])) {
        @file_put_contents($p['list'], implode("\n", $m[1]) . "\n", FILE_APPEND);
    }

    // Done if this page returned fewer than PER_PAGE entries
    if ($rows < OG_PER_PAGE) {
        // Already written; nothing more to do — the next call sees _list.txt exists.
        return null;
    }

    // More pages exist; next request will fetch page+1.
    return null;
}

function finalize_list(array $p): void
{
    // No-op; _list.txt is already built page-by-page.
}

/* ====================================================================== */
function fetch_to_disk(array $names, string $dir, int $concurrency, float $deadline): int
{
    $written = 0;
    $mh      = curl_multi_init();
    $active  = [];
    $queue   = $names;

    while (($queue || $active) && microtime(true) < $deadline) {

        while (count($active) < $concurrency && $queue) {
            $name = array_shift($queue);
            $dest = $dir . '/' . $name;
            if (is_file($dest)) continue;

            $ch = curl_init(OG_CDN_BASE . $name);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => 'openGym-php-importer/4',
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[(int) $ch] = ['name' => $name, 'dest' => $dest, 'ch' => $ch];
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 0.3);
        } while ($running && $status === CURLM_OK);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $a  = $active[(int) $ch] ?? null;
            if ($a && $info['result'] === CURLE_OK) {
                $body = curl_multi_getcontent($ch);
                if (is_string($body) && $body !== '' && ltrim($body)[0] === '{') {
                    @file_put_contents($a['dest'], $body);
                    $written++;
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if ($a) unset($active[(int) $ch]);
        }
    }

    foreach ($active as $a) {
        curl_multi_remove_handle($mh, $a['ch']);
        curl_close($a['ch']);
    }
    curl_multi_close($mh);
    return $written;
}

/* ====================================================================== */
function import_from_files(PDO $pdo, array $files, float $deadline): void
{
    $existing = array_flip($pdo->query('SELECT id FROM exercises')
                                 ->fetchAll(PDO::FETCH_COLUMN, 0));

    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO exercises
            (id, name, body_part, equipment, target, secondary_muscles,
             category, description, instructions, demo_url)
        VALUES
            (:id, :name, :bp, :eq, :target, :sec,
             :cat, :descr, :instr, :demo)
    SQL);

    $pdo->beginTransaction();
    try {
        foreach ($files as $file) {
            if (microtime(true) > $deadline) break;
            $d = json_decode((string) @file_get_contents($file), true);
            if (!is_array($d) || empty($d['id']) || empty($d['name'])) continue;
            $id = (string) $d['id'];
            if (isset($existing[$id])) continue;
            try {
                $stmt->execute(row_params($d));
                $existing[$id] = 1;
            } catch (Throwable $e) {
                // duplicate / malformed
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function row_params(array $d): array
{
    $id = (string) $d['id'];
    return [
        ':id'    => $id,
        ':name'  => $d['name'],
        ':bp'    => $d['bodyPart']  ?? null,
        ':eq'    => $d['equipment'] ?? null,
        ':target'=> $d['target']    ?? null,
        ':sec'   => json_encode($d['secondaryMuscles'] ?? [], JSON_UNESCAPED_UNICODE),
        ':cat'   => $d['category']  ?? null,
        ':descr' => $d['description'] ?? null,
        ':instr' => json_encode($d['instructions'] ?? [], JSON_UNESCAPED_UNICODE),
        ':demo'  => "https://raw.githubusercontent.com/DuarteSantos8/openGym/main/catalogue/media/clip/{$id}.mp4",
    ];
}

/* ====================================================================== */
function state(PDO $pdo, array $p, ?string $error, ?int $total = null): array
{
    $inserted = exercise_count($pdo);

    $cached = 0;
    foreach (glob($p['cache'] . '/*.json') ?: [] as $f) {
        if (substr(basename($f), 0, 1) !== '_') $cached++;
    }

    $listed = is_file($p['list'])
        ? count(file($p['list'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
        : 0;

    if ($total === null) $total = $listed;

    $done = ($total > 0) && ($inserted >= $total);

    return [
        'inserted' => $inserted,
        'cached'   => $cached,
        'total'    => $total,
        'listed'   => $listed,
        'phase'    => $listed === 0 ? 'listing' : ($cached < $listed ? 'downloading' : 'inserting'),
        'done'     => $done,
        'error'    => $error,
    ];
}

function http_get(string $url, int $timeout): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'openGym-php-importer/4',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code >= 200 && $code < 300) ? $body : null;
}