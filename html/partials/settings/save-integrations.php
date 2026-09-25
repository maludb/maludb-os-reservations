<?php
/**
 * Save voice agent / API integration settings
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-integrations-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-integrations-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$action = $_POST['action'] ?? '';
$pdo = db();

require_once __DIR__ . '/../../../helpers/services/settings.php';

if ($action === 'save_keys') {
    svc_integrations_update($restaurantId, [
        'retell_api_key'  => $_POST['retell_api_key'] ?? '',
        'retell_agent_id' => $_POST['retell_agent_id'] ?? '',
        'mcp_api_key'     => $_POST['mcp_api_key'] ?? '',
        'openai_api_key'  => $_POST['openai_api_key'] ?? '',
    ]);

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-integrations-success">
            <i class="feather-check-circle me-1"></i> API keys saved successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

if ($action === 'save_prompt_sections') {
    svc_integrations_update($restaurantId, [
        'voice_agent_greeting'    => $_POST['voice_agent_greeting'] ?? '',
        'voice_agent_specials'    => $_POST['voice_agent_specials'] ?? '',
        'voice_agent_custom_info' => $_POST['voice_agent_custom_info'] ?? '',
    ]);

    echo '<div class="alert alert-success alert-dismissible fade show" id="save-integrations-prompt-success">
            <i class="feather-check-circle me-1"></i> Voice agent prompt sections saved successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    exit;
}

if ($action === 'test') {
    // Test check_availability using the voice API directly
    require_once __DIR__ . '/../../../helpers/voice-api.php';

    $stmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $restaurant = $stmt->fetch();
    $slug = $restaurant['slug'] ?? '';

    $result = voiceCheckAvailability($slug, date('Y-m-d', strtotime('+1 day')), 2);

    if ($result['success']) {
        $slotCount = count($result['available_slots'] ?? []);
        echo '<div class="alert alert-success" id="integrations-test-success">
                <i class="feather-check-circle me-1"></i>
                <strong>API is working!</strong> Found ' . $slotCount . ' available slot(s) for tomorrow, party of 2.
              </div>';
        if ($slotCount > 0 && !empty($result['slots_by_period'])) {
            echo '<pre class="bg-light p-2 rounded small" id="integrations-test-output">' . htmlspecialchars(json_encode($result['slots_by_period'], JSON_PRETTY_PRINT)) . '</pre>';
        }
    } else {
        echo '<div class="alert alert-warning" id="integrations-test-warning">
                <i class="feather-alert-triangle me-1"></i>
                API responded: ' . htmlspecialchars($result['error'] ?? $result['message'] ?? 'Unknown') . '
              </div>';
    }
    exit;
}

echo '<div class="alert alert-danger" id="save-integrations-action-error">Unknown action.</div>';
