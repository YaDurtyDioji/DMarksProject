<?php
/**
 * Public read-only list of approved client reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/core.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$reviews = mcg_read_data('reviews', []);
$approved = [];
foreach ($reviews as $review) {
    if (empty($review['approved'])) {
        continue;
    }
    $approved[] = [
        'name' => (string) ($review['name'] ?? ''),
        'role' => (string) ($review['role'] ?? ''),
        'quote' => (string) ($review['quote'] ?? ''),
        'rating' => (int) ($review['rating'] ?? 5),
        'order' => (int) ($review['order'] ?? 0),
    ];
}
usort($approved, function ($a, $b) {
    return $a['order'] <=> $b['order'];
});

echo json_encode([
    'site' => mcg_setting('site_name', 'MCG Biokinetics'),
    'count' => count($approved),
    'reviews' => $approved,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
