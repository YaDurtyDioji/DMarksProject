<?php
// admin/settings.php
session_start();
require_once __DIR__ . '/../backend/db.php';

// Authorization Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['admin_user'];

$flashMessage = '';
$flashType = 'success';

if (isset($_SESSION['flash_msg'])) {
    $flashMessage = $_SESSION['flash_msg'];
    $flashType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_general') {
        $waNumber = preg_replace('/[^0-9+]/', '', trim($_POST['whatsapp_business_number'] ?? ''));
        $indemnityLink = trim($_POST['indemnity_form_link'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$waNumber, 'whatsapp_business_number']);
        $stmt->execute([$indemnityLink, 'indemnity_form_link']);

        logAudit($pdo, $currentUser, "Updated General Settings");
        $_SESSION['flash_msg'] = "General settings updated successfully.";

    } elseif ($action === 'update_notifications') {
        $adminEmails = trim($_POST['admin_notification_emails'] ?? '');
        
        // Save notification emails
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['admin_notification_emails', $adminEmails]);

        // Save Per-Function Matrix settings
        $events = ['new_booking', 'reschedule', 'completed', 'first_time', 'indemnity'];
        foreach ($events as $ev) {
            $emailVal = isset($_POST["notif_{$ev}_email"]) ? '1' : '0';
            $waVal = isset($_POST["notif_{$ev}_wa"]) ? '1' : '0';
            $targetVal = $_POST["notif_{$ev}_target"] ?? 'both';

            $stmt->execute(["notif_{$ev}_email", $emailVal]);
            $stmt->execute(["notif_{$ev}_wa", $waVal]);
            $stmt->execute(["notif_{$ev}_target", $targetVal]);
        }

        logAudit($pdo, $currentUser, "Updated Notification Matrix & Admin Emails");
        $_SESSION['flash_msg'] = "Notification settings & email routing updated.";

    } elseif ($action === 'update_gcal') {
        $clientId = trim($_POST['gcal_client_id'] ?? '');
        $clientSecret = trim($_POST['gcal_client_secret'] ?? '');
        $refreshToken = trim($_POST['gcal_refresh_token'] ?? '');
        $calendarId = trim($_POST['gcal_calendar_id'] ?? 'primary');

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['gcal_client_id', $clientId]);
        $stmt->execute(['gcal_client_secret', $clientSecret]);
        $stmt->execute(['gcal_refresh_token', $refreshToken]);
        $stmt->execute(['gcal_calendar_id', $calendarId]);

        logAudit($pdo, $currentUser, "Updated Google Calendar API Credentials");
        $_SESSION['flash_msg'] = "Google Calendar settings updated.";

    } elseif ($action === 'add_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = preg_replace('/[^0-9+]/', '', trim($_POST['phone_number'] ?? ''));
        $role = trim($_POST['role'] ?? 'Administrator');

        if (!empty($username) && !empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, phone_number, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $phone, $role]);
                logAudit($pdo, $currentUser, "Created Admin: {$username}");
                $_SESSION['flash_msg'] = "Admin user added successfully.";
            } catch (Exception $e) {
                $_SESSION['flash_msg'] = "Error: Username might already exist.";
                $_SESSION['flash_type'] = "danger";
            }
        }
    } elseif ($action === 'delete_admin') {
        $id = intval($_POST['admin_id'] ?? 0);
        if ($id > 0) {
            $check = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
            $check->execute([$id]);
            $delUser = $check->fetchColumn();

            if ($delUser !== $currentUser) {
                $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, $currentUser, "Deleted Admin: {$delUser}");
                $_SESSION['flash_msg'] = "Admin deleted successfully.";
            } else {
                $_SESSION['flash_msg'] = "You cannot delete your own active account.";
                $_SESSION['flash_type'] = "danger";
            }
        }
    }

    header('Location: settings.php');
    exit;
}

// Fetch Current Settings
$currentWa = getSetting($pdo, 'whatsapp_business_number', '');
$currentIndemnity = getSetting($pdo, 'indemnity_form_link', 'https://barewithtea.com/indemnity');
$adminNotificationEmails = getSetting($pdo, 'admin_notification_emails', 'admin@barewithtea.co.za');

