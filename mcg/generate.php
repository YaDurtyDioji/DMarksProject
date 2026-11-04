<?php
/**
 * ============================================================
 * ADMIN2 – One-file PHP project generator
 * Run:  php generate.php
 * Creates ./ADMIN2/ with all files, folders, and public HTML.
 * ============================================================
 */
// Write files into a folder PHP can create itself.
$base = __DIR__ . '/ADMIN2';
if (!is_dir($base) && !mkdir($base, 0755, true)) {
    die("Cannot create $base — /mcg/ is not writable by PHP either.\n");
}
if (!is_writable($base)) {
    die("Cannot write to $base — try chmod 777 $base via File Manager.\n");
}
@mkdir($base . '/uploads', 0755, true);
@mkdir($base . '/assets', 0755, true);

$files = [];

/* ---------------- config.php ---------------- */
$files['config.php'] = <<<'EOT'
<?php
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'admin2',
    'db_user' => 'root',
    'db_pass' => '',
    'app_name' => 'Admin2 CMS',
    'recipients_api_url' => '', // optional: URL returning JSON array [{name,email,phone}]
];
EOT;

/* ---------------- db.php ---------------- */
$files['db.php'] = <<<'EOT'
<?php
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = require __DIR__ . '/config.php';
    $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
EOT;

/* ---------------- helpers.php ---------------- */
$files['helpers.php'] = <<<'EOT'
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
EOT;

/* ---------------- Logger.php ---------------- */
$files['Logger.php'] = <<<'EOT'
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
EOT;

/* ---------------- Auth.php ---------------- */
$files['Auth.php'] = <<<'EOT'
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
EOT;

/* ---------------- Services.php ---------------- */
$files['Services.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Services {
    public static function all() {
        return db()->query('SELECT * FROM services ORDER BY sort_order, id')->fetchAll();
    }
    public static function get($id) {
        $s = db()->prepare('SELECT * FROM services WHERE id=?');
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) return null;
        $row['images'] = self::images($id);
        $row['buttons'] = json_decode($row['buttons_json'] ?: '[]', true) ?: [];
        return $row;
    }
    public static function create($data) {
        db()->prepare('INSERT INTO services (menu_title,slug,content_html,video_url,buttons_json,sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$data['menu_title'], $data['slug'], $data['content_html'], $data['video_url'], json_encode($data['buttons'] ?? []), $data['sort_order'] ?? 0]);
        $id = db()->lastInsertId();
        Logger::edit('services', $id, 'create', $data['menu_title']);
        return $id;
    }
    public static function update($id, $data) {
        db()->prepare('UPDATE services SET menu_title=?, slug=?, content_html=?, video_url=?, buttons_json=?, sort_order=? WHERE id=?')
            ->execute([$data['menu_title'], $data['slug'], $data['content_html'], $data['video_url'], json_encode($data['buttons'] ?? []), $data['sort_order'] ?? 0, $id]);
        Logger::edit('services', $id, 'update', $data['menu_title']);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
        Logger::edit('services', $id, 'delete', '');
    }
    public static function addImage($serviceId, $fileField, $alt = '', $order = 0) {
        $name = save_upload($fileField);
        if (!$name) return null;
        db()->prepare('INSERT INTO service_images (service_id,image_path,alt_text,sort_order) VALUES (?,?,?,?)')
            ->execute([$serviceId, $name, $alt, $order]);
        $id = db()->lastInsertId();
        Logger::edit('service_images', $id, 'create', $name);
        return $id;
    }
    public static function deleteImage($id) {
        db()->prepare('DELETE FROM service_images WHERE id=?')->execute([$id]);
        Logger::edit('service_images', $id, 'delete', '');
    }
    public static function reorderImages($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE service_images SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function images($id) {
        $s = db()->prepare('SELECT * FROM service_images WHERE service_id=? ORDER BY sort_order, id');
        $s->execute([$id]);
        return $s->fetchAll();
    }
    public static function publicList() {
        $out = [];
        foreach (self::all() as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'menu_title' => $r['menu_title'],
                'slug' => $r['slug'],
                'content_html' => $r['content_html'],
                'video_url' => $r['video_url'],
                'buttons' => json_decode($r['buttons_json'] ?: '[]', true) ?: [],
                'images' => array_map(fn($i) => ['url' => upload_url($i['image_path']), 'alt' => $i['alt_text']], self::images($r['id'])),
            ];
        }
        return $out;
    }
}
EOT;

