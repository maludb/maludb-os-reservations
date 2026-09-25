<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="toggle-section-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="toggle-section-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$sectionId = (int)($_POST['section_id'] ?? 0);

if (!$restaurantId || !$sectionId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="toggle-section-invalid">Invalid request.</div>';
    exit;
}

require_once '../../../helpers/services/sections.php';

try {
    $section = svc_section_set_active($restaurantId, $sectionId);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="toggle-section-notfound">Section not found.</div>';
    exit;
}

$statusLabel = $section['is_active'] ? 'activated' : 'deactivated';

header('HX-Trigger: refreshSectionList');

echo '<div class="alert alert-success alert-dismissible fade show" id="toggle-section-success">
        <i class="feather-check-circle me-1"></i> Section ' . $statusLabel . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
