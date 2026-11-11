<?php
require_once __DIR__ . '/bootstrap.php';
require_guest();

$error = '';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $pass = (string) ($_POST['password'] ?? '');

    if (attempt_login($email, $pass)) {
        $next = $_GET['next'] ?? 'index.php';
        // Only allow same-origin relative redirects.
        if (!preg_match('#^/?[a-zA-Z0-9_./?=&%-]+$#', $next)) $next = 'index.php';
        header('Location: ' . $next);
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in — openGym PHP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <h1>Sign in</h1>

    <?php if ($error): ?>
        <p class="alert error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <label>Email
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>

        <button type="submit" class="btn-primary">Sign in</button>
    </form>

    <p class="muted">No account? <a href="register.php">Create one</a></p>
</main>
</body>
</html>