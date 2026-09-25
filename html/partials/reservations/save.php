<?php
/**
 * POST handler: Create a new reservation
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/availability.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-res-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-res-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-res-no-restaurant">No restaurant selected.</div>';
    exit;
}

require_once '../../../helpers/services/reservations.php';

try {
    $res = svc_reservation_create($restaurantId, currentUserId(), $_POST);
} catch (ServiceError $e) {
    // Keep the per-field alert ids the page used before
    $ids = ['reservation_date' => 'date', 'reservation_time' => 'time', 'party_size' => 'party', 'name' => 'name'];
    $suffix = $ids[$e->field] ?? 'validation';
    echo '<div class="alert alert-danger" id="save-res-' . $suffix . '-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<script>window.scrollTo({top:0,behavior:"smooth"});</script>';
    echo '<div class="alert alert-danger" id="save-res-error">An error occurred while creating the reservation. Please try again.</div>';
    exit;
}

$confirmationCode = $res['confirmation_code'];
$firstName = $res['first_name'];
$lastName = $res['last_name'];
$date = $res['reservation_date'];
$time = $res['reservation_time'];
$partySize = (int)$res['party_size'];
$tableName = $res['table_name'] ?: 'Auto-assign (pending)';

echo '<script>window.scrollTo({top:0,behavior:"smooth"});</script>';
echo '<div class="alert alert-success" id="save-res-success">
            <h5 class="alert-heading"><i class="feather-check-circle me-1"></i> Reservation Created</h5>
            <p class="mb-1"><strong>Confirmation Code:</strong> <code>' . htmlspecialchars($confirmationCode) . '</code></p>
            <p class="mb-1"><strong>Guest:</strong> ' . htmlspecialchars($firstName . ' ' . $lastName) . '</p>
            <p class="mb-1"><strong>Date/Time:</strong> ' . date('l, M j, Y', strtotime($date)) . ' at ' . date('g:ia', strtotime($time)) . '</p>
            <p class="mb-1"><strong>Party Size:</strong> ' . $partySize . '</p>
            <p class="mb-1"><strong>Table:</strong> ' . htmlspecialchars($tableName) . '</p>
            <p class="mb-0"><strong>Status:</strong> <span class="badge bg-primary">Confirmed</span></p>
            <hr>
            <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary"
                    hx-get="/partials/reservations/create-form.php"
                    hx-target="#page-content"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> New Reservation
            </button>
            <button class="btn btn-sm btn-outline-secondary"
                    hx-get="/partials/reservations/calendar.php"
                    hx-target="#page-content"
                    hx-swap="innerHTML">
                <i class="feather-calendar me-1"></i> View Calendar
            </button>
            </div>
          </div>';

