<?php
/**
 * Run this file (via browser OR CLI) to generate admin-project.zip
 * Requirements: PHP 7.4+ with ZipArchive, PDO, fileinfo.
 */
error_reporting(E_ALL);

$files = [];

// ======================================================================
$files['admin-project/config.php'] = ltrim(<<<'EOT'
<?php
// ====== EDIT THESE BEFORE RUNNING install.php ======
define('DB_HOST', 'localhost');
define('DB_NAME', 'service_admin');
define('DB_USER', 'root');
define('DB_PASS', '');

// Upload limits
define('UPLOAD_BASE',      __DIR__ . '/uploads');   // filesystem path
define('UPLOAD_URL_BASE',  'uploads');               // web path from app root
define('MAX_UPLOAD_SIZE',  5 * 1024 * 1024);         // 5 MB
EOT, "\n");

// ======================================================================
$files['admin-project/db.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}
EOT, "\n");

// ======================================================================
$files['admin-project/auth.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function current_admin(): ?array { return $_SESSION['admin'] ?? null; }

function require_login(): void {
    if (!current_admin()) { header('Location: index.php'); exit; }
}

function require_super(): void {
    require_login();
    if (empty(current_admin()['is_super'])) { header('Location: dashboard.php'); exit; }
}

function client_ip(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

function log_history(?int $admin_id, string $action_type, string $description): void {
    try {
        $stmt = db()->prepare(
            "INSERT INTO history (admin_id, action_type, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$admin_id, $action_type, $description, client_ip()]);
    } catch (Throwable $e) { /* never break the request for a log */ }
}

function try_login(string $username, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin) return false;

    // Salt column is a per-user pepper prepended before bcrypt
    if (!password_verify($admin['salt'] . $password, $admin['password_hash'])) return false;

    $_SESSION['admin'] = [
        'id'       => (int)$admin['id'],
        'username' => $admin['username'],
        'is_super' => (int)$admin['is_super'],
    ];
    log_history((int)$admin['id'], 'login', 'Logged in');
    return true;
}
EOT, "\n");

// ======================================================================
$files['admin-project/functions.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';

function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
function render_flash(): void {
    $f = get_flash();
    if ($f) echo '<div class="flash ' . h($f['type']) . '">' . h($f['msg']) . '</div>';
}

/**
 * Handle a file upload.
 * Returns ['path' => 'uploads/...'] on success,
 *         ['error' => '...'] on failure,
 *         null if no file was sent.
 */
function handle_upload(string $field, string $subdir): ?array {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return ['error' => 'Upload error code ' . $f['error']];
    if ($f['size'] > MAX_UPLOAD_SIZE)  return ['error' => 'File too large'];

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true))
        return ['error' => 'Only jpg, png, gif, webp allowed'];

    $dir = UPLOAD_BASE . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir))
        return ['error' => 'Cannot create upload folder'];

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name))
        return ['error' => 'Could not move file'];

    return ['path' => UPLOAD_URL_BASE . '/' . $subdir . '/' . $name];
}

function render_admin_header(string $title, string $active = ''): void {
    require_login();
    $a = current_admin();
    $nav = [
        'dashboard.php' => 'Dashboard',
        'services.php'  => 'Services',
        'gallery.php'   => 'Gallery',
        'admins.php'    => 'Admins',
        'history.php'   => 'History',
    ];
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>' . h($title) . ' — Admin</title>';
    echo '<link rel="stylesheet" href="assets/admin.css"></head><body>';
    echo '<div class="topbar"><div class="brand">Service Admin</div><nav>';
    foreach ($nav as $url => $label) {
        if ($url === 'admins.php' && empty($a['is_super'])) continue;
        $cls = $active === $url ? ' class="active"' : '';
        echo '<a href="' . $url . '"' . $cls . '>' . h($label) . '</a>';
    }
    echo '<span class="user">' . h($a['username']) . ' · ' . ($a['is_super'] ? 'Super Admin' : 'Admin') . '</span>';
    echo '<a href="logout.php" class="logout">Logout</a>';
    echo '</nav></div><div class="container">';
    render_flash();
}

function render_admin_footer(): void {
    echo '</div></body></html>';
}
EOT, "\n");

// ======================================================================
$files['admin-project/install.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('<h1>Connection failed</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");

$pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    salt VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_super TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    menu_label VARCHAR(128) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS service_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    block_type ENUM('text','image','button') NOT NULL,
    content TEXT NULL,
    image_path VARCHAR(255) NULL,
    link VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_block_service FOREIGN KEY (service_id)
        REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS gallery_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS gallery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_item_category FOREIGN KEY (category_id)
        REFERENCES gallery_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action_type VARCHAR(32) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(64) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// First super admin
$count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($count === 0) {
    $salt = bin2hex(random_bytes(16));
    $hash = password_hash($salt . 'admin123', PASSWORD_BCRYPT);
    $ins  = $pdo->prepare("INSERT INTO admins (username, salt, password_hash, is_super, created_at) VALUES (?,?,?,1,NOW())");
    $ins->execute(['admin', $salt, $hash]);
    echo "<p><b>Default super admin:</b> admin / admin123</p>";
}

// Sample gallery categories
$count = (int)$pdo->query("SELECT COUNT(*) FROM gallery_categories")->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO gallery_categories (name, sort_order) VALUES ('General', 0), ('Featured', 1)");
}

// Sample service (helps first login)
$count = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO services (slug, title, menu_label, sort_order, created_at, updated_at)
                VALUES ('welcome', 'Welcome', 'Welcome', 0, NOW(), NOW())");
    $sid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO service_blocks (service_id, block_type, content, sort_order) VALUES (?,?,?,?)");
    $ins->execute([$sid, 'text', "This is the Welcome service. Edit or delete it from the admin panel.", 0]);
}

echo '<h1>Installation complete ✅</h1>';
echo '<p><a href="index.php">Go to login →</a></p>';
echo '<p style="color:#a00"><b>Delete install.php after installation.</b></p>';
EOT, "\n");

