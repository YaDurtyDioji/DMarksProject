<?php
// admin/manage-admins.php
session_start();
require_once __DIR__ . '/../backend/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle creating a new admin account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $role = trim($_POST['role'] ?? 'Administrator');

    if (!empty($newUsername) && !empty($newPassword)) {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->execute([$newUsername, $hash, $role]);
            $message = "Admin account '{$newUsername}' created successfully!";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Error: Username may already exist.";
            $messageType = 'danger';
        }
    } else {
        $message = "Please provide both username and password.";
        $messageType = 'danger';
    }
}

// Handle deleting an admin account
if (isset($_GET['action']) && $_GET['action'] === 'delete_admin' && isset($_GET['id'])) {
    $adminId = intval($_GET['id']);
    // Prevent deleting yourself if you are the only one or check count
    $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($count > 1) {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $message = "Admin account deleted successfully.";
        $messageType = 'danger';
    } else {
        $message = "Cannot delete the last remaining admin account.";
        $messageType = 'warning';
    }
}

// Fetch all admins
$admins = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch login audit logs
$logs = $pdo->query("SELECT * FROM login_logs ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Admins & Security Audit Logs - barewithtea.</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>body { background: #f4f1ea; }</style>
</head>
<body>

    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center my-4">
            <div>
                <h3 class="fw-bold text-uppercase m-0">barewithtea.</h3>
                <small class="text-muted">Admin Account Management & Login Audit Logs</small>
            </div>
            <a href="dashboard.php" class="btn btn-outline-dark fw-bold"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <!-- Create Admin Form -->
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="fa-solid fa-user-plus me-2"></i>Create New Admin Account
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="create_admin" value="1">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Username</label>
                                <input type="text" name="new_username" class="form-control bg-light" required placeholder="manager_name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Password</label>
                                <input type="password" name="new_password" class="form-control bg-light" required placeholder="Secure password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Access Role</label>
                                <select name="role" class="form-select bg-light">
                                    <option value="Administrator">Administrator</option>
                                    <option value="Super Administrator">Super Administrator</option>
                                    <option value="Boutique Manager">Boutique Manager</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-shield-halved me-2"></i>Create Admin Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Admins Table -->
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-users-gear me-2"></i>Authorized Admin Accounts</span>
                        <span class="badge bg-light text-dark"><?= count($admins) ?> Accounts</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Username</th>
                                        <th>Role</th>
                                        <th>Created Date</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $adm): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold"><?= htmlspecialchars($adm['username']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($adm['role']) ?></span></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($adm['created_at']) ?></small></td>
                                            <td class="text-end pe-3">
                                                <?php if ($adm['username'] !== $_SESSION['admin_user']): ?>
                                                    <a href="manage-admins.php?action=delete_admin&id=<?= $adm['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete admin account?');" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Audit Logs Table (IP Address Logging) -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-list-check me-2"></i>Security & Login Audit Logs (IP Address Tracking)</span>
                <span class="badge bg-light text-dark">Latest 100 Attempts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Timestamp</th>
                                <th>Username Attempted</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th class="pe-3">User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No login audit records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="ps-3"><small class="text-muted"><?= htmlspecialchars($l['created_at']) ?></small></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($l['username']) ?></td>
                                        <td><code><?= htmlspecialchars($l['ip_address']) ?></code></td>
                                        <td>
                                            <span class="badge <?= $l['status'] === 'SUCCESS' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= htmlspecialchars($l['status']) ?>
                                            </span>
                                        </td>
                                        <td class="pe-3"><small class="text-muted text-truncate d-inline-block" style="max-width: 300px;"><?= htmlspecialchars($l['user_agent']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
