<?php
/**
 * Email Template Editor — List, edit, and preview email templates
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAdmin();
$restaurantId = currentRestaurantId();
$pdo = db();

$message = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $action = $_POST['action'];
        require_once __DIR__ . '/../../../helpers/services/settings.php';

        try {
            if ($action === 'save_template') {
                svc_email_templates_save($restaurantId, (int)($_POST['id'] ?? 0), [
                    'template_key' => $_POST['template_key'] ?? '',
                    'subject'      => $_POST['subject'] ?? '',
                    'body_html'    => $_POST['body_html'] ?? '',
                    'body_text'    => $_POST['body_text'] ?? '',
                    'is_active'    => isset($_POST['is_active']),
                ]);
                $message = '<div class="alert alert-success">Template saved.</div>';
            }

            if ($action === 'delete_template') {
                svc_email_templates_delete($restaurantId, (int)($_POST['id'] ?? 0));
                $message = '<div class="alert alert-success">Template deleted.</div>';
            }
        } catch (ServiceError $e) {
            $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Editing a template?
$editId = (int)($_GET['edit'] ?? 0);
$editTemplate = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$editId, $restaurantId]);
    $editTemplate = $stmt->fetch();
}

// Show new form?
$showNew = isset($_GET['new']);

// Fetch all templates
$stmt = $pdo->prepare("SELECT * FROM email_templates WHERE restaurant_id = ? ORDER BY template_key");
$stmt->execute([$restaurantId]);
$templates = $stmt->fetchAll();

$keyLabels = [
    'confirmation'   => 'Booking Confirmation',
    'reminder'       => 'Reservation Reminder',
    'cancellation'   => 'Cancellation Notice',
    'modification'   => 'Modification Notice',
    'waitlist_ready'  => 'Waitlist Ready',
    'thank_you'      => 'Thank You / Follow-up',
];
?>

<div id="email-templates-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="email-templates-header">
        <div id="email-templates-title-wrap">
            <h4 class="mb-0" id="email-templates-title">Email Templates</h4>
            <small class="text-muted" id="email-templates-desc">Customize notification email content</small>
        </div>
        <div id="email-templates-actions">
            <a href="#" class="btn btn-primary btn-sm" id="email-templates-add-btn"
               hx-get="/partials/settings/email-templates.php?new=1"
               hx-target="#page-content">
                <i class="feather-plus me-1"></i> New Template
            </a>
            <a href="#" class="btn btn-outline-secondary btn-sm" id="email-templates-back-btn"
               hx-get="/partials/settings/notifications.php"
               hx-target="#page-content">
                Back to Settings
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($editTemplate || $showNew): ?>
    <!-- Template Edit Form -->
    <div class="card mb-3" id="email-template-form-card">
        <div class="card-body" id="email-template-form-body">
            <h5 class="card-title" id="email-template-form-title">
                <?php echo $editTemplate ? 'Edit Template' : 'New Template'; ?>
            </h5>
            <form method="POST" hx-post="/partials/settings/email-templates.php" hx-target="#page-content" id="email-template-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_template">
                <?php if ($editTemplate): ?>
                <input type="hidden" name="id" value="<?php echo $editTemplate['id']; ?>">
                <?php endif; ?>

                <?php if (!$editTemplate): ?>
                <div class="mb-3" id="email-template-key-group">
                    <label for="template-key" class="form-label">Template Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="template-key" name="template_key" required>
                        <option value="">Select type...</option>
                        <?php foreach ($keyLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <p class="text-muted" id="email-template-type-display">
                    Type: <strong><?php echo htmlspecialchars($keyLabels[$editTemplate['template_key']] ?? $editTemplate['template_key']); ?></strong>
                </p>
                <?php endif; ?>

                <div class="mb-3" id="email-template-subject-group">
                    <label for="template-subject" class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="template-subject" name="subject"
                           value="<?php echo htmlspecialchars($editTemplate['subject'] ?? ''); ?>" required>
                </div>

                <div class="mb-3" id="email-template-html-group">
                    <label for="template-body-html" class="form-label">HTML Body <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace" id="template-body-html" name="body_html" rows="12"
                              required><?php echo htmlspecialchars($editTemplate['body_html'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3" id="email-template-text-group">
                    <label for="template-body-text" class="form-label">Plain Text Body</label>
                    <textarea class="form-control font-monospace" id="template-body-text" name="body_text" rows="6"><?php echo htmlspecialchars($editTemplate['body_text'] ?? ''); ?></textarea>
                    <div class="form-text">Optional plain text fallback. If empty, HTML will be stripped for text version.</div>
                </div>

                <div class="form-check form-switch mb-3" id="email-template-active-group">
                    <input class="form-check-input" type="checkbox" id="template-active" name="is_active" value="1"
                           <?php echo (!$editTemplate || $editTemplate['is_active']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="template-active">Active</label>
                </div>

                <!-- Available Placeholders -->
                <div class="card bg-light mb-3" id="email-template-placeholders-card">
                    <div class="card-body" id="email-template-placeholders-body">
                        <h6 class="fw-bold" id="email-template-placeholders-title">Available Placeholders</h6>
                        <p class="text-muted small mb-2" id="email-template-placeholders-desc">Use these in your subject and body. They will be replaced with actual values.</p>
                        <div id="email-template-placeholders-list">
                            <code class="me-2">{{guest_name}}</code>
                            <code class="me-2">{{guest_first_name}}</code>
                            <code class="me-2">{{guest_last_name}}</code>
                            <code class="me-2">{{restaurant_name}}</code>
                            <code class="me-2">{{restaurant_phone}}</code>
                            <code class="me-2">{{restaurant_email}}</code>
                            <code class="me-2">{{date}}</code>
                            <code class="me-2">{{time}}</code>
                            <code class="me-2">{{party_size}}</code>
                            <code class="me-2">{{confirmation_code}}</code>
                            <code class="me-2">{{status}}</code>
                            <code class="me-2">{{table_name}}</code>
                            <code class="me-2">{{special_requests}}</code>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2" id="email-template-form-actions">
                    <button type="submit" class="btn btn-primary" id="email-template-save-btn">Save Template</button>
                    <a href="#" class="btn btn-outline-secondary" id="email-template-cancel-btn"
                       hx-get="/partials/settings/email-templates.php"
                       hx-target="#page-content">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Template List -->
    <div class="card" id="email-templates-list-card">
        <div class="card-body p-0" id="email-templates-list-body">
            <?php if (empty($templates)): ?>
            <div class="text-center py-5 text-muted" id="email-templates-empty">
                <p class="mb-1">No email templates configured.</p>
                <p class="small">Templates are optional — default emails will be used when templates are not defined.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="email-templates-table-wrap">
                <table class="table table-hover mb-0" id="email-templates-table">
                    <thead id="email-templates-thead">
                        <tr>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="email-templates-tbody">
                        <?php foreach ($templates as $t): ?>
                        <tr id="email-template-row-<?php echo $t['id']; ?>">
                            <td class="fw-semibold"><?php echo htmlspecialchars($keyLabels[$t['template_key']] ?? $t['template_key']); ?></td>
                            <td><?php echo htmlspecialchars($t['subject']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($t['updated_at'])); ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-outline-primary" title="Edit"
                                   hx-get="/partials/settings/email-templates.php?edit=<?php echo $t['id']; ?>"
                                   hx-target="#page-content">
                                    <i class="feather-edit"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                      hx-post="/partials/settings/email-templates.php"
                                      hx-target="#page-content"
                                      hx-confirm="Delete this template?">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </form>
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