// ======================================================================
$files['admin-project/index.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/auth.php';
if (current_admin()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || $p === '') {
        $error = 'Please enter username and password.';
    } elseif (try_login($u, $p)) {
        header('Location: dashboard.php'); exit;
    } else {
        $error = 'Invalid username or password.';
        log_history(null, 'login_failed', "Failed login for username: $u");
    }
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Admin Login</title>
<link rel="stylesheet" href="assets/admin.css"></head>
<body class="login-page">
<div class="login-box">
  <h1>Admin Login</h1>
  <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Username <input type="text" name="username" autofocus required></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit">Login</button>
  </form>
</div>
</body></html>
EOT, "\n");

// ======================================================================
$files['admin-project/logout.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/auth.php';
if (current_admin()) log_history((int)current_admin()['id'], 'logout', 'Logged out');
session_destroy();
header('Location: index.php');
EOT, "\n");

// ======================================================================
$files['admin-project/dashboard.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
$pdo = db();
$servicesCount = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$galleryCount  = (int)$pdo->query("SELECT COUNT(*) FROM gallery_items")->fetchColumn();
$adminCount    = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
render_admin_header('Dashboard', 'dashboard.php');
?>
<h1>Dashboard</h1>
<div class="cards">
  <div class="card"><div class="num"><?= $servicesCount ?></div><div>Services</div><a href="services.php">Manage →</a></div>
  <div class="card"><div class="num"><?= $galleryCount ?></div><div>Gallery Images</div><a href="gallery.php">Manage →</a></div>
  <div class="card"><div class="num"><?= $adminCount ?></div><div>Admins</div>
    <?php if (!empty(current_admin()['is_super'])): ?><a href="admins.php">Manage →</a><?php endif; ?>
  </div>
</div>
<div class="panel">
  <h2>Public pages</h2>
  <p>These pull their content live from the database via the JSON API:</p>
  <p><a class="btn" href="Services.html" target="_blank">Services.html</a>
     <a class="btn" href="Gallery.html" target="_blank">Gallery.html</a></p>
</div>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/services.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo = db();
$me  = current_admin();

/* -------- actions -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $slug  = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $menu  = trim($_POST['menu_label'] ?? '');
        if ($slug && $title && $menu) {
            try {
                $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM services")->fetchColumn();
                $stmt = $pdo->prepare(
                    "INSERT INTO services (slug, title, menu_label, sort_order, created_at, updated_at)
                     VALUES (?,?,?,?,NOW(),NOW())");
                $stmt->execute([$slug, $title, $menu, $max + 1]);
                log_history((int)$me['id'], 'edit', "Added service '$title'");
                flash('Service added.');
            } catch (Exception $e) {
                flash('That slug is already in use.', 'error');
            }
        } else flash('All fields are required.', 'error');
        header('Location: services.php'); exit;
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT title FROM services WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        $title = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM services WHERE id=?")->execute([(int)$_POST['id']]);
        log_history((int)$me['id'], 'edit', "Deleted service '$title'");
        flash('Service deleted.'); header('Location: services.php'); exit;
    }

    if ($action === 'move' && !empty($_POST['id']) && !empty($_POST['dir'])) {
        move_service((int)$_POST['id'], $_POST['dir']);
        header('Location: services.php'); exit;
    }
}

function move_service(int $id, string $dir): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT sort_order FROM services WHERE id=?");
    $stmt->execute([$id]);
    $cur = $stmt->fetch();
    if (!$cur) return;
    $op = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("SELECT id, sort_order FROM services WHERE sort_order $op ? ORDER BY sort_order $ord LIMIT 1");
    $stmt->execute([$cur['sort_order']]);
    $other = $stmt->fetch();
    if (!$other) return;
    $pdo->prepare("UPDATE services SET sort_order=? WHERE id=?")->execute([$other['sort_order'], $id]);
    $pdo->prepare("UPDATE services SET sort_order=? WHERE id=?")->execute([$cur['sort_order'], $other['id']]);
}

$services = $pdo->query(
    "SELECT s.*, (SELECT COUNT(*) FROM service_blocks WHERE service_id=s.id) AS block_count
     FROM services s ORDER BY sort_order, id")->fetchAll();
render_admin_header('Services', 'services.php');
?>
<h1>Services</h1>
<p>Every service appears in the menu of the public <code>Services.html</code> page.</p>

<div class="panel">
  <h2>Add New Service</h2>
  <form method="post" class="inline-form">
    <input type="hidden" name="action" value="add">
    <label>Slug (URL id)<input type="text" name="slug" placeholder="web-design" required></label>
    <label>Menu Label<input type="text" name="menu_label" placeholder="Web Design" required></label>
    <label>Page Title<input type="text" name="title" placeholder="Professional Web Design" required></label>
    <button type="submit">Add Service</button>
  </form>
</div>

<table class="table">
<thead><tr><th>Order</th><th>Menu</th><th>Slug</th><th>Title</th><th>Blocks</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($services as $s): ?>
<tr>
  <td class="order">
    <form method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="dir" value="up"><button class="mini">▲</button></form>
    <form method="post"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="dir" value="down"><button class="mini">▼</button></form>
  </td>
  <td><?= h($s['menu_label']) ?></td>
  <td><code><?= h($s['slug']) ?></code></td>
  <td><?= h($s['title']) ?></td>
  <td><?= (int)$s['block_count'] ?></td>
  <td>
    <a class="btn" href="service_edit.php?id=<?= $s['id'] ?>">Edit</a>
    <form method="post" onsubmit="return confirm('Delete this service?')">
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
      <button class="btn danger">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($services)): ?><tr><td colspan="6">No services yet.</td></tr><?php endif; ?>
</tbody></table>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/service_edit.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo = db();
$me  = current_admin();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
$stmt->execute([$id]);
$service = $stmt->fetch();
if (!$service) { flash('Service not found.', 'error'); header('Location: services.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $title = trim($_POST['title'] ?? '');
        $menu  = trim($_POST['menu_label'] ?? '');
        if ($title && $menu) {
            $pdo->prepare("UPDATE services SET title=?, menu_label=?, updated_at=NOW() WHERE id=?")
                ->execute([$title, $menu, $id]);
            log_history((int)$me['id'], 'edit', "Updated service '$title' info");
            flash('Service info saved.');
        } else flash('Title and menu label are required.', 'error');
        header("Location: service_edit.php?id=$id"); exit;
    }

    if ($action === 'add_block') {
        $type = $_POST['block_type'] ?? 'text';
        $content = ''; $link = ''; $imagePath = '';
        if ($type === 'text') {
            $content = trim($_POST['block_text'] ?? '');
        } elseif ($type === 'button') {
            $content = trim($_POST['block_button_text'] ?? '');
            $link    = trim($_POST['block_link'] ?? '');
        } elseif ($type === 'image') {
            $content = trim($_POST['block_caption'] ?? '');
            $up = handle_upload('image_file', 'services');
            if (!$up || isset($up['error'])) {
                flash('Image upload failed: ' . ($up['error'] ?? 'no file'), 'error');
                header("Location: service_edit.php?id=$id"); exit;
            }
            $imagePath = $up['path'];
        } else { flash('Invalid block type.', 'error'); header("Location: service_edit.php?id=$id"); exit; }

        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM service_blocks WHERE service_id=$id")->fetchColumn();
        $pdo->prepare("INSERT INTO service_blocks (service_id, block_type, content, image_path, link, sort_order)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$id, $type, $content, $imagePath, $link, $max + 1]);
        log_history((int)$me['id'], 'edit', "Added $type block to '{$service['title']}'");
        flash(ucfirst($type) . ' block added.');
        header("Location: service_edit.php?id=$id"); exit;
    }

    if ($action === 'update_block' && !empty($_POST['block_id'])) {
        $bid     = (int)$_POST['block_id'];
        $content = trim($_POST['content'] ?? '');
        $link    = trim($_POST['link'] ?? '');
        $up      = handle_upload('image_file', 'services');

        if ($up && isset($up['path'])) {
            $pdo->prepare("UPDATE service_blocks SET content=?, link=?, image_path=? WHERE id=? AND service_id=?")
                ->execute([$content, $link, $up['path'], $bid, $id]);
        } else {
            $pdo->prepare("UPDATE service_blocks SET content=?, link=? WHERE id=? AND service_id=?")
                ->execute([$content, $link, $bid, $id]);
        }
        log_history((int)$me['id'], 'edit', "Updated block in '{$service['title']}'");
        flash('Block saved.');
        header("Location: service_edit.php?id=$id"); exit;
    }

    if ($action === 'delete_block' && !empty($_POST['block_id'])) {
        $pdo->prepare("DELETE FROM service_blocks WHERE id=? AND service_id=?")
            ->execute([(int)$_POST['block_id'], $id]);
        log_history((int)$me['id'], 'edit', "Deleted block from '{$service['title']}'");
        flash('Block deleted.');
        header("Location: service_edit.php?id=$id"); exit;
    }

    if ($action === 'move_block' && !empty($_POST['block_id']) && !empty($_POST['dir'])) {
        move_block($id, (int)$_POST['block_id'], $_POST['dir']);
        header("Location: service_edit.php?id=$id"); exit;
    }
}

function move_block(int $serviceId, int $blockId, string $dir): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT sort_order FROM service_blocks WHERE id=? AND service_id=?");
    $stmt->execute([$blockId, $serviceId]);
    $cur = $stmt->fetch();
    if (!$cur) return;
    $op = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("SELECT id, sort_order FROM service_blocks WHERE service_id=? AND sort_order $op ? ORDER BY sort_order $ord LIMIT 1");
    $stmt->execute([$serviceId, $cur['sort_order']]);
    $other = $stmt->fetch();
    if (!$other) return;
    $pdo->prepare("UPDATE service_blocks SET sort_order=? WHERE id=?")->execute([$other['sort_order'], $blockId]);
    $pdo->prepare("UPDATE service_blocks SET sort_order=? WHERE id=?")->execute([$cur['sort_order'], $other['id']]);
}

$blocks = $pdo->prepare("SELECT * FROM service_blocks WHERE service_id=? ORDER BY sort_order, id");
$blocks->execute([$id]);
$blocks = $blocks->fetchAll();

render_admin_header('Edit Service', 'services.php');
?>
<p><a href="services.php">← Back to services</a></p>
<h1>Edit Service — <?= h($service['title']) ?></h1>

<div class="panel">
  <h2>Service Info</h2>
  <form method="post" class="inline-form">
    <input type="hidden" name="action" value="update_info">
    <label>Page Title<input type="text" name="title" value="<?= h($service['title']) ?>" required></label>
    <label>Menu Label<input type="text" name="menu_label" value="<?= h($service['menu_label']) ?>" required></label>
    <button type="submit">Save Info</button>
  </form>
</div>

<h2>Content Blocks (reorder with ▲ ▼)</h2>
<?php foreach ($blocks as $b): ?>
<div class="block">
  <div class="block-head">
    <strong><?= strtoupper(h($b['block_type'])) ?></strong>
    <span class="order">
      <form method="post"><input type="hidden" name="action" value="move_block"><input type="hidden" name="block_id" value="<?= $b['id'] ?>"><input type="hidden" name="dir" value="up"><button class="mini">▲</button></form>
      <form method="post"><input type="hidden" name="action" value="move_block"><input type="hidden" name="block_id" value="<?= $b['id'] ?>"><input type="hidden" name="dir" value="down"><button class="mini">▼</button></form>
      <form method="post" onsubmit="return confirm('Delete block?')"><input type="hidden" name="action" value="delete_block"><input type="hidden" name="block_id" value="<?= $b['id'] ?>"><button class="mini danger">✕</button></form>
    </span>
  </div>
  <form method="post" enctype="multipart/form-data" class="block-form">
    <input type="hidden" name="action" value="update_block">
    <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
    <?php if ($b['block_type'] === 'image'): ?>
      <img src="<?= h($b['image_path']) ?>" class="thumb" alt="">
      <label>Caption<input type="text" name="content" value="<?= h($b['content']) ?>"></label>
      <label>Replace image<input type="file" name="image_file" accept="image/*"></label>
    <?php elseif ($b['block_type'] === 'button'): ?>
      <label>Button text<input type="text" name="content" value="<?= h($b['content']) ?>"></label>
      <label>Link URL<input type="text" name="link" value="<?= h($b['link']) ?>"></label>
    <?php else: ?>
      <label>Text<textarea name="content" rows="4"><?= h($b['content']) ?></textarea></label>
    <?php endif; ?>
    <button type="submit">Save Block</button>
  </form>
</div>
<?php endforeach; ?>
<?php if (empty($blocks)): ?><p>No blocks yet. Add your first block below.</p><?php endif; ?>

<div class="panel">
  <h2>Add New Block</h2>
  <form method="post" enctype="multipart/form-data" id="addBlockForm">
    <input type="hidden" name="action" value="add_block">
    <label>Block Type
      <select name="block_type" onchange="
        var v=this.value, f=this.form;
        f.querySelectorAll('.type-text,.type-image,.type-button').forEach(function(el){el.style.display='none';});
        f.querySelector('.type-'+v).style.display='block';
      ">
        <option value="text">Text Paragraph</option>
        <option value="image">Image</option>
        <option value="button">Button / Link</option>
      </select>
    </label>

    <div class="type-text">
      <label>Text<textarea name="block_text" rows="4"></textarea></label>
    </div>

    <div class="type-image" style="display:none">
      <label>Image File<input type="file" name="image_file" accept="image/*"></label>
      <label>Caption<input type="text" name="block_caption"></label>
    </div>

    <div class="type-button" style="display:none">
      <label>Button Text<input type="text" name="block_button_text"></label>
      <label>Link URL<input type="text" name="block_link" placeholder="https://..."></label>
    </div>

    <button type="submit" style="margin-top:12px">Add Block</button>
  </form>
</div>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/gallery.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo = db();
$me  = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cat') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_categories")->fetchColumn();
            $pdo->prepare("INSERT INTO gallery_categories (name, sort_order) VALUES (?,?)")
                ->execute([$name, $max + 1]);
            log_history((int)$me['id'], 'edit', "Added gallery category '$name'");
            flash('Category added.');
        } else flash('Category name required.', 'error');
        header('Location: gallery.php'); exit;
    }

    if ($action === 'rename_cat' && !empty($_POST['id'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE gallery_categories SET name=? WHERE id=?")->execute([$name, (int)$_POST['id']]);
            log_history((int)$me['id'], 'edit', "Renamed gallery category to '$name'");
            flash('Category renamed.');
        } else flash('Name required.', 'error');
        header('Location: gallery.php'); exit;
    }

    if ($action === 'delete_cat' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT name FROM gallery_categories WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        $n = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM gallery_categories WHERE id=?")->execute([(int)$_POST['id']]);
        log_history((int)$me['id'], 'edit', "Deleted gallery category '$n'");
        flash('Category and all its images deleted.');
        header('Location: gallery.php'); exit;
    }

    if ($action === 'move_cat' && !empty($_POST['id']) && !empty($_POST['dir'])) {
        move_cat((int)$_POST['id'], $_POST['dir']);
        header('Location: gallery.php'); exit;
    }
}

function move_cat(int $id, string $dir): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT sort_order FROM gallery_categories WHERE id=?");
    $stmt->execute([$id]);
    $cur = $stmt->fetch();
    if (!$cur) return;
    $op = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("SELECT id, sort_order FROM gallery_categories WHERE sort_order $op ? ORDER BY sort_order $ord LIMIT 1");
    $stmt->execute([$cur['sort_order']]);
    $other = $stmt->fetch();
    if (!$other) return;
    $pdo->prepare("UPDATE gallery_categories SET sort_order=? WHERE id=?")->execute([$other['sort_order'], $id]);
    $pdo->prepare("UPDATE gallery_categories SET sort_order=? WHERE id=?")->execute([$cur['sort_order'], $other['id']]);
}

$cats = $pdo->query("
    SELECT c.*,
      (SELECT COUNT(*) FROM gallery_items WHERE category_id=c.id) AS item_count
    FROM gallery_categories c
    ORDER BY sort_order, id")->fetchAll();

render_admin_header('Gallery', 'gallery.php');
?>
<h1>Gallery Categories</h1>
<p>Create categories, then click <b>Manage Images</b> to add/reorder photos and captions.</p>

<div class="panel">
  <h2>Add Category</h2>
  <form method="post" class="inline-form">
    <input type="hidden" name="action" value="add_cat">
    <label>Category Name<input type="text" name="name" required></label>
    <button type="submit">Add Category</button>
  </form>
</div>

<table class="table">
<thead><tr><th>Order</th><th>Name</th><th>Images</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($cats as $c): ?>
<tr>
  <td class="order">
    <form method="post"><input type="hidden" name="action" value="move_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="up"><button class="mini">▲</button></form>
    <form method="post"><input type="hidden" name="action" value="move_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="down"><button class="mini">▼</button></form>
  </td>
  <td>
    <form method="post" class="inline-form">
      <input type="hidden" name="action" value="rename_cat">
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <input type="text" name="name" value="<?= h($c['name']) ?>">
      <button class="mini">Save</button>
    </form>
  </td>
  <td><?= (int)$c['item_count'] ?></td>
  <td>
    <a class="btn" href="gallery_items.php?cat=<?= $c['id'] ?>">Manage Images</a>
    <form method="post" onsubmit="return confirm('Delete category AND all images?')">
      <input type="hidden" name="action" value="delete_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <button class="btn danger">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($cats)): ?><tr><td colspan="4">No categories yet.</td></tr><?php endif; ?>
</tbody></table>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/gallery_items.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo   = db();
$me    = current_admin();
$catId = (int)($_GET['cat'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM gallery_categories WHERE id=?");
$stmt->execute([$catId]);
$cat = $stmt->fetch();
if (!$cat) { flash('Category not found.', 'error'); header('Location: gallery.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $up = handle_upload('image_file', 'gallery');
        if (!$up || isset($up['error'])) {
            flash('Upload failed: ' . ($up['error'] ?? 'no image'), 'error');
            header("Location: gallery_items.php?cat=$catId"); exit;
        }
        $caption = trim($_POST['caption'] ?? '');
        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_items WHERE category_id=$catId")->fetchColumn();
        $pdo->prepare("INSERT INTO gallery_items (category_id, image_path, caption, sort_order, created_at)
                       VALUES (?,?,?,?,NOW())")
            ->execute([$catId, $up['path'], $caption, $max + 1]);
        log_history((int)$me['id'], 'edit', "Added gallery image to '{$cat['name']}'");
        flash('Image added.');
        header("Location: gallery_items.php?cat=$catId"); exit;
    }

    if ($action === 'update_item' && !empty($_POST['id'])) {
        $caption = trim($_POST['caption'] ?? '');
        $up = handle_upload('image_file', 'gallery');
        if ($up && isset($up['path'])) {
            $pdo->prepare("UPDATE gallery_items SET caption=?, image_path=? WHERE id=? AND category_id=?")
                ->execute([$caption, $up['path'], (int)$_POST['id'], $catId]);
        } else {
            $pdo->prepare("UPDATE gallery_items SET caption=? WHERE id=? AND category_id=?")
                ->execute([$caption, (int)$_POST['id'], $catId]);
        }
        log_history((int)$me['id'], 'edit', "Updated gallery image in '{$cat['name']}'");
        flash('Image updated.');
        header("Location: gallery_items.php?cat=$catId"); exit;
    }

    if ($action === 'delete_item' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM gallery_items WHERE id=? AND category_id=?")
            ->execute([(int)$_POST['id'], $catId]);
        log_history((int)$me['id'], 'edit', "Deleted gallery image from '{$cat['name']}'");
        flash('Image deleted.');
        header("Location: gallery_items.php?cat=$catId"); exit;
    }

    if ($action === 'move_item' && !empty($_POST['id']) && !empty($_POST['dir'])) {
        move_item($catId, (int)$_POST['id'], $_POST['dir']);
        header("Location: gallery_items.php?cat=$catId"); exit;
    }
}

function move_item(int $catId, int $id, string $dir): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT sort_order FROM gallery_items WHERE id=? AND category_id=?");
    $stmt->execute([$id, $catId]);
    $cur = $stmt->fetch();
    if (!$cur) return;
    $op = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("SELECT id, sort_order FROM gallery_items WHERE category_id=? AND sort_order $op ? ORDER BY sort_order $ord LIMIT 1");
    $stmt->execute([$catId, $cur['sort_order']]);
    $other = $stmt->fetch();
    if (!$other) return;
    $pdo->prepare("UPDATE gallery_items SET sort_order=? WHERE id=?")->execute([$other['sort_order'], $id]);
    $pdo->prepare("UPDATE gallery_items SET sort_order=? WHERE id=?")->execute([$cur['sort_order'], $other['id']]);
}

$items = $pdo->prepare("SELECT * FROM gallery_items WHERE category_id=? ORDER BY sort_order, id");
$items->execute([$catId]);
$items = $items->fetchAll();

render_admin_header('Gallery Items', 'gallery.php');
?>
<p><a href="gallery.php">← Back to categories</a></p>
<h1>Images in: <?= h($cat['name']) ?></h1>

<div class="panel">
  <h2>Add Image</h2>
  <form method="post" enctype="multipart/form-data" class="inline-form">
    <input type="hidden" name="action" value="add_item">
    <label>Image<input type="file" name="image_file" accept="image/*" required></label>
    <label>Caption<input type="text" name="caption" placeholder="Optional caption"></label>
    <button type="submit">Upload</button>
  </form>
</div>

<div class="gallery-grid">
<?php foreach ($items as $it): ?>
  <div class="gallery-card">
    <img src="<?= h($it['image_path']) ?>" alt="">
    <div class="order">
      <form method="post"><input type="hidden" name="action" value="move_item"><input type="hidden" name="id" value="<?= $it['id'] ?>"><input type="hidden" name="dir" value="up"><button class="mini">▲</button></form>
      <form method="post"><input type="hidden" name="action" value="move_item"><input type="hidden" name="id" value="<?= $it['id'] ?>"><input type="hidden" name="dir" value="down"><button class="mini">▼</button></form>
      <form method="post" onsubmit="return confirm('Delete image?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= $it['id'] ?>"><button class="mini danger">✕</button></form>
    </div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" name="id" value="<?= $it['id'] ?>">
      <label>Caption<input type="text" name="caption" value="<?= h($it['caption']) ?>"></label>
      <label>Replace<input type="file" name="image_file" accept="image/*"></label>
      <button type="submit">Save</button>
    </form>
    <div class="path"><?= h($it['image_path']) ?></div>
  </div>
<?php endforeach; ?>
<?php if (empty($items)): ?><p>No images yet. Add one above.</p><?php endif; ?>
</div>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/admins.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_super();
$pdo = db();
$me  = current_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $isSuper  = !empty($_POST['is_super']) ? 1 : 0;
        if ($username && strlen($password) >= 6) {
            $salt = bin2hex(random_bytes(16));
            $hash = password_hash($salt . $password, PASSWORD_BCRYPT);
            try {
                $pdo->prepare("INSERT INTO admins (username, salt, password_hash, is_super, created_at)
                               VALUES (?,?,?,?,NOW())")
                    ->execute([$username, $salt, $hash, $isSuper]);
                log_history((int)$me['id'], 'edit', "Added admin '$username' (super=$isSuper)");
                flash('Admin added.');
            } catch (Exception $e) {
                flash('Username already exists.', 'error');
            }
        } else flash('Username required and password must be 6+ chars.', 'error');
        header('Location: admins.php'); exit;
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        if ((int)$_POST['id'] === (int)$me['id']) {
            flash('You cannot delete your own account.', 'error');
        } else {
            $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([(int)$_POST['id']]);
            log_history((int)$me['id'], 'edit', "Deleted admin id " . (int)$_POST['id']);
            flash('Admin deleted.');
        }
        header('Location: admins.php'); exit;
    }

    if ($action === 'reset_pw' && !empty($_POST['id'])) {
        $newPw = $_POST['password'] ?? '';
        if (strlen($newPw) >= 6) {
            $salt = bin2hex(random_bytes(16));
            $hash = password_hash($salt . $newPw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE admins SET salt=?, password_hash=? WHERE id=?")
                ->execute([$salt, $hash, (int)$_POST['id']]);
            log_history((int)$me['id'], 'edit', "Reset password for admin id " . (int)$_POST['id']);
            flash('Password reset.');
        } else flash('Password must be 6+ chars.', 'error');
        header('Location: admins.php'); exit;
    }
}

$admins = $pdo->query("SELECT id, username, is_super, created_at FROM admins ORDER BY id")->fetchAll();
render_admin_header('Admins', 'admins.php');
?>
<h1>Admins <span style="font-size:13px;color:#888">(super‑admin only)</span></h1>

<div class="panel">
  <h2>Add Admin</h2>
  <form method="post" class="inline-form">
    <input type="hidden" name="action" value="add">
    <label>Username<input type="text" name="username" required></label>
    <label>Password<input type="password" name="password" minlength="6" required></label>
    <label><input type="checkbox" name="is_super" value="1"> Super Admin</label>
    <button type="submit">Add Admin</button>
  </form>
</div>

<table class="table">
<thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Reset Password</th><th></th></tr></thead>
<tbody>
<?php foreach ($admins as $a): ?>
<tr>
  <td><?= $a['id'] ?></td>
  <td><?= h($a['username']) ?><?= (int)$a['id'] === (int)$me['id'] ? ' <em>(you)</em>' : '' ?></td>
  <td><?= $a['is_super'] ? 'Super Admin' : 'Admin' ?></td>
  <td><?= h($a['created_at']) ?></td>
  <td>
    <form method="post" class="inline-form">
      <input type="hidden" name="action" value="reset_pw">
      <input type="hidden" name="id" value="<?= $a['id'] ?>">
      <input type="password" name="password" minlength="6" placeholder="New password">
      <button class="mini">Reset</button>
    </form>
  </td>
  <td>
    <?php if ((int)$a['id'] !== (int)$me['id']): ?>
    <form method="post" onsubmit="return confirm('Delete admin?')">
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>">
      <button class="btn danger">Delete</button>
    </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/history.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/functions.php';
require_login();
$pdo = db();

$filter = $_GET['type'] ?? '';
$where  = '';
$params = [];

if (in_array($filter, ['login', 'login_failed', 'logout', 'edit'], true)) {
    $where  = "WHERE h.action_type = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT h.*, a.username
    FROM history h
    LEFT JOIN admins a ON a.id = h.admin_id
    $where
    ORDER BY h.id DESC
    LIMIT 500");
$stmt->execute($params);
$rows = $stmt->fetchAll();

render_admin_header('History', 'history.php');
?>
<h1>History Log</h1>
<div class="panel">
  <form method="get" class="inline-form">
    <label>Filter
      <select name="type" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="login"        <?= $filter==='login'        ? 'selected' : '' ?>>Successful Logins</option>
        <option value="login_failed" <?= $filter==='login_failed' ? 'selected' : '' ?>>Failed Logins</option>
        <option value="logout"       <?= $filter==='logout'       ? 'selected' : '' ?>>Logouts</option>
        <option value="edit"         <?= $filter==='edit'         ? 'selected' : '' ?>>Edits</option>
      </select>
    </label>
  </form>
</div>
<table class="table">
<thead><tr><th>#</th><th>Time</th><th>Admin</th><th>Type</th><th>IP</th><th>Description</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= $r['id'] ?></td>
  <td><?= h($r['created_at']) ?></td>
  <td><?= h($r['username'] ?? '(unknown)') ?></td>
  <td><?= h($r['action_type']) ?></td>
  <td><?= h($r['ip_address']) ?></td>
  <td><?= h($r['description']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="6">No log entries.</td></tr><?php endif; ?>
</tbody></table>
<?php render_admin_footer();
EOT, "\n");

// ======================================================================
$files['admin-project/api/menu.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
$rows = db()->query("SELECT slug, menu_label FROM services ORDER BY sort_order, id")->fetchAll();
echo json_encode($rows);
EOT, "\n");

// ======================================================================
$files['admin-project/api/service.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$slug = $_GET['slug'] ?? '';
if ($slug === '') { http_response_code(400); echo json_encode(['error' => 'missing slug']); exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM services WHERE slug=?");
$stmt->execute([$slug]);
$service = $stmt->fetch();
if (!$service) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

$stmt = $pdo->prepare("SELECT * FROM service_blocks WHERE service_id=? ORDER BY sort_order, id");
$stmt->execute([$service['id']]);
$blocks = $stmt->fetchAll();

echo json_encode(['service' => $service, 'blocks' => $blocks]);
EOT, "\n");

// ======================================================================
$files['admin-project/api/gallery.php'] = ltrim(<<<'EOT'
<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$catName = $_GET['category'] ?? '';

if ($catName === '') {
    $cats = $pdo->query("SELECT * FROM gallery_categories ORDER BY sort_order, id")->fetchAll();
    foreach ($cats as &$c) {
        $stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE category_id=? ORDER BY sort_order, id");
        $stmt->execute([$c['id']]);
        $c['items'] = $stmt->fetchAll();
    }
    echo json_encode($cats);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM gallery_categories WHERE name=?");
$stmt->execute([$catName]);
$cat = $stmt->fetch();
if (!$cat) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

$stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE category_id=? ORDER BY sort_order, id");
$stmt->execute([$cat['id']]);
$cat['items'] = $stmt->fetchAll();
echo json_encode($cat);
EOT, "\n");

// ======================================================================
$files['admin-project/Services.html'] = ltrim(<<<'EOT'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Our Services</title>
<link rel="stylesheet" href="assets/public.css">
<style>
  body { margin:0; font-family: system-ui, sans-serif; color:#222; }
  .menu { display:flex; flex-wrap:wrap; background:#222; }
  .menu a { color:#fff; padding:14px 20px; text-decoration:none; cursor:pointer; }
  .menu a:hover, .menu a.active { background:#444; }
  .content { max-width:900px; margin:0 auto; padding:30px; }
  .block { margin:15px 0; }
  .block img { max-width:100%; border-radius:6px; }
  .block .caption { color:#666; font-size:14px; margin-top:6px; }
  .btn { display:inline-block; background:#2271b1; color:#fff; padding:10px 18px; text-decoration:none; border-radius:4px; }
  h1 { margin-top:0; }
</style>
</head>
<body>
<div class="menu" id="menu"></div>
<div class="content" id="content"><p>Loading…</p></div>

<script>
const menuEl = document.getElementById('menu');
const contentEl = document.getElementById('content');

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function loadMenu() {
  try {
    const res  = await fetch('api/menu.php');
    const items = await res.json();
    if (!items.length) { menuEl.innerHTML = '<span style="padding:14px 20px;color:#999">No services</span>'; return; }
    menuEl.innerHTML = '';
    items.forEach(it => {
      const a = document.createElement('a');
      a.textContent = it.menu_label;
      a.href = '#';
      a.addEventListener('click', e => { e.preventDefault(); selectService(it.slug, a); });
      menuEl.appendChild(a);
    });
    selectService(items[0].slug, menuEl.firstChild);
  } catch (err) {
    contentEl.innerHTML = '<p>Error loading menu.</p>';
  }
}

async function selectService(slug, linkEl) {
  document.querySelectorAll('.menu a').forEach(a => a.classList.remove('active'));
  if (linkEl) linkEl.classList.add('active');
  contentEl.innerHTML = '<p>Loading…</p>';
  try {
    const res = await fetch('api/service.php?slug=' + encodeURIComponent(slug));
    if (!res.ok) { contentEl.innerHTML = '<p>Service not found.</p>'; return; }
    const data = await res.json();
    const s = data.service;
    let html = '<h1>' + esc(s.title) + '</h1>';
    data.blocks.forEach(b => {
      if (b.block_type === 'text') {
        html += '<div class="block"><p>' + esc(b.content).replace(/\n/g, '<br>') + '</p></div>';
      } else if (b.block_type === 'image') {
        html += '<div class="block"><img src="' + esc(b.image_path) + '" alt="' + esc(b.content) + '">';
        if (b.content) html += '<div class="caption">' + esc(b.content) + '</div>';
        html += '</div>';
      } else if (b.block_type === 'button') {
        html += '<div class="block"><a class="btn" href="' + esc(b.link) + '">' + esc(b.content) + '</a></div>';
      }
    });
    contentEl.innerHTML = html;
  } catch (err) {
    contentEl.innerHTML = '<p>Error loading service.</p>';
  }
}

loadMenu();
</script>
</body>
</html>
EOT, "\n");

// ======================================================================
$files['admin-project/Gallery.html'] = ltrim(<<<'EOT'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Gallery</title>
<link rel="stylesheet" href="assets/public.css">
<style>
  body { margin:0; font-family: system-ui, sans-serif; color:#222; }
  .tabs { display:flex; flex-wrap:wrap; background:#222; }
  .tabs button { background:none; border:none; color:#fff; padding:14px 20px; cursor:pointer; font-size:15px; }
  .tabs button.active, .tabs button:hover { background:#444; }
  .content { max-width:1100px; margin:0 auto; padding:30px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; }
  .item { background:#f5f5f5; border-radius:8px; overflow:hidden; }
  .item img { width:100%; height:200px; object-fit:cover; display:block; }
  .item .cap { padding:10px; font-size:14px; }
</style>
</head>
<body>
<div class="tabs" id="tabs"></div>
<div class="content"><div class="grid" id="grid"><p>Loading…</p></div></div>

<script>
const tabsEl = document.getElementById('tabs');
const gridEl = document.getElementById('grid');
let allCats = [];

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function load() {
  try {
    const res = await fetch('api/gallery.php');
    allCats = await res.json();
    if (!allCats.length) { gridEl.innerHTML = '<p>No gallery categories yet.</p>'; return; }
    tabsEl.innerHTML = '';
    allCats.forEach((c, i) => {
      const b = document.createElement('button');
      b.textContent = c.name;
      b.addEventListener('click', () => selectCat(i, b));
      tabsEl.appendChild(b);
    });
    selectCat(0, tabsEl.firstChild);
  } catch (err) {
    gridEl.innerHTML = '<p>Error loading gallery.</p>';
  }
}

function selectCat(idx, btn) {
  document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const c = allCats[idx];
  if (!c || !c.items.length) { gridEl.innerHTML = '<p>No images in this category.</p>'; return; }
  gridEl.innerHTML = c.items.map(it =>
    '<div class="item"><img src="' + esc(it.image_path) + '" alt="' + esc(it.caption) + '">'
    + '<div class="cap">' + esc(it.caption) + '</div></div>'
  ).join('');
}

load();
</script>
</body>
</html>
EOT, "\n");

// ======================================================================
$files['admin-project/assets/admin.css'] = ltrim(<<<'EOT'
* { box-sizing: border-box; }
body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#f4f6f8; color:#222; }
.topbar { background:#1d2327; color:#fff; display:flex; flex-wrap:wrap; align-items:center; }
.topbar .brand { font-weight:bold; padding:16px 20px; font-size:18px; }
.topbar nav { display:flex; align-items:center; flex:1; flex-wrap:wrap; }
.topbar nav a { color:#c0c8d0; text-decoration:none; padding:16px 14px; }
.topbar nav a:hover, .topbar nav a.active { color:#fff; background:#2c3338; }
.topbar .user { padding:0 12px; color:#8a9199; font-size:13px; margin-left:auto; }
.topbar nav a.logout { color:#ff8a80; }

.container { max-width:1100px; margin:24px auto; padding:0 16px; }
h1 { margin-top:0; }

.flash { padding:12px 16px; border-radius:6px; margin-bottom:16px; }
.flash.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.flash.error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

.panel { background:#fff; padding:16px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.panel h2 { margin-top:0; font-size:16px; }

.inline-form { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.inline-form label { display:flex; flex-direction:column; font-size:13px; }
.inline-form input[type=text],
.inline-form input[type=password],
.inline-form input[type=file],
.inline-form textarea,
.inline-form select {
  padding:6px 8px; border:1px solid #ccc; border-radius:4px; min-width:180px;
  font-family:inherit; font-size:14px;
}

button, .btn {
  background:#2271b1; color:#fff; border:none; padding:7px 14px; border-radius:4px;
  cursor:pointer; text-decoration:none; font-size:14px; display:inline-block;
}
button:hover, .btn:hover { background:#135e96; }
.btn.danger, button.danger { background:#d63638; }
.btn.danger:hover { background:#a3272a; }
button.mini { padding:2px 7px; font-size:12px; }

.table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.06); margin-bottom:20px; }
.table th, .table td { padding:10px 12px; text-align:left; border-bottom:1px solid #eee; font-size:14px; }
.table th { background:#f7f9fa; }
.table form { display:inline; }

code { background:#eef; padding:2px 6px; border-radius:3px; font-size:12px; }

.login-page { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#1d2327; }
.login-box { background:#fff; padding:30px 28px; border-radius:8px; width:340px; box-shadow:0 10px 40px rgba(0,0,0,.3); }
.login-box h1 { font-size:20px; margin:0 0 18px; }
.login-box label { display:flex; flex-direction:column; margin-bottom:14px; font-size:14px; }
.login-box input { padding:8px 10px; border:1px solid #ccc; border-radius:4px; margin-top:4px; }
.login-box button { width:100%; padding:10px; font-size:15px; }

.cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:20px; }
.card { background:#fff; padding:20px; border-radius:8px; text-align:center; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.card .num { font-size:32px; font-weight:bold; }
.card a { color:#2271b1; text-decoration:none; font-size:13px; }

.block { background:#fff; padding:14px 16px; border-radius:8px; margin-bottom:14px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.block-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.block-form label { display:block; margin:8px 0 4px; font-size:13px; }
.block-form input[type=text],
.block-form textarea,
.block-form input[type=file] {
  width:100%; padding:7px 9px; border:1px solid #ccc; border-radius:4px; font-family:inherit; font-size:14px;
}
.thumb { max-width:200px; display:block; margin:8px 0; border-radius:4px; }

.gallery-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; }
.gallery-card { background:#fff; border-radius:8px; padding:12px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.gallery-card img { width:100%; height:160px; object-fit:cover; border-radius:4px; display:block; margin-bottom:8px; }
.gallery-card label { display:block; font-size:13px; margin:6px 0; }
.gallery-card input[type=text],
.gallery-card input[type=file] {
  width:100%; padding:5px 7px; border:1px solid #ccc; border-radius:4px;
}
.gallery-card .path { font-size:11px; color:#888; word-break:break-all; margin-top:6px; }
.gallery-card .order { margin-bottom:6px; text-align:center; }

#addBlockForm > label { display:block; margin-bottom:10px; }
EOT, "\n");

// ======================================================================
$files['admin-project/assets/public.css'] = ltrim(<<<'EOT'
/* Intentionally minimal — each public page has its own <style> block. */
EOT, "\n");

