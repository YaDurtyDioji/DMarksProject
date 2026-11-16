<?php
// log.php — silently logs visitor info to TEST/test.txt
// This file is fetched by JS on the main pages. No visible UI element.

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$dir = __DIR__ . '/TEST';
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}

$file = $dir . '/test.txt';
$data = file_get_contents('php://input');

// Enrich with server-side data
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$entry = json_decode($data ?: '{}', true) ?: [];
$entry['serverIp']   = $ip;
$entry['forwarded']  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
$entry['serverTime'] = date('c');
$entry['host']       = $_SERVER['HTTP_HOST'] ?? '';

$line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

@file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

http_response_code(204);