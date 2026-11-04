<?php
/**
 * Admin login / first-run account setup.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';

if (mcg_current_user() !== null) {
    header('Location: index.php');
    exit;
}

$users = mcg_users();
$setup = count($users) === 0;
$error = '';
$username = '';
$password = '';
$confirm = '';

if (isset($_GET['logged_out'])) {
    $error = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_check_csrf();
    $username = mcg_clean_text($_POST['username'] ?? '', 60);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($setup) {
        if ($password !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            $created = mcg_create_user($username, $password);
            if (!$created['ok']) {
                $error = $created['error'];
            } else {
                $user = mcg_find_user($username);
                if ($user) {
                    mcg_login_user($user);
                    mcg_flash('success', 'Admin account created. Welcome to the portal.');
                    header('Location: index.php');
                    exit;
                }
                $error = 'The account was created, but sign-in failed. Please try again.';
            }
        }
    } else {
        if ($username === '' || $password === '') {
            $error = 'Enter your username and password.';
        } else {
            $key = 'login:' . mcg_ip_hash();
            $limit = mcg_rate_limit($key, 6, 900);
            if (!$limit['ok']) {
                $minutes = max(1, (int) ceil($limit['retry'] / 60));
                $error = 'Too many sign-in attempts. Please try again in about ' . $minutes . ' minute(s).';
            } else {
                $user = mcg_find_user($username);
                if ($user && password_verify($password, (string) $user['password_hash'])) {
                    mcg_rate_clear($key);
                    mcg_login_user($user);
                    mcg_flash('success', 'Welcome back, ' . $username . '.');
                    header('Location: index.php');
                    exit;
                }
                $error = 'That username or password was not recognised.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $setup ? 'Set up admin' : 'Admin sign in' ?> · MCG Biokinetics</title>
    <link rel="icon" href="../images/MCG%20BIOKINETICS%20LOGOtp.png">
    <link rel="stylesheet" href="assets/admin.css">
</head>

<body class="auth-body">
    <a class="skip-link" href="#auth-card">Skip to form</a>
    <div class="auth-wrap">
        <section class="auth-brand-panel" aria-labelledby="auth-title">
            <div class="auth-brand-lockup">
                <img src="../images/MCG%20BIOKINETICS%20LOGOtp.png" alt="">
                <div>
                    <strong>MCG Biokinetics</strong>
                    <span>Admin portal</span>
                </div>
            </div>
            <h1 id="auth-title">The practice,<br><em>behind the scenes.</em></h1>
            <p>Manage pages, gallery, reviews, client messages and communications from one private workspace.</p>
            <ul class="auth-points">
                <li><?= mcg_icon('pages') ?> Publish new pages and imagery</li>
                <li><?= mcg_icon('messages') ?> Read and filter contact enquiries</li>
                <li><?= mcg_icon('mail') ?> Send branded bulk emails</li>
                <li><?= mcg_icon('lock') ?> Protected by sessions and CSRF tokens</li>
            </ul>
        </section>

        <section class="auth-card" id="auth-card">
            <?php if (isset($_GET['logged_out'])): ?>
                <div class="notice auth-error">You have been signed out securely.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="notice notice-danger auth-error"><?= mcg_e($error) ?></div>
            <?php endif; ?>

            <h2><?= $setup ? 'Create admin account' : 'Sign in' ?></h2>
            <p><?= $setup ? 'There is no admin account yet. Create the first one now — this screen disappears after setup.' : 'Use your admin credentials to continue.' ?></p>

            <?php if ($setup): ?>
                <div class="auth-setup-note">
                    Use a unique password of at least 10 characters. The first account becomes the site administrator.
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="on">
                <?= mcg_csrf_field() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= mcg_e($username) ?>"
                        autocomplete="username" required maxlength="40" autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                        autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required
                        minlength="<?= $setup ? '10' : '1' ?>">
                </div>
                <?php if ($setup): ?>
                    <div class="field">
                        <label for="confirm">Confirm password</label>
                        <input type="password" id="confirm" name="confirm" autocomplete="new-password" required minlength="10">
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary btn-block" type="submit">
                    <?= $setup ? 'Create account & sign in' : 'Sign in' ?>
                    <?= mcg_icon('arrow-right') ?>
                </button>
            </form>

            <p class="auth-foot">
                This area is for MCG Biokinetics staff only. Sessions expire after two hours of inactivity.
            </p>
            <a class="auth-return" href="../index.html"><?= mcg_icon('arrow-right') ?> Return to the website</a>
        </section>
    </div>
</body>

</html>
