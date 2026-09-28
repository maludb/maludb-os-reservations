<?php
require_once '../helpers/auth.php';
require_once '../helpers/db.php';
require_once '../helpers/csrf.php';

requireAuth();
$user = get_user();
$restaurantName = $_SESSION['current_restaurant_name'] ?? 'Restaurant';
$currentRole = $_SESSION['current_role'] ?? '';
$restaurantPhone = '';
if (!empty($_SESSION['current_restaurant_id'])) {
    $r = getRestaurant($_SESSION['current_restaurant_id']);
    if ($r && !empty($r['phone'])) {
        $digits = preg_replace('/\D/', '', $r['phone']);
        if (strlen($digits) === 10) {
            $restaurantPhone = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            $restaurantPhone = '(' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7);
        } else {
            $restaurantPhone = $r['phone'];
        }
    }
}
$restaurants = getUserRestaurants($user['id']);
$hasMultipleRestaurants = count($restaurants) > 1;

// Load status for current restaurant
$currentRestaurantStatus = 'active';
if (!empty($_SESSION['current_restaurant_id'])) {
    $stStmt = db()->prepare("SELECT status FROM restaurants WHERE id = ?");
    $stStmt->execute([$_SESSION['current_restaurant_id']]);
    $stRow = $stStmt->fetch(PDO::FETCH_ASSOC);
    if ($stRow && !empty($stRow['status'])) {
        $currentRestaurantStatus = $stRow['status'];
    }
}

$defaultPageContentPath = '/partials/dashboard/index.php';
$userSettingsPath = '/partials/settings/profile.php';
$appMetaDescription = 'Restaurant Reservation System';
$appTitleSuffix = 'Reservations';
$appFooterLabel = 'Restaurant Reservation System';

