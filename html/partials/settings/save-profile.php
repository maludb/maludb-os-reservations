<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-profile-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-profile-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-profile-no-restaurant">No restaurant selected.</div>';
    exit;
}

require_once '../../../helpers/services/settings.php';

try {
    $profile = svc_settings_profile_update($restaurantId, currentUserId(), [
        'name'          => $_POST['name'] ?? '',
        'timezone'      => $_POST['timezone'] ?? '',
        'email'         => $_POST['email'] ?? '',
        'website'       => $_POST['website'] ?? '',
        'phone'         => $_POST['phone'] ?? '',
        'address_line1' => $_POST['address_line1'] ?? '',
        'city'          => $_POST['city'] ?? '',
        'state'         => $_POST['state'] ?? '',
        'postal_code'   => $_POST['postal_code'] ?? '',
    ]);
} catch (ServiceError $e) {
    // Field codes match the alert ids this page used before (name, tz, email, url)
    echo '<div class="alert alert-danger" id="save-profile-' . htmlspecialchars($e->field ?: 'validation') . '-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
$name = $profile['name'];

// Update session with new restaurant name
$_SESSION['current_restaurant_name'] = $name;

echo '<div class="alert alert-success alert-dismissible fade show" id="save-profile-success">
        <i class="feather-check-circle me-1"></i> Restaurant profile updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
