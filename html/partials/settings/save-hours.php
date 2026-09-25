<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-hours-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-hours-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-hours-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$hourId = (int)($_POST['hour_id'] ?? 0);

require_once '../../../helpers/services/settings.php';

// Handle toggle action
if ($action === 'toggle' && $hourId > 0) {
    try {
        $row = svc_hours_set_active($restaurantId, $hourId);
    } catch (ServiceError $e) {
        echo '<div class="alert alert-danger" id="save-hours-toggle-notfound">Service period not found.</div>';
        exit;
    }

    header('HX-Trigger: refreshHoursList');
    $label = $row['is_active'] ? 'activated' : 'deactivated';
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-toggle-success">
            <i class="feather-check-circle me-1"></i> Service period ' . $label . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle delete action
if ($action === 'delete' && $hourId > 0) {
    try {
        svc_hours_delete($restaurantId, $hourId);
    } catch (ServiceError $e) {
        echo '<div class="alert alert-danger" id="save-hours-delete-notfound">Service period not found.</div>';
        exit;
    }

    header('HX-Trigger: refreshHoursList');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-delete-success">
            <i class="feather-check-circle me-1"></i> Service period deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save (add/edit)
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-hours-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $hourId > 0;

try {
    svc_hours_save($restaurantId, $hourId, [
        'day_of_week'   => $_POST['day_of_week'] ?? '',
        'service_name'  => $_POST['service_name'] ?? '',
        'open_time'     => $_POST['open_time'] ?? '',
        'close_time'    => $_POST['close_time'] ?? '',
        'first_seating' => $_POST['first_seating'] ?? '',
        'last_seating'  => $_POST['last_seating'] ?? '',
        'is_active'     => isset($_POST['is_active']),
    ]);
} catch (ServiceError $e) {
    // Field codes match the alert ids this page used before (day, service, times, oc, fs, ls, fl, dup)
    $suffix = $e->status === 404 ? 'notfound' : (($e->field ?: 'validation') . '-error');
    echo '<div class="alert alert-danger" id="save-hours-' . htmlspecialchars($suffix) . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-hours-error">An error occurred while saving. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshHoursList');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-hours-success">
        <i class="feather-check-circle me-1"></i> Service period ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("hours-modal-container").innerHTML="";</script>';
