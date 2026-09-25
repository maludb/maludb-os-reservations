<?php
/**
 * Add to Waitlist — Modal form with guest search
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
?>

<div class="modal show d-block" tabindex="-1" id="waitlist-form-modal" style="background:rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="waitlist-form-dialog">
        <div class="modal-content" id="waitlist-form-content">
            <div class="modal-header" id="waitlist-form-header">
                <h5 class="modal-title" id="waitlist-form-title">Add to Waitlist</h5>
                <button type="button" class="btn-close" id="waitlist-form-close"
                        onclick="document.getElementById('waitlist-modal-container').innerHTML='';"></button>
            </div>
            <form hx-post="/partials/waitlist/save.php" hx-target="#waitlist-form-messages" hx-swap="innerHTML" id="waitlist-form">
                <div class="modal-body" id="waitlist-form-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="guest_id" id="waitlist-guest-id" value="">

                    <div id="waitlist-form-messages"></div>

                    <!-- Guest Search -->
                    <div class="mb-3" id="waitlist-search-group">
                        <label class="form-label" id="waitlist-search-label">Search Existing Guest</label>
                        <input type="text" class="form-control" id="waitlist-guest-search"
                               placeholder="Type name, email, or phone..."
                               hx-get="/partials/guests/search.php"
                               hx-trigger="keyup changed delay:300ms"
                               hx-target="#waitlist-search-results"
                               hx-swap="innerHTML"
                               name="q"
                               autocomplete="off">
                        <div class="list-group mt-1" id="waitlist-search-results"></div>
                    </div>

                    <hr id="waitlist-form-divider">

                    <div class="row g-3" id="waitlist-form-row1">
                        <div class="col-8" id="waitlist-name-group">
                            <label for="waitlist-guest-name" class="form-label">Guest Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="waitlist-guest-name" name="guest_name" required>
                        </div>
                        <div class="col-4" id="waitlist-party-group">
                            <label for="waitlist-party-size" class="form-label">Party Size <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="waitlist-party-size" name="party_size"
                                   min="1" max="20" value="2" required>
                        </div>
                    </div>

                    <div class="mb-3 mt-3" id="waitlist-phone-group">
                        <label for="waitlist-phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="waitlist-phone" name="phone"
                               placeholder="For SMS notification">
                    </div>

                    <div class="mb-3" id="waitlist-quoted-wait-group">
                        <label for="waitlist-quoted-wait" class="form-label">Quoted Wait Time (minutes) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="waitlist-quoted-wait" name="quoted_wait_minutes"
                               min="0" max="180" value="" placeholder="e.g., 30" required>
                        <div class="form-text">The wait time communicated to the guest.</div>
                    </div>

                    <div class="mb-3" id="waitlist-seating-group">
                        <label for="waitlist-seating" class="form-label">Seating Preference</label>
                        <input type="text" class="form-control" id="waitlist-seating" name="seating_preference"
                               placeholder="e.g., booth, patio, quiet area">
                    </div>

                    <div class="mb-3" id="waitlist-notes-group">
                        <label for="waitlist-notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="waitlist-notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer" id="waitlist-form-footer">
                    <button type="button" class="btn btn-secondary" id="waitlist-form-cancel-btn"
                            onclick="document.getElementById('waitlist-modal-container').innerHTML='';">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="waitlist-form-save-btn">Add to Waitlist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Override guest search result clicks to populate waitlist form instead of navigating
document.getElementById('waitlist-search-results').addEventListener('click', function(e) {
    var link = e.target.closest('a');
    if (link) {
        e.preventDefault();
        e.stopPropagation();
        // Extract guest ID from href
        var match = link.getAttribute('hx-get').match(/id=(\d+)/);
        if (match) {
            // Fetch guest data and populate form
            fetch('/partials/guests/search.php?q=' + encodeURIComponent(document.getElementById('waitlist-guest-search').value))
                .then(function() {
                    var name = link.querySelector('strong');
                    if (name) {
                        document.getElementById('waitlist-guest-name').value = name.textContent.trim();
                    }
                    document.getElementById('waitlist-guest-id').value = match[1];
                    document.getElementById('waitlist-search-results').innerHTML = '';
                    document.getElementById('waitlist-guest-search').value = '';
                });
        }
    }
}, true);
</script>
