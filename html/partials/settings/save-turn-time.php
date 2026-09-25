<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-tt-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-tt-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-tt-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$ttId = (int)($_POST['tt_id'] ?? 0);

require_once '../../../helpers/services/settings.php';

// Handle delete
if ($action === 'delete' && $ttId > 0) {
    try {
        svc_turn_times_delete($restaurantId, $ttId);
    } catch (ServiceError $e) {
        echo '<div class="alert alert-danger" id="save-tt-delete-notfound">Turn time not found.</div>';
        exit;
    }

    header('HX-Trigger: refreshTurnTimes');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-tt-delete-success">
            <i class="feather-check-circle me-1"></i> Turn time deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-tt-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $ttId > 0;

try {
    svc_turn_times_save($restaurantId, $ttId, [
        'min_party_size'   => $_POST['min_party_size'] ?? 0,
        'max_party_size'   => $_POST['max_party_size'] ?? 0,
        'service_period'   => $_POST['service_period'] ?? '',
        'duration_minutes' => $_POST['duration_minutes'] ?? 0,
        'buffer_minutes'   => $_POST['buffer_minutes'] ?? 0,
    ]);
} catch (ServiceError $e) {
    // Field codes match the alert ids this page used before (size, range, service, duration, buffer, dup)
    $suffix = $e->status === 404 ? 'notfound' : (($e->field ?: 'validation') . '-error');
    echo '<div class="alert alert-danger" id="save-tt-' . htmlspecialchars($suffix) . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-tt-error">An error occurred while saving. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshTurnTimes');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-tt-success">
        <i class="feather-check-circle me-1"></i> Turn time ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("turn-times-modal-container").innerHTML="";</script>';
