<?php
/**
 * Sign out securely.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mcg_check_csrf();
    mcg_logout_user();
    header('Location: login.php?logged_out=1');
    exit;
}

header('Location: login.php');
exit;
