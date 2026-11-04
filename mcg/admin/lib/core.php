<?php
/**
 * MCG Biokinetics — admin core
 * Storage, settings, security helpers and data seeding.
 *
 * PHP 8.1+ · no database required · JSON storage.
 */

declare(strict_types=1);

define('MCG_ADMIN_DIR', dirname(__DIR__));
define('MCG_ROOT', dirname(MCG_ADMIN_DIR));
define('MCG_DATA_DIR', MCG_ADMIN_DIR . DIRECTORY_SEPARATOR . 'data');
define('MCG_UPLOAD_DIR', MCG_ADMIN_DIR . DIRECTORY_SEPARATOR . 'uploads');
define('MCG_ADMIN_VERSION', '1.0.0');

/* -------------------------------------------------------------------------
   Small helpers
   ------------------------------------------------------------------------- */

function mcg_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcg_substr(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }
    return substr($value, 0, $length);
}

function mcg_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function mcg_admin_web_path(): string
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '/admin/index.php';
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        return '/';
    }
    return rtrim($dir, '/') . '/';
}

function mcg_now(): string
{
    return date('c');
}

function mcg_format_date($value): string
{
    if (!$value) {
        return '—';
    }
    $time = strtotime((string) $value);
    return $time ? date('d M Y, H:i', $time) : '—';
}

function mcg_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value === '' ? 'page-' . date('YmdHis') : $value;
}

function mcg_clean_text($value, int $max = 2000): string
{
    $value = (string) $value;
    $value = str_replace("\0", '', $value);
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    $value = trim((string) $value);
    return mcg_substr($value, $max);
}

function mcg_clean_multiline($value, int $max = 5000): string
{
    $value = (string) $value;
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = str_replace("\0", '', $value);
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    $value = preg_replace("/\n{4,}/", "\n\n\n", (string) $value);
    return mcg_substr(trim((string) $value), $max);
}

