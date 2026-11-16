<?php
// backend/calendar-events.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT id, client_name, service, booking_date, booking_time, status, total_amount FROM bookings WHERE status NOT IN ('cancelled', 'mistake')");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($bookings as $b) {
        $color = '#0d6efd'; // default blue (confirmed/pending)
        if ($b['status'] === 'completed') {
            $color = '#198754'; // green
        } elseif ($b['status'] === 'no_show') {
            $color = '#dc3545'; // red
        } elseif ($b['status'] === 'reschedule_requested') {
            $color = '#fd7e14'; // orange
        }

        // Standardize date and time ISO string
        $startStr = $b['booking_date'] . 'T' . ($b['booking_time'] ? $b['booking_time'] : '09:00:00');

        $events[] = [
            'id' => $b['id'],
            'title' => $b['client_name'] . ' - ' . $b['service'],
            'start' => $startStr,
            'color' => $color,
            'extendedProps' => [
                'status' => $b['status'],
                'client' => $b['client_name'],
                'service' => $b['service'],
                'time' => $b['booking_time'],
                'amount' => 'R ' . number_format((float)($b['total_amount'] ?? 0), 2)
            ]
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode([]);
}
