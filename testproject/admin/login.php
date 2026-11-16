<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../backend/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Log successful login
                $logStmt = $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, user_agent) VALUES (?, ?, 'SUCCESS', ?)");
                $logStmt->execute([$username, $ipAddress, $userAgent]);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                // Log failed login
                $logStmt = $pdo->prepare("INSERT INTO login_logs (username, ip_address, status, user_agent) VALUES (?, ?, 'FAILED', ?)");
                $logStmt->execute([$username, $ipAddress, $userAgent]);

                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred during authentication. Please try again.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login - barewithtea.</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="container" style="max-width: 420px;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-uppercase m-0">barewithtea.</h3>
                    <p class="text-muted small">Admin Portal & Security Authentication</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-3 border-0 shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control bg-light" required autofocus placeholder="admin">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control bg-light" required placeholder="••••••••">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Secure Login
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <a href="../index.html" class="text-muted small text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Back to Main Website</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
