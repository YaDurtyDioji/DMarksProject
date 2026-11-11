<?php
require_once __DIR__ . '/bootstrap.php';
require_guest();

$errors = [];
$email = $name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    $name  = trim((string) ($_POST['display_name'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password_confirm'] ?? '');

    if ($pass !== $pass2) {
        $errors[] = 'Passwords do not match.';
    } else {
        [$ok, $err] = register_user($email, $pass, $name);
        if ($ok) {
            header('Location: index.php');
            exit;
        }
        $errors[] = $err;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register — openGym PHP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <h1>Create account</h1>

    <?php foreach ($errors as $e): ?>
        <p class="alert error"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>

    <form method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <label>Email
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
        </label>
        <label>Display name <span class="muted">(optional)</span>
            <input type="text" name="display_name" value="<?= htmlspecialchars($name) ?>" maxlength="100">
        </label>
        <label>Password <span class="muted">(min 8 chars)</span>
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Confirm password
            <input type="password" name="password_confirm" required minlength="8">
        </label>

        <button type="submit" class="btn-primary">Create account</button>
    </form>

    <p class="muted">Already registered? <a href="login.php">Sign in</a></p>
</main>
</body>
</html>