// ======================================================================
$files['admin-project/README.md'] = ltrim(<<<'EOT'
# Service & Gallery Admin

A self-contained PHP admin project to manage **service HTML pages** and a **photo gallery**, with login, super-admin management, image ordering, and full history logging.

## Files at a glance

- `install.php`     – one-time DB + user creator (delete after running)
- `index.php`       – admin login
- `dashboard.php`   – landing page
- `services.php` / `service_edit.php` – CRUD services + blocks (text, image, button)
- `gallery.php` / `gallery_items.php` – CRUD categories + images with reorder
- `admins.php`      – super-admin only; add admins / reset passwords
- `history.php`     – login + edit log with IP addresses
- `Services.html` / `Gallery.html` – public pages (JS fetch from `api/*.php`)
- `api/menu.php`, `api/service.php`, `api/gallery.php` – JSON endpoints
- `uploads/services/`, `uploads/gallery/` – image storage

## Installation

1. Copy the `admin-project/` folder to your web root (e.g. `public_html/admin-project/`).
2. Edit `config.php` and set your MySQL credentials.
3. Make sure `uploads/` is writable by PHP (`chmod -R 755 uploads`).
4. Open `http://your-site/admin-project/install.php` in your browser.
5. Log in at `index.php`.

**Default super admin:** `admin` / `admin123` — **change it immediately.**

6. **Delete `install.php`** after installation.

## Password / salt model

- Every admin has a per-user `salt` column (random 16-byte hex) stored in `admins`.
- `password_hash = password_hash($salt . $password, PASSWORD_BCRYPT)`.
- bcrypt itself also adds its own internal salt, so you effectively get "salt + pepper + bcrypt".
- Verification: `password_verify($salt . $password, $password_hash)`.

## Image locations

All uploaded images live under `uploads/services/<file>` or `uploads/gallery/<file>` on your hosting server. The exact path stored in the DB is what appears in the admin UI (see `gallery_items.php` cards) so you can always see where an image points.

## Notes for a non-technical operator

- **Add a service**: Services → fill the 3 fields → Add. Then Edit → add blocks (text / image / button). Use the ▲▼ arrows to reorder blocks.
- **Reorder gallery**: every image has ▲▼ and the list is per-category.
- **Replace an image** without losing its position: click *Save* on the card after picking a new file.
- Every change is timestamped with your IP in **History**.

## Public API (for embedding elsewhere)

- `GET api/menu.php`                    – list of `{slug, menu_label}`
- `GET api/service.php?slug=web-design` – `{service:{...}, blocks:[...]}`
- `GET api/gallery.php`                 – all categories with items
- `GET api/gallery.php?category=General`– single category with items

## Security checklist before going live

- Change the default `admin/admin123` password.
- Delete `install.php`.
- Restrict `uploads/` from executing PHP (`Options -ExecCGI` / `.htaccess`).
- Use HTTPS so the session cookie is protected.
EOT, "\n");

// ======================================================================
//  Build the ZIP
// ======================================================================
$zipPath = __DIR__ . '/admin-project.zip';
if (file_exists($zipPath)) @unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Could not create zip.\n");
}

foreach ($files as $path => $content) {
    $zip->addFromString($path, $content);
}

// Ensure empty upload dirs exist
$zip->addEmptyDir('admin-project/uploads');
$zip->addEmptyDir('admin-project/uploads/services');
$zip->addEmptyDir('admin-project/uploads/gallery');

$zip->close();

if (PHP_SAPI === 'cli') {
    echo "Created: $zipPath\n";
    echo "Size: " . number_format(filesize($zipPath)) . " bytes\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Done!</h1><p>Download: <a href="admin-project.zip">admin-project.zip</a></p>';
}