/* ---------------- Gallery.php ---------------- */
$files['Gallery.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Gallery {
    public static function categories() {
        return db()->query('SELECT * FROM gallery_categories ORDER BY sort_order, id')->fetchAll();
    }
    public static function createCategory($name) {
        db()->prepare('INSERT INTO gallery_categories (name,sort_order) VALUES (?,999)')->execute([$name]);
        $id = db()->lastInsertId();
        Logger::edit('gallery_categories', $id, 'create', $name);
        return $id;
    }
    public static function renameCategory($id, $name) {
        db()->prepare('UPDATE gallery_categories SET name=? WHERE id=?')->execute([$name, $id]);
        Logger::edit('gallery_categories', $id, 'update', $name);
    }
    public static function deleteCategory($id) {
        db()->prepare('DELETE FROM gallery_categories WHERE id=?')->execute([$id]);
        Logger::edit('gallery_categories', $id, 'delete', '');
    }
    public static function reorderCategories($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE gallery_categories SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function items($catId) {
        $s = db()->prepare('SELECT * FROM gallery_items WHERE category_id=? ORDER BY sort_order, id');
        $s->execute([$catId]);
        return $s->fetchAll();
    }
    public static function addItem($catId, $caption, $fileField) {
        $name = save_upload($fileField);
        if (!$name) throw new Exception('No file uploaded');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = in_array($ext, ['mp4','webm','ogg']) ? 'video' : 'image';
        db()->prepare('INSERT INTO gallery_items (category_id,media_type,file_path,caption,sort_order) VALUES (?,?,?,?,999)')
            ->execute([$catId, $type, $name, $caption]);
        $id = db()->lastInsertId();
        Logger::edit('gallery_items', $id, 'create', $name);
        return $id;
    }
    public static function updateItem($id, $caption) {
        db()->prepare('UPDATE gallery_items SET caption=? WHERE id=?')->execute([$caption, $id]);
        Logger::edit('gallery_items', $id, 'update', $caption);
    }
    public static function deleteItem($id) {
        db()->prepare('DELETE FROM gallery_items WHERE id=?')->execute([$id]);
        Logger::edit('gallery_items', $id, 'delete', '');
    }
    public static function reorderItems($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE gallery_items SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicGallery() {
        $out = [];
        foreach (self::categories() as $c) {
            $items = [];
            foreach (self::items($c['id']) as $i) {
                $items[] = ['id'=>(int)$i['id'], 'type'=>$i['media_type'], 'url'=>upload_url($i['file_path']), 'caption'=>$i['caption']];
            }
            $out[] = ['id'=>(int)$c['id'], 'name'=>$c['name'], 'items'=>$items];
        }
        return $out;
    }
}
EOT;

/* ---------------- Testimonials.php ---------------- */
$files['Testimonials.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Testimonials {
    public static function all() {
        return db()->query('SELECT * FROM testimonials ORDER BY sort_order, id')->fetchAll();
    }
    public static function create($author, $content, $fileField = null) {
        $img = $fileField ? save_upload($fileField) : null;
        db()->prepare('INSERT INTO testimonials (author,content,image_path,sort_order) VALUES (?,?,?,999)')
            ->execute([$author, $content, $img]);
        $id = db()->lastInsertId();
        Logger::edit('testimonials', $id, 'create', $author);
        return $id;
    }
    public static function update($id, $author, $content, $fileField = null) {
        $img = $fileField ? save_upload($fileField) : null;
        if ($img) {
            db()->prepare('UPDATE testimonials SET author=?, content=?, image_path=? WHERE id=?')->execute([$author, $content, $img, $id]);
        } else {
            db()->prepare('UPDATE testimonials SET author=?, content=? WHERE id=?')->execute([$author, $content, $id]);
        }
        Logger::edit('testimonials', $id, 'update', $author);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM testimonials WHERE id=?')->execute([$id]);
        Logger::edit('testimonials', $id, 'delete', '');
    }
    public static function reorder($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE testimonials SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicList() {
        return array_map(fn($t) => [
            'author' => $t['author'],
            'content' => $t['content'],
            'image' => $t['image_path'] ? upload_url($t['image_path']) : null,
        ], self::all());
    }
}
EOT;

/* ---------------- AboutPage.php ---------------- */
$files['AboutPage.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class AboutPage {
    public static function get() {
        $row = db()->query('SELECT * FROM about_page LIMIT 1')->fetch();
        if (!$row) {
            db()->exec("INSERT INTO about_page (content_html) VALUES ('')");
            $row = db()->query('SELECT * FROM about_page LIMIT 1')->fetch();
        }
        $row['media'] = db()->query('SELECT * FROM about_media ORDER BY sort_order, id')->fetchAll();
        return $row;
    }
    public static function update($content, $video) {
        $row = self::get();
        db()->prepare('UPDATE about_page SET content_html=?, video_url=? WHERE id=?')->execute([$content, $video, $row['id']]);
        Logger::edit('about_page', $row['id'], 'update', '');
    }
    public static function addMedia($fileField) {
        $name = save_upload($fileField);
        if (!$name) throw new Exception('No file');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = in_array($ext, ['mp4','webm','ogg']) ? 'video' : 'image';
        db()->prepare('INSERT INTO about_media (media_type,file_path) VALUES (?,?)')->execute([$type, $name]);
        Logger::edit('about_media', db()->lastInsertId(), 'create', $name);
    }
    public static function deleteMedia($id) {
        db()->prepare('DELETE FROM about_media WHERE id=?')->execute([$id]);
        Logger::edit('about_media', $id, 'delete', '');
    }
    public static function reorderMedia($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE about_media SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicData() {
        $row = self::get();
        return [
            'content_html' => $row['content_html'],
            'video_url' => $row['video_url'],
            'media' => array_map(fn($m) => ['type'=>$m['media_type'], 'url'=>upload_url($m['file_path'])], $row['media']),
        ];
    }
}
EOT;

/* ---------------- ContactPage.php ---------------- */
$files['ContactPage.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class ContactPage {
    public static function get() {
        $row = db()->query('SELECT * FROM contact_page LIMIT 1')->fetch();
        if (!$row) {
            db()->exec("INSERT INTO contact_page (content_html) VALUES ('')");
            $row = db()->query('SELECT * FROM contact_page LIMIT 1')->fetch();
        }
        return $row;
    }
    public static function update($content) {
        $row = self::get();
        db()->prepare('UPDATE contact_page SET content_html=? WHERE id=?')->execute([$content, $row['id']]);
        Logger::edit('contact_page', $row['id'], 'update', '');
    }
    public static function publicData() { $r = self::get(); return ['content_html' => $r['content_html']]; }
}
EOT;

/* ---------------- Messages.php ---------------- */
$files['Messages.php'] = <<<'EOT'
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
EOT;

/* ---------------- Recipients.php ---------------- */
$files['Recipients.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Logger.php';

class Recipients {
    public static function all() {
        return db()->query('SELECT * FROM recipients ORDER BY name, id')->fetchAll();
    }
    public static function create($name, $email, $phone) {
        db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)')->execute([$name, $email, $phone]);
        $id = db()->lastInsertId();
        Logger::edit('recipients', $id, 'create', $email);
        return $id;
    }
    public static function update($id, $name, $email, $phone) {
        db()->prepare('UPDATE recipients SET name=?,email=?,phone=? WHERE id=?')->execute([$name, $email, $phone, $id]);
        Logger::edit('recipients', $id, 'update', $email);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM recipients WHERE id=?')->execute([$id]);
        Logger::edit('recipients', $id, 'delete', '');
    }
    public static function importFromApi($url) {
        if (!$url) throw new Exception('No API URL configured');
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) throw new Exception('API did not return data');
        $data = json_decode($json, true);
        if (!is_array($data)) throw new Exception('API returned invalid JSON');
        $count = 0;
        $exists = db()->prepare('SELECT id FROM recipients WHERE email=?');
        $insert = db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)');
        foreach ($data as $r) {
            $name = $r['name'] ?? ''; $email = $r['email'] ?? ''; $phone = $r['phone'] ?? '';
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $exists->execute([$email]);
            if ($exists->fetch()) continue;
            $insert->execute([$name, $email, $phone]);
            $count++;
        }
        Logger::edit('recipients', 0, 'import', "$count rows from API");
        return $count;
    }
    public static function importCsv($fileField) {
        if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) throw new Exception('No file');
        $fh = fopen($_FILES[$fileField]['tmp_name'], 'r');
        $count = 0;
        $exists = db()->prepare('SELECT id FROM recipients WHERE email=?');
        $insert = db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)');
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 2) continue;
            $name = trim($row[0]); $email = trim($row[1]); $phone = trim($row[2] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $exists->execute([$email]);
            if ($exists->fetch()) continue;
            $insert->execute([$name, $email, $phone]);
            $count++;
        }
        fclose($fh);
        Logger::edit('recipients', 0, 'import', "$count rows from CSV");
        return $count;
    }
}
EOT;