$gcalClientId = getSetting($pdo, 'gcal_client_id', '');
$gcalClientSecret = getSetting($pdo, 'gcal_client_secret', '');
$gcalRefreshToken = getSetting($pdo, 'gcal_refresh_token', '');
$gcalCalendarId = getSetting($pdo, 'gcal_calendar_id', 'primary');

$admins = $pdo->query("SELECT id, username, phone_number, role, created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Notification events definition
$notifEvents = [
    'new_booking' => 'New Booking Created',
    'reschedule' => 'Booking Rescheduled / Time Suggested',
    'completed' => 'Booking Completed / Invoice Dispatched',
    'first_time' => 'First-Time Client Onboarding',
    'indemnity' => 'Digital Indemnity Form Completed'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings & Notifications - barewithtea</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #faf9f6; font-family: system-ui, -apple-system, sans-serif; color: #212529; }
        .card-custom { border-radius: 0px; border: 1px solid #d7ccab; background: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .nav-tabs .nav-link { color: #212529; font-weight: bold; border-radius: 0; }
        .nav-tabs .nav-link.active { background-color: #e4dcc6; border-color: #d7ccab #d7ccab #e4dcc6; }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <?php if (!empty($flashMessage)): ?>
                <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <?= $flashMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card card-custom p-4">
                <h3 class="fw-bold mb-4 text-uppercase"><i class="fa-solid fa-gear me-2"></i> System Settings & Notifications</h3>
                
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button"><i class="fa-solid fa-sliders me-1"></i> General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#notifications" type="button"><i class="fa-solid fa-bell me-1"></i> Email & Notifications Matrix</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#gcal" type="button"><i class="fa-solid fa-calendar-days me-1"></i> Google Calendar</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#admins" type="button"><i class="fa-solid fa-user-shield me-1"></i> Manage Admins</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#logs" type="button"><i class="fa-solid fa-list-check me-1"></i> Audit Logs</button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabsContent">
                    
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="general">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_general">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="fa-brands fa-whatsapp text-success me-2"></i> Business WhatsApp Number</label>
                                <p class="small text-muted mb-2">Fallback number if logged-in Admin's personal number isn't set. Use country code (e.g. +27821234567).</p>
                                <input type="text" name="whatsapp_business_number" class="form-control rounded-0" value="<?= htmlspecialchars($currentWa) ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="fa-solid fa-file-contract text-primary me-2"></i> Digital Indemnity Form Link</label>
                                <p class="small text-muted mb-2">URL automatically included in onboarding emails and WhatsApp messages for first-time clients.</p>
                                <input type="url" name="indemnity_form_link" class="form-control rounded-0" value="<?= htmlspecialchars($currentIndemnity) ?>" required>
                            </div>

                            <button type="submit" class="btn btn-dark rounded-0 px-4 text-uppercase fw-bold">Save General Settings</button>
                        </form>
                    </div>

                    <!-- Email & Per-Function Notification Matrix Tab -->
                    <div class="tab-pane fade" id="notifications">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="card p-3 mb-4 bg-light border-0 rounded-0">
                                <h6 class="fw-bold text-uppercase mb-2"><i class="fa-solid fa-envelope me-2"></i> System Notification Email Addresses</h6>
                                <p class="small text-muted mb-2">Add email addresses that will receive system notification alerts when events occur. Separate multiple emails with commas.</p>
                                <input type="text" name="admin_notification_emails" class="form-control rounded-0" value="<?= htmlspecialchars($adminNotificationEmails) ?>" placeholder="admin@barewithtea.co.za, manager@barewithtea.co.za">
                            </div>

                            <h6 class="fw-bold text-uppercase mb-3"><i class="fa-solid fa-sliders me-2"></i> Per-Function Notification Matrix</h6>
                            <p class="small text-muted mb-3">Configure which notification channels (Email and/or WhatsApp) are enabled per event type and who receives them.</p>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered align-middle bg-white">
                                    <thead style="background-color: #e4dcc6;">
                                        <tr>
                                            <th>Event / Function</th>
                                            <th class="text-center" style="width: 130px;">Email Alert</th>
                                            <th class="text-center" style="width: 130px;">WhatsApp Alert</th>
                                            <th style="width: 180px;">Recipient Target</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notifEvents as $evKey => $evLabel): 
                                            $eChecked = getSetting($pdo, "notif_{$evKey}_email", '1') === '1' ? 'checked' : '';
                                            $wChecked = getSetting($pdo, "notif_{$evKey}_wa", '1') === '1' ? 'checked' : '';
                                            $target = getSetting($pdo, "notif_{$evKey}_target", 'both');
                                        ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($evLabel) ?></td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="checkbox" name="notif_<?= $evKey ?>_email" value="1" <?= $eChecked ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="checkbox" name="notif_<?= $evKey ?>_wa" value="1" <?= $wChecked ?>>
                                                </td>
                                                <td>
                                                    <select name="notif_<?= $evKey ?>_target" class="form-select form-select-sm rounded-0">
                                                        <option value="both" <?= $target === 'both' ? 'selected' : '' ?>>Both Admin & Client</option>
                                                        <option value="admin" <?= $target === 'admin' ? 'selected' : '' ?>>Admin Only</option>
                                                        <option value="client" <?= $target === 'client' ? 'selected' : '' ?>>Client Only</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-dark rounded-0 px-4 text-uppercase fw-bold">Save Notification Matrix</button>
                        </form>
                    </div>

                    <!-- Google Calendar Tab -->
                    <div class="tab-pane fade" id="gcal">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_gcal">
                            <p class="text-muted small">Configure OAuth2 API Credentials to automatically sync bookings to Google Calendar in real time.</p>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Client ID</label>
                                <input type="text" name="gcal_client_id" class="form-control rounded-0" value="<?= htmlspecialchars($gcalClientId) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Client Secret</label>
                                <input type="password" name="gcal_client_secret" class="form-control rounded-0" value="<?= htmlspecialchars($gcalClientSecret) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Refresh Token</label>
                                <input type="password" name="gcal_refresh_token" class="form-control rounded-0" value="<?= htmlspecialchars($gcalRefreshToken) ?>">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Calendar ID</label>
                                <input type="text" name="gcal_calendar_id" class="form-control rounded-0" value="<?= htmlspecialchars($gcalCalendarId) ?>" placeholder="primary">
                            </div>

                            <button type="submit" class="btn btn-dark rounded-0 px-4 text-uppercase fw-bold">Save Google API Credentials</button>
                        </form>
                    </div>

                    <!-- Admins Tab -->
                    <div class="tab-pane fade" id="admins">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">System Administrators</h5>
                            <button class="btn btn-sm btn-dark text-uppercase rounded-0" data-bs-toggle="modal" data-bs-target="#addAdminModal"><i class="fa-solid fa-plus me-1"></i> Add Admin</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead style="background-color: #e4dcc6;">
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Personal WA Number</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $ad): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($ad['username']) ?></td>
                                        <td><?= htmlspecialchars($ad['role']) ?></td>
                                        <td><?= htmlspecialchars($ad['phone_number'] ?: 'None (Uses Business Number)') ?></td>
                                        <td class="text-end">
                                            <?php if ($ad['username'] !== $currentUser): ?>
                                                <form method="POST" action="settings.php" class="d-inline" onsubmit="return confirm('Delete this admin?');">
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="admin_id" value="<?= $ad['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger rounded-0"><i class="fa-solid fa-trash"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-0">Active User</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Audit Logs Tab -->
                    <div class="tab-pane fade" id="logs">
                        <h5 class="fw-bold mb-3">Recent System Audit Trail</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped border">
                                <thead style="background-color: #e4dcc6;">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Admin</th>
                                        <th>Action Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($logs)): ?>
                                        <tr><td colspan="3" class="text-muted text-center py-3">No activity logged yet.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="small"><?= htmlspecialchars($log['created_at']) ?></td>
                                            <td class="fw-bold small"><?= htmlspecialchars($log['admin_username']) ?></td>
                                            <td class="small"><?= htmlspecialchars($log['action']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="add_admin">
                <div class="modal-header border-bottom-0" style="background-color: #e4dcc6;">
                    <h5 class="modal-title fw-bold text-uppercase">New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" class="form-control rounded-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" name="password" class="form-control rounded-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Personal WA Number</label>
                        <p class="small text-muted mb-1">If set, notifications dispatched by this admin will use this number instead of the global business number.</p>
                        <input type="text" name="phone_number" class="form-control rounded-0" placeholder="+27...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select name="role" class="form-select rounded-0">
                            <option value="Administrator">Administrator</option>
                            <option value="Super Administrator">Super Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-dark w-100 rounded-0 text-uppercase fw-bold">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
