<?php
// setup-debug.php — TEMPORARY. Delete after use.
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit:       " . ini_get('memory_limit') . "\n";
echo "pdo_sqlite: " . (extension_loaded('pdo_sqlite') ? 'yes' : 'NO') . "\n";
echo "sqlite3:    " . (extension_loaded('sqlite3')    ? 'yes' : 'NO') . "\n";
echo "curl:       " . (function_exists('curl_init')   ? 'yes' : 'NO') . "\n";
echo "zip:        " . (class_exists('ZipArchive')     ? 'yes' : 'NO') . "\n\n";

$dataDir = __DIR__ . '/data';
echo "data dir: $dataDir\n";
echo "  exists:   " . (is_dir($dataDir)     ? 'yes' : 'NO') . "\n";
echo "  writable: " . (is_writable($dataDir) ? 'yes' : 'NO') . "\n";
if (is_dir($dataDir)) {
    foreach (glob($dataDir . '/*') as $f) {
        echo "  - " . basename($f) . " (" . filesize($f) . " bytes)\n";
    }
}

echo "\nSQLite connect test...\n";
try {
    $pdo = new PDO('sqlite:' . $dataDir . '/exercises.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $t = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "  tables: " . (implode(', ', $t) ?: '(none)') . "\n";
    if (in_array('exercises', $t)) {
        echo "  exercise rows: " . $pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn() . "\n";
    }
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\nOutbound HTTPS test...\n";
$ch = curl_init('https://data.jsdelivr.com/v1/packages/gh/DuarteSantos8/openGym@main?structure=flat');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'opengym-debug',
]);
$t0 = microtime(true);
$body = curl_exec($ch);
$dt = round(microtime(true) - $t0, 2);
if (curl_errno($ch)) {
    echo "  curl error: " . curl_error($ch) . "\n";
} else {
    echo "  jsDelivr tree: " . strlen($body) . " bytes in {$dt}s\n";
    $tree = json_decode($body, true);
    $jsonCount = 0;
    if (!empty($tree['files'])) {
        foreach ($tree['files'] as $f) {
            if (preg_match('#^/catalogue/.+\.json$#', $f['name'])) $jsonCount++;
        }
    }
    echo "  catalogue JSONs found: $jsonCount\n";
}
curl_close($ch);