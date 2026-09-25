<?php
/**
 * POST handler: Save meal status percentages per restaurant
 * Each status stored as its own row: meal_pct_seated, meal_pct_ordering, etc.
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

require_once '../../../helpers/services/settings.php';

// The form posts pct_<status> for every status; unset fields save as 0 as before
$values = [];
foreach (array_keys(getDefaultMealPercentages()) as $status) {
    $values[$status] = $_POST['pct_' . $status] ?? 0;
}
svc_meal_percentages_update($restaurantId, $values);

echo '<div class="alert alert-success alert-dismissible fade show" id="meal-pct-success">
        <i class="feather-check-circle me-1"></i> Meal progress estimates saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>';
