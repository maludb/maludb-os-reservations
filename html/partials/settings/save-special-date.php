<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-sd-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-sd-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-sd-no-restaurant">No restaurant selected.</div>';
    exit;
}

$action = trim($_POST['action'] ?? '');
$sdId = (int)($_POST['sd_id'] ?? 0);

require_once '../../../helpers/services/settings.php';

// Handle delete
if ($action === 'delete' && $sdId > 0) {
    try {
        svc_special_dates_delete($restaurantId, $sdId);
    } catch (ServiceError $e) {
        echo '<div class="alert alert-danger" id="save-sd-delete-notfound">Special date not found.</div>';
        exit;
    }

    header('HX-Trigger: refreshSpecialDates');
    echo '<div class="alert alert-success alert-dismissible fade show" id="save-sd-delete-success">
            <i class="feather-check-circle me-1"></i> Special date deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

// Handle save
if ($action !== 'save') {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-sd-action-error">Invalid action.</div>';
    exit;
}

$isEdit = $sdId > 0;

try {
    svc_special_dates_save($restaurantId, $sdId, [
        'special_date'         => $_POST['special_date'] ?? '',
        'date_type'            => $_POST['date_type'] ?? '',
        'label'                => $_POST['label'] ?? '',
        'is_closed'            => isset($_POST['is_closed']),
        'custom_open_time'     => $_POST['custom_open_time'] ?? '',
        'custom_close_time'    => $_POST['custom_close_time'] ?? '',
        'custom_first_seating' => $_POST['custom_first_seating'] ?? '',
        'custom_last_seating'  => $_POST['custom_last_seating'] ?? '',
        'max_covers'           => $_POST['max_covers'] ?? '',
        'outbound_voice_agent' => $_POST['outbound_voice_agent'] ?? '',
        'notes'                => $_POST['notes'] ?? '',
    ]);
} catch (ServiceError $e) {
    // Field codes match the alert ids this page used before (date, type, label, hours, oc, seating, dup)
    $suffix = $e->status === 404 ? 'notfound' : (($e->field ?: 'validation') . '-error');
    echo '<div class="alert alert-danger" id="save-sd-' . htmlspecialchars($suffix) . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-sd-error">An error occurred while saving. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshSpecialDates');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-sd-success">
        <i class="feather-check-circle me-1"></i> Special date ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("special-dates-modal-container").innerHTML="";</script>';
