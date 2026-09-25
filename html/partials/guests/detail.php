<?php
/**
 * Guest Profile Detail — Full CRM view
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// Get restaurant slug for voice agent context
$slugStmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
$slugStmt->execute([$restaurantId]);
$restaurantSlug = ($slugStmt->fetch())['slug'] ?? '';

$guestId = (int)($_GET['id'] ?? 0);
if ($guestId < 1) {
    echo '<div class="alert alert-danger" id="guest-detail-error">Invalid guest ID.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$guestId, $restaurantId]);
$guest = $stmt->fetch();

if (!$guest) {
    echo '<div class="alert alert-warning" id="guest-detail-not-found">Guest not found.</div>';
    exit;
}

// Reservation history
$stmt = $pdo->prepare(
    "SELECT r.*, t.table_name
     FROM reservations r
     LEFT JOIN tables t ON r.table_id = t.id
     WHERE r.guest_id = ? AND r.restaurant_id = ?
     ORDER BY r.reservation_date DESC, r.reservation_time DESC
     LIMIT 50"
);
$stmt->execute([$guestId, $restaurantId]);
$reservations = $stmt->fetchAll();

$tags = $guest['tags'] ? array_map('trim', explode(',', $guest['tags'])) : [];
$tags = array_filter($tags);

$statusColors = [
    'pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'success',
    'completed' => 'secondary', 'no_show' => 'danger', 'cancelled' => 'dark',
];
?>

<div id="guest-detail-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="guest-detail-header">
        <div id="guest-detail-title-wrap">
            <h4 class="mb-0" id="guest-detail-name"><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></h4>
            <small class="text-muted" id="guest-detail-since">Guest since <?php echo date('M j, Y', strtotime($guest['created_at'])); ?></small>
        </div>
        <div id="guest-detail-actions">
            <button class="btn btn-retell-call btn-sm d-none" id="guest-detail-call-btn"
                    data-retell-call
                    data-call-title="Call Guest"
                    data-call-subtitle="<?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?>"
                    data-guest-name="<?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?>"
                    data-guest-phone="<?php echo htmlspecialchars($guest['phone'] ?? ''); ?>"
                    data-guest-email="<?php echo htmlspecialchars($guest['email'] ?? ''); ?>"
                    data-restaurant-slug="<?php echo htmlspecialchars($restaurantSlug); ?>">
                <i class="feather-phone me-1"></i> Call AI Agent
            </button>
            <button class="btn btn-outline-primary btn-sm" id="guest-detail-edit-btn"
                    hx-get="/partials/guests/form.php?id=<?php echo $guestId; ?>"
                    hx-target="#guest-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-edit me-1"></i> Edit
            </button>
            <button class="btn btn-outline-secondary btn-sm" id="guest-detail-back-btn"
                    hx-get="/partials/guests/list.php"
                    hx-target="#page-content">
                <i class="feather-arrow-left me-1"></i> Back
            </button>
        </div>
    </div>

    <div id="guest-detail-messages"></div>
    <div id="guest-modal-container"></div>

    <div class="row" id="guest-detail-row">
        <!-- Left Column -->
        <div class="col-lg-4" id="guest-detail-left">
            <!-- Contact Info -->
            <div class="card mb-3" id="guest-contact-card">
                <div class="card-body" id="guest-contact-body">
                    <h6 class="fw-bold mb-3" id="guest-contact-title">Contact Information</h6>
                    <table class="table table-borderless table-sm mb-0" id="guest-contact-table">
                        <tr id="guest-contact-email"><td class="text-muted" style="width:80px;">Email</td><td><?php echo htmlspecialchars($guest['email'] ?? '—'); ?></td></tr>
                        <tr id="guest-contact-phone"><td class="text-muted">Phone</td><td><?php echo htmlspecialchars($guest['phone'] ?? '—'); ?></td></tr>
                        <?php if ($guest['address_line1']): ?>
                        <tr id="guest-contact-address"><td class="text-muted">Address</td>
                            <td>
                                <?php echo htmlspecialchars($guest['address_line1']); ?>
                                <?php if ($guest['address_line2']) echo '<br>' . htmlspecialchars($guest['address_line2']); ?>
                                <?php if ($guest['city'] || $guest['state'] || $guest['postal_code']): ?>
                                <br><?php echo htmlspecialchars(trim($guest['city'] . ', ' . $guest['state'] . ' ' . $guest['postal_code'], ', ')); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <!-- Contact Opt-Out Flags -->
                    <div class="mt-3 pt-3 border-top" id="guest-contact-optouts">
                        <div class="d-flex flex-wrap gap-3" id="guest-optout-toggles">
                            <?php
                            $optouts = [
                                'do_not_call'  => ['label' => 'Do Not Call',  'icon' => 'feather-phone-off'],
                                'do_not_email' => ['label' => 'Do Not Email', 'icon' => 'feather-mail'],
                                'do_not_text'  => ['label' => 'Do Not Text',  'icon' => 'feather-message-circle'],
                            ];
                            foreach ($optouts as $field => $meta):
                                $checked = !empty($guest[$field]);
                            ?>
                            <div class="form-check form-switch" id="guest-optout-<?php echo $field; ?>-wrap">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="guest-optout-<?php echo $field; ?>"
                                       name="<?php echo $field; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>
                                       hx-post="/partials/guests/save.php"
                                       hx-vals='<?php echo json_encode(["action" => "update_optout", "id" => $guestId, "field" => $field, "csrf_token" => generate_csrf_token()]); ?>'
                                       hx-trigger="change"
                                       hx-target="#guest-detail-messages"
                                       hx-swap="innerHTML">
                                <label class="form-check-label small <?php echo $checked ? 'text-danger' : 'text-muted'; ?>"
                                       for="guest-optout-<?php echo $field; ?>" id="guest-optout-<?php echo $field; ?>-label">
                                    <i class="<?php echo $meta['icon']; ?> me-1"></i><?php echo $meta['label']; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="card mb-3" id="guest-stats-card">
                <div class="card-body" id="guest-stats-body">
                    <h6 class="fw-bold mb-3" id="guest-stats-title">Statistics</h6>
                    <div class="row text-center" id="guest-stats-row">
                        <div class="col-4" id="guest-stat-visits">
                            <div class="fs-4 fw-bold text-primary"><?php echo (int)$guest['visit_count']; ?></div>
                            <small class="text-muted">Visits</small>
                        </div>
                        <div class="col-4" id="guest-stat-noshows">
                            <div class="fs-4 fw-bold <?php echo (int)$guest['noshow_count'] > 0 ? 'text-danger' : 'text-muted'; ?>"><?php echo (int)$guest['noshow_count']; ?></div>
                            <small class="text-muted">No-Shows</small>
                        </div>
                        <div class="col-4" id="guest-stat-lastvisit">
                            <div class="fs-6 fw-bold text-muted"><?php echo $guest['last_visit_at'] ? date('M j', strtotime($guest['last_visit_at'])) : '—'; ?></div>
                            <small class="text-muted">Last Visit</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="card mb-3" id="guest-tags-card">
                <div class="card-body" id="guest-tags-body">
                    <h6 class="fw-bold mb-2" id="guest-tags-title">Tags</h6>
                    <div id="guest-tags-container"
                         hx-get="/partials/guests/detail.php?id=<?php echo $guestId; ?>"
                         hx-trigger="refreshGuestDetail from:body"
                         hx-target="#guest-detail-main"
                         hx-swap="outerHTML"
                         hx-select="#guest-detail-main">
                        <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-<?php echo $tag === 'vip' ? 'warning' : ($tag === 'reviewer' ? 'info' : 'secondary'); ?> me-1 mb-1" id="guest-tag-<?php echo htmlspecialchars($tag); ?>">
                            <?php echo htmlspecialchars(ucfirst($tag)); ?>
                            <a href="#" class="text-white ms-1" style="text-decoration:none;"
                               hx-post="/partials/guests/save.php"
                               hx-vals='<?php echo json_encode(["action" => "remove_tag", "id" => $guestId, "tag" => $tag, "csrf_token" => generate_csrf_token()]); ?>'
                               hx-target="#guest-detail-main"
                               hx-swap="outerHTML">&times;</a>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group input-group-sm mt-2" id="guest-tag-add-group">
                        <select class="form-select form-select-sm" id="guest-tag-add-select">
                            <option value="">Add tag...</option>
                            <?php
                            $availableTags = ['vip', 'regular', 'reviewer', 'difficult', 'allergies', 'celebration', 'business'];
                            foreach ($availableTags as $at):
                                if (!in_array($at, $tags)):
                            ?>
                            <option value="<?php echo htmlspecialchars($at); ?>"><?php echo ucfirst($at); ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary btn-sm" type="button" id="guest-tag-add-btn"
                                hx-post="/partials/guests/save.php"
                                hx-include="#guest-tag-add-select"
                                hx-vals='<?php echo json_encode(["action" => "add_tag", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                                hx-target="#guest-detail-main"
                                hx-swap="outerHTML">Add</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-8" id="guest-detail-right">
            <!-- Preferences -->
            <div class="card mb-3" id="guest-prefs-card">
                <div class="card-body" id="guest-prefs-body">
                    <h6 class="fw-bold mb-3" id="guest-prefs-title">Preferences</h6>
                    <div class="row g-3" id="guest-prefs-row">
                        <div class="col-md-6" id="guest-pref-dietary-group">
                            <label class="form-label text-muted small" id="guest-pref-dietary-label">Dietary Restrictions</label>
                            <input type="text" class="form-control form-control-sm" id="guest-pref-dietary" name="dietary_restrictions"
                                   value="<?php echo htmlspecialchars($guest['dietary_restrictions'] ?? ''); ?>"
                                   placeholder="e.g., vegetarian, gluten-free"
                                   hx-post="/partials/guests/save.php"
                                   hx-vals='<?php echo json_encode(["action" => "update_pref", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                                   hx-trigger="change"
                                   hx-target="#guest-detail-messages"
                                   hx-swap="innerHTML">
                        </div>
                        <div class="col-md-6" id="guest-pref-allergy-group">
                            <label class="form-label text-muted small" id="guest-pref-allergy-label">Allergies</label>
                            <input type="text" class="form-control form-control-sm" id="guest-pref-allergies" name="allergies"
                                   value="<?php echo htmlspecialchars($guest['allergies'] ?? ''); ?>"
                                   placeholder="e.g., peanuts, shellfish"
                                   hx-post="/partials/guests/save.php"
                                   hx-vals='<?php echo json_encode(["action" => "update_pref", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                                   hx-trigger="change"
                                   hx-target="#guest-detail-messages"
                                   hx-swap="innerHTML">
                        </div>
                        <div class="col-md-6" id="guest-pref-seating-group">
                            <label class="form-label text-muted small" id="guest-pref-seating-label">Seating Preference</label>
                            <input type="text" class="form-control form-control-sm" id="guest-pref-seating" name="seating_preference"
                                   value="<?php echo htmlspecialchars($guest['seating_preference'] ?? ''); ?>"
                                   placeholder="e.g., booth, window, quiet"
                                   hx-post="/partials/guests/save.php"
                                   hx-vals='<?php echo json_encode(["action" => "update_pref", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                                   hx-trigger="change"
                                   hx-target="#guest-detail-messages"
                                   hx-swap="innerHTML">
                        </div>
                        <div class="col-md-6" id="guest-pref-server-group">
                            <label class="form-label text-muted small" id="guest-pref-server-label">Favorite Server</label>
                            <input type="text" class="form-control form-control-sm" id="guest-pref-server" name="favorite_server"
                                   value="<?php echo htmlspecialchars($guest['favorite_server'] ?? ''); ?>"
                                   placeholder="Server name"
                                   hx-post="/partials/guests/save.php"
                                   hx-vals='<?php echo json_encode(["action" => "update_pref", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                                   hx-trigger="change"
                                   hx-target="#guest-detail-messages"
                                   hx-swap="innerHTML">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="card mb-3" id="guest-notes-card">
                <div class="card-body" id="guest-notes-body">
                    <h6 class="fw-bold mb-2" id="guest-notes-title">Notes</h6>
                    <textarea class="form-control" id="guest-notes-text" name="notes" rows="3"
                              placeholder="Staff notes about this guest..."
                              hx-post="/partials/guests/save.php"
                              hx-vals='<?php echo json_encode(["action" => "update_notes", "id" => $guestId, "csrf_token" => generate_csrf_token()]); ?>'
                              hx-trigger="change"
                              hx-target="#guest-detail-messages"
                              hx-swap="innerHTML"><?php echo htmlspecialchars($guest['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Reservation History -->
            <div class="card mb-3" id="guest-history-card">
                <div class="card-body" id="guest-history-body">
                    <h6 class="fw-bold mb-3" id="guest-history-title">Reservation History</h6>
                    <?php if (empty($reservations)): ?>
                    <p class="text-muted mb-0" id="guest-history-empty">No reservations found.</p>
                    <?php else: ?>
                    <div class="table-responsive" id="guest-history-table-wrap">
                        <table class="table table-sm table-hover mb-0" id="guest-history-table">
                            <thead id="guest-history-thead">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Party</th>
                                    <th>Table</th>
                                    <th>Status</th>
                                    <th>Code</th>
                                </tr>
                            </thead>
                            <tbody id="guest-history-tbody">
                                <?php foreach ($reservations as $r): ?>
                                <tr id="guest-res-<?php echo $r['id']; ?>" style="cursor:pointer;"
                                    hx-get="/partials/reservations/detail.php?id=<?php echo $r['id']; ?>"
                                    hx-target="#page-content">
                                    <td><?php echo date('M j, Y', strtotime($r['reservation_date'])); ?></td>
                                    <td><?php echo date('g:ia', strtotime($r['reservation_time'])); ?></td>
                                    <td><?php echo (int)$r['party_size']; ?></td>
                                    <td><?php echo htmlspecialchars($r['table_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusColors[$r['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($r['confirmation_code']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
