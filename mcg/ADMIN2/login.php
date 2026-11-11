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