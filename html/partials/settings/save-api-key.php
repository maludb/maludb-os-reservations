<?php
/**
 * POST handler: create or revoke an API key (Settings → API Keys)
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/api/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="api-keys-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $days = in_array((int)($_POST['days'] ?? 0), [30, 90, 365], true) ? (int)$_POST['days'] : 365;
    if ($name === '') {
        echo '<div class="alert alert-danger" id="api-keys-name-error">Give the key a name.</div>';
        exit;
    }
    $raw = api_create_key(currentUserId(), $restaurantId, mb_substr($name, 0, 100), $days);
    ?>
    <div class="alert alert-success" id="api-keys-created">
        <h6 class="alert-heading" id="api-keys-created-heading"><i class="feather-check-circle me-1"></i> Key “<?php echo htmlspecialchars($name); ?>” created</h6>
        <p class="mb-2" id="api-keys-created-note">Copy it now. It will not be shown again.</p>
        <div class="input-group" id="api-keys-created-group">
            <input type="text" class="form-control font-monospace" id="api-keys-created-value" value="<?php echo htmlspecialchars($raw); ?>" readonly>
            <button class="btn btn-outline-secondary" type="button" id="api-keys-copy-btn"
                    onclick="navigator.clipboard.writeText(document.getElementById('api-keys-created-value').value); this.textContent='Copied';">Copy</button>
        </div>
        <button class="btn btn-sm btn-link px-0 mt-2" id="api-keys-refresh-btn"
                hx-get="/partials/settings/api-keys.php" hx-target="#page-content" hx-swap="innerHTML">I've saved it — refresh the list</button>
    </div>
    <?php
    exit;
}

if ($action === 'revoke') {
    $stmt = db()->prepare("UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND restaurant_id = ? AND revoked_at IS NULL");
    $stmt->execute([(int)($_POST['key_id'] ?? 0), $restaurantId]);
    if ($stmt->rowCount() === 0) {
        echo '<div class="alert alert-warning" id="api-keys-revoke-missing">Key not found or already revoked.</div>';
        exit;
    }
    header('HX-Trigger: refreshApiKeys');
    exit;
}

echo '<div class="alert alert-danger" id="api-keys-action-error">Unknown action.</div>';
