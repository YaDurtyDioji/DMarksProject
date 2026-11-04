<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Logger.php';

class Messages {
    public static function all($includeSpam = false) {
        $sql = 'SELECT * FROM contact_messages' . ($includeSpam ? '' : ' WHERE is_spam=0') . ' ORDER BY id DESC LIMIT 500';
        return db()->query($sql)->fetchAll();
    }
    public static function isSpam($name, $email, $subject, $message, $ip) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
        if (strlen(trim($message)) < 5) return true;
        $bad = ['viagra','casino','lottery','xxx','bitcoin','prize winner','free money','click here now','buy now'];
        $txt = strtolower($name.' '.$subject.' '.$message);
        foreach ($bad as $w) if (strpos($txt, $w) !== false) return true;
        if (preg_match_all('/https?:\/\//i', $message, $m) && count($m[0]) > 3) return true;
        $r = db()->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip=? AND created_at > NOW() - INTERVAL 1 MINUTE');
        $r->execute([$ip]);
        if ((int)$r->fetchColumn() > 5) return true;
        return false;
    }
    public static function create($name, $email, $subject, $message) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $spam = self::isSpam($name, $email, $subject, $message, $ip) ? 1 : 0;
        db()->prepare('INSERT INTO contact_messages (name,email,subject,message,ip,is_spam) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, $subject, $message, $ip, $spam]);
        return db()->lastInsertId();
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
        Logger::edit('contact_messages', $id, 'delete', '');
    }
    public static function markSpam($id, $val) {
        db()->prepare('UPDATE contact_messages SET is_spam=? WHERE id=?')->execute([$val ? 1 : 0, $id]);
        Logger::edit('contact_messages', $id, 'update', 'spam=' . ($val ? 1 : 0));
    }
}