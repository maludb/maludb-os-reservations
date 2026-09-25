<?php
/**
 * Email Agent Management
 * Admins and super-admins can manage email agent prompts per restaurant.
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

// Fetch all email agent prompts for this restaurant
$stmt = $pdo->prepare(
    "SELECT * FROM email_agent_prompts WHERE restaurant_id = ? ORDER BY created_at ASC"
);
$stmt->execute([$restaurantId]);
$prompts = $stmt->fetchAll();

// Fetch prompt details (variables) grouped by prompt id
$promptDetails = [];
if (!empty($prompts)) {
    $promptIds = array_column($prompts, 'id');
    $placeholders = implode(',', array_fill(0, count($promptIds), '?'));
    $detStmt = $pdo->prepare(
        "SELECT * FROM email_agent_prompt_details WHERE email_agent_prompt_id IN ({$placeholders}) ORDER BY id ASC"
    );
    $detStmt->execute($promptIds);
    foreach ($detStmt->fetchAll() as $d) {
        $promptDetails[$d['email_agent_prompt_id']][] = $d;
    }
}
?>

<div class="p-4" id="email-agents-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="email-agents-header">
        <h4 class="fw-bold mb-0" id="email-agents-title">
            <i class="feather-mail me-2"></i>Email Agents
        </h4>
        <button class="btn btn-primary" id="email-agents-add-btn"
                onclick="editEmailAgent('', '', '', '', '', [])">
            <i class="feather-plus me-1"></i> Add Email Agent
        </button>
    </div>

    <div id="email-agents-feedback"></div>

    <!-- Add/Edit Form (hidden by default) -->
    <div class="card mb-4" id="email-agents-form-card" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center" id="email-agents-form-header">
            <h6 class="mb-0" id="email-agent-form-title">Add Email Agent</h6>
            <button type="button" class="btn-close" id="email-agents-form-close"
                    onclick="document.getElementById('email-agents-form-card').style.display='none';"></button>
        </div>
        <div class="card-body" id="email-agents-form-body">
            <form hx-post="/partials/settings/save-email-agent.php"
                  hx-target="#email-agents-feedback"
                  hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshEmailAgents'); }"
                  id="email-agent-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="email-agent-id" value="">

                <div class="row g-3" id="email-agent-fields">
                    <div class="col-md-6" id="email-agent-agent-id-wrap">
                        <label for="email-agent-agent-id" class="form-label fw-semibold">Agent ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="email-agent-agent-id" name="agent_id"
                               required placeholder="email_agent_xxxxxxxxxxxx">
                        <small class="text-muted">A unique identifier for this email agent.</small>
                    </div>
                    <div class="col-md-6" id="email-agent-email-wrap">
                        <label for="email-agent-email" class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control" id="email-agent-email" name="email_address"
                               placeholder="reservations@example.com">
                        <small class="text-muted">The email address associated with this agent.</small>
                    </div>
                    <div class="col-12" id="email-agent-description-wrap">
                        <label for="email-agent-description" class="form-label fw-semibold">Description</label>
                        <input type="text" class="form-control" id="email-agent-description" name="description"
                               placeholder="e.g. Reservation inquiries, General questions">
                        <small class="text-muted">A short description of what this email agent is used for.</small>
                    </div>
                    <div class="col-12" id="email-agent-greeting-wrap">
                        <label for="email-agent-greeting" class="form-label fw-semibold">Email Agent Greeting</label>
                        <textarea class="form-control" id="email-agent-greeting" name="email_agent_greeting"
                                  rows="4" placeholder="e.g. Thank you for contacting Mario's Trattoria! How can we assist you today?"></textarea>
                        <small class="text-muted">The default greeting used when composing email replies through this agent.</small>
                    </div>
                </div>

                <div class="col-12 mt-3" id="email-agent-variables-wrap">
                    <label class="form-label fw-semibold">Custom Variables</label>
                    <small class="text-muted d-block mb-2">Define variable name/value pairs available to the email agent when processing messages.</small>
                    <div id="email-agent-variables-list"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="email-agent-add-variable-btn"
                            onclick="addEmailAgentVariableRow()">
                        <i class="feather-plus me-1"></i> Add Variable
                    </button>
                </div>

                <div class="mt-3 d-flex gap-2" id="email-agent-submit-wrap">
                    <button type="submit" class="btn btn-primary" id="email-agent-save-btn">
                        <i class="feather-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="email-agent-cancel-btn"
                            onclick="document.getElementById('email-agents-form-card').style.display='none';">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Agents List -->
    <div class="card mb-4" id="email-agents-list-card"
         hx-get="/partials/settings/email-agents.php"
         hx-trigger="refreshEmailAgents from:body"
         hx-target="#email-agents-page"
         hx-swap="outerHTML"
         hx-select="#email-agents-page">
        <div class="card-header" id="email-agents-list-header">
            <h6 class="mb-0" id="email-agents-list-title">Configured Email Agents (<?php echo count($prompts); ?>)</h6>
        </div>
        <div class="card-body p-0" id="email-agents-list-body">
            <?php if (empty($prompts)): ?>
            <div class="text-center py-5 text-muted" id="email-agents-empty">
                <i class="feather-mail fs-1 d-block mb-2"></i>
                <p class="mb-0">No email agents configured yet.</p>
                <small>Click "Add Email Agent" to create one.</small>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="email-agents-table-wrap">
                <table class="table table-hover mb-0" id="email-agents-table">
                    <thead id="email-agents-thead">
                        <tr>
                            <th>Agent ID</th>
                            <th>Email</th>
                            <th>Description</th>
                            <th>Greeting</th>
                            <th>Variables</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="email-agents-tbody">
                        <?php foreach ($prompts as $p): ?>
                        <tr id="email-agent-row-<?php echo $p['id']; ?>">
                            <td><code class="user-select-all"><?php echo htmlspecialchars($p['agent_id']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['email_address'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($p['description'] ?: '—'); ?></td>
                            <td>
                                <?php if ($p['email_agent_greeting']): ?>
                                <span title="<?php echo htmlspecialchars($p['email_agent_greeting']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($p['email_agent_greeting'], 0, 60)); ?><?php echo mb_strlen($p['email_agent_greeting']) > 60 ? '...' : ''; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td id="email-agent-vars-cell-<?php echo $p['id']; ?>">
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
                                <div class="btn-group btn-group-sm" id="email-agent-actions-<?php echo $p['id']; ?>">
                                    <button class="btn btn-outline-primary" title="Edit"
                                            onclick="editEmailAgent(<?php echo $p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['agent_id'])); ?>, <?php echo htmlspecialchars(json_encode($p['email_address'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['email_agent_greeting'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['description'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($details)); ?>)">
                                        <i class="feather-edit-2"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            hx-post="/partials/settings/delete-email-agent.php"
                                            hx-vals='<?php echo json_encode(["id" => $p['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#email-agents-feedback"
                                            hx-swap="innerHTML"
                                            hx-confirm="Delete this email agent?"
                                            hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshEmailAgents'); }">
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
function addEmailAgentVariableRow(name, value, detailId) {
    var list = document.getElementById('email-agent-variables-list');
    var idx = list.children.length;
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center';
    row.id = 'email-agent-var-row-' + idx;
    row.innerHTML =
        '<input type="hidden" name="detail_ids[]" value="' + (detailId || '') + '">' +
        '<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="var_names[]" placeholder="variable_name" value="' + (name || '') + '"></div>' +
        '<div class="col-md-7"><textarea class="form-control form-control-sm" name="var_values[]" rows="1" placeholder="Variable value">' + (value || '') + '</textarea></div>' +
        '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.row\').remove()"><i class="feather-x"></i></button></div>';
    list.appendChild(row);
}

function editEmailAgent(id, agentId, email, greeting, description, details) {
    document.getElementById('email-agents-form-card').style.display = 'block';
    document.getElementById('email-agent-form-title').textContent = id ? 'Edit Email Agent' : 'Add Email Agent';
    document.getElementById('email-agent-id').value = id || '';
    document.getElementById('email-agent-agent-id').value = agentId || '';
    document.getElementById('email-agent-email').value = email || '';
    document.getElementById('email-agent-greeting').value = greeting || '';
    document.getElementById('email-agent-description').value = description || '';

    var list = document.getElementById('email-agent-variables-list');
    list.innerHTML = '';
    if (details && details.length > 0) {
        details.forEach(function(d) {
            addEmailAgentVariableRow(d.variable_name, d.variable_value, d.id);
        });
    }
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
