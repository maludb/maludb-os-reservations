<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-section-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-section-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-section-no-restaurant">No restaurant selected.</div>';
    exit;
}

$sectionId = (int)($_POST['section_id'] ?? 0);
$isEdit = $sectionId > 0;

require_once '../../../helpers/services/sections.php';

try {
    svc_section_save($restaurantId, $sectionId, ['is_active' => isset($_POST['is_active'])] + $_POST);
} catch (ServiceError $e) {
    // Keep the per-field alert ids the page used before
    $id = $e->status === 404 ? 'save-section-notfound' : 'save-section-' . ($e->field ?: 'validation') . '-error';
    echo '<div class="alert alert-danger" id="' . $id . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-section-error">An error occurred while saving. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshSectionList');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-section-success">
        <i class="feather-check-circle me-1"></i> Section ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("sections-modal-container").innerHTML="";</script>';
