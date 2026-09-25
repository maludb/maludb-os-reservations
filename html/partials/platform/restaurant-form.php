<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
$restaurant = null;
$isEdit = false;

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$id]);
    $restaurant = $stmt->fetch();
    if (!$restaurant) {
        echo '<div class="alert alert-danger" id="platform-form-not-found">Restaurant not found.</div>';
        exit;
    }
    $isEdit = true;
}

$timezones = [
    'America/New_York' => 'Eastern Time (ET)',
    'America/Chicago' => 'Central Time (CT)',
    'America/Denver' => 'Mountain Time (MT)',
    'America/Los_Angeles' => 'Pacific Time (PT)',
    'America/Anchorage' => 'Alaska Time (AKT)',
    'Pacific/Honolulu' => 'Hawaii Time (HT)',
    'America/Phoenix' => 'Arizona (no DST)',
];
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-restaurant-modal">
    <div class="modal-dialog modal-lg" id="platform-restaurant-modal-dialog">
        <div class="modal-content" id="platform-restaurant-modal-content">
            <div class="modal-header" id="platform-restaurant-modal-header">
                <h5 class="modal-title" id="platform-restaurant-modal-title">
                    <?php echo $isEdit ? 'Edit Restaurant' : 'Add Restaurant'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-restaurant.php"
                  hx-target="#page-content"
                  id="platform-restaurant-form">
                <div class="modal-body" id="platform-restaurant-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$restaurant['id']; ?>">
                    <?php endif; ?>

                    <div id="platform-form-feedback"></div>

                    <div class="row g-3" id="platform-form-fields">
                        <div class="col-md-8" id="platform-form-name-wrap">
                            <label for="platform-form-name" class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-form-name" name="name"
                                   value="<?php echo htmlspecialchars($restaurant['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4" id="platform-form-slug-wrap">
                            <label for="platform-form-slug" class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-form-slug" name="slug"
                                   value="<?php echo htmlspecialchars($restaurant['slug'] ?? ''); ?>"
                                   pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only"
                                   <?php echo $isEdit ? '' : 'oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9-]/g,\'-\').replace(/--+/g,\'-\')"'; ?>
                                   required>
                            <small class="text-muted">Used in booking URL</small>
                        </div>
                        <div class="col-md-6" id="platform-form-phone-wrap">
                            <label for="platform-form-phone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" class="form-control" id="platform-form-phone" name="phone"
                                   value="<?php echo htmlspecialchars($restaurant['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="platform-form-email-wrap">
                            <label for="platform-form-email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="platform-form-email" name="email"
                                   value="<?php echo htmlspecialchars($restaurant['email'] ?? ''); ?>">
                        </div>
                        <div class="col-12" id="platform-form-address-wrap">
                            <label for="platform-form-address" class="form-label fw-semibold">Street Address</label>
                            <input type="text" class="form-control" id="platform-form-address" name="address_line1"
                                   value="<?php echo htmlspecialchars($restaurant['address_line1'] ?? ''); ?>">
                        </div>
                        <div class="col-md-5" id="platform-form-city-wrap">
                            <label for="platform-form-city" class="form-label fw-semibold">City</label>
                            <input type="text" class="form-control" id="platform-form-city" name="city"
                                   value="<?php echo htmlspecialchars($restaurant['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3" id="platform-form-state-wrap">
                            <label for="platform-form-state" class="form-label fw-semibold">State</label>
                            <input type="text" class="form-control" id="platform-form-state" name="state"
                                   value="<?php echo htmlspecialchars($restaurant['state'] ?? ''); ?>" maxlength="2">
                        </div>
                        <div class="col-md-4" id="platform-form-zip-wrap">
                            <label for="platform-form-zip" class="form-label fw-semibold">ZIP Code</label>
                            <input type="text" class="form-control" id="platform-form-zip" name="postal_code"
                                   value="<?php echo htmlspecialchars($restaurant['postal_code'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="platform-form-website-wrap">
                            <label for="platform-form-website" class="form-label fw-semibold">Website</label>
                            <input type="url" class="form-control" id="platform-form-website" name="website"
                                   value="<?php echo htmlspecialchars($restaurant['website'] ?? ''); ?>"
                                   placeholder="https://">
                        </div>
                        <div class="col-md-6" id="platform-form-timezone-wrap">
                            <label for="platform-form-timezone" class="form-label fw-semibold">Timezone <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-form-timezone" name="timezone" required>
                                <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?php echo $tz; ?>" <?php echo ($restaurant['timezone'] ?? 'America/New_York') === $tz ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-restaurant-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="platform-form-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-form-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Restaurant
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