function mcg_valid_email($email): bool
{
    if (!is_string($email) || $email === '' || strlen($email) > 190) {
        return false;
    }
    if (strpos($email, "\n") !== false || strpos($email, "\r") !== false || strpos($email, '<') !== false) {
        return false;
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function mcg_web_url($url): bool
{
    if (!is_string($url) || $url === '') {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    return $scheme === 'http' || $scheme === 'https';
}

function mcg_secret(): string
{
    $path = MCG_DATA_DIR . DIRECTORY_SEPARATOR . 'secret.txt';
    if (is_file($path)) {
        $secret = trim((string) file_get_contents($path));
        if (strlen($secret) >= 32) {
            return $secret;
        }
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($path, $secret, LOCK_EX);
    @chmod($path, 0600);
    return $secret;
}

function mcg_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function mcg_ip_hash(): string
{
    return substr(hash_hmac('sha256', mcg_ip(), mcg_secret()), 0, 24);
}

/* -------------------------------------------------------------------------
   Settings
   ------------------------------------------------------------------------- */

function mcg_default_settings(): array
{
    return [
        'site_name' => 'MCG Biokinetics',
        'site_url' => '',
        'admin_email' => 'admin@mcgbiokinetics.co.za',
        'from_email' => 'admin@mcgbiokinetics.co.za',
        'from_name' => 'MCG Biokinetics',
        'contact_address' => '23 4th Avenue, Parktown North, Randburg, 2193',
        'contact_phone' => '082 448 1890',
        'booking_url' => 'https://bookme.guru/mcgbiokinetics/',
        'instagram' => 'https://www.instagram.com/mcgbiokineticist/',
        'facebook' => '',
        'whatsapp' => 'https://wa.me/27744436580',
        'linkedin' => '',
        'youtube' => '',
        'gallery_intro' => 'A look at the space, the sessions and the movement that happens at MCG Biokinetics.',
        'mail_batch_size' => '25',
        'mail_max_recipients' => '300',
    ];
}

function mcg_settings(): array
{
    if (!isset($GLOBALS['mcg_settings_cache'])) {
        $saved = mcg_read_data('settings', []);
        $GLOBALS['mcg_settings_cache'] = array_merge(mcg_default_settings(), is_array($saved) ? $saved : []);
    }
    return $GLOBALS['mcg_settings_cache'];
}

function mcg_setting(string $key, string $default = ''): string
{
    $settings = mcg_settings();
    $value = isset($settings[$key]) ? $settings[$key] : $default;
    return is_scalar($value) ? (string) $value : $default;
}

function mcg_save_settings(array $settings): bool
{
    $clean = [];
    foreach (mcg_default_settings() as $key => $default) {
        $clean[$key] = isset($settings[$key]) ? mcg_clean_text($settings[$key], 500) : $default;
    }
    $GLOBALS['mcg_settings_cache'] = $clean;
    return mcg_write_data('settings', $clean);
}

/* -------------------------------------------------------------------------
   JSON storage
   ------------------------------------------------------------------------- */

function mcg_data_path(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid data file name.');
    }
    return MCG_DATA_DIR . DIRECTORY_SEPARATOR . $name . '.json';
}

function mcg_read_data(string $name, $default = [])
{
    $path = mcg_data_path($name);
    if (!is_file($path)) {
        return $default;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function mcg_write_data(string $name, $data): bool
{
    $path = mcg_data_path($name);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function mcg_next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        if (isset($row['id']) && (int) $row['id'] > $max) {
            $max = (int) $row['id'];
        }
    }
    return $max + 1;
}

/* -------------------------------------------------------------------------
   First-run storage, protection and seed content
   ------------------------------------------------------------------------- */

function mcg_storage_init(): void
{
    foreach ([MCG_DATA_DIR, MCG_UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    $dataHtaccess = MCG_DATA_DIR . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($dataHtaccess)) {
        @file_put_contents($dataHtaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $uploadHtaccess = MCG_UPLOAD_DIR . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($uploadHtaccess)) {
        @file_put_contents($uploadHtaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php3|php4|php5|php7|pl|py|cgi|sh|exe|html?|js)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    foreach ([MCG_DATA_DIR, MCG_UPLOAD_DIR] as $dir) {
        $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }

    if (!is_file(mcg_data_path('settings'))) {
        mcg_write_data('settings', mcg_default_settings());
    }
    foreach (['pages', 'messages', 'subscribers', 'mail-log', 'rate-limits'] as $name) {
        if (!is_file(mcg_data_path($name))) {
            mcg_write_data($name, []);
        }
    }
    if (!is_file(mcg_data_path('users'))) {
        mcg_write_data('users', []);
    }

    if (!is_file(mcg_data_path('gallery'))) {
        mcg_write_data('gallery', mcg_seed_gallery());
    }
    if (!is_file(mcg_data_path('reviews'))) {
        mcg_write_data('reviews', mcg_seed_reviews());
    }
}

function mcg_seed_gallery(): array
{
    $categories = [
        ['id' => 1, 'name' => 'Practice', 'order' => 10],
        ['id' => 2, 'name' => 'Sessions', 'order' => 20],
        ['id' => 3, 'name' => 'Studio', 'order' => 30],
    ];
    $seed = [
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(1).jpeg', 'Hands-on assessment', 1],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(2).jpeg', 'Mat work in the studio', 2],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(3).jpeg', 'Mobility and control', 2],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(7).jpeg', 'Strength and stability', 2],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(9).jpeg', 'Guided rehabilitation', 2],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(10).jpeg', 'Individual sessions', 2],
        ['images/WhatsApp Image 2024-03-06 at 06.42.49(6).jpeg', 'Posture and alignment', 2],
        ['images/GroupExercise1.jpg', 'Group exercise', 1],
        ['images/whatisbio1.jpg', 'Movement practice', 3],
    ];
    $photos = [];
    $id = 1;
    foreach ($seed as $index => $row) {
        if (!is_file(MCG_ROOT . DIRECTORY_SEPARATOR . $row[0])) {
            continue;
        }
        $photos[] = [
            'id' => $id,
            'path' => $row[0],
            'alt' => $row[1] . ' at MCG Biokinetics',
            'caption' => $row[1],
            'category_id' => $row[2],
            'order' => ($index + 1) * 10,
            'created' => mcg_now(),
        ];
        $id++;
    }
    return ['categories' => $categories, 'photos' => $photos];
}

function mcg_seed_reviews(): array
{
    return [
        [
            'id' => 1,
            'name' => 'Patient',
            'role' => 'MCG Biokinetics',
            'quote' => 'I just want to commend you and your team on your continued efforts in helping people and healing people. May the Almighty continue your success in this world and hereafter.',
            'rating' => 5,
            'approved' => true,
            'order' => 10,
            'created' => mcg_now(),
        ],
        [
            'id' => 2,
            'name' => 'Patient',
            'role' => 'MCG Biokinetics',
            'quote' => 'May Almighty continue to reward you and your family.',
            'rating' => 5,
            'approved' => true,
            'order' => 20,
            'created' => mcg_now(),
        ],
        [
            'id' => 3,
            'name' => 'Parent',
            'role' => 'Of a young patient',
            'quote' => 'Jazakallah for all the help you giving my daughter I am so happy with Caitlyn and the way she dealing with Aisha.',
            'rating' => 5,
            'approved' => true,
            'order' => 30,
            'created' => mcg_now(),
        ],
    ];
}

/* -------------------------------------------------------------------------
   Rates, spam and messages
   ------------------------------------------------------------------------- */

function mcg_rate_limit(string $key, int $max, int $window): array
{
    $data = mcg_read_data('rate-limits', []);
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    $times = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    $valid = [];
    foreach ($times as $stamp) {
        if (is_numeric($stamp) && (int) $stamp > ($now - $window)) {
            $valid[] = (int) $stamp;
        }
    }
    $valid = array_values(array_filter($valid, function ($stamp) use ($now, $window) {
        return $stamp > ($now - $window);
    }));
    if (count($valid) >= $max) {
        $retry = 1;
        if (!empty($valid)) {
            $retry = max(1, ((int) $valid[0] + $window) - $now);
        }
        $data[$key] = $valid;
        if (!mcg_write_data('rate-limits', $data)) {
            return ['ok' => false, 'retry' => 60];
        }
        return ['ok' => false, 'retry' => $retry];
    }
    $valid[] = $now;
    $data[$key] = $valid;
    /* keep the file bounded */
    if (count($data) > 400) {
        $data = array_slice($data, -400, null, true);
    }
    if (!mcg_write_data('rate-limits', $data)) {
        return ['ok' => false, 'retry' => 60];
    }
    return ['ok' => true, 'remaining' => max(0, $max - count($valid))];
}

function mcg_rate_clear(string $key): void
{
    $data = mcg_read_data('rate-limits', []);
    if (isset($data[$key])) {
        unset($data[$key]);
        mcg_write_data('rate-limits', $data);
    }
}

function mcg_spam_score(string $name, string $email, string $message): array
{
    $score = 0;
    $reasons = [];
    $haystack = strtolower($name . ' ' . $email . ' ' . $message);

    $links = preg_match_all('#https?://#i', $message);
    if ($links >= 2) {
        $score += 2;
        $reasons[] = 'Multiple links';
    }
    if ($links >= 5) {
        $score += 3;
        $reasons[] = 'Many links';
    }

    $spamWords = ['viagra', 'cialis', 'casino', 'porn', 'sex', 'loan', 'crypto', 'bitcoin', 'seo services', 'buy now', 'click here', 'work from home', 'bulk email', 'telegram', 'whatsapp me'];
    foreach ($spamWords as $word) {
        if (strpos($haystack, $word) !== false) {
            $score += 2;
            $reasons[] = 'Spam phrase';
            break;
        }
    }

    $suspicious = ['<script', '<?php', 'javascript:', 'onerror=', 'onload=', 'data:text/html', 'eval('];
    foreach ($suspicious as $needle) {
        if (strpos(strtolower($message), $needle) !== false) {
            $score += 6;
            $reasons[] = 'Code-like content';
            break;
        }
    }

    $domain = '';
    $at = strrpos($email, '@');
    if ($at !== false) {
        $domain = strtolower(substr($email, $at + 1));
    }
    $disposable = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'yopmail.com', 'sharklasers.com'];
    if (in_array($domain, $disposable, true)) {
        $score += 3;
        $reasons[] = 'Disposable email';
    }

    if (preg_match('/[A-Z]{12,}/', $name . $message)) {
        $score += 1;
        $reasons[] = 'Shouting';
    }

    return ['score' => min(12, $score), 'reasons' => array_values(array_unique($reasons))];
}

function mcg_store_message(array $data): int
{
    $messages = mcg_read_data('messages', []);
    if (!is_array($messages)) {
        $messages = [];
    }
    $id = mcg_next_id($messages);
    $messages[] = [
        'id' => $id,
        'name' => $data['name'],
        'email' => $data['email'],
        'message' => $data['message'],
        'ip_hash' => mcg_ip_hash(),
        'user_agent' => mcg_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 220),
        'created' => mcg_now(),
        'read' => false,
        'status' => $data['status'],
        'spam_score' => $data['score'],
        'spam_reasons' => $data['reasons'],
        'email_sent' => false,
    ];
    return mcg_write_data('messages', $messages) ? $id : 0;
}

function mcg_update_message(int $id, array $changes): bool
{
    $messages = mcg_read_data('messages', []);
    $found = false;
    foreach ($messages as $index => $message) {
        if ((int) $message['id'] === $id) {
            $messages[$index] = array_merge($message, $changes);
            $found = true;
            break;
        }
    }
    return $found ? mcg_write_data('messages', $messages) : false;
}

function mcg_unread_count(): int
{
    $count = 0;
    foreach (mcg_read_data('messages', []) as $message) {
        if (isset($message['status']) && $message['status'] !== 'read' && $message['status'] !== 'spam') {
            $count++;
        }
    }
    return $count;
}

/* -------------------------------------------------------------------------
   Media helpers
   ------------------------------------------------------------------------- */

function mcg_media_url(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if (strpos($relativePath, '..') !== false) {
        return '';
    }
    $parts = explode('/', $relativePath);
    $encoded = array_map(function ($part) {
        return rawurlencode($part);
    }, $parts);
    return '../' . implode('/', $encoded);
}

function mcg_absolute_url(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $site = rtrim(mcg_setting('site_url'), '/');
    if ($site !== '') {
        return $site . '/' . $relativePath;
    }
    $scheme = mcg_is_https() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '/admin/index.php';
    $root = dirname(dirname($script));
    if ($root === '/' || $root === '.' || $root === '\\') {
        $root = '';
    }
    return $scheme . '://' . $host . rtrim($root, '/') . '/' . $relativePath;
}

function mcg_handle_image_upload(array $file, string $prefix = 'image'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file selected.'];
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . (int) $file['error'] . ').'];
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload could not be verified.'];
    }
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image is larger than 8 MB.'];
    }
    if (!is_dir(MCG_UPLOAD_DIR) || !is_writable(MCG_UPLOAD_DIR)) {
        return ['ok' => false, 'error' => 'The uploads folder is not writable.'];
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($file['tmp_name']);
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'error' => 'Only JPEG, PNG or WebP images are allowed.'];
    }
    $size = @getimagesize($file['tmp_name']);
    if ($size === false) {
        return ['ok' => false, 'error' => 'The file is not a valid image.'];
    }
    if (((int) $size[0] * (int) $size[1]) > 40000000) {
        return ['ok' => false, 'error' => 'The image dimensions are too large to process safely.'];
    }
    if (!function_exists('imagecreatefromstring')) {
        return ['ok' => false, 'error' => 'The PHP GD extension is required to process uploads safely.'];
    }

    $data = @file_get_contents($file['tmp_name']);
    if ($data === false) {
        return ['ok' => false, 'error' => 'Could not read the upload.'];
    }
    $source = @imagecreatefromstring($data);
    if (!$source) {
        return ['ok' => false, 'error' => 'The image could not be decoded.'];
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width < 1 || $height < 1) {
        return ['ok' => false, 'error' => 'The image has invalid dimensions.'];
    }
    if (($width * $height) > 40000000) {
        return ['ok' => false, 'error' => 'The image dimensions are too large to process safely.'];
    }
    $maxSide = 2400;
    $scale = min(1, $maxSide / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $safePrefix = preg_replace('/[^a-z0-9-]/', '-', strtolower($prefix));
    $filename = ($safePrefix !== '' ? $safePrefix : 'image') . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $target = MCG_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!imagejpeg($canvas, $target, 86)) {
        return ['ok' => false, 'error' => 'Could not save the processed image.'];
    }
    @chmod($target, 0644);

    return ['ok' => true, 'path' => 'admin/uploads/' . $filename];
}

