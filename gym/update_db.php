<?php
require_once __DIR__ . '/db.php';
$pdo = get_db();
try {
    $pdo->exec("ALTER TABLE routine_exercises ADD COLUMN sets_data TEXT DEFAULT NULL;");
    echo "Added sets_data.<br>";
} catch (Exception $e) { echo "sets_data already exists.<br>"; }

try {
    $pdo->exec("ALTER TABLE routine_exercises ADD COLUMN notes TEXT DEFAULT NULL;");
    echo "Added notes.<br>";
} catch (Exception $e) { echo "notes already exists.<br>"; }
echo "Done!";