/* ---------------- BulkEmail.php ---------------- */
$files['BulkEmail.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Recipients.php';

class BulkEmail {
    public static function all() {
        return db()->query('SELECT * FROM bulk_emails ORDER BY id DESC')->fetchAll();
    }
    public static function get($id) {
        $s = db()->prepare('SELECT * FROM bulk_emails WHERE id=?');
        $s->execute([$id]);
        $r = $s->fetch();
        if ($r) $r['buttons'] = json_decode($r['buttons_json'] ?: '[]', true) ?: [];
        return $r;
    }
    public static function create($subject, $body, $buttons) {
        db()->prepare('INSERT INTO bulk_emails (subject,body_html,buttons_json) VALUES (?,?,?)')
            ->execute([$subject, $body, json_encode($buttons)]);
        $id = db()->lastInsertId();
        Logger::edit('bulk_emails', $id, 'create', $subject);
        return $id;
    }
    public static function update($id, $subject, $body, $buttons) {
        db()->prepare('UPDATE bulk_emails SET subject=?, body_html=?, buttons_json=? WHERE id=?')
            ->execute([$subject, $body, json_encode($buttons), $id]);
        Logger::edit('bulk_emails', $id, 'update', $subject);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM bulk_emails WHERE id=?')->execute([$id]);
        Logger::edit('bulk_emails', $id, 'delete', '');
    }
    public static function send($id) {
        $email = self::get($id);
        if (!$email) throw new Exception('Not found');
        $recipients = Recipients::all();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: noreply@$host\r\n";
        $sent = 0;
        foreach ($recipients as $r) {
            $body = $email['body_html'];
            foreach ($email['buttons'] as $b) {
                $body .= '<p><a href="' . h($b['href']) . '" style="display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;margin:5px;">' . h($b['text']) . '</a></p>';
            }
            @mail($r['email'], $email['subject'], $body, $headers);
            $sent++;
        }
        db()->prepare('UPDATE bulk_emails SET sent_at=NOW() WHERE id=?')->execute([$id]);
        Logger::edit('bulk_emails', $id, 'send', "sent to $sent");
        return $sent;
    }
}
EOT;

/* ---------------- layout.php ---------------- */
$files['layout.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/helpers.php';
require_login();

function render_header($title) {
    $isSuper = ($_SESSION['role'] ?? '') === 'super_admin';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.h($title).' – Admin2</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
    echo '</head><body>';
    echo '<nav class="topnav"><a href="dashboard.php" class="brand">ADMIN2</a>';
    echo '<a href="services_list.php">Services</a>';
    echo '<a href="gallery_list.php">Gallery</a>';
    echo '<a href="testimonials_edit.php">Testimonials</a>';
    echo '<a href="about_edit.php">About</a>';
    echo '<a href="contact_edit.php">Contact Page</a>';
    echo '<a href="messages_list.php">Messages</a>';
    echo '<a href="recipients_list.php">Recipients</a>';
    echo '<a href="bulk_emails_list.php">Bulk Email</a>';
    echo '<a href="history.php">History</a>';
    if ($isSuper) echo '<a href="users.php">Users</a>';
    echo '<span class="spacer"></span>';
    echo '<span class="user">'.h($_SESSION['username'] ?? '').' ('.h($_SESSION['role'] ?? '').')</span>';
    echo '<a href="logout.php" class="btn-small">Logout</a></nav>';
    echo '<main class="container">';
    if ($m = get_flash()) echo '<div class="flash">'.h($m).'</div>';
}
function render_footer() { echo '</main></body></html>'; }
EOT;

/* ---------------- login.php ---------------- */
$files['login.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db.php';

$usersCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($usersCount === 0) {
            Auth::createUser($_POST['username'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', 'super_admin');
            flash('Super admin created. Please login.');
            header('Location: login.php'); exit;
        } else {
            if (Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
                header('Location: dashboard.php'); exit;
            }
            $error = 'Invalid credentials.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Login – Admin2</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page">
  <div class="login-box">
    <h1><?= $usersCount === 0 ? 'Create Super Admin' : 'Admin2 Login' ?></h1>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($m = get_flash()): ?><div class="flash"><?= h($m) ?></div><?php endif; ?>
    <form method="post">
      <label>Username</label>
      <input name="username" required autofocus>
      <?php if ($usersCount === 0): ?>
        <label>Email</label>
        <input name="email" type="email" required>
      <?php endif; ?>
      <label>Password</label>
      <input name="password" type="password" required>
      <button type="submit"><?= $usersCount === 0 ? 'Create' : 'Login' ?></button>
    </form>
  </div>
</body></html>
EOT;

/* ---------------- logout.php ---------------- */
$files['logout.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/helpers.php';
session_destroy();
header('Location: login.php');
EOT;

/* ---------------- dashboard.php ---------------- */
$files['dashboard.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';
require_once __DIR__ . '/Gallery.php';
require_once __DIR__ . '/Testimonials.php';
require_once __DIR__ . '/Messages.php';
require_once __DIR__ . '/Recipients.php';

render_header('Dashboard');
?>
<h1>Dashboard</h1>
<p>Welcome, <?= h($_SESSION['username']) ?>.</p>
<div class="cards">
  <a href="services_list.php" class="card"><h3>Services</h3><p><?= count(Services::all()) ?> items</p></a>
  <a href="gallery_list.php" class="card"><h3>Gallery</h3><p><?= count(Gallery::categories()) ?> categories</p></a>
  <a href="testimonials_edit.php" class="card"><h3>Testimonials</h3><p><?= count(Testimonials::all()) ?> items</p></a>
  <a href="messages_list.php" class="card"><h3>Messages</h3><p><?= count(Messages::all()) ?> new</p></a>
  <a href="recipients_list.php" class="card"><h3>Recipients</h3><p><?= count(Recipients::all()) ?> emails</p></a>
  <a href="bulk_emails_list.php" class="card"><h3>Bulk Email</h3><p>Send campaigns</p></a>
</div>
<?php render_footer(); ?>
EOT;

/* ---------------- users.php ---------------- */
$files['users.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Auth.php';
require_super();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['act'] ?? '') === 'create') {
            Auth::createUser($_POST['username'], $_POST['email'], $_POST['password'], $_POST['role'] ?? 'admin');
            flash('User created');
        } elseif (($_POST['act'] ?? '') === 'delete') {
            Auth::deleteUser((int)$_POST['id']); flash('User deleted');
        } elseif (($_POST['act'] ?? '') === 'role') {
            Auth::changeRole((int)$_POST['id'], $_POST['role']); flash('Role changed');
        } elseif (($_POST['act'] ?? '') === 'pwd') {
            Auth::changePassword((int)$_POST['id'], $_POST['password']); flash('Password reset');
        }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: users.php'); exit;
}
render_header('Users');
?>
<h1>User Management</h1>
<details class="panel"><summary>+ Add User</summary>
<form method="post" class="grid">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="act" value="create">
  <input name="username" placeholder="Username" required>
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Password" required>
  <select name="role"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select>
  <button>Create</button>
</form></details>
<table class="data">
<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
<?php foreach (Auth::users() as $u): ?>
<tr>
<td><?= (int)$u['id'] ?></td>
<td><?= h($u['username']) ?></td>
<td><?= h($u['email']) ?></td>
<td>
  <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
  <select name="role" onchange="this.form.submit()"><option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option><option value="super_admin" <?= $u['role']==='super_admin'?'selected':'' ?>>Super</option></select></form>
</td>
<td><?= h($u['created_at']) ?></td>
<td>
  <form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn-danger">Del</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="pwd"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input name="password" type="password" placeholder="New pwd" style="width:90px"><button class="btn-small">Set</button></form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
EOT;

/* ---------------- history.php ---------------- */
$files['history.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Logger.php';
render_header('History');
$tab = $_GET['tab'] ?? 'logins';
?>
<h1>History</h1>
<p><a href="?tab=logins" class="btn <?= $tab==='logins'?'btn-primary':'' ?>">Login Logs</a>
<a href="?tab=edits" class="btn <?= $tab==='edits'?'btn-primary':'' ?>">Edit Logs</a></p>
<?php if ($tab === 'logins'): ?>
<table class="data"><tr><th>ID</th><th>User</th><th>IP</th><th>Success</th><th>Agent</th><th>When</th></tr>
<?php foreach (Logger::loginLogs() as $l): ?>
<tr><td><?= (int)$l['id'] ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($l['ip']) ?></td><td><?= $l['success']?'✅':'❌' ?></td><td><small><?= h($l['user_agent']) ?></small></td><td><?= h($l['created_at']) ?></td></tr>
<?php endforeach; ?></table>
<?php else: ?>
<table class="data"><tr><th>ID</th><th>User</th><th>Table</th><th>Record</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr>
<?php foreach (Logger::editLogs() as $l): ?>
<tr><td><?= (int)$l['id'] ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($l['table_name']) ?></td><td><?= (int)$l['record_id'] ?></td><td><?= h($l['action']) ?></td><td><?= h($l['details']) ?></td><td><?= h($l['ip']) ?></td><td><?= h($l['created_at']) ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<?php render_footer(); ?>
EOT;

/* ---------------- services_list.php ---------------- */
$files['services_list.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';
render_header('Services');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
    csrf_check(); Services::delete((int)$_POST['id']); flash('Service deleted');
    header('Location: services_list.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reorder') {
    csrf_check(); Services::reorderImages($_POST['order'] ?? []);
    jexit(['ok' => 1]);
}
?>
<h1>Services</h1>
<p><a href="service_edit.php" class="btn btn-primary">+ New Service</a></p>
<table class="data"><tr><th>ID</th><th>Menu Title</th><th>Slug</th><th>Updated</th><th></th></tr>
<?php foreach (Services::all() as $s): ?>
<tr>
<td><?= (int)$s['id'] ?></td>
<td><?= h($s['menu_title']) ?></td>
<td><?= h($s['slug']) ?></td>
<td><?= h($s['updated_at']) ?></td>
<td>
  <a class="btn" href="service_edit.php?id=<?= (int)$s['id'] ?>">Edit</a>
  <form method="post" style="display:inline" onsubmit="return confirm('Delete service?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-danger">Delete</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
EOT;

/* ---------------- service_edit.php ---------------- */
$files['service_edit.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Services.php';

$id = (int)($_GET['id'] ?? 0);
$svc = $id ? Services::get($id) : ['menu_title'=>'','slug'=>'','content_html'=>'','video_url'=>'','buttons'=>[],'images'=>[],'sort_order'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? 'save';
    try {
        if ($act === 'save') {
            $data = [
                'menu_title' => $_POST['menu_title'],
                'slug' => preg_replace('/[^a-z0-9-]+/i', '-', strtolower($_POST['slug'] ?: $_POST['menu_title'])),
                'content_html' => $_POST['content_html'],
                'video_url' => $_POST['video_url'] ?? '',
                'buttons' => array_values(array_filter(array_map(fn($t,$h) => ['text'=>trim($t),'href'=>trim($h)], $_POST['btn_text'] ?? [], $_POST['btn_href'] ?? []), fn($b) => $b['text'] !== '')),
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($id) Services::update($id, $data); else $id = Services::create($data);
            flash('Saved');
            header('Location: service_edit.php?id='.$id); exit;
        } elseif ($act === 'add_img') {
            Services::addImage($id, 'image', $_POST['alt'] ?? '');
            flash('Image added'); header('Location: service_edit.php?id='.$id); exit;
        } elseif ($act === 'del_img') {
            Services::deleteImage((int)$_POST['img_id']);
            flash('Image deleted'); header('Location: service_edit.php?id='.$id); exit;
        }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: service_edit.php?id='.$id); exit;
}

render_header('Edit Service');
$btns = array_merge($svc['buttons'] ?? [], [['text'=>'','href'=>''], ['text'=>'','href'=>'']]);
?>
<h1><?= $id ? 'Edit' : 'New' ?> Service</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="save">
<label>Menu Title</label>
<input name="menu_title" value="<?= h($svc['menu_title']) ?>" required>
<label>Slug (URL)</label>
<input name="slug" value="<?= h($svc['slug']) ?>" placeholder="auto">
<label>Content (HTML allowed)</label>
<textarea name="content_html" rows="10"><?= h($svc['content_html']) ?></textarea>
<label>Video URL (YouTube embed or mp4 URL)</label>
<input name="video_url" value="<?= h($svc['video_url']) ?>">
<label>Buttons</label>
<div id="buttons">
<?php foreach ($btns as $b): ?>
<div class="row"><input name="btn_text[]" placeholder="Button text" value="<?= h($b['text']) ?>"><input name="btn_href[]" placeholder="https://link" value="<?= h($b['href']) ?>"></div>
<?php endforeach; ?>
</div>
<label>Sort Order</label><input name="sort_order" type="number" value="<?= (int)$svc['sort_order'] ?>">
<button class="btn btn-primary">Save</button>
</form>

<?php if ($id): ?>
<h2>Images (drag to reorder)</h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="add_img">
<input type="file" name="image" accept="image/*,video/mp4,video/webm" required>
<input name="alt" placeholder="Alt text">
<button>Upload</button>
</form>
<div id="imgsort" class="img-grid">
<?php foreach (Services::images($id) as $img): ?>
<div class="img-card" data-id="<?= (int)$img['id'] ?>">
  <img src="<?= h(upload_url($img['image_path'])) ?>" alt="">
  <div><?= h($img['alt_text']) ?></div>
  <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del_img"><input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('imgsort'), {
  animation: 150,
  onEnd: function() {
    var ids = Array.from(document.querySelectorAll('#imgsort .img-card')).map(e => +e.dataset.id);
    var fd = new FormData();
    fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','reorder');
    ids.forEach((id,i) => fd.append('order[]', id));
    fetch('service_edit.php?id=<?= $id ?>', {method:'POST', body:fd});
  }
});
</script>
<?php endif; ?>
<?php render_footer(); ?>
EOT;

/* ---------------- gallery_list.php ---------------- */
$files['gallery_list.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Gallery.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'cat_new') { Gallery::createCategory($_POST['name']); flash('Category added'); }
        if ($act === 'cat_rename') { Gallery::renameCategory((int)$_POST['id'], $_POST['name']); flash('Renamed'); }
        if ($act === 'cat_del') { Gallery::deleteCategory((int)$_POST['id']); flash('Category deleted'); }
        if ($act === 'cat_reorder') { Gallery::reorderCategories($_POST['order'] ?? []); jexit(['ok' => 1]); }
        if ($act === 'item_new') { Gallery::addItem((int)$_POST['cat_id'], $_POST['caption'] ?? '', 'file'); flash('Item uploaded'); }
        if ($act === 'item_caption') { Gallery::updateItem((int)$_POST['id'], $_POST['caption'] ?? ''); flash('Caption updated'); }
        if ($act === 'item_del') { Gallery::deleteItem((int)$_POST['id']); flash('Item deleted'); }
        if ($act === 'item_reorder') { Gallery::reorderItems($_POST['order'] ?? []); jexit(['ok' => 1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if (!in_array($act, ['cat_reorder','item_reorder'])) { header('Location: gallery_list.php'); exit; }
}
render_header('Gallery');
$cats = Gallery::categories();
?>
<h1>Gallery</h1>

<details class="panel"><summary>+ Add Category</summary>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_new">
<input name="name" placeholder="Category name" required> <button>Add</button></form>
</details>

<h2>Categories (drag to reorder)</h2>
<div id="catsort">
<?php foreach ($cats as $c): ?>
<div class="cat-row" data-id="<?= (int)$c['id'] ?>">
  <span class="handle">☰</span>
  <form method="post" style="display:flex;gap:6px;align-items:center;flex:1">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_rename"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <input name="name" value="<?= h($c['name']) ?>" style="flex:1"><button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete category and all items?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="cat_del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn-danger">Del</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('catsort'), {animation:150, handle:'.handle', onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#catsort .cat-row')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','cat_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('gallery_list.php', {method:'POST', body:fd});
}});
</script>

<?php foreach ($cats as $c): ?>
<h2>📁 <?= h($c['name']) ?></h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_new"><input type="hidden" name="cat_id" value="<?= (int)$c['id'] ?>">
<input type="file" name="file" accept="image/*,video/mp4,video/webm" required>
<input name="caption" placeholder="Caption">
<button>Upload</button>
</form>
<div class="img-grid" id="sort-<?= (int)$c['id'] ?>">
<?php foreach (Gallery::items($c['id']) as $it): ?>
<div class="img-card" data-id="<?= (int)$it['id'] ?>">
  <?php if ($it['media_type'] === 'video'): ?>
    <video src="<?= h(upload_url($it['file_path'])) ?>" controls></video>
  <?php else: ?>
    <img src="<?= h(upload_url($it['file_path'])) ?>" alt="">
  <?php endif; ?>
  <form method="post" style="display:flex;gap:4px;margin-top:4px">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_caption"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
    <input name="caption" value="<?= h($it['caption']) ?>" placeholder="Caption" style="width:100%"><button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="item_del"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('sort-<?= (int)$c['id'] ?>'), {animation:150, onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#sort-<?= (int)$c['id'] ?> .img-card')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','item_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('gallery_list.php', {method:'POST', body:fd});
}});
</script>
<?php endforeach; ?>
<?php render_footer(); ?>
EOT;

/* ---------------- testimonials_edit.php ---------------- */
$files['testimonials_edit.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Testimonials.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'new') { Testimonials::create($_POST['author'], $_POST['content'], 'image'); flash('Added'); }
        if ($act === 'upd') { Testimonials::update((int)$_POST['id'], $_POST['author'], $_POST['content'], 'image'); flash('Updated'); }
        if ($act === 'del') { Testimonials::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'order') { Testimonials::reorder($_POST['order'] ?? []); jexit(['ok'=>1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if ($act !== 'order') { header('Location: testimonials_edit.php'); exit; }
}
render_header('Testimonials');
?>
<h1>Testimonials</h1>
<details class="panel"><summary>+ New Testimonial</summary>
<form method="post" enctype="multipart/form-data" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="new">
<input name="author" placeholder="Author name" required>
<textarea name="content" placeholder="Testimonial text" required></textarea>
<input type="file" name="image" accept="image/*">
<button class="btn-primary">Add</button>
</form></details>

<div id="tsort" class="stack">
<?php foreach (Testimonials::all() as $t): ?>
<div class="card-edit" data-id="<?= (int)$t['id'] ?>">
  <span class="handle">☰</span>
  <form method="post" enctype="multipart/form-data" style="flex:1">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="upd"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <input name="author" value="<?= h($t['author']) ?>" placeholder="Author">
    <textarea name="content" rows="3"><?= h($t['content']) ?></textarea>
    <input type="file" name="image" accept="image/*">
    <?php if ($t['image_path']): ?><img src="<?= h(upload_url($t['image_path'])) ?>" style="max-height:60px"><?php endif; ?>
    <button class="btn-small">Save</button>
  </form>
  <form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn-danger">Del</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('tsort'), {animation:150, handle:'.handle', onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#tsort .card-edit')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','order');
  ids.forEach(id => fd.append('order[]', id));
  fetch('testimonials_edit.php', {method:'POST', body:fd});
}});
</script>
<?php render_footer(); ?>
EOT;

/* ---------------- about_edit.php ---------------- */
$files['about_edit.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/AboutPage.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'save') { AboutPage::update($_POST['content_html'], $_POST['video_url'] ?? ''); flash('Saved'); }
        if ($act === 'media_add') { AboutPage::addMedia('file'); flash('Media added'); }
        if ($act === 'media_del') { AboutPage::deleteMedia((int)$_POST['id']); flash('Media deleted'); }
        if ($act === 'media_reorder') { AboutPage::reorderMedia($_POST['order'] ?? []); jexit(['ok'=>1]); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    if ($act !== 'media_reorder') { header('Location: about_edit.php'); exit; }
}
$p = AboutPage::get();
render_header('About Page');
?>
<h1>Edit About Page</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="save">
<label>Content (HTML)</label>
<textarea name="content_html" rows="12"><?= h($p['content_html']) ?></textarea>
<label>Main Video URL</label>
<input name="video_url" value="<?= h($p['video_url']) ?>">
<button class="btn-primary">Save</button>
</form>

<h2>Images & Videos (drag to reorder)</h2>
<form method="post" enctype="multipart/form-data" class="grid">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="media_add">
<input type="file" name="file" accept="image/*,video/mp4,video/webm" required>
<button>Upload</button>
</form>
<div id="msort" class="img-grid">
<?php foreach (AboutPage::get()['media'] as $m): ?>
<div class="img-card" data-id="<?= (int)$m['id'] ?>">
  <?php if ($m['media_type'] === 'video'): ?><video src="<?= h(upload_url($m['file_path'])) ?>" controls></video>
  <?php else: ?><img src="<?= h(upload_url($m['file_path'])) ?>"><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="media_del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-danger">Remove</button></form>
</div>
<?php endforeach; ?>
</div>
<script>
Sortable.create(document.getElementById('msort'), {animation:150, onEnd:function(){
  var ids = Array.from(document.querySelectorAll('#msort .img-card')).map(e => +e.dataset.id);
  var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('act','media_reorder');
  ids.forEach(id => fd.append('order[]', id));
  fetch('about_edit.php', {method:'POST', body:fd});
}});
</script>
<?php render_footer(); ?>
EOT;

/* ---------------- contact_edit.php ---------------- */
$files['contact_edit.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/ContactPage.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    ContactPage::update($_POST['content_html'] ?? '');
    flash('Saved');
    header('Location: contact_edit.php'); exit;
}
$p = ContactPage::get();
render_header('Contact Page');
?>
<h1>Edit Contact Page</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
<label>Content (HTML) – include address, phone, map, etc.</label>
<textarea name="content_html" rows="14"><?= h($p['content_html']) ?></textarea>
<button class="btn-primary">Save</button>
</form>
<?php render_footer(); ?>
EOT;

/* ---------------- messages_list.php ---------------- */
$files['messages_list.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Messages.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'del') { Messages::delete((int)$_POST['id']); flash('Deleted'); }
    if ($act === 'spam') { Messages::markSpam((int)$_POST['id'], 1); flash('Marked spam'); }
    if ($act === 'ham') { Messages::markSpam((int)$_POST['id'], 0); flash('Marked ham'); }
    header('Location: messages_list.php'); exit;
}
$showSpam = isset($_GET['spam']);
render_header('Messages');
?>
<h1>Contact Messages</h1>
<p><a href="messages_list.php" class="btn <?= $showSpam?'':'btn-primary' ?>">Inbox</a>
<a href="messages_list.php?spam=1" class="btn <?= $showSpam?'btn-primary':'' ?>">Spam</a></p>
<table class="data">
<tr><th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>IP</th><th>When</th><th></th></tr>
<?php foreach (Messages::all($showSpam) as $m): ?>
<tr>
<td><?= (int)$m['id'] ?></td>
<td><?= h($m['name']) ?></td>
<td><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a></td>
<td><?= h($m['subject']) ?></td>
<td><small><?= nl2br(h($m['message'])) ?></small></td>
<td><?= h($m['ip']) ?></td>
<td><small><?= h($m['created_at']) ?></small></td>
<td>
<?php if ($showSpam): ?>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="ham"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-small">Not spam</button></form>
<?php else: ?>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="spam"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-small">Spam</button></form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn-danger">Del</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
EOT;

/* ---------------- recipients_list.php ---------------- */
$files['recipients_list.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/Recipients.php';
$cfg = require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'new') { Recipients::create($_POST['name'], $_POST['email'], $_POST['phone']); flash('Added'); }
        if ($act === 'upd') { Recipients::update((int)$_POST['id'], $_POST['name'], $_POST['email'], $_POST['phone']); flash('Updated'); }
        if ($act === 'del') { Recipients::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'api') { $n = Recipients::importFromApi($cfg['recipients_api_url']); flash("Imported $n"); }
        if ($act === 'csv') { $n = Recipients::importCsv('file'); flash("Imported $n"); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: recipients_list.php'); exit;
}
render_header('Recipients');
?>
<h1>Recipients</h1>
<details class="panel"><summary>+ Add manually</summary>
<form method="post" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="new">
<input name="name" placeholder="Name"><input name="email" type="email" placeholder="Email" required><input name="phone" placeholder="Phone">
<button>Add</button></form></details>
<details class="panel"><summary>Import</summary>
<form method="post" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="api">
<button>Import from API (<?= h($cfg['recipients_api_url'] ?: 'not configured') ?>)</button></form>
<form method="post" enctype="multipart/form-data" class="grid"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="csv">
<input type="file" name="file" accept=".csv" required><button>Import CSV (name,email,phone)</button></form>
</details>
<table class="data"><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th></th></tr>
<?php foreach (Recipients::all() as $r): ?>
<tr>
<td><?= (int)$r['id'] ?></td>
<td colspan="3">
<form method="post" class="grid" style="grid-template-columns:1fr 1fr 1fr auto">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="upd"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input name="name" value="<?= h($r['name']) ?>"><input name="email" value="<?= h($r['email']) ?>"><input name="phone" value="<?= h($r['phone']) ?>">
<button class="btn-small">Save</button></form>
</td>
<td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn-danger">Del</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
EOT;

/* ---------------- bulk_emails_list.php ---------------- */
$files['bulk_emails_list.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/BulkEmail.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'del') { BulkEmail::delete((int)$_POST['id']); flash('Deleted'); }
        if ($act === 'send') { $n = BulkEmail::send((int)$_POST['id']); flash("Sent to $n recipients"); }
    } catch (Exception $e) { flash('Error: '.$e->getMessage()); }
    header('Location: bulk_emails_list.php'); exit;
}
render_header('Bulk Email');
?>
<h1>Bulk Emails</h1>
<p><a href="bulk_email_edit.php" class="btn btn-primary">+ New Campaign</a></p>
<table class="data"><tr><th>ID</th><th>Subject</th><th>Created</th><th>Sent</th><th></th></tr>
<?php foreach (BulkEmail::all() as $e): ?>
<tr>
<td><?= (int)$e['id'] ?></td>
<td><?= h($e['subject']) ?></td>
<td><?= h($e['created_at']) ?></td>
<td><?= $e['sent_at'] ? h($e['sent_at']) : '—' ?></td>
<td>
<a class="btn" href="bulk_email_edit.php?id=<?= (int)$e['id'] ?>">Edit</a>
<form method="post" style="display:inline" onsubmit="return confirm('Send now to ALL recipients?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="send"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn-primary">Send</button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn-danger">Del</button></form>
</td></tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
EOT;

/* ---------------- bulk_email_edit.php ---------------- */
$files['bulk_email_edit.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/BulkEmail.php';
$id = (int)($_GET['id'] ?? 0);
$e = $id ? BulkEmail::get($id) : ['subject'=>'','body_html'=>'','buttons'=>[]];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $buttons = [];
        foreach (($_POST['btn_text'] ?? []) as $i => $t) {
            if (trim($t) !== '') $buttons[] = ['text'=>trim($t), 'href'=>trim($_POST['btn_href'][$i] ?? '')];
        }
        if ($id) BulkEmail::update($id, $_POST['subject'], $_POST['body_html'], $buttons);
        else $id = BulkEmail::create($_POST['subject'], $_POST['body_html'], $buttons);
        flash('Saved');
    } catch (Exception $ex) { flash('Error: '.$ex->getMessage()); }
    header('Location: bulk_email_edit.php?id='.$id); exit;
}
render_header('Bulk Email');
$btns = array_merge($e['buttons'] ?? [], [['text'=>'','href'=>''],['text'=>'','href'=>'']]);
?>
<h1><?= $id ? 'Edit' : 'New' ?> Bulk Email</h1>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
<label>Subject</label><input name="subject" value="<?= h($e['subject']) ?>" required>
<label>Body (HTML)</label><textarea name="body_html" rows="12"><?= h($e['body_html']) ?></textarea>
<label>Buttons</label>
<?php foreach ($btns as $b): ?>
<div class="row"><input name="btn_text[]" placeholder="Button text" value="<?= h($b['text']) ?>"><input name="btn_href[]" placeholder="https://link" value="<?= h($b['href']) ?>"></div>
<?php endforeach; ?>
<button class="btn-primary">Save Campaign</button>
</form>
<?php render_footer(); ?>
EOT;

/* ---------------- public API ---------------- */
$files['api.php'] = <<<'EOT'
<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Services.php';
require_once __DIR__ . '/Gallery.php';
require_once __DIR__ . '/Testimonials.php';
require_once __DIR__ . '/AboutPage.php';
require_once __DIR__ . '/ContactPage.php';
require_once __DIR__ . '/Messages.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'services':    jexit(Services::publicList());
        case 'gallery':     jexit(Gallery::publicGallery());
        case 'testimonials':jexit(Testimonials::publicList());
        case 'about':       jexit(AboutPage::publicData());
        case 'contact_page':jexit(ContactPage::publicData());
        case 'contact_submit':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false,'error'=>'POST only']);
            $in = jbody();
            if (!empty($in['website'])) jexit(['ok'=>true]); // honeypot
            if (empty($in['name']) || empty($in['email']) || empty($in['message'])) jexit(['ok'=>false,'error'=>'Missing fields']);
            Messages::create(
                substr($in['name'], 0, 255),
                substr($in['email'], 0, 255),
                substr($in['subject'] ?? '', 0, 255),
                substr($in['message'], 0, 5000)
            );
            jexit(['ok'=>true]);
    }
    jexit(['ok'=>false,'error'=>'Unknown action']);
} catch (Exception $e) {
    jexit(['ok'=>false,'error'=>$e->getMessage()]);
}
EOT;

/* ---------------- assets/style.css ---------------- */
$files['assets/style.css'] = <<<'EOT'
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f4f6f9;color:#222}
a{color:#007bff;text-decoration:none}
.topnav{display:flex;align-items:center;background:#212529;color:#fff;padding:0 16px;gap:14px;min-height:50px;flex-wrap:wrap}
.topnav a{color:#cfd8e3;font-size:14px;padding:8px 0}
.topnav a:hover{color:#fff}
.topnav .brand{font-weight:700;color:#fff;letter-spacing:1px;margin-right:12px}
.topnav .spacer{flex:1}
.topnav .user{color:#8be9fd;font-size:13px}
.container{max-width:1200px;margin:20px auto;padding:0 16px}
h1{margin:10px 0 16px}
.btn,.btn-small,.btn-danger,.btn-primary{display:inline-block;border:none;padding:6px 12px;border-radius:5px;background:#e2e6ea;color:#222;cursor:pointer;font-size:14px}
.btn-primary{background:#007bff;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-small{padding:4px 8px;font-size:12px}
.data{width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.data th,.data td{padding:8px 10px;border-bottom:1px solid #eee;text-align:left;font-size:14px;vertical-align:middle}
.data th{background:#f8f9fa;font-weight:600}
.panel{background:#fff;padding:14px;margin:10px 0;border-radius:6px}
.panel summary{cursor:pointer;font-weight:600}
.stack{display:flex;flex-direction:column;gap:8px}
.stack>label{font-weight:600;font-size:13px;margin-top:8px}
.stack input,.stack textarea,.stack select{padding:8px;border:1px solid #ccc;border-radius:4px;font-size:14px;width:100%}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;background:#fff;padding:14px;border-radius:6px;margin:10px 0}
.grid input,.grid textarea,.grid select{padding:8px;border:1px solid #ccc;border-radius:4px;font-size:14px}
.img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin:10px 0}
.img-card{background:#fff;padding:8px;border-radius:6px;border:1px solid #e2e6ea;cursor:move}
.img-card img,.img-card video{width:100%;height:120px;object-fit:cover;border-radius:4px;display:block}
.card-edit{display:flex;gap:10px;background:#fff;padding:10px;border-radius:6px;margin:6px 0;align-items:flex-start}
.card-edit form{display:flex;flex-direction:column;gap:6px;flex:1}
.handle{cursor:move;font-size:20px;padding:6px;color:#888;user-select:none}
.cat-row{display:flex;gap:8px;align-items:center;background:#fff;padding:8px;border-radius:6px;margin:4px 0}
.cat-row form{display:flex;gap:6px}
.flash{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:10px;border-radius:5px;margin:10px 0}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:10px;border-radius:5px;margin:10px 0}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);color:#222}
.card h3{margin:0 0 8px}
.login-page{display:flex;justify-content:center;align-items:center;min-height:100vh;background:#212529}
.login-box{background:#fff;padding:30px;border-radius:8px;width:340px;box-shadow:0 10px 30px rgba(0,0,0,.3)}
.login-box h1{margin-top:0;font-size:20px}
.login-box label{display:block;margin:12px 0 4px;font-weight:600;font-size:13px}
.login-box input{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
.login-box button{width:100%;margin-top:16px;padding:10px;background:#007bff;color:#fff;border:none;border-radius:5px;cursor:pointer}
.row{display:flex;gap:6px}
.row input{flex:1;padding:6px;border:1px solid #ccc;border-radius:4px}
EOT;

/* ---------------- Services.html (public) ---------------- */
$files['Services.html'] = <<<'EOT'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Services</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f4f6f9;color:#222}
header{background:#212529;color:#fff;padding:16px 24px}
header h1{margin:0;font-size:20px}
.layout{display:grid;grid-template-columns:240px 1fr;min-height:calc(100vh - 60px)}
nav#menu{background:#fff;border-right:1px solid #e2e6ea;padding:14px}
nav#menu a{display:block;padding:10px 12px;border-radius:6px;color:#333;text-decoration:none;margin-bottom:4px;font-size:14px}
nav#menu a:hover{background:#f1f3f5}
nav#menu a.active{background:#007bff;color:#fff}
main#content{padding:24px}
main#content h1{margin-top:0}
main#content img{max-width:100%;border-radius:6px;margin:8px 8px 8px 0;max-height:320px}
main#content video,main#content iframe{max-width:100%;border-radius:6px;margin:10px 0}
.buttons{margin-top:16px}
.buttons .btn{display:inline-block;padding:10px 18px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;margin:4px 8px 4px 0}
.buttons .btn:hover{background:#0056b3}
.gallery{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
.gallery img{width:auto;height:180px;object-fit:cover;margin:0}
@media(max-width:700px){.layout{grid-template-columns:1fr}nav#menu{border-right:none;border-bottom:1px solid #e2e6ea}}
</style>
</head>
<body>
<header><h1>Our Services</h1></header>
<div class="layout">
  <nav id="menu"></nav>
  <main id="content"><p>Loading…</p></main>
</div>
<script>
(function(){
  const menu = document.getElementById('menu');
  const content = document.getElementById('content');
  let active = null;

  function renderService(s) {
    let html = '<h1>' + escapeHtml(s.menu_title) + '</h1>';
    if (s.content_html) html += s.content_html;
    if (s.video_url) {
      const u = s.video_url;
      if (/youtube\.com|youtu\.be/i.test(u)) {
        const m = u.match(/(?:v=|youtu\.be\/)([\w-]{6,})/);
        if (m) html += '<iframe width="640" height="360" src="https://www.youtube.com/embed/' + m[1] + '" frameborder="0" allowfullscreen></iframe>';
      } else if (/vimeo\.com/i.test(u)) {
        const m = u.match(/vimeo\.com\/(\d+)/);
        if (m) html += '<iframe src="https://player.vimeo.com/video/' + m[1] + '" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
      } else {
        html += '<video src="' + escapeAttr(u) + '" controls></video>';
      }
    }
    if (s.images && s.images.length) {
      html += '<div class="gallery">';
      s.images.forEach(function(i){ html += '<img src="' + escapeAttr(i.url) + '" alt="' + escapeAttr(i.alt||'') + '">'; });
      html += '</div>';
    }
    if (s.buttons && s.buttons.length) {
      html += '<div class="buttons">';
      s.buttons.forEach(function(b){ html += '<a class="btn" href="' + escapeAttr(b.href) + '">' + escapeHtml(b.text) + '</a>'; });
      html += '</div>';
    }
    content.innerHTML = html;
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function escapeAttr(s){ return escapeHtml(s); }

  fetch('api.php?action=services')
    .then(function(r){ return r.json(); })
    .then(function(services){
      menu.innerHTML = '';
      if (!services.length) { content.innerHTML = '<p>No services yet.</p>'; return; }
      services.forEach(function(s){
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = s.menu_title;
        a.onclick = function(e){
          e.preventDefault();
          if (active) active.classList.remove('active');
          a.classList.add('active');
          active = a;
          renderService(s);
        };
        menu.appendChild(a);
      });
      const first = menu.querySelector('a');
      if (first) first.click();
    })
    .catch(function(err){ content.innerHTML = '<p>Error loading services: ' + err.message + '</p>'; });
})();
</script>
</body>
</html>
EOT;

/* ---------------- Gallery.html (public) ---------------- */
$files['Gallery.html'] = <<<'EOT'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gallery</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f4f6f9;color:#222}
header{background:#212529;color:#fff;padding:16px 24px}
header h1{margin:0;font-size:20px}
.toolbar{padding:14px 24px;display:flex;flex-wrap:wrap;gap:8px}
.toolbar button{padding:8px 14px;border:1px solid #ccc;background:#fff;border-radius:20px;cursor:pointer;font-size:14px}
.toolbar button.active{background:#007bff;color:#fff;border-color:#007bff}
#grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;padding:0 24px 40px}
.item{background:#fff;border-radius:8px;overflow:hidden;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:transform .15s}
.item:hover{transform:translateY(-2px)}
.item img,.item video{width:100%;height:180px;object-fit:cover;display:block}
.cap{padding:8px 10px;font-size:13px;color:#555}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:20px;z-index:10}
.modal img,.modal video{max-width:92vw;max-height:88vh;border-radius:6px}
.close{position:fixed;top:16px;right:20px;color:#fff;font-size:32px;cursor:pointer;user-select:none}
</style>
</head>
<body>
<header><h1>Gallery</h1></header>
<div class="toolbar" id="cats"></div>
<div id="grid"></div>
<div class="modal" id="modal" onclick="closeModal()">
  <span class="close" onclick="closeModal()">×</span>
  <img id="modalImg" style="display:none">
  <video id="modalVid" controls style="display:none"></video>
</div>
<script>
(function(){
  const catsEl = document.getElementById('cats');
  const grid = document.getElementById('grid');
  const modal = document.getElementById('modal');
  const modalImg = document.getElementById('modalImg');
  const modalVid = document.getElementById('modalVid');
  let data = [];

  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  function makeTile(item){
    const div = document.createElement('div');
    div.className = 'item';
    if (item.type === 'video') {
      const v = document.createElement('video');
      v.src = item.url;
      v.muted = true;
      v.preload = 'metadata';
      div.appendChild(v);
    } else {
      const img = document.createElement('img');
      img.src = item.url;
      img.alt = item.caption || '';
      img.loading = 'lazy';
      div.appendChild(img);
    }
    if (item.caption) {
      const c = document.createElement('div');
      c.className = 'cap';
      c.textContent = item.caption;
      div.appendChild(c);
    }
    div.onclick = function(){ openModal(item); };
    return div;
  }

  function render(items){
    grid.innerHTML = '';
    if (!items.length){ grid.innerHTML = '<p style="padding:20px">No items.</p>'; return; }
    items.forEach(function(i){ grid.appendChild(makeTile(i)); });
  }

  function renderAll(){
    const all = [];
    data.forEach(function(c){ c.items.forEach(function(i){ all.push(i); }); });
    render(all);
  }

  function renderCat(cat){ render(cat.items); }

  function setActive(btn){
    Array.from(catsEl.children).forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
  }

  function openModal(item){
    if (item.type === 'video'){
      modalImg.style.display = 'none';
      modalVid.style.display = 'block';
      modalVid.src = item.url;
      modalVid.play();
    } else {
      modalVid.style.display = 'none';
      modalVid.pause();
      modalImg.style.display = 'block';
      modalImg.src = item.url;
    }
    modal.style.display = 'flex';
  }
  window.closeModal = function(){
    modal.style.display = 'none';
    modalVid.pause();
  };

  fetch('api.php?action=gallery')
    .then(function(r){ return r.json(); })
    .then(function(json){
      data = json || [];
      const allBtn = document.createElement('button');
      allBtn.textContent = 'All';
      allBtn.onclick = function(){ setActive(allBtn); renderAll(); };
      catsEl.appendChild(allBtn);
      data.forEach(function(c){
        const b = document.createElement('button');
        b.textContent = c.name;
        b.onclick = function(){ setActive(b); renderCat(c); };
        catsEl.appendChild(b);
      });
      setActive(allBtn);
      renderAll();
    })
    .catch(function(err){ grid.innerHTML = '<p style="padding:20px">Error: ' + err.message + '</p>'; });
})();
</script>
</body>
</html>
EOT;

$fail = 0;
foreach ($files as $path => $content) {
    $full = $base . '/' . $path;
    @mkdir(dirname($full), 0755, true);
    $n = @file_put_contents($full, $content);
    if ($n === false) { echo "FAILED : $path\n"; $fail++; }
    else              { echo "Created: $path ($n bytes)\n"; }
}
if ($fail) { echo "\n$fail file(s) failed.\n"; exit(1); }
echo "\nDone — " . count($files) . " files in $base\n";