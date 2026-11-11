<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = require_login();          // every API call requires a session

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? '';

// CSRF only on state-changing verbs.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
}

try {
    switch ($action) {

        case 'filters':
            echo json_encode(get_filter_options());
            break;

        case 'search':
            echo json_encode(search_exercises(
                trim((string) ($_GET['q'] ?? '')),
                trim((string) ($_GET['bodyPart'] ?? '')),
                trim((string) ($_GET['equipment'] ?? ''))
            ));
            break;

        case 'routines':
            echo json_encode(list_routines((int) $user['id']));
            break;

        case 'routine':
            $routine = get_routine((int) ($_GET['id'] ?? 0), (int) $user['id']);
            if ($routine === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Routine not found']);
            } else {
                echo json_encode($routine);
            }
            break;

        case 'save-routine':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'POST required']);
                break;
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload) || empty($payload['name']) || !isset($payload['exercises'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Body must contain name and exercises[]']);
                break;
            }
            $id = save_routine(
                (string) $payload['name'],
                $payload['exercises'],
                (int) $user['id'],
                isset($payload['id']) ? (int) $payload['id'] : null
            );
            echo json_encode(['routine_id' => $id]);
            break;

        case 'delete-routine':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'POST required']);
                break;
            }
            $ok = delete_routine((int) ($_GET['id'] ?? 0), (int) $user['id']);
            echo json_encode(['deleted' => $ok]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log('[openGym] API error: ' . $e->getMessage());
}