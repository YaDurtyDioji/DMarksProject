<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = require __DIR__ . '/config.php';

    if ($cfg['db_driver'] === 'sqlite') {
        $dir = dirname($cfg['sqlite_path']);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $cfg['sqlite_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        init_schema_sqlite($pdo);
    } else {
        $m   = $cfg['mysql'];
        $dsn = "mysql:host={$m['host']};port={$m['port']};dbname={$m['dbname']};charset={$m['charset']}";
        $pdo = new PDO($dsn, $m['user'], $m['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        init_schema_mysql($pdo);
    }

    return $pdo;
}

function init_schema_sqlite(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            display_name  TEXT,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS exercises (
            id                TEXT PRIMARY KEY,
            name              TEXT NOT NULL,
            body_part         TEXT,
            equipment         TEXT,
            target            TEXT,
            secondary_muscles TEXT,
            category          TEXT,
            description       TEXT,
            instructions      TEXT,
            demo_url          TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_ex_body_part ON exercises(body_part);
        CREATE INDEX IF NOT EXISTS idx_ex_equipment ON exercises(equipment);

        CREATE TABLE IF NOT EXISTS routines (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            name       TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_routines_user ON routines(user_id);

        CREATE TABLE IF NOT EXISTS routine_exercises (
            routine_id  INTEGER NOT NULL,
            exercise_id TEXT    NOT NULL,
            position    INTEGER NOT NULL,
            sets        INTEGER DEFAULT 3,
            reps        TEXT    DEFAULT '8-12',
            rest_sec    INTEGER DEFAULT 90,
            PRIMARY KEY (routine_id, exercise_id),
            FOREIGN KEY (routine_id)  REFERENCES routines(id)  ON DELETE CASCADE,
            FOREIGN KEY (exercise_id) REFERENCES exercises(id)
        );
        CREATE INDEX IF NOT EXISTS idx_re_routine ON routine_exercises(routine_id, position);

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );
    SQL);
}

function init_schema_mysql(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id            INT PRIMARY KEY AUTO_INCREMENT,
            email         VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name  VARCHAR(100),
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS exercises (
            id                VARCHAR(16)  PRIMARY KEY,
            name              VARCHAR(255) NOT NULL,
            body_part         VARCHAR(64),
            equipment         VARCHAR(64),
            target            VARCHAR(64),
            secondary_muscles TEXT,
            category          VARCHAR(64),
            description       TEXT,
            instructions      TEXT,
            demo_url          VARCHAR(512),
            INDEX idx_body_part (body_part),
            INDEX idx_equipment (equipment)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS routines (
            id         INT PRIMARY KEY AUTO_INCREMENT,
            user_id    INT NOT NULL,
            name       VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_routines_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS routine_exercises (
            routine_id  INT         NOT NULL,
            exercise_id VARCHAR(16) NOT NULL,
            position    INT         NOT NULL,
            sets        INT         DEFAULT 3,
            reps        VARCHAR(16) DEFAULT '8-12',
            rest_sec    INT         DEFAULT 90,
            PRIMARY KEY (routine_id, exercise_id),
            FOREIGN KEY (routine_id)  REFERENCES routines(id)  ON DELETE CASCADE,
            FOREIGN KEY (exercise_id) REFERENCES exercises(id),
            INDEX idx_routine (routine_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS settings (
            `key`  VARCHAR(64) PRIMARY KEY,
            value  TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
}