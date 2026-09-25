<?php
/**
 * HTMX endpoint: Search guests within current restaurant
 * Returns selectable list of matching guests
 */
require_once '../../../helpers/auth.php';

requireAuth();

$restaurantId = currentRestaurantId();
$query = trim($_GET['guest_query'] ?? '');

if ($query === '' || strlen($query) < 2) {
    exit;
}

$pdo = db();
$searchTerm = '%' . $query . '%';

$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, email, phone, tags
     FROM guests
     WHERE restaurant_id = ?
       AND is_active = 1
       AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?)
     ORDER BY last_name ASC, first_name ASC
     LIMIT 10"
);
$stmt->execute([$restaurantId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$guests = $stmt->fetchAll();

if (empty($guests)) {
    echo '<div class="text-muted small mt-1" id="guest-search-no-results">No matching guests found. Fill in the fields below to create a new guest.</div>';
    exit;
}
?>
<div class="list-group mt-1 shadow-sm" id="guest-search-results" style="max-height: 200px; overflow-y: auto;">
    <?php foreach ($guests as $g): ?>
    <a href="#" class="list-group-item list-group-item-action py-2" id="guest-search-item-<?php echo $g['id']; ?>"
       onclick="selectGuest(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['first_name'])); ?>', '<?php echo htmlspecialchars(addslashes($g['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($g['phone'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($g['email'] ?? '')); ?>'); return false;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?></strong>
                <?php if ($g['tags']): ?>
                <?php foreach (explode(',', $g['tags']) as $tag): ?>
                <span class="badge bg-info text-dark ms-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <small class="text-muted">
                <?php echo htmlspecialchars($g['phone'] ?? ''); ?>
                <?php if ($g['email']): ?>
                 | <?php echo htmlspecialchars($g['email']); ?>
                <?php endif; ?>
            </small>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<script>
function selectGuest(id, firstName, lastName, phone, email) {
    document.getElementById('res-guest-id').value = id;
    document.getElementById('res-fname').value = firstName;
    document.getElementById('res-lname').value = lastName;
    document.getElementById('res-phone').value = phone;
    document.getElementById('res-email').value = email;
    document.getElementById('create-res-guest-results').innerHTML =
        '<div class="text-success small mt-1"><i class="feather-check-circle me-1"></i>Selected: ' + firstName + ' ' + lastName + '</div>';
    document.getElementById('res-guest-search').value = '';
}
</script>
