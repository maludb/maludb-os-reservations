<?php
/**
 * Guest HTMX Search Endpoint — scoped by restaurant_id
 * Returns matching guests as selectable list items
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$restaurantId = currentRestaurantId();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo '';
    exit;
}

$search = "%{$q}%";
$stmt = db()->prepare(
    "SELECT id, first_name, last_name, email, phone, tags
     FROM guests
     WHERE restaurant_id = ?
       AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?)
     ORDER BY last_name, first_name
     LIMIT 10"
);
$stmt->execute([$restaurantId, $search, $search, $search, $search, $search]);
$results = $stmt->fetchAll();

if (empty($results)) {
    echo '<div class="list-group-item text-muted" id="guest-search-none">No guests found.</div>';
    exit;
}

foreach ($results as $g) {
    $name = htmlspecialchars($g['first_name'] . ' ' . $g['last_name']);
    $detail = htmlspecialchars($g['email'] ?? $g['phone'] ?? '');
    $tags = '';
    if ($g['tags']) {
        foreach (explode(',', $g['tags']) as $tag) {
            $tag = trim($tag);
            if ($tag) {
                $color = $tag === 'vip' ? 'warning' : 'secondary';
                $tags .= ' <span class="badge bg-' . $color . ' ms-1">' . htmlspecialchars(ucfirst($tag)) . '</span>';
            }
        }
    }
    echo '<a href="#" class="list-group-item list-group-item-action" id="guest-search-result-' . $g['id'] . '"
            data-guest-id="' . $g['id'] . '"
            data-first-name="' . htmlspecialchars($g['first_name']) . '"
            data-last-name="' . htmlspecialchars($g['last_name']) . '"
            data-phone="' . htmlspecialchars($g['phone'] ?? '') . '"
            data-email="' . htmlspecialchars($g['email'] ?? '') . '"
            hx-get="/partials/guests/detail.php?id=' . $g['id'] . '"
            hx-target="#page-content">
            <div class="d-flex justify-content-between align-items-center">
                <div><strong>' . $name . '</strong>' . $tags . '<br><small class="text-muted">' . $detail . '</small></div>
            </div>
          </a>';
}
