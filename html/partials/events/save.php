<?php
/**
 * Event Save Handler — Create, update, cancel events
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

require_once __DIR__ . '/../../../helpers/services/events.php';

try {
    // --- SAVE (create/update) ---
    if ($action === 'save') {
        $data = [];
        foreach (['name', 'event_date', 'start_time', 'end_time', 'max_capacity', 'price_per_person', 'status', 'description', 'notes'] as $f) {
            $data[$f] = $_POST[$f] ?? '';
        }
        $data['is_public'] = isset($_POST['is_public']);
        $event = svc_event_save($restaurantId, currentUserId(), $id, $data);

        // Redirect to event detail
        header("HX-Redirect: ");
        $_GET['id'] = $event['id'];
        include __DIR__ . '/detail.php';
        exit;
    }

    // --- CANCEL ---
    if ($action === 'cancel') {
        if ($id < 1) { exit; }
        svc_event_cancel($restaurantId, currentUserId(), $id);

        header("HX-Redirect: ");
        $_GET['id'] = $id;
        include __DIR__ . '/detail.php';
        exit;
    }
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="event-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

echo '<div class="alert alert-danger">Unknown action.</div>';
