<?php
require_once __DIR__ . '/db.php';

class Logger {
    public static function login($userId, $ip, $ua, $success) {
        db()->prepare('INSERT INTO login_logs (user_id,ip,user_agent,success) VALUES (?,?,?,?)')
            ->execute([$userId, $ip, substr((string)$ua, 0, 255), $success ? 1 : 0]);
    }
    public static function edit($table, $recordId, $action, $details = '') {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        db()->prepare('INSERT INTO edit_logs (user_id,table_name,record_id,action,details,ip) VALUES (?,?,?,?,?,?)')
            ->execute([$userId, $table, $recordId, $action, $details, $ip]);
    }
    public static function loginLogs() {
        return db()->query('SELECT l.*, u.username FROM login_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 500')->fetchAll();
    }
    public static function editLogs() {
        return db()->query('SELECT e.*, u.username FROM edit_logs e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.id DESC LIMIT 500')->fetchAll();
    }
}