function mcg_delete_media(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if (strpos($relativePath, 'admin/uploads/') !== 0) {
        return false;
    }
    $full = MCG_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $real = realpath($full);
    $dir = realpath(MCG_UPLOAD_DIR);
    if ($real === false || $dir === false) {
        return false;
    }
    if (strpos($real, $dir . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }
    return @unlink($real);
}

/* -------------------------------------------------------------------------
   Subscribers
   ------------------------------------------------------------------------- */

function mcg_add_subscriber($email, $name = '', $source = 'manual'): array
{
    $email = is_string($email) ? strtolower(trim($email)) : '';
    $name = is_string($name) ? $name : '';
    $source = is_string($source) ? $source : 'manual';
    if (!mcg_valid_email($email)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    $subscribers = mcg_read_data('subscribers', []);
    foreach ($subscribers as $subscriber) {
        if (strtolower((string) $subscriber['email']) === $email) {
            return ['ok' => false, 'error' => 'That email address is already subscribed.'];
        }
    }
    $subscribers[] = [
        'id' => mcg_next_id($subscribers),
        'name' => mcg_clean_text($name, 120),
        'email' => $email,
        'source' => mcg_clean_text($source, 40),
        'active' => true,
        'created' => mcg_now(),
    ];
    mcg_write_data('subscribers', $subscribers);
    return ['ok' => true, 'error' => ''];
}

mcg_storage_init();
