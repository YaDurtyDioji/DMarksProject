<?php
// backend/gcal-helper.php
require_once __DIR__ . '/db.php';

function getGoogleAccessToken($pdo) {
    $clientId = getSetting($pdo, 'gcal_client_id');
    $clientSecret = getSetting($pdo, 'gcal_client_secret');
    $refreshToken = getSetting($pdo, 'gcal_refresh_token');

    if (!$clientId || !$clientSecret || !$refreshToken) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

function syncBookingToGcal($pdo, $bookingId) {
    // Fetch booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) return false;

    $accessToken = getGoogleAccessToken($pdo);
    if (!$accessToken) return false;

    $calendarId = getSetting($pdo, 'gcal_calendar_id', 'primary');
    
    // Determine Start and End Times
    $startDateTime = $booking['booking_date'] . 'T' . $booking['booking_time'] . ':00';
    // Assume 1 hour duration by default if not set
    $endTimestamp = strtotime($startDateTime) + 3600; 
    $endDateTime = date('Y-m-d\TH:i:s', $endTimestamp);
    
    $timeZone = 'Africa/Johannesburg'; // Default for SA

    $eventData = [
        'summary' => $booking['client_name'] . ' - ' . $booking['service'],
        'description' => "Phone: {$booking['client_phone']}\nEmail: {$booking['client_email']}\nStatus: {$booking['status']}\nTotal: R{$booking['total_amount']}",
        'start' => [
            'dateTime' => $startDateTime,
            'timeZone' => $timeZone,
        ],
        'end' => [
            'dateTime' => $endDateTime,
            'timeZone' => $timeZone,
        ],
    ];

    $ch = curl_init();
    $headers = [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ];

    if (!empty($booking['gcal_event_id'])) {
        // Update Event
        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . $booking['gcal_event_id']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    } else {
        // Insert Event
        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events");
        curl_setopt($ch, CURLOPT_POST, 1);
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if (($httpCode == 200 || $httpCode == 201) && isset($responseData['id'])) {
        // Save the event ID to DB if it's new
        if (empty($booking['gcal_event_id'])) {
            $upd = $pdo->prepare("UPDATE bookings SET gcal_event_id = ?, synced_to_calendar = 1 WHERE id = ?");
            $upd->execute([$responseData['id'], $bookingId]);
        }
        return true;
    }

    return false;
}

function deleteBookingFromGcal($pdo, $gcalEventId) {
    if (empty($gcalEventId)) return true;

    $accessToken = getGoogleAccessToken($pdo);
    if (!$accessToken) return false;

    $calendarId = getSetting($pdo, 'gcal_calendar_id', 'primary');

    $ch = curl_init();
    $headers = [
        "Authorization: Bearer {$accessToken}"
    ];

    curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . $gcalEventId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode == 204;
}
?>
