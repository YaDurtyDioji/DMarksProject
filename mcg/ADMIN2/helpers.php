<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function upload_dir() { return __DIR__ . '/uploads'; }
function upload_url($f) { return 'uploads/' . rawurlencode($f); }

function save_upload($key) {
    if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$key];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','mp4','webm','ogg','pdf'];
    if (!in_array($ext, $allowed)) throw new Exception('File type not allowed: ' . $ext);
    if ($f['size'] > 25 * 1024 * 1024) throw new Exception('File too large (max 25MB)');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir(upload_dir())) mkdir(upload_dir(), 0755, true);
    if (!move_uploaded_file($f['tmp_name'], upload_dir() . '/' . $name)) throw new Exception('Upload failed');
    return $name;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) { http_response_code(403); exit('CSRF failed'); }
}
function require_login() {
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
}
function require_super() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'super_admin') { http_response_code(403); exit('Super admin only'); }
}
function jexit($d) { header('Content-Type: application/json'); echo json_encode($d); exit; }
function jbody() { return json_decode(file_get_contents('php://input'), true) ?: []; }
function flash($m) { $_SESSION['flash'] = $m; }
function get_flash() { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }