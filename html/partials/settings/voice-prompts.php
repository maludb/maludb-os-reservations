<?php
/**
 * Voice Agent Prompt Management
 * Admins and super-admins can manage voice agent prompts per restaurant.
 * Supports creating/updating agents via Retell API.
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

// Fetch all prompts for this restaurant
$stmt = $pdo->prepare(
    "SELECT * FROM restaurant_prompts WHERE restaurant_id = ? ORDER BY created_at ASC"
);
$stmt->execute([$restaurantId]);
$prompts = $stmt->fetchAll();

// Fetch prompt details (variables) grouped by prompt id
$promptDetails = [];
if (!empty($prompts)) {
    $promptIds = array_column($prompts, 'id');
    $placeholders = implode(',', array_fill(0, count($promptIds), '?'));
    $detStmt = $pdo->prepare(
        "SELECT * FROM restaurant_prompt_details WHERE restaurant_prompt_id IN ({$placeholders}) ORDER BY id ASC"
    );
    $detStmt->execute($promptIds);
    foreach ($detStmt->fetchAll() as $d) {
        $promptDetails[$d['restaurant_prompt_id']][] = $d;
    }
}

?>

<div class="p-4" id="voice-prompts-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="voice-prompts-header">
        <h4 class="fw-bold mb-0" id="voice-prompts-title">
            <i class="feather-mic me-2"></i>Voice Agents
        </h4>
        <div class="d-flex gap-2" id="voice-prompts-create-wrap">
            <input type="text" class="form-control form-control-sm" id="voice-prompts-new-name"
                   placeholder="Agent name" style="max-width:220px;">
            <button class="btn btn-primary btn-sm text-nowrap" id="voice-prompts-create-btn"
                    onclick="createAgent()">
                <i class="feather-plus me-1"></i> Create Agent
            </button>
        </div>
    </div>

    <div id="voice-prompts-feedback"></div>

    <!-- Edit Form (hidden by default) -->
    <div class="card mb-4" id="voice-prompts-form-card" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center" id="voice-prompts-form-header">
            <h6 class="mb-0" id="voice-prompt-form-title">Add Voice Agent</h6>
            <div class="d-flex gap-2 align-items-center" id="voice-prompts-form-header-actions">
                <button type="button" class="btn btn-outline-info btn-sm" id="voice-prompt-fetch-btn"
                        onclick="fetchFromRetell()" title="Fetch current config from Retell">
                    <i class="feather-download me-1"></i> Fetch from Retell
                </button>
                <button type="button" class="btn-close" id="voice-prompts-form-close"
                        onclick="document.getElementById('voice-prompts-form-card').style.display='none';"></button>
            </div>
        </div>
        <div class="card-body" id="voice-prompts-form-body">
            <form hx-post="/partials/settings/save-voice-prompt.php"
                  hx-target="#voice-prompts-feedback"
                  hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshVoicePrompts'); }"
                  id="voice-prompt-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="voice-prompt-id" value="">
                <input type="hidden" name="retell_llm_id" id="voice-prompt-llm-id" value="">

                <!-- Two-column layout -->
                <div class="row g-4" id="voice-prompt-columns">
                    <!-- Left Column: Agent Prompt -->
                    <div class="col-md-6 d-flex flex-column" id="voice-prompt-col-left">
                        <div id="voice-prompt-agent-prompt-wrap" class="flex-grow-1 d-flex flex-column">
                            <label for="voice-prompt-agent-prompt" class="form-label fw-semibold">Agent Prompt</label>
                            <textarea class="form-control flex-grow-1" id="voice-prompt-agent-prompt" name="agent_prompt"
                                      style="min-height:400px; resize:vertical;"
                                      placeholder="You are a friendly restaurant reservation assistant for {{restaurant_name}}. Help callers book tables, check availability, and answer questions about the restaurant."></textarea>
                            <small class="text-muted mt-1">The main system prompt for the voice agent. Use {{variable_name}} for dynamic variables.</small>
                        </div>
                        <div class="mt-2" id="voice-prompt-update-prompt-wrap">
                            <button type="button" class="btn btn-outline-success btn-sm" id="voice-prompt-update-prompt-btn"
                                    onclick="updatePromptInRetell()">
                                <i class="feather-upload me-1"></i> Update Prompt in Retell
                            </button>
                            <small class="text-muted ms-2">Pushes the agent prompt to Retell immediately.</small>
                        </div>
                    </div>

                    <!-- Right Column: All other fields -->
                    <div class="col-md-6" id="voice-prompt-col-right">
                        <div class="row g-3" id="voice-prompt-fields">
                            <!-- Description -->
                            <div class="col-12" id="voice-prompt-description-wrap">
                                <label for="voice-prompt-description" class="form-label fw-semibold">Description</label>
                                <input type="text" class="form-control" id="voice-prompt-description" name="description"
                                       placeholder="e.g. Main reservation line, After-hours agent">
                                <small class="text-muted">A short description — also used as the agent name in Retell.</small>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-12" id="voice-prompt-phone-wrap">
                                <label for="voice-prompt-phone-select" class="form-label fw-semibold">Phone Number</label>
                                <div class="d-flex gap-2" id="voice-prompt-phone-row">
                                    <select class="form-select" id="voice-prompt-phone-select" name="phone_number"
                                            onfocus="loadUnassignedPhones(this)">
                                        <option value="">— Select phone number —</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-success btn-sm text-nowrap" id="voice-prompt-phone-assign-btn"
                                            onclick="assignPhone()" title="Assign this phone to the agent in Retell">
                                        <i class="feather-phone me-1"></i> Assign
                                    </button>
                                </div>
                                <small class="text-muted">Select an unassigned Retell phone number and click Assign to link it to this agent.</small>
                            </div>

                            <!-- Language -->
                            <div class="col-md-6" id="voice-prompt-language-wrap">
                                <label for="voice-prompt-language" class="form-label fw-semibold">Language</label>
                                <select class="form-select" id="voice-prompt-language" name="language_code" onchange="updateLanguageName(this)">
                                    <option value="en">English</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                    <option value="pt">Portuguese</option>
                                    <option value="de">German</option>
                                    <option value="it">Italian</option>
                                    <option value="zh">Chinese</option>
                                    <option value="ja">Japanese</option>
                                    <option value="ko">Korean</option>
                                    <option value="pl">Polish</option>
                                    <option value="ro">Romanian</option>
                                    <option value="ar">Arabic</option>
                                    <option value="hi">Hindi</option>
                                    <option value="other">Other</option>
                                </select>
                                <input type="hidden" id="voice-prompt-language-name" name="language_name" value="English">
                            </div>
                            <div class="col-md-6" id="voice-prompt-language-other-wrap" style="display:none;">
                                <label for="voice-prompt-language-other" class="form-label fw-semibold">Language Name</label>
                                <input type="text" class="form-control" id="voice-prompt-language-other" placeholder="e.g. Vietnamese">
                                <small class="text-muted">Enter the language name when "Other" is selected.</small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end" id="voice-prompt-primary-wrap">
                                <div class="form-check mb-2" id="voice-prompt-primary-check">
                                    <input class="form-check-input" type="checkbox" id="voice-prompt-primary" name="is_primary_language" value="1">
                                    <label class="form-check-label" for="voice-prompt-primary">Primary language</label>
                                </div>
                            </div>

                            <!-- Model & Voice -->
                            <div class="col-md-4" id="voice-prompt-model-wrap">
                                <label for="voice-prompt-model" class="form-label fw-semibold">LLM Model</label>
                                <select class="form-select" id="voice-prompt-model" name="model">
                                    <option value="gpt-4.1">GPT-4.1</option>
                                    <option value="gpt-4.1-mini">GPT-4.1 Mini</option>
                                    <option value="gpt-4o">GPT-4o</option>
                                    <option value="gpt-4o-mini">GPT-4o Mini</option>
                                    <option value="claude-4.5-sonnet">Claude 4.5 Sonnet</option>
                                    <option value="claude-3.5-sonnet">Claude 3.5 Sonnet</option>
                                    <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="voice-prompt-voice-id-wrap">
                                <label for="voice-prompt-voice-id" class="form-label fw-semibold">Voice</label>
                                <input type="text" class="form-control" id="voice-prompt-voice-id" name="voice_id"
                                       placeholder="e.g. retell-Cimo">
                            </div>
                            <div class="col-md-4" id="voice-prompt-voice-model-wrap">
                                <label for="voice-prompt-voice-model" class="form-label fw-semibold">Voice Model</label>
                                <select class="form-select" id="voice-prompt-voice-model" name="voice_model">
                                    <option value="">Default</option>
                                    <option value="eleven_turbo_v2">Eleven Turbo v2</option>
                                    <option value="eleven_flash_v2">Eleven Flash v2</option>
                                    <option value="sonic-3">Sonic 3</option>
                                    <option value="gpt-4o-mini-tts">GPT-4o Mini TTS</option>
                                </select>
                            </div>

                            <!-- Template -->
                            <div class="col-12" id="voice-prompt-template-wrap">
                                <label for="voice-prompt-template" class="form-label fw-semibold">Prompt Template</label>
                                <select class="form-select" id="voice-prompt-template" onchange="applyTemplate(this.value)"
                                        onfocus="loadTemplates(this)">
                                    <option value="">— Select a starter template —</option>
                                </select>
                                <small class="text-muted">Choose a template to pre-fill the prompt. You can edit after applying.</small>
                            </div>

                            <!-- Functions -->
                            <div class="col-12" id="voice-prompt-section-functions">
                                <h6 class="fw-semibold border-bottom pb-2 mb-2" id="voice-prompt-section-functions-title">
                                    <i class="feather-phone-forwarded me-1"></i> Functions
                                </h6>
                                <div class="alert alert-light mb-2 py-2" id="voice-prompt-end-call-info">
                                    <i class="feather-phone-off me-1"></i> <strong>end_call</strong> — Always included.
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6" id="voice-prompt-transfer-wrap">
                                        <label for="voice-prompt-transfer" class="form-label fw-semibold">Transfer Number</label>
                                        <input type="text" class="form-control" id="voice-prompt-transfer" name="transfer_number"
                                               placeholder="+18005551234">
                                    </div>
                                    <div class="col-md-6" id="voice-prompt-swap-wrap">
                                        <label for="voice-prompt-swap" class="form-label fw-semibold">Agent Swap ID</label>
                                        <input type="text" class="form-control" id="voice-prompt-swap" name="agent_swap_id"
                                               placeholder="agent_xxxxxxxxxxxx">
                                    </div>
                                </div>
                            </div>

                            <!-- Webhooks -->
                            <div class="col-12" id="voice-prompt-section-webhooks">
                                <h6 class="fw-semibold border-bottom pb-2 mb-2" id="voice-prompt-section-webhooks-title">
                                    <i class="feather-link me-1"></i> Webhooks
                                </h6>
                                <div class="row g-2">
                                    <div class="col-md-6" id="voice-prompt-webhook-wrap">
                                        <label for="voice-prompt-webhook" class="form-label fw-semibold">Webhook URL</label>
                                        <input type="url" class="form-control" id="voice-prompt-webhook" name="webhook_url"
                                               placeholder="https://yourdomain.com/api/retell/restaurant-webhook.php">
                                    </div>
                                    <div class="col-md-6" id="voice-prompt-inbound-webhook-wrap">
                                        <label for="voice-prompt-inbound-webhook" class="form-label fw-semibold">Inbound Webhook</label>
                                        <input type="url" class="form-control" id="voice-prompt-inbound-webhook" name="inbound_webhook_url"
                                               placeholder="https://yourdomain.com/api/retell/restaurant-webhook.php">
                                    </div>
                                </div>
                            </div>

                            <!-- MCP -->
                            <div class="col-12" id="voice-prompt-section-mcp">
                                <h6 class="fw-semibold border-bottom pb-2 mb-2" id="voice-prompt-section-mcp-title">
                                    <i class="feather-server me-1"></i> MCP Server
                                </h6>
                                <div class="row g-2">
                                    <div class="col-md-6" id="voice-prompt-mcp-url-wrap">
                                        <label for="voice-prompt-mcp-url" class="form-label fw-semibold">MCP Server URL</label>
                                        <input type="url" class="form-control" id="voice-prompt-mcp-url" name="mcp_url"
                                               placeholder="https://yourdomain.com/api/mcp/sse">
                                    </div>
                                    <div class="col-md-6" id="voice-prompt-mcp-headers-wrap">
                                        <label for="voice-prompt-mcp-headers" class="form-label fw-semibold">MCP Auth Headers</label>
                                        <textarea class="form-control" id="voice-prompt-mcp-headers" name="mcp_headers"
                                                  rows="2" placeholder='{"Authorization": "Bearer your-token"}'></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Variables -->
                            <div class="col-12" id="voice-prompt-section-variables">
                                <h6 class="fw-semibold border-bottom pb-2 mb-2" id="voice-prompt-section-variables-title">
                                    <i class="feather-code me-1"></i> Custom Variables
                                </h6>
                                <small class="text-muted d-block mb-2">Default dynamic variables set on the Retell LLM and also returned via webhooks.</small>
                                <div id="voice-prompt-variables-list"></div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="voice-prompt-add-variable-btn"
                                        onclick="addVariableRow()">
                                    <i class="feather-plus me-1"></i> Add Variable
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="mt-4 d-flex gap-2" id="voice-prompt-submit-wrap">
                    <button type="submit" class="btn btn-primary" id="voice-prompt-save-btn">
                        <i class="feather-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="voice-prompt-cancel-btn"
                            onclick="document.getElementById('voice-prompts-form-card').style.display='none';">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Prompts List -->
    <div class="card mb-4" id="voice-prompts-list-card"
         hx-get="/partials/settings/voice-prompts.php"
         hx-trigger="refreshVoicePrompts from:body"
         hx-target="#voice-prompts-page"
         hx-swap="outerHTML"
         hx-select="#voice-prompts-page">
        <div class="card-header" id="voice-prompts-list-header">
            <h6 class="mb-0" id="voice-prompts-list-title">Configured Agents (<?php echo count($prompts); ?>)</h6>
        </div>
        <div class="card-body" id="voice-prompts-list-body">
            <?php if (empty($prompts)): ?>
            <div class="text-center py-5 text-muted" id="voice-prompts-empty">
                <i class="feather-mic-off fs-1 d-block mb-2"></i>
                <p class="mb-0">No voice agents configured yet.</p>
                <small>Click "Add Agent" to create one.</small>
            </div>
            <?php else: ?>
            <div id="voice-prompts-cards-list">
                <?php foreach ($prompts as $p): ?>
                <?php
                $details = $promptDetails[$p['id']] ?? [];
                $activeCount = count(array_filter($details, fn($d) => $d['is_active']));
                ?>
                <div class="card mb-3 border" id="voice-prompt-card-<?php echo $p['id']; ?>">
                    <div class="card-body" id="voice-prompt-card-body-<?php echo $p['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2" id="voice-prompt-card-top-<?php echo $p['id']; ?>">
                            <div>
                                <h6 class="mb-1" id="voice-prompt-card-desc-<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['description'] ?: 'Untitled Agent'); ?>
                                    <span class="badge bg-info ms-1" id="voice-prompt-card-lang-<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['language_name'] ?? 'English'); ?></span>
                                    <?php if (!empty($p['is_primary_language'])): ?>
                                    <span class="badge bg-success ms-1" id="voice-prompt-card-primary-<?php echo $p['id']; ?>">Primary</span>
                                    <?php endif; ?>
                                </h6>
                                <div class="small" id="voice-prompt-card-ids-<?php echo $p['id']; ?>">
                                    <?php if (!empty($p['agent_id'])): ?>
                                    <span class="text-muted">Agent:</span> <code class="user-select-all"><?php echo htmlspecialchars($p['agent_id']); ?></code>
                                    <?php endif; ?>
                                    <?php if (!empty($p['retell_llm_id'])): ?>
                                    <span class="text-muted ms-2">LLM:</span> <code class="user-select-all"><?php echo htmlspecialchars($p['retell_llm_id']); ?></code>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-1" id="voice-prompt-actions-<?php echo $p['id']; ?>">
                                <button class="btn btn-outline-primary btn-sm" title="Edit"
                                        onclick="editPrompt(<?php echo htmlspecialchars(json_encode([
                                            'id' => $p['id'],
                                            'agent_id' => $p['agent_id'] ?? '',
                                            'retell_llm_id' => $p['retell_llm_id'] ?? '',
                                            'phone_number' => $p['phone_number'] ?? '',
                                            'description' => $p['description'] ?? '',
                                            'language_code' => $p['language_code'] ?? 'en',
                                            'language_name' => $p['language_name'] ?? 'English',
                                            'is_primary_language' => (int)($p['is_primary_language'] ?? 0),
                                            'model' => $p['model'] ?? 'gpt-4.1',
                                            'voice_id' => $p['voice_id'] ?? '',
                                            'voice_model' => $p['voice_model'] ?? '',
                                            'agent_prompt' => $p['agent_prompt'] ?? '',
                                            'transfer_number' => $p['transfer_number'] ?? '',
                                            'agent_swap_id' => $p['agent_swap_id'] ?? '',
                                            'webhook_url' => $p['webhook_url'] ?? '',
                                            'inbound_webhook_url' => $p['inbound_webhook_url'] ?? '',
                                            'mcp_url' => $p['mcp_url'] ?? '',
                                            'mcp_headers' => $p['mcp_headers'] ?? '',
                                            'details' => $details,
                                        ])); ?>)">
                                    <i class="feather-edit-2"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" title="Delete"
                                        hx-post="/partials/settings/delete-voice-prompt.php"
                                        hx-vals='<?php echo json_encode(["id" => $p['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                        hx-target="#voice-prompts-feedback"
                                        hx-swap="innerHTML"
                                        hx-confirm="Delete this voice agent prompt?"
                                        hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshVoicePrompts'); }">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row g-2 small" id="voice-prompt-card-details-<?php echo $p['id']; ?>">
                            <div class="col-sm-6 col-md-3" id="voice-prompt-card-phone-<?php echo $p['id']; ?>">
                                <span class="text-muted">Phone:</span> <?php echo htmlspecialchars($p['phone_number'] ?: '—'); ?>
                            </div>
                            <div class="col-sm-6 col-md-3" id="voice-prompt-card-model-<?php echo $p['id']; ?>">
                                <span class="text-muted">Model:</span> <?php echo htmlspecialchars($p['model'] ?? 'gpt-4.1'); ?>
                            </div>
                            <div class="col-sm-6 col-md-3" id="voice-prompt-card-voice-<?php echo $p['id']; ?>">
                                <span class="text-muted">Voice:</span> <?php echo htmlspecialchars($p['voice_id'] ?: '—'); ?>
                            </div>
                            <div class="col-sm-6 col-md-3" id="voice-prompt-card-vars-<?php echo $p['id']; ?>">
                                <span class="text-muted">Variables:</span>
                                <?php if ($activeCount > 0): ?>
                                <span class="badge bg-primary"><?php echo $activeCount; ?></span>
                                <?php else: ?>
                                <span>—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($p['agent_prompt'])): ?>
                        <div class="mt-2 small" id="voice-prompt-card-prompt-<?php echo $p['id']; ?>">
                            <span class="text-muted">Prompt:</span>
                            <?php echo htmlspecialchars(mb_substr($p['agent_prompt'], 0, 150)); ?><?php echo mb_strlen($p['agent_prompt'] ?? '') > 150 ? '...' : ''; ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
function addVariableRow(name, value, detailId) {
    var list = document.getElementById('voice-prompt-variables-list');
    var idx = list.children.length;
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center';
    row.id = 'voice-prompt-var-row-' + idx;
    row.innerHTML =
        '<input type="hidden" name="detail_ids[]" value="' + (detailId || '') + '">' +
        '<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="var_names[]" placeholder="variable_name" value="' + (name || '') + '"></div>' +
        '<div class="col-md-7"><textarea class="form-control form-control-sm" name="var_values[]" rows="1" placeholder="Variable value">' + (value || '') + '</textarea></div>' +
        '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.row\').remove()"><i class="feather-x"></i></button></div>';
    list.appendChild(row);
}

var languageMap = {en:'English',es:'Spanish',fr:'French',pt:'Portuguese',de:'German',it:'Italian',zh:'Chinese',ja:'Japanese',ko:'Korean',pl:'Polish',ro:'Romanian',ar:'Arabic',hi:'Hindi'};

function updateLanguageName(sel) {
    var code = sel.value;
    var otherWrap = document.getElementById('voice-prompt-language-other-wrap');
    if (code === 'other') {
        otherWrap.style.display = '';
        document.getElementById('voice-prompt-language-name').value = document.getElementById('voice-prompt-language-other').value || '';
    } else {
        otherWrap.style.display = 'none';
        document.getElementById('voice-prompt-language-name').value = languageMap[code] || code;
        document.getElementById('voice-prompt-language-other').value = '';
    }
}

function editPrompt(data) {
    var d = data || {};
    document.getElementById('voice-prompts-form-card').style.display = 'block';
    document.getElementById('voice-prompt-form-title').textContent = d.id ? 'Edit Voice Agent' : 'Add Voice Agent';

    // Basic info
    document.getElementById('voice-prompt-id').value = d.id || '';
    document.getElementById('voice-prompt-llm-id').value = d.retell_llm_id || '';
    document.getElementById('voice-prompt-description').value = d.description || '';

    // Phone dropdown: reset and set current value
    var phoneSel = document.getElementById('voice-prompt-phone-select');
    phoneSel.innerHTML = '<option value="">— Select phone number —</option>';
    _phonesLoaded = false;
    if (d.phone_number) {
        var curOpt = document.createElement('option');
        curOpt.value = d.phone_number;
        curOpt.textContent = d.phone_number + ' (current)';
        curOpt.selected = true;
        phoneSel.appendChild(curOpt);
    }

    // Language
    var lc = d.language_code || 'en';
    var ln = d.language_name || 'English';
    var sel = document.getElementById('voice-prompt-language');
    var otherWrap = document.getElementById('voice-prompt-language-other-wrap');
    if (languageMap[lc]) {
        sel.value = lc;
        otherWrap.style.display = 'none';
        document.getElementById('voice-prompt-language-other').value = '';
    } else {
        sel.value = 'other';
        otherWrap.style.display = '';
        document.getElementById('voice-prompt-language-other').value = ln;
    }
    document.getElementById('voice-prompt-language-name').value = ln;
    document.getElementById('voice-prompt-primary').checked = !!d.is_primary_language;

    document.getElementById('voice-prompt-language-other').oninput = function() {
        document.getElementById('voice-prompt-language-name').value = this.value;
    };

    // Prompt & Model
    document.getElementById('voice-prompt-model').value = d.model || 'gpt-4.1';
    document.getElementById('voice-prompt-voice-id').value = d.voice_id || '';
    document.getElementById('voice-prompt-voice-model').value = d.voice_model || '';
    document.getElementById('voice-prompt-agent-prompt').value = d.agent_prompt || '';

    // Functions
    document.getElementById('voice-prompt-transfer').value = d.transfer_number || '';
    document.getElementById('voice-prompt-swap').value = d.agent_swap_id || '';

    // Webhooks
    document.getElementById('voice-prompt-webhook').value = d.webhook_url || '';
    document.getElementById('voice-prompt-inbound-webhook').value = d.inbound_webhook_url || '';

    // MCP
    document.getElementById('voice-prompt-mcp-url').value = d.mcp_url || '';
    document.getElementById('voice-prompt-mcp-headers').value = d.mcp_headers || '';

    // Custom variables
    var list = document.getElementById('voice-prompt-variables-list');
    list.innerHTML = '';
    if (d.details && d.details.length > 0) {
        d.details.forEach(function(v) {
            addVariableRow(v.variable_name, v.variable_value, v.id);
        });
    }

    window.scrollTo({top: 0, behavior: 'smooth'});
}

// --- Update prompt in Retell ---
function updatePromptInRetell() {
    var promptId = document.getElementById('voice-prompt-id').value;
    var agentPrompt = document.getElementById('voice-prompt-agent-prompt').value.trim();
    var csrf = document.querySelector('#voice-prompt-form input[name="csrf_token"]').value;

    if (!promptId) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">Save the agent first.</div>';
        return;
    }
    if (!agentPrompt) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">Agent prompt cannot be empty.</div>';
        return;
    }

    var btn = document.getElementById('voice-prompt-update-prompt-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather-loader me-1"></i> Updating...';

    var form = new FormData();
    form.append('id', promptId);
    form.append('agent_prompt', agentPrompt);
    form.append('csrf_token', csrf);

    fetch('/partials/settings/retell-update-prompt.php', { method: 'POST', body: form })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('voice-prompts-feedback').innerHTML = html;
            btn.disabled = false;
            btn.innerHTML = '<i class="feather-upload me-1"></i> Update Prompt in Retell';
        });
}

// --- Prompt templates ---
var _templates = null;

function loadTemplates(selectEl) {
    if (_templates !== null) return;
    var opt = document.createElement('option');
    opt.textContent = 'Loading...';
    opt.disabled = true;
    selectEl.appendChild(opt);

    fetch('/partials/settings/retell-prompt-templates.php')
        .then(function(r) { return r.json(); })
        .then(function(templates) {
            _templates = templates;
            selectEl.removeChild(opt);
            templates.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.id;
                o.textContent = t.name + (t.description ? ' — ' + t.description : '');
                selectEl.appendChild(o);
            });
        })
        .catch(function() {
            opt.textContent = 'Error loading templates';
        });
}

function applyTemplate(templateId) {
    if (!templateId || !_templates) return;
    var t = _templates.find(function(t) { return t.id == templateId; });
    if (!t) return;
    document.getElementById('voice-prompt-agent-prompt').value = t.agent_prompt || '';
}

// --- Fetch from Retell ---
function fetchFromRetell() {
    var promptId = document.getElementById('voice-prompt-id').value;
    if (!promptId) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">No agent to fetch — save first.</div>';
        return;
    }
    var btn = document.getElementById('voice-prompt-fetch-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather-loader me-1"></i> Fetching...';

    fetch('/partials/settings/retell-fetch-agent.php?id=' + promptId)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="feather-download me-1"></i> Fetch from Retell';
            if (res.error) {
                document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-danger">' + res.error + '</div>';
                return;
            }
            var d = res.data;

            // Populate form fields
            if (d.description) document.getElementById('voice-prompt-description').value = d.description;
            if (d.retell_llm_id) document.getElementById('voice-prompt-llm-id').value = d.retell_llm_id;
            if (d.model) document.getElementById('voice-prompt-model').value = d.model;
            if (d.voice_id) document.getElementById('voice-prompt-voice-id').value = d.voice_id;
            if (d.voice_model !== undefined) document.getElementById('voice-prompt-voice-model').value = d.voice_model || '';
            if (d.agent_prompt !== undefined) document.getElementById('voice-prompt-agent-prompt').value = d.agent_prompt || '';
            if (d.transfer_number !== undefined) document.getElementById('voice-prompt-transfer').value = d.transfer_number || '';
            if (d.agent_swap_id !== undefined) document.getElementById('voice-prompt-swap').value = d.agent_swap_id || '';
            if (d.webhook_url !== undefined) document.getElementById('voice-prompt-webhook').value = d.webhook_url || '';
            if (d.mcp_url !== undefined) document.getElementById('voice-prompt-mcp-url').value = d.mcp_url || '';
            if (d.mcp_headers !== undefined) document.getElementById('voice-prompt-mcp-headers').value = d.mcp_headers || '';

            // Language
            if (d.language_code) {
                var sel = document.getElementById('voice-prompt-language');
                if (languageMap[d.language_code]) {
                    sel.value = d.language_code;
                    document.getElementById('voice-prompt-language-other-wrap').style.display = 'none';
                }
                document.getElementById('voice-prompt-language-name').value = languageMap[d.language_code] || d.language_code;
            }

            // Dynamic variables
            if (d.dynamic_variables && typeof d.dynamic_variables === 'object') {
                var list = document.getElementById('voice-prompt-variables-list');
                list.innerHTML = '';
                Object.keys(d.dynamic_variables).forEach(function(key) {
                    addVariableRow(key, d.dynamic_variables[key], '');
                });
            }

            document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-success">Fetched current config from Retell and updated local fields.</div>';
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="feather-download me-1"></i> Fetch from Retell';
            document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-danger">Failed to fetch from Retell.</div>';
        });
}

// --- Create agent ---
function createAgent() {
    var name = document.getElementById('voice-prompts-new-name').value.trim();
    if (!name) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">Enter an agent name first.</div>';
        return;
    }
    var csrf = '<?php echo generate_csrf_token(); ?>';
    var form = new FormData();
    form.append('agent_name', name);
    form.append('csrf_token', csrf);

    var btn = document.getElementById('voice-prompts-create-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather-loader me-1"></i> Creating...';

    fetch('/partials/settings/retell-create-agent.php', { method: 'POST', body: form })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('voice-prompts-feedback').innerHTML = html;
            btn.disabled = false;
            btn.innerHTML = '<i class="feather-plus me-1"></i> Create Agent';
            if (html.indexOf('alert-success') !== -1) {
                document.getElementById('voice-prompts-new-name').value = '';
                setTimeout(function() { htmx.trigger(document.body, 'refreshVoicePrompts'); }, 2000);
            }
        });
}

// --- Phone number assignment ---
var _phonesLoaded = false;

function loadUnassignedPhones(selectEl) {
    if (_phonesLoaded) return;
    _phonesLoaded = true;

    var opt = document.createElement('option');
    opt.textContent = 'Loading...';
    opt.disabled = true;
    selectEl.appendChild(opt);

    fetch('/partials/settings/retell-unassigned-phones.php')
        .then(function(r) { return r.json(); })
        .then(function(phones) {
            selectEl.removeChild(opt);
            var current = selectEl.value;
            phones.forEach(function(phone) {
                if (phone.phone_number === current) return;
                var o = document.createElement('option');
                o.value = phone.phone_number;
                o.textContent = phone.phone_number;
                selectEl.appendChild(o);
            });
        })
        .catch(function() {
            opt.textContent = 'Error loading phones';
        });
}

function assignPhone() {
    var sel = document.getElementById('voice-prompt-phone-select');
    var phone = sel.value;
    var promptId = document.getElementById('voice-prompt-id').value;
    var csrf = document.querySelector('#voice-prompt-form input[name="csrf_token"]').value;

    if (!phone) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">Select a phone number first.</div>';
        return;
    }
    if (!promptId) {
        document.getElementById('voice-prompts-feedback').innerHTML = '<div class="alert alert-warning">Save the agent first before assigning a phone number.</div>';
        return;
    }

    var form = new FormData();
    form.append('id', promptId);
    form.append('phone_number', phone);
    form.append('csrf_token', csrf);

    fetch('/partials/settings/retell-assign-phone.php', { method: 'POST', body: form })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('voice-prompts-feedback').innerHTML = html;
            if (html.indexOf('alert-success') !== -1) {
                _phonesLoaded = false;
                setTimeout(function() { htmx.trigger(document.body, 'refreshVoicePrompts'); }, 2000);
            }
        });
}
</script>
</div>
