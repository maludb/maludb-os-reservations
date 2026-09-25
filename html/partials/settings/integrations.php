<?php
/**
 * Voice Agent & API Integrations Settings
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAdmin();

$restaurantId = currentRestaurantId();
$pdo = db();

// Load current settings
$settingKeys = ['retell_api_key', 'retell_agent_id', 'mcp_api_key', 'openai_api_key', 'voice_agent_greeting', 'voice_agent_specials', 'voice_agent_custom_info'];
$settings = [];
foreach ($settingKeys as $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = ?");
    $stmt->execute([$restaurantId, $key]);
    $row = $stmt->fetch();
    $settings[$key] = $row ? $row['setting_value'] : '';
}

// Get restaurant slug for endpoint URLs
$stmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch();
$slug = $restaurant['slug'] ?? 'your-restaurant';

// Build base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$baseUrl = "{$protocol}://{$host}";
?>

<div class="p-4" id="integrations-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="integrations-header">
        <h4 class="fw-bold mb-0" id="integrations-title">
            <i class="feather-zap me-2"></i>Voice Agent & API Integrations
        </h4>
    </div>

    <div id="integrations-feedback"
         hx-swap-oob="true"></div>

    <!-- API Keys -->
    <div class="card mb-4" id="integrations-keys-card">
        <div class="card-header" id="integrations-keys-header">
            <h6 class="mb-0" id="integrations-keys-title">API Keys</h6>
        </div>
        <div class="card-body" id="integrations-keys-body">
            <form hx-post="/partials/settings/save-integrations.php"
                  hx-target="#integrations-feedback"
                  hx-swap="innerHTML"
                  id="integrations-keys-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_keys">

                <div class="row g-3" id="integrations-keys-fields">
                    <div class="col-md-6" id="integrations-retell-key-wrap">
                        <label for="integrations-retell-key" class="form-label fw-semibold">Retell API Key</label>
                        <input type="password" class="form-control" id="integrations-retell-key" name="retell_api_key"
                               value="<?php echo htmlspecialchars($settings['retell_api_key']); ?>"
                               placeholder="Enter Retell API key">
                        <small class="text-muted">Used for API authentication and signature verification.</small>
                    </div>
                    <div class="col-md-6" id="integrations-retell-agent-wrap">
                        <label for="integrations-retell-agent" class="form-label fw-semibold">Retell Agent ID</label>
                        <input type="text" class="form-control" id="integrations-retell-agent" name="retell_agent_id"
                               value="<?php echo htmlspecialchars($settings['retell_agent_id']); ?>"
                               placeholder="agent_xxxxxxxxxxxx">
                        <small class="text-muted">The default Retell agent ID for web calls. Find this in your <a href="https://dashboard.retellai.com" target="_blank">Retell dashboard</a>.</small>
                    </div>
                    <div class="col-md-6" id="integrations-openai-key-wrap">
                        <label for="integrations-openai-key" class="form-label fw-semibold">OpenAI API Key</label>
                        <input type="password" class="form-control" id="integrations-openai-key" name="openai_api_key"
                               value="<?php echo htmlspecialchars($settings['openai_api_key']); ?>"
                               placeholder="sk-...">
                        <small class="text-muted">Used by the SMS and email agents to answer guests.</small>
                    </div>
                    <div class="col-12" id="integrations-mcp-key-wrap">
                        <label for="integrations-mcp-key" class="form-label fw-semibold">MCP Bearer Token</label>
                        <div class="input-group" id="integrations-mcp-key-group">
                            <input type="password" class="form-control font-monospace" id="integrations-mcp-key" name="mcp_api_key"
                                   value="<?php echo htmlspecialchars($settings['mcp_api_key']); ?>"
                                   placeholder="Click Generate to create a bearer token">
                            <button type="button" class="btn btn-outline-secondary" id="integrations-toggle-mcp-key"
                                    onclick="var f=document.getElementById('integrations-mcp-key');f.type=f.type==='password'?'text':'password';this.innerHTML=f.type==='password'?'<i class=\'feather-eye\'></i>':'<i class=\'feather-eye-off\'></i>';">
                                <i class="feather-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="integrations-copy-mcp-key"
                                    onclick="var v=document.getElementById('integrations-mcp-key').value;if(!v){return;}navigator.clipboard.writeText(v);this.innerHTML='<i class=\'feather-check\'></i> Copied';setTimeout(()=>this.innerHTML='<i class=\'feather-copy\'></i> Copy',2000);">
                                <i class="feather-copy"></i> Copy
                            </button>
                            <button type="button" class="btn btn-primary" id="integrations-generate-mcp-key"
                                    onclick="var t=Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');var f=document.getElementById('integrations-mcp-key');f.value=t;f.type='text';document.getElementById('integrations-toggle-mcp-key').innerHTML='<i class=\'feather-eye-off\'></i>';navigator.clipboard.writeText(t);var cb=document.getElementById('integrations-copy-mcp-key');cb.innerHTML='<i class=\'feather-check\'></i> Copied';setTimeout(()=>cb.innerHTML='<i class=\'feather-copy\'></i> Copy',2000);">
                                <i class="feather-refresh-cw me-1"></i> Generate
                            </button>
                        </div>
                        <small class="text-muted">Bearer token for MCP server authentication. Click Generate to create a new token — it will be copied to your clipboard automatically. Remember to click Save after generating.</small>
                    </div>
                </div>

                <div class="mt-3" id="integrations-keys-submit">
                    <button type="submit" class="btn btn-primary" id="integrations-save-keys-btn">
                        <i class="feather-save me-1"></i> Save API Keys
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Retell Custom Function Endpoints -->
    <div class="card mb-4" id="integrations-retell-card">
        <div class="card-header" id="integrations-retell-header">
            <h6 class="mb-0" id="integrations-retell-title">Retell AI — Custom Function Endpoints</h6>
        </div>
        <div class="card-body" id="integrations-retell-body">
            <p class="text-muted mb-3" id="integrations-retell-desc">Configure these URLs as Custom Functions in your Retell AI agent dashboard. All endpoints accept POST requests.</p>

            <div class="table-responsive" id="integrations-retell-table-wrap">
                <table class="table table-sm" id="integrations-retell-table">
                    <thead id="integrations-retell-thead">
                        <tr><th>Function Name</th><th>Endpoint URL</th><th>Method</th></tr>
                    </thead>
                    <tbody id="integrations-retell-tbody">
                        <tr id="integrations-retell-row-check">
                            <td><code>check_availability</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/check-availability.php</code></td>
                            <td>POST</td>
                        </tr>
                        <tr id="integrations-retell-row-make">
                            <td><code>make_reservation</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/make-reservation.php</code></td>
                            <td>POST</td>
                        </tr>
                        <tr id="integrations-retell-row-lookup">
                            <td><code>lookup_reservation</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/lookup-reservation.php</code></td>
                            <td>POST</td>
                        </tr>
                        <tr id="integrations-retell-row-cancel">
                            <td><code>cancel_reservation</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/cancel-reservation.php</code></td>
                            <td>POST</td>
                        </tr>
                        <tr id="integrations-retell-row-confirm">
                            <td><code>confirm_reservation</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/confirm-reservation.php</code></td>
                            <td>POST</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-semibold mt-4 mb-2" id="integrations-webhook-title">Webhook Endpoints</h6>
            <p class="text-muted mb-3" id="integrations-webhook-desc">Configure these URLs in your Retell AI dashboard under <strong>Agent &gt; Webhook</strong>. They fire automatically when calls start and end.</p>

            <div class="table-responsive" id="integrations-webhook-table-wrap">
                <table class="table table-sm" id="integrations-webhook-table">
                    <thead id="integrations-webhook-thead">
                        <tr><th>Event</th><th>Webhook URL</th><th>Method</th></tr>
                    </thead>
                    <tbody id="integrations-webhook-tbody">
                        <tr id="integrations-webhook-row-begin">
                            <td><code>call_started</code></td>
                            <td>
                                <code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/restaurant-webhook.php?id=<em>AGENT_ID</em></code>
                                <br><small class="text-muted">Append <code>?id=agent_xxxx</code> to load agent-specific prompts from <a href="#" hx-get="/partials/settings/voice-prompts.php" hx-target="#page-content">Voice Prompts</a>.</small>
                            </td>
                            <td>POST</td>
                        </tr>
                        <tr id="integrations-webhook-row-end">
                            <td><code>call_ended</code></td>
                            <td><code class="user-select-all"><?php echo htmlspecialchars($baseUrl); ?>/api/retell/webhook-call-end.php</code></td>
                            <td>POST</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info py-2 mb-0" id="integrations-retell-slug-note">
                <i class="feather-info me-1"></i>
                Your restaurant slug is <strong><code><?php echo htmlspecialchars($slug); ?></code></strong>. Use this as the <code>restaurant_slug</code> parameter in all function calls.
            </div>
        </div>
    </div>

    <!-- Retell Agent Prompt Download -->
    <div class="card mb-4" id="integrations-prompt-download-card">
        <div class="card-header" id="integrations-prompt-download-header">
            <h6 class="mb-0" id="integrations-prompt-download-title">Retell Agent Prompt</h6>
        </div>
        <div class="card-body" id="integrations-prompt-download-body">
            <p class="text-muted mb-3" id="integrations-prompt-download-desc">Download the voice agent prompt to paste into your Retell AI agent's system prompt. Includes CRM-aware conversation flows, tool usage instructions, and handling for known/new callers.</p>
            <a href="/downloads/retell-prompt.php?slug=<?php echo urlencode($slug); ?>" download="retell-prompt.txt" class="btn btn-outline-primary" id="integrations-download-prompt-btn">
                <i class="feather-download me-1"></i> Download Prompt
            </a>
        </div>
    </div>

    <!-- MCP Server -->
    <div class="card mb-4" id="integrations-mcp-card">
        <div class="card-header" id="integrations-mcp-header">
            <h6 class="mb-0" id="integrations-mcp-title">MCP Server — How to Call</h6>
        </div>
        <div class="card-body" id="integrations-mcp-body">
            <p class="text-muted mb-3" id="integrations-mcp-desc">The MCP server uses JSON-RPC 2.0 over HTTP. All requests are <code>POST</code> to a single endpoint with your bearer token.</p>

            <div class="mb-3" id="integrations-mcp-url-wrap">
                <label class="form-label fw-semibold" id="integrations-mcp-url-label">MCP Server URL</label>
                <div class="input-group" id="integrations-mcp-url-group">
                    <input type="text" class="form-control bg-light font-monospace" id="integrations-mcp-url"
                           value="<?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="integrations-copy-mcp-url"
                            onclick="navigator.clipboard.writeText(document.getElementById('integrations-mcp-url').value);this.innerHTML='<i class=\'feather-check\'></i> Copied';setTimeout(()=>this.innerHTML='<i class=\'feather-copy\'></i> Copy',2000);">
                        <i class="feather-copy"></i> Copy
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4" id="integrations-mcp-config">
                <div class="col-md-6" id="integrations-mcp-auth-wrap">
                    <strong>Authentication:</strong> Bearer Token<br>
                    <small class="text-muted">Header: <code>Authorization: Bearer &lt;your-mcp-api-key&gt;</code></small>
                </div>
                <div class="col-md-6" id="integrations-mcp-tools-wrap">
                    <strong>Available Tools (5):</strong><br>
                    <small class="text-muted">check_availability, make_reservation, lookup_reservation, cancel_reservation, confirm_reservation</small>
                </div>
            </div>

            <h6 class="fw-semibold mb-2" id="integrations-mcp-examples-title">Usage Examples (curl)</h6>

            <div class="mb-3" id="integrations-mcp-example-list">
                <label class="form-label text-muted small" id="integrations-mcp-ex1-label">1. List available tools:</label>
                <div class="position-relative" id="integrations-mcp-ex1-wrap">
                    <pre class="bg-light p-3 rounded small mb-0" id="integrations-mcp-ex1-code"><code>curl -X POST <?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'</code></pre>
                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="integrations-copy-ex1"
                            onclick="var c=document.getElementById('integrations-mcp-ex1-code').innerText;navigator.clipboard.writeText(c);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='Copy',2000);">Copy</button>
                </div>
            </div>

            <div class="mb-3" id="integrations-mcp-example-check">
                <label class="form-label text-muted small" id="integrations-mcp-ex2-label">2. Check availability:</label>
                <div class="position-relative" id="integrations-mcp-ex2-wrap">
                    <pre class="bg-light p-3 rounded small mb-0" id="integrations-mcp-ex2-code"><code>curl -X POST <?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "check_availability",
      "arguments": {
        "restaurant_slug": "<?php echo htmlspecialchars($slug); ?>",
        "date": "2026-03-15",
        "party_size": 4
      }
    },
    "id": 2
  }'</code></pre>
                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="integrations-copy-ex2"
                            onclick="var c=document.getElementById('integrations-mcp-ex2-code').innerText;navigator.clipboard.writeText(c);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='Copy',2000);">Copy</button>
                </div>
            </div>

            <div class="mb-3" id="integrations-mcp-example-make">
                <label class="form-label text-muted small" id="integrations-mcp-ex3-label">3. Make a reservation:</label>
                <div class="position-relative" id="integrations-mcp-ex3-wrap">
                    <pre class="bg-light p-3 rounded small mb-0" id="integrations-mcp-ex3-code"><code>curl -X POST <?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "make_reservation",
      "arguments": {
        "restaurant_slug": "<?php echo htmlspecialchars($slug); ?>",
        "date": "2026-03-15",
        "time": "18:30",
        "party_size": 4,
        "guest_name": "John Smith",
        "guest_phone": "5551234567",
        "guest_email": "john@example.com",
        "special_requests": "Window seat, gluten-free"
      }
    },
    "id": 3
  }'</code></pre>
                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="integrations-copy-ex3"
                            onclick="var c=document.getElementById('integrations-mcp-ex3-code').innerText;navigator.clipboard.writeText(c);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='Copy',2000);">Copy</button>
                </div>
            </div>

            <div class="mb-3" id="integrations-mcp-example-lookup">
                <label class="form-label text-muted small" id="integrations-mcp-ex4-label">4. Lookup reservation (by code or phone):</label>
                <div class="position-relative" id="integrations-mcp-ex4-wrap">
                    <pre class="bg-light p-3 rounded small mb-0" id="integrations-mcp-ex4-code"><code>curl -X POST <?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "lookup_reservation",
      "arguments": {
        "restaurant_slug": "<?php echo htmlspecialchars($slug); ?>",
        "confirmation_code": "AB3F7K9D2E"
      }
    },
    "id": 4
  }'</code></pre>
                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="integrations-copy-ex4"
                            onclick="var c=document.getElementById('integrations-mcp-ex4-code').innerText;navigator.clipboard.writeText(c);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='Copy',2000);">Copy</button>
                </div>
            </div>

            <div class="mb-0" id="integrations-mcp-example-cancel">
                <label class="form-label text-muted small" id="integrations-mcp-ex5-label">5. Cancel reservation:</label>
                <div class="position-relative" id="integrations-mcp-ex5-wrap">
                    <pre class="bg-light p-3 rounded small mb-0" id="integrations-mcp-ex5-code"><code>curl -X POST <?php echo htmlspecialchars($baseUrl); ?>/api/mcp/server.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "cancel_reservation",
      "arguments": {
        "restaurant_slug": "<?php echo htmlspecialchars($slug); ?>",
        "confirmation_code": "AB3F7K9D2E"
      }
    },
    "id": 5
  }'</code></pre>
                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="integrations-copy-ex5"
                            onclick="var c=document.getElementById('integrations-mcp-ex5-code').innerText;navigator.clipboard.writeText(c);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='Copy',2000);">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Voice Agent Custom Prompt Sections -->
    <div class="card mb-4" id="integrations-prompt-card">
        <div class="card-header" id="integrations-prompt-header">
            <h6 class="mb-0" id="integrations-prompt-title">Voice Agent — Custom Prompt Sections</h6>
        </div>
        <div class="card-body" id="integrations-prompt-body">
            <p class="text-muted mb-3" id="integrations-prompt-desc">These fields are passed to the voice agent as dynamic variables when a call begins. Use them to customize the agent's behavior per restaurant.</p>

            <form hx-post="/partials/settings/save-integrations.php"
                  hx-target="#integrations-feedback"
                  hx-swap="innerHTML"
                  id="integrations-prompt-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_prompt_sections">

                <div class="mb-3" id="integrations-prompt-greeting-wrap">
                    <label for="integrations-prompt-greeting" class="form-label fw-semibold">Custom Greeting</label>
                    <textarea class="form-control" id="integrations-prompt-greeting" name="voice_agent_greeting"
                              rows="3" placeholder="e.g. Welcome to Mario's Trattoria! We're known for our handmade pasta and wood-fired pizzas."><?php echo htmlspecialchars($settings['voice_agent_greeting']); ?></textarea>
                    <small class="text-muted">Personalized greeting or restaurant personality for the voice agent.</small>
                </div>

                <div class="mb-3" id="integrations-prompt-specials-wrap">
                    <label for="integrations-prompt-specials" class="form-label fw-semibold">Today's Specials / Promotions</label>
                    <textarea class="form-control" id="integrations-prompt-specials" name="voice_agent_specials"
                              rows="3" placeholder="e.g. Tonight's special is pan-seared salmon with lemon butter. We also have a Valentine's Day prix fixe menu available."><?php echo htmlspecialchars($settings['voice_agent_specials']); ?></textarea>
                    <small class="text-muted">Current specials, promotions, or menu highlights the agent can mention.</small>
                </div>

                <div class="mb-3" id="integrations-prompt-custom-wrap">
                    <label for="integrations-prompt-custom" class="form-label fw-semibold">Additional Information</label>
                    <textarea class="form-control" id="integrations-prompt-custom" name="voice_agent_custom_info"
                              rows="3" placeholder="e.g. Free valet parking is available on weekends. Dress code is business casual. We are BYOB on Tuesdays."><?php echo htmlspecialchars($settings['voice_agent_custom_info']); ?></textarea>
                    <small class="text-muted">Any extra info the agent should know (parking, dress code, BYOB policy, etc.).</small>
                </div>

                <button type="submit" class="btn btn-primary" id="integrations-save-prompt-btn">
                    <i class="feather-save me-1"></i> Save Prompt Sections
                </button>
            </form>
        </div>
    </div>

    <!-- Test Connection -->
    <div class="card mb-4" id="integrations-test-card">
        <div class="card-header" id="integrations-test-header">
            <h6 class="mb-0" id="integrations-test-title">Test Connection</h6>
        </div>
        <div class="card-body" id="integrations-test-body">
            <p class="text-muted mb-3" id="integrations-test-desc">Test the API by checking availability for today.</p>
            <button class="btn btn-outline-primary" id="integrations-test-btn"
                    hx-post="/partials/settings/save-integrations.php"
                    hx-vals='{"action": "test", "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                    hx-target="#integrations-test-result">
                <i class="feather-play me-1"></i> Test check_availability
            </button>
            <div id="integrations-test-result" class="mt-3"></div>
        </div>
    </div>
</div>
