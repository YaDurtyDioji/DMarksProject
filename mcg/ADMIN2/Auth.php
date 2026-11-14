<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Logger.php';

class Auth {
    public static function hashPassword($password, &$salt = null) {
        $salt = bin2hex(random_bytes(16));
        return password_hash($salt . $password, PASSWORD_BCRYPT);
    }
    public static function verify($password, $hash, $salt) {
        return password_verify($salt . $password, $hash);
    }
    public static function login($u, $p) {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$u, $u]);
        $user = $stmt->fetch();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($user && self::verify($p, $user['password_hash'], $user['salt'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            Logger::login($user['id'], $ip, $ua, true);
            return true;
        }
        Logger::login($user ? $user['id'] : null, $ip, $ua, false);
        return false;
    }
    public static function createUser($username, $email, $password, $role = 'admin') {
        $hash = self::hashPassword($password, $salt);
        db()->prepare('INSERT INTO users (username,email,password_hash,salt,role) VALUES (?,?,?,?,?)')
            ->execute([$username, $email, $hash, $salt, $role]);
        return db()->lastInsertId();
    }
    public static function users() {
        return db()->query('SELECT id,username,email,role,created_at FROM users ORDER BY id')->fetchAll();
    }
    public static function deleteUser($id) {
        if (($_SESSION['role'] ?? '') !== 'super_admin') throw new Exception('Super admin only');
        if ($id == ($_SESSION['user_id'] ?? 0)) throw new Exception('Cannot delete self');
        db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
    public static function changePassword($id, $password) {
        $hash = self::hashPassword($password, $salt);
        db()->prepare('UPDATE users SET password_hash=?, salt=? WHERE id=?')->execute([$hash, $salt, $id]);
    }
    public static function changeRole($id, $role) {
        if (($_SESSION['role'] ?? '') !== 'super_admin') throw new Exception('Super admin only');
        if (!in_array($role, ['admin','super_admin'])) throw new Exception('Bad role');
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
    }
}