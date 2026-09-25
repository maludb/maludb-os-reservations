<?php
/**
 * Guest Save Handler — Multi-action POST endpoint
 * Actions: save, add_tag, remove_tag, update_pref, update_notes
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$userId = currentUserId();
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

require_once __DIR__ . '/../../../helpers/services/guests.php';

try {
    // --- SAVE (create/update guest) ---
    if ($action === 'save') {
        $fields = ['first_name', 'last_name', 'email', 'phone', 'address_line1', 'city', 'state', 'postal_code',
                   'tags', 'dietary_restrictions', 'allergies', 'seating_preference', 'notes'];
        $data = [];
        foreach ($fields as $f) $data[$f] = $_POST[$f] ?? '';
        // Checkboxes: present = checked
        foreach (['do_not_call', 'do_not_email', 'do_not_text'] as $f) $data[$f] = isset($_POST[$f]);

        if ($id > 0) {
            svc_guest_update($restaurantId, $userId, $id, $data);
        } else {
            svc_guest_create($restaurantId, $userId, $data);
        }

        header('HX-Trigger: refreshGuestList');
        echo '<div class="alert alert-success">Guest saved successfully.</div>';
        echo '<script>document.getElementById("guest-modal-container").innerHTML="";</script>';
        exit;
    }

    // --- ADD / REMOVE TAG ---
    if ($action === 'add_tag') {
        if ($id < 1 || trim($_POST['guest-tag-add-select'] ?? '') === '') {
            echo '<div class="alert alert-warning">Select a tag to add.</div>';
            exit;
        }
        svc_guest_tag($restaurantId, $userId, $id, $_POST['guest-tag-add-select'], true);
        header("HX-Redirect: ");
        $_GET['id'] = $id;
        include __DIR__ . '/detail.php';
        exit;
    }

    if ($action === 'remove_tag') {
        if ($id < 1 || trim($_POST['tag'] ?? '') === '') { exit; }
        svc_guest_tag($restaurantId, $userId, $id, $_POST['tag'], false);
        $_GET['id'] = $id;
        include __DIR__ . '/detail.php';
        exit;
    }

    // --- UPDATE PREFERENCES (inline) ---
    if ($action === 'update_pref') {
        if ($id < 1) { exit; }
        $changes = array_intersect_key($_POST, array_flip(['dietary_restrictions', 'allergies', 'seating_preference', 'favorite_server']));
        if (empty($changes)) { exit; }
        svc_guest_update($restaurantId, $userId, $id, $changes);
        echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-pref-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
        exit;
    }

    // --- UPDATE OPT-OUT FLAGS (inline toggle) ---
    if ($action === 'update_optout') {
        $field = $_POST['field'] ?? '';
        if ($id < 1 || !in_array($field, ['do_not_call', 'do_not_email', 'do_not_text'], true)) { exit; }
        // Checkbox: present = checked (1), absent = unchecked (0)
        svc_guest_update($restaurantId, $userId, $id, [$field => isset($_POST[$field])]);
        echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-optout-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
        exit;
    }

    // --- UPDATE NOTES (inline) ---
    if ($action === 'update_notes') {
        if ($id < 1) { exit; }
        svc_guest_update($restaurantId, $userId, $id, ['notes' => $_POST['notes'] ?? '']);
        echo '<div class="alert alert-success alert-dismissible fade show py-1 px-2 small" id="guest-notes-saved"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>Saved</div>';
        exit;
    }
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="guest-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

echo '<div class="alert alert-danger">Unknown action.</div>';
