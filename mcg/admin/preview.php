<?php
/**
 * Read-only preview of a generated page, including drafts.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/page-render.php';
mcg_require_auth();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$page = null;
foreach (mcg_read_data('pages', []) as $item) {
    if ((int) $item['id'] === $id) {
        $page = $item;
        break;
    }
}

if (!$page) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><body style="font-family:system-ui;padding:40px;"><h1>Page not found</h1><p>This managed page does not exist.</p></body></html>';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
echo mcg_render_content_page($page, true);
