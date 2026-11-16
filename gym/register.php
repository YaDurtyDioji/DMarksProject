<?php
require_once __DIR__ . '/bootstrap.php';
require_guest();

$errors = [];
$email  = $name = '';

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
        if ($ok) { header('Location: index.php'); exit; }
        $errors[] = $err;
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — openGym PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="auth-bg d-flex align-items-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">

                <div class="text-center mb-4">
                    <div class="auth-icon mb-3"><i class="bi bi-lightning-charge-fill"></i></div>
                    <h1 class="h3 fw-bold mb-1">
                        <span class="brand-gradient">openGym</span> <span class="text-secondary">PHP</span>
                    </h1>
                    <p class="text-secondary small mb-0">Create your account</p>
                </div>

                <div class="card shadow-lg border-secondary-subtle">
                    <div class="card-body p-4">

                        <?php foreach ($errors as $e): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span class="small"><?= htmlspecialchars($e) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <form method="post" autocomplete="on">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                            <div class="mb-3">
                                <label class="form-label small text-secondary">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control"
                                           value="<?= htmlspecialchars($email) ?>" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-secondary">
                                    Display name <span class="text-secondary">(optional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="display_name" class="form-control"
                                           value="<?= htmlspecialchars($name) ?>" maxlength="100">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-secondary">
                                    Password <span class="text-secondary">(min 8 chars)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required minlength="8">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small text-secondary">Confirm password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Create account <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>

                    </div>
                </div>

                <p class="text-center text-secondary small mt-4 mb-0">
                    Already registered? <a href="login.php" class="link-primary text-decoration-none fw-semibold">Sign in</a>
                </p>

            </div>
        </div>
    </div>
</body>
</html>