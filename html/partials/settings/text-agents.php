<?php
/**
 * Text Agent Management
 * Admins and super-admins can manage text/SMS agent prompts per restaurant.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$restaurantId = currentRestaurantId();
$pdo = db();

// Fetch all text agent prompts for this restaurant
$stmt = $pdo->prepare(
    "SELECT * FROM text_agent_prompts WHERE restaurant_id = ? ORDER BY created_at ASC"
);
$stmt->execute([$restaurantId]);
$prompts = $stmt->fetchAll();

// Fetch prompt details (variables) grouped by prompt id
$promptDetails = [];
if (!empty($prompts)) {
    $promptIds = array_column($prompts, 'id');
    $placeholders = implode(',', array_fill(0, count($promptIds), '?'));
    $detStmt = $pdo->prepare(
        "SELECT * FROM text_agent_prompt_details WHERE text_agent_prompt_id IN ({$placeholders}) ORDER BY id ASC"
    );
    $detStmt->execute($promptIds);
    foreach ($detStmt->fetchAll() as $d) {
        $promptDetails[$d['text_agent_prompt_id']][] = $d;
    }
}

// Build base URL for webhook display
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$baseUrl = "{$protocol}://{$host}";
?>

<div class="p-4" id="text-agents-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="text-agents-header">
        <h4 class="fw-bold mb-0" id="text-agents-title">
            <i class="feather-message-square me-2"></i>Text Agents
        </h4>
        <button class="btn btn-primary" id="text-agents-add-btn"
                onclick="editTextAgent('', '', '', '', '', '', [])">
            <i class="feather-plus me-1"></i> Add Text Agent
        </button>
    </div>

    <div id="text-agents-feedback"></div>

    <!-- Add/Edit Form (hidden by default) -->
    <div class="card mb-4" id="text-agents-form-card" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center" id="text-agents-form-header">
            <h6 class="mb-0" id="text-agent-form-title">Add Text Agent</h6>
            <button type="button" class="btn-close" id="text-agents-form-close"
                    onclick="document.getElementById('text-agents-form-card').style.display='none';"></button>
        </div>
        <div class="card-body" id="text-agents-form-body">
            <form hx-post="/partials/settings/save-text-agent.php"
                  hx-target="#text-agents-feedback"
                  hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshTextAgents'); }"
                  id="text-agent-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="text-agent-id" value="">

                <div class="row g-3" id="text-agent-fields">
                    <div class="col-md-6" id="text-agent-phone-wrap">
                        <label for="text-agent-phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="text-agent-phone" name="phone_number"
                               required placeholder="+18005551234">
                        <small class="text-muted">The SMS phone number for this agent (also used as the Agent ID).</small>
                    </div>
                    <div class="col-md-6" id="text-agent-description-wrap">
                        <label for="text-agent-description" class="form-label fw-semibold">Description</label>
                        <input type="text" class="form-control" id="text-agent-description" name="description"
                               placeholder="e.g. Reservation confirmations, After-hours auto-reply">
                    </div>
                    <div class="col-12" id="text-agent-webhook-wrap">
                        <label for="text-agent-webhook" class="form-label fw-semibold">Custom Webhook</label>
                        <input type="url" class="form-control" id="text-agent-webhook" name="custom_webhook"
                               placeholder="https://example.com/api/webhook">
                        <small class="text-muted">URL to fetch company and client context (same as voice agent webhook).</small>
                    </div>
                    <div class="col-12" id="text-agent-system-prompt-wrap">
                        <label for="text-agent-system-prompt" class="form-label fw-semibold">System Prompt</label>
                        <textarea class="form-control" id="text-agent-system-prompt" name="system_prompt"
                                  rows="6" placeholder="You are an SMS assistant for ..."></textarea>
                        <small class="text-muted">The system prompt that guides the AI agent's behavior when responding to messages.</small>
                    </div>
                </div>

                <div class="col-12 mt-3" id="text-agent-variables-wrap">
                    <label class="form-label fw-semibold">Custom Variables</label>
                    <small class="text-muted d-block mb-2">Define variable name/value pairs available to the text agent when processing messages.</small>
                    <div id="text-agent-variables-list"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="text-agent-add-variable-btn"
                            onclick="addTextAgentVariableRow()">
                        <i class="feather-plus me-1"></i> Add Variable
                    </button>
                </div>

                <div class="mt-3 d-flex gap-2" id="text-agent-submit-wrap">
                    <button type="submit" class="btn btn-primary" id="text-agent-save-btn">
                        <i class="feather-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="text-agent-cancel-btn"
                            onclick="document.getElementById('text-agents-form-card').style.display='none';">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Agents List -->
    <div class="card mb-4" id="text-agents-list-card"
         hx-get="/partials/settings/text-agents.php"
         hx-trigger="refreshTextAgents from:body"
         hx-target="#text-agents-page"
         hx-swap="outerHTML"
         hx-select="#text-agents-page">
        <div class="card-header" id="text-agents-list-header">
            <h6 class="mb-0" id="text-agents-list-title">Configured Text Agents (<?php echo count($prompts); ?>)</h6>
        </div>
        <div class="card-body p-0" id="text-agents-list-body">
            <?php if (empty($prompts)): ?>
            <div class="text-center py-5 text-muted" id="text-agents-empty">
                <i class="feather-message-circle fs-1 d-block mb-2"></i>
                <p class="mb-0">No text agents configured yet.</p>
                <small>Click "Add Text Agent" to create one.</small>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="text-agents-table-wrap">
                <table class="table table-hover mb-0" id="text-agents-table">
                    <thead id="text-agents-thead">
                        <tr>
                            <th>Phone</th>
                            <th>Description</th>
                            <th>Webhook</th>
                            <th>System Prompt</th>
                            <th>Variables</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="text-agents-tbody">
                        <?php foreach ($prompts as $p): ?>
                        <tr id="text-agent-row-<?php echo $p['id']; ?>">
                            <td><code class="user-select-all"><?php echo htmlspecialchars($p['phone_number']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['description'] ?: '—'); ?></td>
                            <td>
                                <?php if ($p['custom_webhook']): ?>
                                <span title="<?php echo htmlspecialchars($p['custom_webhook']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($p['custom_webhook'], 0, 40)); ?><?php echo mb_strlen($p['custom_webhook']) > 40 ? '...' : ''; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['system_prompt']): ?>
                                <span title="<?php echo htmlspecialchars($p['system_prompt']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($p['system_prompt'], 0, 60)); ?><?php echo mb_strlen($p['system_prompt']) > 60 ? '...' : ''; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td id="text-agent-vars-cell-<?php echo $p['id']; ?>">
                                <?php
                                $details = $promptDetails[$p['id']] ?? [];
                                $activeCount = count(array_filter($details, fn($d) => $d['is_active']));
                                ?>
                                <?php if ($activeCount > 0): ?>
                                <span class="badge bg-primary"><?php echo $activeCount; ?></span>
                                <small class="text-muted ms-1"><?php echo implode(', ', array_map(fn($d) => $d['variable_name'], array_filter($details, fn($d) => $d['is_active']))); ?></small>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" id="text-agent-actions-<?php echo $p['id']; ?>">
                                    <button class="btn btn-outline-primary" title="Edit"
                                            onclick="editTextAgent(<?php echo $p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['phone_number'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['description'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['custom_webhook'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['system_prompt'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($details)); ?>)">
                                        <i class="feather-edit-2"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            hx-post="/partials/settings/delete-text-agent.php"
                                            hx-vals='<?php echo json_encode(["id" => $p['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#text-agents-feedback"
                                            hx-swap="innerHTML"
                                            hx-confirm="Delete this text agent?"
                                            hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshTextAgents'); }">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function addTextAgentVariableRow(name, value, detailId) {
    var list = document.getElementById('text-agent-variables-list');
    var idx = list.children.length;
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center';
    row.id = 'text-agent-var-row-' + idx;
    row.innerHTML =
        '<input type="hidden" name="detail_ids[]" value="' + (detailId || '') + '">' +
        '<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="var_names[]" placeholder="variable_name" value="' + (name || '') + '"></div>' +
        '<div class="col-md-7"><textarea class="form-control form-control-sm" name="var_values[]" rows="1" placeholder="Variable value">' + (value || '') + '</textarea></div>' +
        '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.row\').remove()"><i class="feather-x"></i></button></div>';
    list.appendChild(row);
}

function editTextAgent(id, phone, description, webhook, systemPrompt, details) {
    document.getElementById('text-agents-form-card').style.display = 'block';
    document.getElementById('text-agent-form-title').textContent = id ? 'Edit Text Agent' : 'Add Text Agent';
    document.getElementById('text-agent-id').value = id || '';
    document.getElementById('text-agent-phone').value = phone || '';
    document.getElementById('text-agent-description').value = description || '';
    document.getElementById('text-agent-webhook').value = webhook || '';
    document.getElementById('text-agent-system-prompt').value = systemPrompt || '';

    var list = document.getElementById('text-agent-variables-list');
    list.innerHTML = '';
    if (details && details.length > 0) {
        details.forEach(function(d) {
            addTextAgentVariableRow(d.variable_name, d.variable_value, d.id);
        });
    }
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
