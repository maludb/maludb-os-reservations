<?php
/**
 * Guest Add/Edit Modal Form
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();

$id = (int)($_GET['id'] ?? 0);
$guest = null;

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM guests WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);
    $guest = $stmt->fetch();
    if (!$guest) {
        echo '<div class="alert alert-danger">Guest not found.</div>';
        exit;
    }
}

$isEdit = $guest !== null;
$title = $isEdit ? 'Edit Guest' : 'Add Guest';
?>

<div class="modal show d-block" tabindex="-1" id="guest-form-modal" style="background:rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="guest-form-dialog">
        <div class="modal-content" id="guest-form-content">
            <div class="modal-header" id="guest-form-header">
                <h5 class="modal-title" id="guest-form-title"><?php echo $title; ?></h5>
                <button type="button" class="btn-close" id="guest-form-close"
                        onclick="document.getElementById('guest-modal-container').innerHTML='';"></button>
            </div>
            <form hx-post="/partials/guests/save.php" hx-target="#guest-form-messages" hx-swap="innerHTML" id="guest-form">
                <div class="modal-body" id="guest-form-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $guest['id']; ?>">
                    <?php endif; ?>

                    <div id="guest-form-messages"></div>

                    <div class="row g-3" id="guest-form-row1">
                        <div class="col-md-6" id="guest-form-fname-group">
                            <label for="guest-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="guest-fname" name="first_name"
                                   value="<?php echo htmlspecialchars($guest['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="guest-form-lname-group">
                            <label for="guest-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="guest-lname" name="last_name"
                                   value="<?php echo htmlspecialchars($guest['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="guest-form-row2">
                        <div class="col-md-6" id="guest-form-email-group">
                            <label for="guest-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="guest-email" name="email"
                                   value="<?php echo htmlspecialchars($guest['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="guest-form-phone-group">
                            <label for="guest-phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="guest-phone" name="phone"
                                   value="<?php echo htmlspecialchars($guest['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="guest-form-row3">
                        <div class="col-12" id="guest-form-addr1-group">
                            <label for="guest-addr1" class="form-label">Address</label>
                            <input type="text" class="form-control" id="guest-addr1" name="address_line1"
                                   value="<?php echo htmlspecialchars($guest['address_line1'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="guest-form-row4">
                        <div class="col-md-5" id="guest-form-city-group">
                            <label for="guest-city" class="form-label">City</label>
                            <input type="text" class="form-control" id="guest-city" name="city"
                                   value="<?php echo htmlspecialchars($guest['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4" id="guest-form-state-group">
                            <label for="guest-state" class="form-label">State</label>
                            <input type="text" class="form-control" id="guest-state" name="state"
                                   value="<?php echo htmlspecialchars($guest['state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3" id="guest-form-zip-group">
                            <label for="guest-zip" class="form-label">ZIP</label>
                            <input type="text" class="form-control" id="guest-zip" name="postal_code"
                                   value="<?php echo htmlspecialchars($guest['postal_code'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="guest-form-row5">
                        <div class="col-md-6" id="guest-form-tags-group">
                            <label for="guest-tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="guest-tags" name="tags"
                                   value="<?php echo htmlspecialchars($guest['tags'] ?? ''); ?>"
                                   placeholder="vip, regular, reviewer">
                            <div class="form-text">Comma-separated</div>
                        </div>
                        <div class="col-md-6" id="guest-form-seating-group">
                            <label for="guest-seating" class="form-label">Seating Preference</label>
                            <input type="text" class="form-control" id="guest-seating" name="seating_preference"
                                   value="<?php echo htmlspecialchars($guest['seating_preference'] ?? ''); ?>"
                                   placeholder="booth, window, quiet">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="guest-form-row6">
                        <div class="col-md-6" id="guest-form-dietary-group">
                            <label for="guest-dietary" class="form-label">Dietary Restrictions</label>
                            <input type="text" class="form-control" id="guest-dietary" name="dietary_restrictions"
                                   value="<?php echo htmlspecialchars($guest['dietary_restrictions'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="guest-form-allergies-group">
                            <label for="guest-allergies" class="form-label">Allergies</label>
                            <input type="text" class="form-control" id="guest-allergies" name="allergies"
                                   value="<?php echo htmlspecialchars($guest['allergies'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mt-3" id="guest-form-notes-group">
                        <label for="guest-notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="guest-notes" name="notes" rows="2"><?php echo htmlspecialchars($guest['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="mt-3" id="guest-form-optout-group">
                        <label class="form-label">Contact Preferences</label>
                        <div class="d-flex flex-wrap gap-3" id="guest-form-optout-checks">
                            <div class="form-check" id="guest-form-dnc-wrap">
                                <input class="form-check-input" type="checkbox" id="guest-form-dnc" name="do_not_call" value="1"
                                       <?php echo !empty($guest['do_not_call']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="guest-form-dnc">Do Not Call</label>
                            </div>
                            <div class="form-check" id="guest-form-dne-wrap">
                                <input class="form-check-input" type="checkbox" id="guest-form-dne" name="do_not_email" value="1"
                                       <?php echo !empty($guest['do_not_email']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="guest-form-dne">Do Not Email</label>
                            </div>
                            <div class="form-check" id="guest-form-dnt-wrap">
                                <input class="form-check-input" type="checkbox" id="guest-form-dnt" name="do_not_text" value="1"
                                       <?php echo !empty($guest['do_not_text']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="guest-form-dnt">Do Not Text</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="guest-form-footer">
                    <button type="button" class="btn btn-secondary" id="guest-form-cancel-btn"
                            onclick="document.getElementById('guest-modal-container').innerHTML='';">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="guest-form-save-btn">
                        <?php echo $isEdit ? 'Update Guest' : 'Add Guest'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
