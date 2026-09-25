<?php
/**
 * Event Booking Save — Create a reservation linked to an event
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
$eventId = (int)($_POST['event_id'] ?? 0);

require_once __DIR__ . '/../../../helpers/services/events.php';

try {
    svc_event_book($restaurantId, currentUserId(), $eventId, $_POST);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="event-booking-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="event-booking-error">An error occurred. Please try again.</div>';
    exit;
}

// Reload event detail
header("HX-Retarget: #page-content");
header("HX-Reswap: innerHTML");
$_GET['id'] = $eventId;
include __DIR__ . '/detail.php';
