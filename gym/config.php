<?php
return [
    'db_driver'   => 'sqlite',                            // 'sqlite' | 'mysql'
    'sqlite_path' => __DIR__ . '/data/exercises.sqlite',

    'mysql' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'opengym',
        'user'   => 'root',
        'pass'   => '',
        'charset'=> 'utf8mb4',
    ],

    // Where to look for the catalogue JSONs.
    // If this folder is empty, importer.php will download from GitHub.
    'catalogue_dir' => __DIR__ . '/openGym/catalogue/exercises',

    // Git archive URL — only used if catalogue_dir is empty.
    'catalogue_zip' => 'https://github.com/DuarteSantos8/openGym/archive/refs/heads/main.zip',

    // Cookies: set 'secure' true on HTTPS.
    'session' => [
        'name'   => 'opengym_sid',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ],
];