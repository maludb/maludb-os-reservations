<?php
/**
 * Staff Reservation Creation Form
 * Progressive multi-step form using HTMX
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();

// Get tables for manual assignment dropdown
$stmt = db()->prepare(
    "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name as section_name
     FROM tables t
     JOIN sections s ON t.section_id = s.id
     WHERE t.restaurant_id = ? AND t.is_active = 1 AND s.is_active = 1
     ORDER BY s.display_order ASC, t.sort_order ASC"
);
$stmt->execute([$restaurantId]);
$tables = $stmt->fetchAll();

$today = date('Y-m-d');
?>
<div class="main-content" id="create-res-main">
    <div class="row" id="create-res-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="create-res-col">

            <div class="card" id="create-res-card">
                <div class="card-header" id="create-res-card-header">
                    <h5 class="card-title mb-0" id="create-res-card-title">
                        <i class="feather-plus-circle me-2"></i>New Reservation
                    </h5>
                </div>
                <div class="card-body" id="create-res-card-body">

                    <div id="create-res-messages"></div>

                    <form id="create-res-form"
                          hx-post="/partials/reservations/save.php"
                          hx-target="#create-res-messages"
                          hx-swap="innerHTML">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="reservation_time" id="res-time" value="">
                        <input type="hidden" name="turn_time" id="res-turn-time" value="">

                        <!-- Step 1: Date, Party Size, Check Availability -->
                        <div class="card card-body bg-light mb-3" id="create-res-step1">
                            <h6 class="fw-bold mb-3" id="create-res-step1-title">1. Select Date &amp; Party Size</h6>
                            <div class="row" id="create-res-step1-row">
                                <div class="col-md-4 mb-3" id="create-res-date-group">
                                    <label for="res-date" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="res-date" name="reservation_date"
                                           value="<?php echo $today; ?>" min="<?php echo $today; ?>" required>
                                </div>
                                <div class="col-md-3 mb-3" id="create-res-party-group">
                                    <label for="res-party" class="form-label">Party Size <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="res-party" name="party_size"
                                           value="2" min="1" max="50" required>
                                </div>
                                <div class="col-md-5 mb-3 d-flex align-items-end" id="create-res-check-group">
                                    <button type="button" class="btn btn-outline-primary" id="create-res-check-btn"
                                            hx-get="/partials/reservations/check-availability.php"
                                            hx-include="#res-date, #res-party"
                                            hx-target="#create-res-availability"
                                            hx-swap="innerHTML">
                                        <i class="feather-search me-1"></i> Check Availability
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Availability Results -->
                        <div class="card card-body bg-light mb-3" id="create-res-step2">
                            <h6 class="fw-bold mb-2" id="create-res-step2-title">
                                2. Select Time
                                <span id="res-selected-time" class="badge bg-primary ms-2" style="display:none;"></span>
                            </h6>
                            <div id="create-res-availability">
                                <p class="text-muted" id="create-res-avail-placeholder">Click "Check Availability" to see available time slots.</p>
                            </div>
                        </div>

                        <!-- Step 3: Guest Information -->
                        <div class="card card-body bg-light mb-3" id="create-res-step3">
                            <h6 class="fw-bold mb-3" id="create-res-step3-title">3. Guest Information</h6>

                            <!-- Guest search -->
                            <div class="mb-3" id="create-res-guest-search-group">
                                <label for="res-guest-search" class="form-label">Search Existing Guest</label>
                                <input type="text" class="form-control" id="res-guest-search"
                                       placeholder="Type name, email, or phone..."
                                       hx-get="/partials/reservations/guest-search.php"
                                       hx-trigger="keyup changed delay:300ms"
                                       hx-target="#create-res-guest-results"
                                       hx-swap="innerHTML"
                                       name="guest_query"
                                       autocomplete="off">
                                <div id="create-res-guest-results"></div>
                            </div>

                            <input type="hidden" name="guest_id" id="res-guest-id" value="">

                            <div class="row" id="create-res-guest-row">
                                <div class="col-md-6 mb-3" id="create-res-fname-group">
                                    <label for="res-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="res-fname" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3" id="create-res-lname-group">
                                    <label for="res-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="res-lname" name="last_name" required>
                                </div>
                            </div>
                            <div class="row" id="create-res-contact-row">
                                <div class="col-md-6 mb-3" id="create-res-phone-group">
                                    <label for="res-phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="res-phone" name="phone">
                                </div>
                                <div class="col-md-6 mb-3" id="create-res-email-group">
                                    <label for="res-email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="res-email" name="email">
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Submit -->
                        <div class="d-flex gap-2" id="create-res-submit-group">
                            <button type="submit" class="btn btn-primary btn-lg" id="create-res-submit-btn">
                                <i class="feather-check me-1"></i> Create Reservation
                                <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg" id="create-res-cancel-btn"
                                    hx-get="/partials/reservations/calendar.php"
                                    hx-target="#page-content"
                                    hx-swap="innerHTML">
                                Cancel
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
        <div class="col-xxl-4 col-12" id="create-res-details-col">
            <!-- Step 4: Details -->
            <div class="card card-body bg-light mb-3" id="create-res-step4">
                <h6 class="fw-bold mb-3" id="create-res-step4-title">4. Details</h6>

                <div class="mb-3" id="create-res-table-group">
                    <label for="res-table" class="form-label">Table (optional)</label>
                    <select class="form-select" id="res-table" name="table_id" form="create-res-form">
                        <option value="">Auto-assign</option>
                        <?php foreach ($tables as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['table_name']); ?>
                            (<?php echo htmlspecialchars($t['section_name']); ?>, <?php echo $t['min_seats']; ?>-<?php echo $t['max_seats']; ?> seats)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3" id="create-res-source-group">
                    <label for="res-source" class="form-label">Source</label>
                    <select class="form-select" id="res-source" name="source" form="create-res-form">
                        <option value="phone">Phone</option>
                        <option value="staff">Staff / In-Person</option>
                        <option value="walk_in">Walk-In</option>
                    </select>
                </div>

                <div class="mb-3" id="create-res-requests-group">
                    <label for="res-requests" class="form-label">Special Requests</label>
                    <textarea class="form-control" id="res-requests" name="special_requests" rows="2"
                              placeholder="Allergies, celebrations, seating preferences..." form="create-res-form"></textarea>
                </div>

                <div class="mb-3" id="create-res-notes-group">
                    <label for="res-notes" class="form-label">Internal Notes (staff only)</label>
                    <textarea class="form-control" id="res-notes" name="internal_notes" rows="2" form="create-res-form"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
