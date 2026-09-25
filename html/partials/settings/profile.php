<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

$restaurantId = currentRestaurantId();
$stmt = db()->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    echo '<div class="alert alert-danger" id="profile-error-notfound">Restaurant not found.</div>';
    exit;
}

$timezones = [
    'America/New_York' => 'Eastern (New York)',
    'America/Chicago' => 'Central (Chicago)',
    'America/Denver' => 'Mountain (Denver)',
    'America/Phoenix' => 'Arizona (Phoenix)',
    'America/Los_Angeles' => 'Pacific (Los Angeles)',
    'America/Anchorage' => 'Alaska (Anchorage)',
    'Pacific/Honolulu' => 'Hawaii (Honolulu)',
];
?>
<div class="main-content" id="profile-main">
    <div class="row" id="profile-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="profile-col">

            <div class="card" id="profile-card">
                <div class="card-header d-flex align-items-center" id="profile-card-header">
                    <h5 class="card-title mb-0" id="profile-card-title">
                        <i class="feather-home me-2"></i>Business Profile
                    </h5>
                </div>
                <div class="card-body" id="profile-card-body">

                    <!-- Feedback message area -->
                    <div id="profile-messages"></div>

                    <form id="profile-form"
                          hx-post="/partials/settings/save-profile.php"
                          hx-target="#profile-messages"
                          hx-swap="innerHTML">
                        <?php echo csrf_field(); ?>

                        <!-- Restaurant Name -->
                        <div class="mb-3" id="profile-name-group">
                            <label for="profile-name" class="form-label">Restaurant Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="profile-name" name="name"
                                   value="<?php echo htmlspecialchars($restaurant['name']); ?>" required>
                        </div>

                        <!-- Slug (read-only) -->
                        <div class="mb-3" id="profile-slug-group">
                            <label for="profile-slug" class="form-label">Slug (URL identifier)</label>
                            <input type="text" class="form-control" id="profile-slug" name="slug"
                                   value="<?php echo htmlspecialchars($restaurant['slug']); ?>" readonly
                                   style="background-color: #f8f9fa;">
                            <div class="form-text" id="profile-slug-help">Public booking URL: /booking/?restaurant=<?php echo htmlspecialchars($restaurant['slug']); ?></div>
                        </div>

                        <div class="row" id="profile-contact-row">
                            <!-- Phone -->
                            <div class="col-md-6 mb-3" id="profile-phone-group">
                                <label for="profile-phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="profile-phone" name="phone"
                                       value="<?php echo htmlspecialchars($restaurant['phone'] ?? ''); ?>">
                            </div>

                            <!-- Email -->
                            <div class="col-md-6 mb-3" id="profile-email-group">
                                <label for="profile-email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="profile-email" name="email"
                                       value="<?php echo htmlspecialchars($restaurant['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="mb-3" id="profile-address1-group">
                            <label for="profile-address1" class="form-label">Street Address</label>
                            <input type="text" class="form-control" id="profile-address1" name="address_line1"
                                   value="<?php echo htmlspecialchars($restaurant['address_line1'] ?? ''); ?>">
                        </div>

                        <div class="row" id="profile-city-state-row">
                            <div class="col-md-5 mb-3" id="profile-city-group">
                                <label for="profile-city" class="form-label">City</label>
                                <input type="text" class="form-control" id="profile-city" name="city"
                                       value="<?php echo htmlspecialchars($restaurant['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3" id="profile-state-group">
                                <label for="profile-state" class="form-label">State</label>
                                <input type="text" class="form-control" id="profile-state" name="state"
                                       value="<?php echo htmlspecialchars($restaurant['state'] ?? ''); ?>" maxlength="50">
                            </div>
                            <div class="col-md-4 mb-3" id="profile-zip-group">
                                <label for="profile-zip" class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" id="profile-zip" name="postal_code"
                                       value="<?php echo htmlspecialchars($restaurant['postal_code'] ?? ''); ?>" maxlength="20">
                            </div>
                        </div>

                        <!-- Website -->
                        <div class="mb-3" id="profile-website-group">
                            <label for="profile-website" class="form-label">Website URL</label>
                            <input type="url" class="form-control" id="profile-website" name="website"
                                   value="<?php echo htmlspecialchars($restaurant['website'] ?? ''); ?>"
                                   placeholder="https://">
                        </div>

                        <!-- Timezone -->
                        <div class="mb-4" id="profile-timezone-group">
                            <label for="profile-timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                            <select class="form-select" id="profile-timezone" name="timezone" required>
                                <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?php echo htmlspecialchars($tz); ?>"
                                    <?php echo ($restaurant['timezone'] === $tz) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Submit -->
                        <div id="profile-submit-group">
                            <button type="submit" class="btn btn-primary" id="profile-save-btn">
                                <i class="feather-save me-1"></i> Save Changes
                                <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
