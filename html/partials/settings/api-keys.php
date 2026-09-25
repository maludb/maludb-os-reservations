<?php
/**
 * Settings → API Keys: keys that let AI agents and scripts use the REST API
 * and the admin MCP server as the key's owner.
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAdmin();

$restaurantId = currentRestaurantId();

$stmt = db()->prepare(
    "SELECT t.id, t.name, t.device_name, t.expires_at, t.last_used_at, t.revoked_at, t.created_at,
            u.first_name, u.last_name, u.email
     FROM api_tokens t JOIN users u ON u.id = t.user_id
     WHERE t.restaurant_id = ?
     ORDER BY t.revoked_at IS NOT NULL, t.created_at DESC"
);
$stmt->execute([$restaurantId]);
$keys = $stmt->fetchAll();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $_SERVER['HTTP_HOST'];
$csrf = htmlspecialchars(generate_csrf_token());
?>
<div class="main-content" id="api-keys-main"
     hx-get="/partials/settings/api-keys.php"
     hx-trigger="refreshApiKeys from:body"
     hx-target="#api-keys-main"
     hx-swap="outerHTML">
    <div class="row" id="api-keys-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="api-keys-col">

            <div class="card" id="api-keys-card">
                <div class="card-header" id="api-keys-card-header">
                    <h5 class="card-title mb-0" id="api-keys-card-title">
                        <i class="feather-key me-2"></i>API Keys
                    </h5>
                </div>
                <div class="card-body" id="api-keys-card-body">

                    <p class="text-muted" id="api-keys-help">
                        An API key lets an AI agent or script manage this restaurant through the REST API or the
                        MCP server. A key acts as the user who created it, with that user's role, and every change
                        it makes is recorded in the activity log.
                    </p>

                    <div id="api-keys-messages"></div>

                    <form class="row g-2 align-items-end mb-4" id="api-keys-create-form"
                          hx-post="/partials/settings/save-api-key.php"
                          hx-target="#api-keys-messages"
                          hx-swap="innerHTML">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="col-md-6" id="api-keys-name-col">
                            <label class="form-label" for="api-keys-name">Key name</label>
                            <input type="text" class="form-control" id="api-keys-name" name="name" maxlength="100"
                                   placeholder="e.g. Claude admin agent" required>
                        </div>
                        <div class="col-md-3" id="api-keys-days-col">
                            <label class="form-label" for="api-keys-days">Expires after</label>
                            <select class="form-select" id="api-keys-days" name="days">
                                <option value="30">30 days</option>
                                <option value="90">90 days</option>
                                <option value="365" selected>1 year</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="api-keys-submit-col">
                            <button type="submit" class="btn btn-primary w-100" id="api-keys-create-btn">
                                <i class="feather-plus me-1"></i> Create Key
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive" id="api-keys-table-wrapper">
                        <table class="table table-hover align-middle" id="api-keys-table">
                            <thead id="api-keys-table-head">
                                <tr>
                                    <th>Name</th>
                                    <th>Owner</th>
                                    <th>Last used</th>
                                    <th>Expires</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="api-keys-table-body">
                                <?php if (empty($keys)): ?>
                                <tr id="api-keys-empty-row">
                                    <td colspan="5" class="text-center text-muted py-4">No API keys yet.</td>
                                </tr>
                                <?php endif; ?>
                                <?php foreach ($keys as $k):
                                    $expired = strtotime($k['expires_at']) < time(); ?>
                                <tr id="api-keys-row-<?php echo (int)$k['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($k['name'] ?? $k['device_name'] ?? 'Login token'); ?></strong></td>
                                    <td><?php echo htmlspecialchars(trim($k['first_name'] . ' ' . $k['last_name']) ?: $k['email']); ?></td>
                                    <td class="text-muted"><?php echo $k['last_used_at'] ? date('M j, Y g:ia', strtotime($k['last_used_at'])) : 'Never'; ?></td>
                                    <td class="text-muted"><?php echo date('M j, Y', strtotime($k['expires_at'])); ?></td>
                                    <td class="text-end" id="api-keys-status-<?php echo (int)$k['id']; ?>">
                                        <?php if ($k['revoked_at']): ?>
                                        <span class="badge bg-secondary">Revoked</span>
                                        <?php elseif ($expired): ?>
                                        <span class="badge bg-warning text-dark">Expired</span>
                                        <?php else: ?>
                                        <button class="btn btn-outline-danger btn-sm"
                                                hx-post="/partials/settings/save-api-key.php"
                                                hx-vals='{"action": "revoke", "key_id": <?php echo (int)$k['id']; ?>, "csrf_token": "<?php echo $csrf; ?>"}'
                                                hx-target="#api-keys-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Revoke this key? Agents using it will stop working immediately.">
                                            Revoke
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3" id="api-keys-connect-card">
                <div class="card-header" id="api-keys-connect-header">
                    <h5 class="card-title mb-0" id="api-keys-connect-title">
                        <i class="feather-link me-2"></i>Connecting an agent
                    </h5>
                </div>
                <div class="card-body" id="api-keys-connect-body">
                    <dl class="row mb-0" id="api-keys-connect-list">
                        <dt class="col-sm-3">MCP server</dt>
                        <dd class="col-sm-9"><code><?php echo htmlspecialchars($base); ?>/api/mcp/admin.php</code> (Streamable HTTP)</dd>
                        <dt class="col-sm-3">REST API</dt>
                        <dd class="col-sm-9"><code><?php echo htmlspecialchars($base); ?>/api/v1/index.php/</code></dd>
                        <dt class="col-sm-3">OpenAPI</dt>
                        <dd class="col-sm-9"><code><?php echo htmlspecialchars($base); ?>/api/v1/index.php/openapi.json</code></dd>
                        <dt class="col-sm-3">Header</dt>
                        <dd class="col-sm-9 mb-0"><code>Authorization: Bearer &lt;your key&gt;</code></dd>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</div>
