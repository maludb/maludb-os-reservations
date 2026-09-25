<?php
/**
 * POST handler: Update reservation (edit, reassign table, update notes)
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$resId = (int)($_POST['reservation_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

require_once '../../../helpers/services/reservations.php';

// Each inline action changes a subset of fields; 'edit' changes them all
$changes = match ($action) {
    'reassign_table'  => ['table_id' => $_POST['table_id'] ?? 0],
    'update_requests' => ['special_requests' => $_POST['special_requests'] ?? ''],
    'update_notes'    => ['internal_notes' => $_POST['internal_notes'] ?? ''],
    'edit'            => [
        'reservation_date'  => $_POST['reservation_date'] ?? '',
        'reservation_time'  => $_POST['reservation_time'] ?? '',
        'party_size'        => $_POST['party_size'] ?? 0,
        'table_id'          => $_POST['table_id'] ?? 0,
        'turn_time_minutes' => trim($_POST['turn_time_minutes'] ?? ''),
        'special_requests'  => $_POST['special_requests'] ?? '',
        'internal_notes'    => $_POST['internal_notes'] ?? '',
    ],
    default => null,
};

if ($changes === null) {
    echo '<div class="alert alert-danger">Invalid action.</div>';
    exit;
}

try {
    svc_reservation_update($restaurantId, currentUserId(), $resId, $changes);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="update-res-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="update-res-error">An error occurred while updating.</div>';
    exit;
}

$messages = [
    'reassign_table'  => ['update-table-success', 'Table updated.'],
    'update_requests' => ['update-requests-success', 'Special requests updated.'],
    'update_notes'    => ['update-notes-success', 'Notes updated.'],
];
if (isset($messages[$action])) {
    [$msgId, $msg] = $messages[$action];
    echo '<div class="alert alert-success alert-dismissible fade show" id="' . $msgId . '">
            <i class="feather-check-circle me-1"></i> ' . $msg . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    exit;
}

// Full edit: reload the detail page into #page-content
$_GET['id'] = $resId;
include __DIR__ . '/detail.php';
