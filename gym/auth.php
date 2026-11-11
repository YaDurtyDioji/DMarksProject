<?php
declare(strict_types=1);

/**
 * All hashing goes through password_hash($pwd, PASSWORD_DEFAULT).
 * PASSWORD_DEFAULT is bcrypt: each call generates a fresh random salt
 * and embeds it in the resulting 60-char hash ("$2y$10$<22 salt><31 hash>").
 * That is already "hashing with salt" — never do it manually.
 */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $cfg = require __DIR__ . '/config.php';

    session_name($cfg['session']['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $cfg['session']['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---- user lookup -------------------------------------------------------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;

    static $cached = null;
    if ($cached !== null && (int)$cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }

    $stmt = get_db()->prepare('SELECT id, email, display_name FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    return $cached = ($row ?: null);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
    return $user;
}

function require_guest(): void
{
    if (current_user()) {
        header('Location: index.php');
        exit;
    }
}

/* ---- login / register --------------------------------------------------- */

function attempt_login(string $email, string $password): bool
{
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        // Constant-time decoy to avoid user-enumeration via timing.
        password_verify($password, '$2y$10$' . str_repeat('a', 53));
        return false;
    }

    if (!password_verify($password, $row['password_hash'])) {
        return false;
    }

    // Re-hash if PHP's default algorithm gets upgraded over time.
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $new = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$new, $row['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    return true;
}

function register_user(string $email, string $password, string $displayName): array
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid email address.'];
    }
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters.'];
    }

    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        return [false, 'That email is already registered.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)'
    );
    $stmt->execute([$email, $hash, $displayName ?: null]);
    $id = (int) $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;

    return [true, null];
}

/* ---- CSRF --------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): void
{
    if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        exit('CSRF token mismatch.');
    }
}