<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();

// Send first-time visitors to the importer instead of running it inline
// (the host's 30 s limit makes inline import unreliable).
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');

if ($script !== 'setup.php') {
    $pdo = get_db();
    $n   = (int) $pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn();

    if ($n === 0) {
        if ($script === 'api.php') {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode([
                'error' => 'Catalogue is still being imported',
                'setup' => 'setup.php',
            ]);
            exit;
        }
        header('Location: setup.php');
        exit;
    }
}