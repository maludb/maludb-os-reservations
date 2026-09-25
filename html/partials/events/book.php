<?php
/**
 * Event Quick-Book Modal — Add a reservation to an event
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId < 1) {
    echo '<div class="alert alert-danger">Invalid event.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$eventId, $restaurantId]);
$event = $stmt->fetch();

if (!$event) {
    echo '<div class="alert alert-danger">Event not found.</div>';
    exit;
}

// Remaining capacity
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(party_size), 0) FROM reservations
     WHERE event_id = ? AND restaurant_id = ? AND status NOT IN ('cancelled', 'no_show')"
);
$stmt->execute([$eventId, $restaurantId]);
$booked = (int)$stmt->fetchColumn();
$spotsLeft = max(0, (int)$event['max_capacity'] - $booked);
?>

<div class="modal show d-block" tabindex="-1" id="event-book-modal" style="background:rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="event-book-dialog">
        <div class="modal-content" id="event-book-content">
            <div class="modal-header" id="event-book-header">
                <h5 class="modal-title" id="event-book-title">Book for: <?php echo htmlspecialchars($event['name']); ?></h5>
                <button type="button" class="btn-close" id="event-book-close"
                        onclick="document.getElementById('event-detail-modal-container').innerHTML='';"></button>
            </div>
            <form hx-post="/partials/events/save-booking.php" hx-target="#event-book-messages" hx-swap="innerHTML" id="event-book-form">
                <div class="modal-body" id="event-book-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                    <div id="event-book-messages"></div>

                    <div class="alert alert-light py-2 mb-3" id="event-book-info">
                        <div class="small" id="event-book-info-date">
                            <i class="feather-calendar me-1"></i>
                            <?php echo date('l, M j, Y', strtotime($event['event_date'])); ?> at <?php echo date('g:ia', strtotime($event['start_time'])); ?>
                        </div>
                        <div class="small" id="event-book-info-spots">
                            <i class="feather-users me-1"></i>
                            <?php echo $spotsLeft; ?> spot<?php echo $spotsLeft !== 1 ? 's' : ''; ?> remaining
                        </div>
                    </div>

                    <!-- Guest Search -->
                    <div class="mb-3" id="event-book-guest-search-group">
                        <label class="form-label">Search Existing Guest</label>
                        <input type="text" class="form-control" id="event-book-guest-search"
                               placeholder="Type name, phone, or email..."
                               hx-get="/partials/guests/search.php"
                               hx-trigger="keyup changed delay:300ms"
                               hx-target="#event-book-guest-results"
                               hx-swap="innerHTML"
                               name="q" autocomplete="off">
                        <div id="event-book-guest-results" class="list-group mt-1"></div>
                        <input type="hidden" name="guest_id" id="event-book-guest-id" value="0">
                    </div>

                    <hr>
                    <div class="small text-muted mb-2" id="event-book-or-new">Or enter new guest details:</div>

                    <div class="row g-3" id="event-book-name-row">
                        <div class="col-md-6" id="event-book-fname-group">
                            <label for="event-book-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event-book-fname" name="first_name">
                        </div>
                        <div class="col-md-6" id="event-book-lname-group">
                            <label for="event-book-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event-book-lname" name="last_name">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="event-book-contact-row">
                        <div class="col-md-6" id="event-book-phone-group">
                            <label for="event-book-phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="event-book-phone" name="phone">
                        </div>
                        <div class="col-md-6" id="event-book-email-group">
                            <label for="event-book-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="event-book-email" name="email">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="event-book-party-row">
                        <div class="col-md-6" id="event-book-party-group">
                            <label for="event-book-party" class="form-label">Party Size <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="event-book-party" name="party_size"
                                   value="2" min="1" max="<?php echo $spotsLeft; ?>" required>
                        </div>
                    </div>

                    <div class="mt-3" id="event-book-requests-group">
                        <label for="event-book-requests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="event-book-requests" name="special_requests" rows="2"
                                  placeholder="Dietary needs, allergies, seating preferences..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" id="event-book-footer">
                    <button type="button" class="btn btn-secondary" id="event-book-cancel-btn"
                            onclick="document.getElementById('event-detail-modal-container').innerHTML='';">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="event-book-submit-btn">
                        <i class="feather-check me-1"></i> Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle guest search result selection
document.addEventListener('click', function(e) {
    var item = e.target.closest('[data-guest-id]');
    if (!item || !document.getElementById('event-book-modal')) return;
    document.getElementById('event-book-guest-id').value = item.dataset.guestId;
    document.getElementById('event-book-fname').value = item.dataset.firstName || '';
    document.getElementById('event-book-lname').value = item.dataset.lastName || '';
    document.getElementById('event-book-phone').value = item.dataset.phone || '';
    document.getElementById('event-book-email').value = item.dataset.email || '';
    document.getElementById('event-book-guest-search').value = item.dataset.firstName + ' ' + item.dataset.lastName;
    document.getElementById('event-book-guest-results').innerHTML = '';
});
</script>
