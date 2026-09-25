<?php
/**
 * Phone Number Management
 * Admins can manage multiple phone numbers per restaurant with language associations.
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

// Fetch all phone numbers for this restaurant
$stmt = $pdo->prepare(
    "SELECT * FROM restaurant_phone_numbers WHERE restaurant_id = ? ORDER BY is_primary DESC, created_at ASC"
);
$stmt->execute([$restaurantId]);
$phones = $stmt->fetchAll();
?>

<div class="p-4" id="phone-numbers-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="phone-numbers-header">
        <h4 class="fw-bold mb-0" id="phone-numbers-title">
            <i class="feather-phone me-2"></i>Phone Numbers
        </h4>
        <button class="btn btn-primary" id="phone-numbers-add-btn"
                onclick="editPhone('', '', '', 'en', 'English', 0)">
            <i class="feather-plus me-1"></i> Add Phone Number
        </button>
    </div>

    <div id="phone-numbers-feedback"></div>

    <!-- Add/Edit Form (hidden by default) -->
    <div class="card mb-4" id="phone-numbers-form-card" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center" id="phone-numbers-form-header">
            <h6 class="mb-0" id="phone-number-form-title">Add Phone Number</h6>
            <button type="button" class="btn-close" id="phone-numbers-form-close"
                    onclick="document.getElementById('phone-numbers-form-card').style.display='none';"></button>
        </div>
        <div class="card-body" id="phone-numbers-form-body">
            <form hx-post="/partials/settings/save-phone-number.php"
                  hx-target="#phone-numbers-feedback"
                  hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshPhoneNumbers'); }"
                  id="phone-number-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="phone-number-id" value="">

                <div class="row g-3" id="phone-number-fields">
                    <div class="col-md-6" id="phone-number-value-wrap">
                        <label for="phone-number-value" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone-number-value" name="phone_number"
                               required placeholder="+18005551234">
                    </div>
                    <div class="col-md-6" id="phone-number-label-wrap">
                        <label for="phone-number-label" class="form-label fw-semibold">Label</label>
                        <input type="text" class="form-control" id="phone-number-label" name="label"
                               placeholder="e.g. Main Line, Reservations, Spanish Line">
                    </div>
                    <div class="col-md-4" id="phone-number-language-wrap">
                        <label for="phone-number-language" class="form-label fw-semibold">Default Language</label>
                        <select class="form-select" id="phone-number-language" name="language_code" onchange="updatePhoneLangName(this)">
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
                        <input type="hidden" id="phone-number-language-name" name="language_name" value="English">
                    </div>
                    <div class="col-md-4" id="phone-number-language-other-wrap" style="display:none;">
                        <label for="phone-number-language-other" class="form-label fw-semibold">Language Name</label>
                        <input type="text" class="form-control" id="phone-number-language-other" placeholder="e.g. Vietnamese">
                    </div>
                    <div class="col-md-4 d-flex align-items-end" id="phone-number-primary-wrap">
                        <div class="form-check mb-2" id="phone-number-primary-check">
                            <input class="form-check-input" type="checkbox" id="phone-number-primary" name="is_primary" value="1">
                            <label class="form-check-label" for="phone-number-primary">Primary number</label>
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2" id="phone-number-submit-wrap">
                    <button type="submit" class="btn btn-primary" id="phone-number-save-btn">
                        <i class="feather-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="phone-number-cancel-btn"
                            onclick="document.getElementById('phone-numbers-form-card').style.display='none';">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Phone Numbers List -->
    <div class="card mb-4" id="phone-numbers-list-card"
         hx-get="/partials/settings/phone-numbers.php"
         hx-trigger="refreshPhoneNumbers from:body"
         hx-target="#phone-numbers-page"
         hx-swap="outerHTML"
         hx-select="#phone-numbers-page">
        <div class="card-header" id="phone-numbers-list-header">
            <h6 class="mb-0" id="phone-numbers-list-title">Phone Numbers (<?php echo count($phones); ?>)</h6>
        </div>
        <div class="card-body" id="phone-numbers-list-body">
            <?php if (empty($phones)): ?>
            <div class="text-center py-5 text-muted" id="phone-numbers-empty">
                <i class="feather-phone-off fs-1 d-block mb-2"></i>
                <p class="mb-0">No phone numbers configured yet.</p>
                <small>Click "Add Phone Number" to create one.</small>
            </div>
            <?php else: ?>
            <div id="phone-numbers-cards-list">
                <?php foreach ($phones as $p): ?>
                <div class="card mb-3 border" id="phone-number-card-<?php echo $p['id']; ?>">
                    <div class="card-body" id="phone-number-card-body-<?php echo $p['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start" id="phone-number-card-top-<?php echo $p['id']; ?>">
                            <div>
                                <h6 class="mb-1" id="phone-number-card-number-<?php echo $p['id']; ?>">
                                    <i class="feather-phone me-1"></i>
                                    <?php echo htmlspecialchars($p['phone_number']); ?>
                                    <?php if (!empty($p['label'])): ?>
                                    <span class="text-muted fw-normal ms-2"><?php echo htmlspecialchars($p['label']); ?></span>
                                    <?php endif; ?>
                                </h6>
                                <div class="d-flex gap-2 mt-1" id="phone-number-card-badges-<?php echo $p['id']; ?>">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($p['language_name']); ?></span>
                                    <?php if ($p['is_primary']): ?>
                                    <span class="badge bg-success">Primary</span>
                                    <?php endif; ?>
                                    <?php if (!$p['is_active']): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm" id="phone-number-actions-<?php echo $p['id']; ?>">
                                <button class="btn btn-outline-primary" title="Edit"
                                        onclick="editPhone(<?php echo $p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['phone_number'])); ?>, <?php echo htmlspecialchars(json_encode($p['label'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($p['language_code'])); ?>, <?php echo htmlspecialchars(json_encode($p['language_name'])); ?>, <?php echo (int)$p['is_primary']; ?>)">
                                    <i class="feather-edit-2"></i>
                                </button>
                                <button class="btn btn-outline-danger" title="Delete"
                                        hx-post="/partials/settings/delete-phone-number.php"
                                        hx-vals='<?php echo json_encode(["id" => $p['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                        hx-target="#phone-numbers-feedback"
                                        hx-swap="innerHTML"
                                        hx-confirm="Delete this phone number?"
                                        hx-on::after-request="if(event.detail.successful) { htmx.trigger(document.body, 'refreshPhoneNumbers'); }">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
var phoneLangMap = {en:'English',es:'Spanish',fr:'French',pt:'Portuguese',de:'German',it:'Italian',zh:'Chinese',ja:'Japanese',ko:'Korean',pl:'Polish',ro:'Romanian',ar:'Arabic',hi:'Hindi'};

function updatePhoneLangName(sel) {
    var code = sel.value;
    var otherWrap = document.getElementById('phone-number-language-other-wrap');
    if (code === 'other') {
        otherWrap.style.display = '';
        document.getElementById('phone-number-language-name').value = document.getElementById('phone-number-language-other').value || '';
    } else {
        otherWrap.style.display = 'none';
        document.getElementById('phone-number-language-name').value = phoneLangMap[code] || code;
        document.getElementById('phone-number-language-other').value = '';
    }
}

function editPhone(id, phoneNumber, label, langCode, langName, isPrimary) {
    document.getElementById('phone-numbers-form-card').style.display = 'block';
    document.getElementById('phone-number-form-title').textContent = id ? 'Edit Phone Number' : 'Add Phone Number';
    document.getElementById('phone-number-id').value = id || '';
    document.getElementById('phone-number-value').value = phoneNumber || '';
    document.getElementById('phone-number-label').value = label || '';

    // Language fields
    var lc = langCode || 'en';
    var ln = langName || 'English';
    var sel = document.getElementById('phone-number-language');
    var otherWrap = document.getElementById('phone-number-language-other-wrap');
    if (phoneLangMap[lc]) {
        sel.value = lc;
        otherWrap.style.display = 'none';
        document.getElementById('phone-number-language-other').value = '';
    } else {
        sel.value = 'other';
        otherWrap.style.display = '';
        document.getElementById('phone-number-language-other').value = ln;
    }
    document.getElementById('phone-number-language-name').value = ln;
    document.getElementById('phone-number-primary').checked = !!isPrimary;

    // Keep language_name in sync when typing custom name
    document.getElementById('phone-number-language-other').oninput = function() {
        document.getElementById('phone-number-language-name').value = this.value;
    };

    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
</div>
