<?php
/**
 * Clear Waitlist — marks all active waitlist entries as 'left'
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger" id="waitlist-clear-csrf-error">Invalid security token.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/waitlist.php';
$cleared = svc_waitlist_clear(currentRestaurantId(), currentUserId());

echo '<div class="alert alert-success alert-dismissible fade show" id="waitlist-clear-success">';
echo "Cleared $cleared waitlist " . ($cleared === 1 ? 'entry' : 'entries') . '.';
echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
echo '</div>';

// Trigger waitlist refresh
echo '<script>document.body.dispatchEvent(new Event("refreshWaitlist"));</script>';