// Load permitted nav items from nav_permissions table
$userPlatformRole = $user['role'] ?? 'user';
$permittedNavItems = getPermittedNavItems($userPlatformRole, $currentRole);
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo htmlspecialchars($appMetaDescription); ?>" />
    <title><?php echo htmlspecialchars($restaurantName); ?> - <?php echo htmlspecialchars($appTitleSuffix); ?></title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon-vt.png" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css" />

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="assets/vendor/kobie-vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/vendor/feather.min.css" />

    <!-- Kobie Theme CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/kobie-theme.min.css" />

    <!-- Overlay Scrollbar CSS -->
    <link rel="stylesheet" href="assets/vendor/overlay-scroll/OverlayScrollbars.min.css" />

    <!-- Custom Overrides (must be last) -->
    <link rel="stylesheet" type="text/css" href="assets/css/kobie-custom.css" />

    <!-- HTMX Library -->
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
  </head>

  <body>

    <!-- Page wrapper starts -->
    <div class="page-wrapper" id="app-page-wrapper">

      <!-- Main container starts -->
      <div class="main-container" id="app-main-container">

        <!-- Sidebar wrapper starts -->
        <nav id="kobie-sidebar" class="nxl-navigation">
          <div id="kobie-nav-wrapper" class="navbar-wrapper">

            <!-- Logo starts -->
            <div id="kobie-logo-area" class="m-header">
              <a href="app.php" class="b-brand">
                <img src="/assets/images/zozocal-logo.png" alt="ZozoCal" class="logo logo-lg" style="max-height: 40px;">
                <img src="/assets/images/zozocal-logo.png" alt="ZozoCal" class="logo logo-sm" style="max-height: 24px;">
              </a>
            </div>
            <!-- Logo ends -->

            <!-- Navigation menu starts -->
            <div id="kobie-nav-menu" class="navbar-content">
              <ul class="nxl-navbar" id="sidebar-nav-list">
                <?php
                // Helper: check if a nav item is permitted
                $navAllowed = function($id) use ($permittedNavItems) {
                    return in_array($id, $permittedNavItems);
                };
                ?>

                <!-- RESTAURANT MODE: SCHEDULING section -->
                <?php if ($navAllowed('nav-caption-scheduling')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-scheduling"><label>SCHEDULING</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-dashboard')): ?>
                <li class="nxl-item active current-page" id="nav-dashboard">
                  <a href="#" class="nxl-link" hx-get="/partials/dashboard/index.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-home"></i></span>
                    <span class="nxl-mtext">Dashboard</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-reservations')): ?>
                <li class="nxl-item" id="nav-reservations">
                  <a href="#" class="nxl-link" hx-get="/partials/reservations/calendar.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-calendar"></i></span>
                    <span class="nxl-mtext">Reservations</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-floor-plan')): ?>
                <li class="nxl-item" id="nav-floor-plan">
                  <a href="#" class="nxl-link" hx-get="/partials/tables/floor-plan.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-grid"></i></span>
                    <span class="nxl-mtext">Floor Plan</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-waitlist')): ?>
                <li class="nxl-item" id="nav-waitlist">
                  <a href="#" class="nxl-link" hx-get="/partials/waitlist/index.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-list"></i></span>
                    <span class="nxl-mtext">Waitlist</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-events')): ?>
                <li class="nxl-item" id="nav-events">
                  <a href="#" class="nxl-link" hx-get="/partials/events/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-star"></i></span>
                    <span class="nxl-mtext">Events</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-todos')): ?>
                <li class="nxl-item" id="nav-todos">
                  <a href="#" class="nxl-link" hx-get="/partials/todos/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-check-square"></i></span>
                    <span class="nxl-mtext">Todo List</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-sms-messages')): ?>
                <li class="nxl-item" id="nav-sms-messages">
                  <a href="#" class="nxl-link" hx-get="/partials/messages/sms-log.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-message-square"></i></span>
                    <span class="nxl-mtext">SMS Messages</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-voice-calls')): ?>
                <li class="nxl-item" id="nav-voice-calls">
                  <a href="#" class="nxl-link" hx-get="/partials/messages/voice-log.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-phone-call"></i></span>
                    <span class="nxl-mtext">Voice Calls</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-email-messages')): ?>
                <li class="nxl-item" id="nav-email-messages">
                  <a href="#" class="nxl-link" hx-get="/partials/messages/email-log.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-mail"></i></span>
                    <span class="nxl-mtext">Email Messages</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- GUESTS section -->
                <?php if ($navAllowed('nav-caption-guests')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-guests"><label>GUESTS</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-guests')): ?>
                <li class="nxl-item" id="nav-guests">
                  <a href="#" class="nxl-link" hx-get="/partials/guests/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-users"></i></span>
                    <span class="nxl-mtext">Guest Directory</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- MANAGEMENT section -->
                <?php if ($navAllowed('nav-caption-management')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-management"><label>MANAGEMENT</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-table-setup')): ?>
                <li class="nxl-item" id="nav-table-setup">
                  <a href="#" class="nxl-link" hx-get="/partials/tables/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-layout"></i></span>
                    <span class="nxl-mtext">Table Setup</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-sections')): ?>
                <li class="nxl-item" id="nav-sections">
                  <a href="#" class="nxl-link" hx-get="/partials/sections/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-layers"></i></span>
                    <span class="nxl-mtext">Sections</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-hours')): ?>
                <li class="nxl-item" id="nav-hours">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/hours.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-clock"></i></span>
                    <span class="nxl-mtext">Operating Hours</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-turn-times')): ?>
                <li class="nxl-item" id="nav-turn-times">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/turn-times.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-watch"></i></span>
                    <span class="nxl-mtext">Turn Times</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-special-dates')): ?>
                <li class="nxl-item" id="nav-special-dates">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/special-dates.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-star"></i></span>
                    <span class="nxl-mtext">Special Dates</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-reports')): ?>
                <li class="nxl-item" id="nav-reports">
                  <a href="#" class="nxl-link" hx-get="/partials/reports/index.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-bar-chart-2"></i></span>
                    <span class="nxl-mtext">Reports</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- SETTINGS section -->
                <?php if ($navAllowed('nav-caption-settings')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-settings"><label>SETTINGS</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-profile')): ?>
                <li class="nxl-item" id="nav-profile">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/profile.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-home"></i></span>
                    <span class="nxl-mtext">Business Profile</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-notifications')): ?>
                <li class="nxl-item" id="nav-notifications">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/notifications.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-bell"></i></span>
                    <span class="nxl-mtext">Notifications</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-staff')): ?>
                <li class="nxl-item" id="nav-staff">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/users.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-user-plus"></i></span>
                    <span class="nxl-mtext">Staff Users</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-integrations')): ?>
                <li class="nxl-item" id="nav-integrations">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/integrations.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-zap"></i></span>
                    <span class="nxl-mtext">Integrations</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-api-keys')): ?>
                <li class="nxl-item" id="nav-api-keys">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/api-keys.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-key"></i></span>
                    <span class="nxl-mtext">API Keys</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-phone-numbers')): ?>
                <li class="nxl-item" id="nav-phone-numbers">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/phone-numbers.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-phone"></i></span>
                    <span class="nxl-mtext">Phone Numbers</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-voice-prompts')): ?>
                <li class="nxl-item" id="nav-voice-prompts">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/voice-prompts.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-mic"></i></span>
                    <span class="nxl-mtext">Voice Agents</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-text-agents')): ?>
                <li class="nxl-item" id="nav-text-agents">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/text-agents.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-message-square"></i></span>
                    <span class="nxl-mtext">Text Agents</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-email-agents')): ?>
                <li class="nxl-item" id="nav-email-agents">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/email-agents.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-mail"></i></span>
                    <span class="nxl-mtext">Email Agents</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- BILLING section -->
                <?php if ($navAllowed('nav-caption-billing')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-billing"><label>BILLING</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-billing')): ?>
                <li class="nxl-item" id="nav-billing">
                  <a href="#" class="nxl-link" hx-get="/partials/billing/invoices.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                    <span class="nxl-mtext">Billing</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- PLATFORM section -->
                <?php if ($navAllowed('nav-caption-platform')): ?>
                <li class="nxl-item nxl-caption" id="nav-caption-platform"><label>PLATFORM</label></li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-manage-restaurants')): ?>
                <li class="nxl-item" id="nav-manage-restaurants">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/restaurants.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                    <span class="nxl-mtext">Manage Locations</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-platform-users')): ?>
                <li class="nxl-item" id="nav-platform-users">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/users.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-user"></i></span>
                    <span class="nxl-mtext">Users</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-products')): ?>
                <li class="nxl-item" id="nav-products">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/products.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-package"></i></span>
                    <span class="nxl-mtext">Products & Pricing</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-subscriptions')): ?>
                <li class="nxl-item" id="nav-subscriptions">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/subscriptions.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-credit-card"></i></span>
                    <span class="nxl-mtext">Subscriptions</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-account-options')): ?>
                <li class="nxl-item" id="nav-account-options">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/account-options.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-toggle-right"></i></span>
                    <span class="nxl-mtext">Account Options</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if ($navAllowed('nav-platform-billing')): ?>
                <li class="nxl-item" id="nav-platform-billing">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/billing-settings.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-file-text"></i></span>
                    <span class="nxl-mtext">Billing Config</span>
                  </a>
                </li>
                <?php endif; ?>
                <?php if (isSuperAdmin()): ?>
                <li class="nxl-item" id="nav-nav-permissions">
                  <a href="#" class="nxl-link" hx-get="/partials/platform/nav-permissions.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-shield"></i></span>
                    <span class="nxl-mtext">Nav Permissions</span>
                  </a>
                </li>
                <?php endif; ?>

                <!-- Logout (always visible) -->
                <li class="nxl-item nxl-caption" id="nav-caption-spacer"><label></label></li>
                <li class="nxl-item" id="nav-logout">
                  <a href="/logout.php" class="nxl-link text-danger">
                    <span class="nxl-micon"><i class="feather-log-out"></i></span>
                    <span class="nxl-mtext">Logout</span>
                  </a>
                </li>
              </ul>
            </div>
            <!-- Navigation menu ends -->

          </div>
        </nav>
        <!-- Sidebar wrapper ends -->

        <!-- App container starts -->
        <div id="kobie-content-wrapper" class="nxl-container">

          <!-- App header starts -->
          <header id="kobie-header" class="nxl-header">
            <div class="header-wrapper" id="header-wrapper">

              <!-- Header left starts -->
              <div class="header-left d-flex align-items-center gap-4" id="header-left">
                <!-- Mobile toggler -->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                  <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                      <div class="hamburger-inner"></div>
                    </div>
                  </div>
                </a>

                <!-- Navigation toggle -->
                <div class="nxl-navigation-toggle" id="nav-toggle-container">
                  <a href="javascript:void(0);" id="vertical-nav-toggle">
                    <i class="feather-align-left"></i>
                  </a>
                </div>

                <!-- Page title -->
                <div class="d-flex align-items-center" id="header-title-area">
                  <h5 class="fw-bold text-white m-0" id="page-title"><?php echo htmlspecialchars($restaurantName); ?><?php if ($restaurantPhone): ?> <span class="fw-normal fs-6 ms-2" id="header-phone"><?php echo htmlspecialchars($restaurantPhone); ?></span><?php endif; ?></h5>
                </div>
              </div>
              <!-- Header left ends -->

              <!-- Header right starts -->
              <div class="header-right ms-auto" id="header-right">
                <div class="d-flex align-items-center" id="header-right-items">

                  <!-- Global loading indicator -->
                  <div class="htmx-indicator me-3" id="global-loading-indicator">
                    <div class="spinner-border spinner-border-sm text-white" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>

                  <!-- Restaurant switcher (if user has multiple restaurants) -->
                  <?php if ($hasMultipleRestaurants): ?>
                  <div class="dropdown me-3" id="restaurant-switcher">
                    <a class="dropdown-toggle d-flex align-items-center text-white"
                       href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="feather-repeat fs-5 me-2"></i>
                      <span class="fw-bold d-none d-md-inline" id="current-restaurant-name"><?php echo htmlspecialchars($restaurantName); ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 20rem;" id="restaurant-switcher-menu">
                      <?php foreach ($restaurants as $r): ?>
                      <a class="dropdown-item d-flex align-items-center <?php echo ($r['id'] == ($_SESSION['current_restaurant_id'] ?? 0)) ? 'active' : ''; ?>"
                         href="#"
                         hx-post="/partials/auth/switch-restaurant.php"
                         hx-vals='<?php echo htmlspecialchars(json_encode(['restaurant_id' => (int)$r['id'], 'csrf_token' => generate_csrf_token()]), ENT_QUOTES); ?>'
                         hx-target="#page-content"
                         id="restaurant-switch-<?php echo (int)$r['id']; ?>">
                        <i class="feather-home fs-5 me-2"></i>
                        <?php echo htmlspecialchars($r['name']); ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($r['role']); ?></span>
                      </a>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- User dropdown -->
                  <div class="dropdown" id="kobie-user-dropdown">
                    <a id="userSettings" class="dropdown-toggle d-flex py-2 align-items-center text-white"
                       href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <div class="text-truncate d-lg-flex flex-column d-none ms-2" id="user-info-display">
                        <span class="fw-bold fs-18"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <span class="fs-11 text-white-50"><?php echo ucfirst(htmlspecialchars($currentRole)); ?></span>
                      </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" id="user-dropdown-menu">
                      <a class="dropdown-item d-flex align-items-center" href="#"
                         hx-get="<?php echo htmlspecialchars($userSettingsPath); ?>" hx-target="#page-content" hx-swap="innerHTML"
                         id="user-menu-settings">
                        <i class="feather-settings fs-5 me-2"></i>Settings
                      </a>
                      <div class="dropdown-divider"></div>
                      <div class="mx-3 mt-2 d-grid" id="user-menu-logout">
                        <a href="/logout.php" class="btn btn-primary">Logout</a>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
              <!-- Header right ends -->

            </div>
          </header>
          <!-- App header ends -->

          <?php if ($currentRestaurantStatus !== 'active'): ?>
          <!-- Restaurant status banner -->
          <div class="alert alert-warning text-center fw-bold mb-0 rounded-0 py-2" id="restaurant-status-banner">
              <i class="feather-alert-triangle me-2"></i>
              This company is currently <strong><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $currentRestaurantStatus))); ?></strong>.
          </div>
          <?php endif; ?>

          <!-- App body starts -->
          <main class="nxl-content" id="page-content"
                hx-get="<?php echo htmlspecialchars($defaultPageContentPath); ?>"
                hx-trigger="load">
            <div class="main-content" id="main-content-inner">
              <!-- Content will be loaded here via HTMX -->
            </div>
          </main>
          <!-- App body ends -->

          <!-- App footer starts -->
          <footer id="kobie-footer" class="nxl-footer">
            <div class="footer-wrapper" id="footer-wrapper">
              <p class="mb-0 text-muted" id="footer-text">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appFooterLabel); ?></p>
            </div>
          </footer>
          <!-- App footer ends -->

        </div>
        <!-- App container ends -->

      </div>
      <!-- Main container ends -->

    </div>
    <!-- Page wrapper ends -->

    <!-- JavaScript Files -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/moment.min.js"></script>

    <!-- Perfect Scrollbar JS -->
    <script src="assets/vendor/perfect-scrollbar.min.js"></script>

    <!-- NXL Navigation JS -->
    <script src="assets/js/nxl-navigation.min.js"></script>

    <!-- Overlay Scroll JS -->
    <script src="assets/vendor/overlay-scroll/jquery.overlayScrollbars.min.js"></script>
    <script src="assets/vendor/overlay-scroll/custom-scrollbar.js"></script>

    <!-- Apex Charts -->
    <script src="assets/vendor/apex/apexcharts.min.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/custom.js"></script>

    <!-- Retell Web SDK for browser-based voice calls -->
    <script src="assets/js/retell-sdk-bundle.min.js"></script>
    <script src="assets/js/retell-integration.js"></script>

    <!-- HTMX Navigation Handler -->
    <script>
      // Handle navigation active state
      document.addEventListener('htmx:afterRequest', function(evt) {
        var requestTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (requestTarget && requestTarget.id === 'page-content') {
          document.querySelectorAll('.nxl-navbar .nxl-item').forEach(function(li) {
            li.classList.remove('active', 'current-page');
          });
          var trigger = evt.detail.elt;
          if (trigger && trigger.closest('.nxl-item')) {
            trigger.closest('.nxl-item').classList.add('active', 'current-page');
          }
        }
      });

      // Reinitialize Bootstrap components after HTMX swap
      document.addEventListener('htmx:afterSwap', function(evt) {
        var swapTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (swapTarget && swapTarget.id === 'page-content') {
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
          tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
        }
      });

      // Handle restaurant switch — reload page to refresh sidebar permissions
      document.body.addEventListener('restaurantSwitched', function() {
        window.location.reload();
      });

      // Global loading indicator
      document.body.addEventListener('htmx:beforeRequest', function() {
        document.getElementById('global-loading-indicator').style.display = 'block';
      });
      document.body.addEventListener('htmx:afterRequest', function() {
        document.getElementById('global-loading-indicator').style.display = 'none';
      });

      // Auto-show modals loaded into HTMX modal targets
      document.body.addEventListener('htmx:afterSwap', function(evt) {
        var swapTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (!swapTarget) {
          return;
        }

        var modal = swapTarget.querySelector('.modal');
        if (modal && modal.classList.contains('fade') && !modal.classList.contains('show')) {
          var bsModal = new bootstrap.Modal(modal);
          bsModal.show();
          modal.addEventListener('shown.bs.modal', function() {
            var firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
            if (firstInput) firstInput.focus();
          });
          modal.addEventListener('hidden.bs.modal', function() {
            swapTarget.innerHTML = '';
          });
        }
      });

      // Close modal event
      document.body.addEventListener('closeModal', function() {
        var modals = document.querySelectorAll('.modal.show');
        modals.forEach(function(modal) {
          var bsModal = bootstrap.Modal.getInstance(modal);
          if (bsModal) bsModal.hide();
        });
        var container = document.getElementById('modal-container');
        if (container) {
          container.innerHTML = '';
        }
      });
    </script>

    <!-- Modal Container for HTMX-loaded modals -->
    <div id="modal-container"></div>

  </body>

</html>
