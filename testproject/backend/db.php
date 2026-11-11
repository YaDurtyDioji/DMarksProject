<?php
// backend/db.php
$dbPath = __DIR__ . '/../secure_db/portal.db';
$dbDir = dirname($dbPath);

if (!file_exists($dbDir)) {
    mkdir($dbDir, 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create bookings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER DEFAULT 0,
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        client_phone TEXT NOT NULL,
        service TEXT NOT NULL,
        subtotal REAL DEFAULT 0,
        additional_costs REAL DEFAULT 0,
        discount_type TEXT DEFAULT 'fixed',
        discount_value REAL DEFAULT 0,
        redeemable_code TEXT DEFAULT '',
        total_amount REAL DEFAULT 0,
        is_first_time INTEGER DEFAULT 0,
        booking_date TEXT NOT NULL,
        booking_time TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        payment_status TEXT DEFAULT 'outstanding',
        indemnity_completed INTEGER DEFAULT 0,
        refill_due_date TEXT DEFAULT '',
        suggested_date TEXT DEFAULT '',
        suggested_time TEXT DEFAULT '',
        synced_to_calendar INTEGER DEFAULT 0,
        gcal_event_id TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration helper for bookings table
    $existingCols = [];
    $pragma = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pragma as $col) {
        $existingCols[] = $col['name'];
    }

    $addColumns = [
        'client_id' => "INTEGER DEFAULT 0",
        'subtotal' => "REAL DEFAULT 0",
        'additional_costs' => "REAL DEFAULT 0",
        'discount_type' => "TEXT DEFAULT 'fixed'",
        'discount_value' => "REAL DEFAULT 0",
        'redeemable_code' => "TEXT DEFAULT ''",
        'total_amount' => "REAL DEFAULT 0",
        'is_first_time' => "INTEGER DEFAULT 0",
        'indemnity_completed' => "INTEGER DEFAULT 0",
        'gcal_event_id' => "TEXT DEFAULT ''"
    ];

    foreach ($addColumns as $colName => $colDef) {
        if (!in_array($colName, $existingCols)) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN {$colName} {$colDef}");
        }
    }

    // Create clients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        phone TEXT NOT NULL,
        indemnity_completed INTEGER DEFAULT 0,
        notes TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create reviews table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        service TEXT NOT NULL,
        session_rating INTEGER NOT NULL,
        overall_rating INTEGER NOT NULL,
        review_comments TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create prices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS prices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        duration TEXT NOT NULL,
        price TEXT NOT NULL
    )");

    // Create admins table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        phone_number TEXT DEFAULT '',
        role TEXT DEFAULT 'Administrator',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Migration for admins table
    $existingAdminCols = [];
    $pragmaAdmins = $pdo->query("PRAGMA table_info(admins)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pragmaAdmins as $col) {
        $existingAdminCols[] = $col['name'];
    }
    if (!in_array('phone_number', $existingAdminCols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN phone_number TEXT DEFAULT ''");
    }

    // Create settings table for global configurations
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT UNIQUE NOT NULL,
        setting_value TEXT NOT NULL
    )");
    
    // Create Audit Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_username TEXT NOT NULL,
        action TEXT NOT NULL,
        details TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create Login Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT DEFAULT '',
        user_agent TEXT DEFAULT '',
        status TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create Promotions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS promotions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        type TEXT NOT NULL DEFAULT 'promo_code', -- 'promo_code', 'price_reduction', 'special_combo'
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        discount_type TEXT DEFAULT 'percent', -- 'fixed' or 'percent'
        discount_value REAL DEFAULT 0,
        promo_price REAL DEFAULT 0,
        applicable_service TEXT DEFAULT 'all',
        start_date TEXT DEFAULT '',
        end_date TEXT DEFAULT '',
        usage_limit INTEGER DEFAULT 0,
        times_used INTEGER DEFAULT 0,
        status TEXT DEFAULT 'active', -- 'active' or 'inactive'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default settings if none exist
    $defaultSettings = [
        'whatsapp_business_number' => '',
        'indemnity_form_link' => 'https://barewithtea.com/indemnity',
        'admin_notification_emails' => 'admin@barewithtea.co.za',
        'gcal_client_id' => '',
        'gcal_client_secret' => '',
        'gcal_refresh_token' => '',
        'gcal_calendar_id' => 'primary',
        // Per-function notifications toggles
        'notif_new_booking_email' => '1',
        'notif_new_booking_wa' => '1',
        'notif_new_booking_target' => 'both',
        'notif_reschedule_email' => '1',
        'notif_reschedule_wa' => '1',
        'notif_reschedule_target' => 'both',
        'notif_completed_email' => '1',
        'notif_completed_wa' => '1',
        'notif_completed_target' => 'both',
        'notif_first_time_email' => '1',
        'notif_first_time_wa' => '1',
        'notif_first_time_target' => 'client',
        'notif_indemnity_email' => '1',
        'notif_indemnity_wa' => '1',
        'notif_indemnity_target' => 'admin'
    ];
    
    $insertSetting = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $key => $val) {
        $insertSetting->execute([$key, $val]);
    }

    // Seed default admin if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)");
        $insert->execute(['admin', $defaultHash, 'Super Administrator']);
    }

    // Seed sample promotion if empty
    $stmtPromo = $pdo->query("SELECT COUNT(*) FROM promotions");
    if ($stmtPromo->fetchColumn() == 0) {
        $insP = $pdo->prepare("INSERT INTO promotions (code, type, title, description, discount_type, discount_value, promo_price, applicable_service, start_date, end_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insP->execute(['SPRING2026', 'promo_code', 'Spring Special 20% Off', '20% discount on all laser & beauty treatments', 'percent', 20, 0, 'all', '2026-09-01', '2026-11-30', 100, 'active']);
        $insP->execute(['LASH-LASER-COMBO', 'special_combo', 'Full Lash & Underarm Combo', 'Special combo offer for Lash Lift + Underarm Laser', 'fixed', 150, 450, 'Lash & Laser Combo', '2026-01-01', '2026-12-31', 0, 'active']);
    }

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Helper function to get settings
function getSetting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

// Helper function to log audit actions
function logAudit($pdo, $username, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (admin_username, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$username, $action, $details]);
}
?>
