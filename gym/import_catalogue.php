<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this script from the command line.\n");
}

$config = require __DIR__ . '/config.php';
$dir    = $config['catalogue_dir'];

if (!is_dir($dir)) {
    fwrite(STDERR, "Catalogue directory not found: {$dir}\n");
    fwrite(STDERR, "Clone the repo first:\n  git clone --depth 1 https://github.com/DuarteSantos8/openGym.git openGym\n");
    exit(1);
}

$pdo = get_db();

echo "Clearing old exercise rows…\n";
$pdo->exec('DELETE FROM exercises');

$stmt = $pdo->prepare(<<<SQL
    INSERT INTO exercises
        (id, name, body_part, equipment, target, secondary_muscles,
         category, description, instructions, demo_url)
    VALUES
        (:id, :name, :body_part, :equipment, :target, :secondary_muscles,
         :category, :description, :instructions, :demo_url)
SQL);

$files   = glob($dir . '/*.json');
$total   = count($files);
$count   = 0;
$skipped = 0;

echo "Found {$total} JSON files. Importing…\n";

foreach ($files as $file) {
    $raw  = file_get_contents($file);
    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data['id']) || empty($data['name'])) {
        $skipped++;
        continue;
    }

    // openGym's ids are strings like "0025" — keep them exactly.
    $id = (string) $data['id'];

    // Some entries may be femaleVariant aliases; still importable if they
    // carry a name. You can skip them by uncommenting the next line.
    // if (!empty($data['variantOf'])) { $skipped++; continue; }

    $secondary = $data['secondaryMuscles'] ?? [];
    $instr     = $data['instructions']     ?? [];

    // Build a best-effort demo URL from the catalogue media layout.
    $demoUrl = "https://raw.githubusercontent.com/DuarteSantos8/openGym/main/catalogue/media/clip/{$id}.mp4";

    $stmt->execute([
        ':id'                => $id,
        ':name'              => $data['name'],
        ':body_part'         => $data['bodyPart']  ?? null,
        ':equipment'         => $data['equipment'] ?? null,
        ':target'            => $data['target']    ?? null,
        ':secondary_muscles' => json_encode($secondary, JSON_UNESCAPED_UNICODE),
        ':category'          => $data['category']  ?? null,
        ':description'       => $data['description'] ?? null,
        ':instructions'      => json_encode($instr, JSON_UNESCAPED_UNICODE),
        ':demo_url'          => $demoUrl,
    ]);

    $count++;

    if ($count % 500 === 0) {
        echo "  imported {$count}/{$total}…\n";
    }
}

echo "\nDone.\n";
echo "  Imported: {$count}\n";
echo "  Skipped:  {$skipped}\n";