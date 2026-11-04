<?php
/**
 * Contact form intake: validation, spam filtering, rate limiting and storage.
 *
 * Included by /sendEmail.php. It never starts an admin session and never trusts
 * user input. Messages are stored as sanitised plain text; attachments are not
 * accepted. The caller sends the notification email only for legitimate mail.
 */

declare(strict_types=1);

require_once __DIR__ . '/core.php';

function mcg_contact_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function mcg_contact_intake(): array
{
    if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        mcg_contact_fail(405, 'Method not allowed.');
    }

    /* Attachments are not part of this form; reject anything trying to upload. */
    if (!empty($_FILES)) {
        mcg_contact_fail(400, 'Attachments are not accepted by this form.');
    }

    $rawName = $_POST['name'] ?? '';
    $rawEmail = $_POST['email'] ?? '';
    $rawMessage = $_POST['message'] ?? '';
    $honeypot = $_POST['mcg_hp'] ?? '';
    if (!is_string($rawName) || !is_string($rawEmail) || !is_string($rawMessage) || !is_string($honeypot)) {
        mcg_contact_fail(422, 'Please submit the contact form normally.');
    }
    $tooLong = strlen($rawName) > 200 || strlen($rawEmail) > 200 || strlen($rawMessage) > 8000;

    if ($tooLong) {
        mcg_contact_fail(422, 'Your message is too long.');
    }

    $name = mcg_clean_text($rawName, 120);
    $name = trim(str_replace(["\r", "\n", "\t"], ' ', $name));
    $email = trim($rawEmail);
    $message = mcg_clean_multiline($rawMessage, 5000);

    if ($name === '' || !mcg_valid_email($email) || $message === '') {
        mcg_contact_fail(422, 'Please provide a valid name, email address and message.');
    }

    $scoreData = mcg_spam_score($name, $email, $rawMessage);
    $score = (int) $scoreData['score'];
    $reasons = $scoreData['reasons'];
    if ($honeypot !== '') {
        $score = 12;
        $reasons[] = 'Honeypot';
    }

    /* Rate limits: per-IP short window, per-IP daily and global hourly. */
    $ipKey = 'contact:' . mcg_ip_hash();
    $short = mcg_rate_limit($ipKey . ':10m', 3, 600);
    $daily = mcg_rate_limit($ipKey . ':1d', 12, 86400);
    $global = mcg_rate_limit('contact:global:1h', 80, 3600);
    if (!$short['ok'] || !$daily['ok'] || !$global['ok']) {
        $retry = max((int) ($short['retry'] ?? 0), (int) ($daily['retry'] ?? 0), (int) ($global['retry'] ?? 0));
        $minutes = max(1, (int) ceil($retry / 60));
        header('Retry-After: ' . $retry);
        mcg_contact_fail(429, 'Too many messages. Please try again in about ' . $minutes . ' minute(s), or call the practice directly.');
    }

    $spam = $score >= 5;
    $status = $spam ? 'spam' : 'new';
    $id = mcg_store_message([
        'name' => $name,
        'email' => strtolower($email),
        'message' => $message,
        'status' => $status,
        'score' => $score,
        'reasons' => array_values(array_unique($reasons)),
    ]);
    if ($id < 1) {
        mcg_contact_fail(503, 'Your message could not be saved right now. Please call the practice or try again later.');
    }

    return [
        'ok' => true,
        'id' => $id,
        'name' => $name,
        'email' => strtolower($email),
        'message' => $message,
        'spam' => $spam,
        'score' => $score,
        'reasons' => array_values(array_unique($reasons)),
    ];
}
