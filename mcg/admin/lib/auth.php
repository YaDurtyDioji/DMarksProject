<?php
/**
 * Admin authentication, sessions and CSRF.
 */

declare(strict_types=1);

function mcg_users(): array
{
    $users = mcg_read_data('users', []);
    return is_array($users) ? $users : [];
}

function mcg_find_user(string $username)
{
    foreach (mcg_users() as $user) {
        if (isset($user['username']) && hash_equals((string) $user['username'], $username)) {
            return $user;
        }
    }
    return null;
}

function mcg_create_user(string $username, string $password): array
{
    $username = trim($username);
    if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username)) {
        return ['ok' => false, 'error' => 'Use 3–40 letters, numbers, dots, dashes or underscores.'];
    }
    if (strlen($password) < 10) {
        return ['ok' => false, 'error' => 'The password must be at least 10 characters.'];
    }
    $users = mcg_users();
    if (mcg_find_user($username) !== null) {
        return ['ok' => false, 'error' => 'That username already exists.'];
    }
    $users[] = [
        'id' => mcg_next_id($users),
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created' => mcg_now(),
        'last_login' => null,
    ];
    if (!mcg_write_data('users', $users)) {
        return ['ok' => false, 'error' => 'Could not save the admin account.'];
    }
    return ['ok' => true, 'error' => ''];
}

function mcg_update_password(int $userId, string $current, string $new): array
{
    if (strlen($new) < 10) {
        return ['ok' => false, 'error' => 'The new password must be at least 10 characters.'];
    }
    $users = mcg_users();
    foreach ($users as $index => $user) {
        if ((int) $user['id'] === $userId) {
            if (!password_verify($current, (string) $user['password_hash'])) {
                return ['ok' => false, 'error' => 'The current password is incorrect.'];
            }
            $users[$index]['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            $users[$index]['password_changed'] = mcg_now();
            return mcg_write_data('users', $users)
                ? ['ok' => true, 'error' => '']
                : ['ok' => false, 'error' => 'Could not save the new password.'];
        }
    }
    return ['ok' => false, 'error' => 'Admin account not found.'];
}

function mcg_login_user(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
    ];
    $_SESSION['admin_last_seen'] = time();
    $users = mcg_users();
    foreach ($users as $index => $stored) {
        if ((int) $stored['id'] === (int) $user['id']) {
            $users[$index]['last_login'] = mcg_now();
            break;
        }
    }
    mcg_write_data('users', $users);
}

function mcg_current_user()
{
    if (empty($_SESSION['admin_user'])) {
        return null;
    }
    if (!empty($_SESSION['admin_last_seen']) && (time() - (int) $_SESSION['admin_last_seen']) > 7200) {
        mcg_logout_user();
        return null;
    }
    $_SESSION['admin_last_seen'] = time();
    return $_SESSION['admin_user'];
}

function mcg_logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function mcg_require_auth(): void
{
    if (mcg_current_user() === null) {
        mcg_flash('error', 'Please sign in to continue.');
        header('Location: login.php');
        exit;
    }
    if (mcg_users() === []) {
        header('Location: login.php');
        exit;
    }
}

function mcg_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function mcg_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . mcg_e(mcg_csrf_token()) . '">';
}

function mcg_check_csrf(): void
{
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($token === '' || !hash_equals(mcg_csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function mcg_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flashes']) || !is_array($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function mcg_take_flashes(): array
{
    $flashes = isset($_SESSION['flashes']) && is_array($_SESSION['flashes']) ? $_SESSION['flashes'] : [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function mcg_require_post(): void
{
    if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

function mcg_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function mcg_review_pending_count(): int
{
    $count = 0;
    foreach (mcg_read_data('reviews', []) as $review) {
        if (empty($review['approved'])) {
            $count++;
        }
    }
    return $count;
}

function mcg_subscriber_count(): int
{
    $count = 0;
    foreach (mcg_read_data('subscribers', []) as $subscriber) {
        if (!empty($subscriber['active'])) {
            $count++;
        }
    }
    return $count;
}
