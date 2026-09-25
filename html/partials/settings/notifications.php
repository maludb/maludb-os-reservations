<?php
/**
 * Notification Settings — Toggle notifications, configure SMS provider
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/availability.php';

requireAdmin();
$restaurantId = currentRestaurantId();
$pdo = db();

// Handle save
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $action = $_POST['action'];
        require_once __DIR__ . '/../../../helpers/services/settings.php';

        try {
            if ($action === 'save_settings') {
                svc_notifications_update($restaurantId, [
                    'confirmation_email'    => $_POST['confirmation_email'] ?? '0',
                    'confirmation_sms'      => $_POST['confirmation_sms'] ?? '0',
                    'reminder_email'        => $_POST['reminder_email'] ?? '0',
                    'cancellation_email'    => $_POST['cancellation_email'] ?? '0',
                    'waitlist_sms'          => $_POST['waitlist_sms'] ?? '0',
                    'from_email'            => $_POST['from_email'] ?? '',
                    'reminder_hours_before' => $_POST['reminder_hours'] ?? 24,
                ]);
                $message = '<div class="alert alert-success">Notification settings saved.</div>';
            }

            if ($action === 'save_mailersend') {
                svc_notifications_update($restaurantId, ['mailersend_api_key' => $_POST['mailersend_api_key'] ?? '']);
                $message = '<div class="alert alert-success">MailerSend settings saved.</div>';
            }

            if ($action === 'save_sms') {
                svc_notifications_update($restaurantId, [
                    'sms_api_key'     => $_POST['sms_api_key'] ?? '',
                    'sms_api_secret'  => $_POST['sms_api_secret'] ?? '',
                    'sms_from_number' => $_POST['sms_from_number'] ?? '',
                ]);
                $message = '<div class="alert alert-success">SMS settings saved.</div>';
            }

            if ($action === 'test_sms') {
                $result = svc_notifications_test_sms($restaurantId, $_POST['test_phone'] ?? '');
                $message = '<div class="alert alert-success">Test SMS sent successfully! SID: ' . htmlspecialchars((string)$result['sid']) . '</div>';
            }
        } catch (ServiceError $e) {
            $class = $e->field === 'test_phone' ? 'alert-warning' : 'alert-danger';
            $message = '<div class="alert ' . $class . '">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Load current settings (re-fetch after potential save)
$confirmEmail   = getRestaurantSetting($restaurantId, 'notification_confirmation_email', '1');
$confirmSms     = getRestaurantSetting($restaurantId, 'notification_confirmation_sms', '0');
$reminderEmail  = getRestaurantSetting($restaurantId, 'notification_reminder_email', '1');
$cancelEmail    = getRestaurantSetting($restaurantId, 'notification_cancellation_email', '1');
$waitlistSms    = getRestaurantSetting($restaurantId, 'notification_waitlist_sms', '1');
$fromEmail      = getRestaurantSetting($restaurantId, 'notification_from_email', '');
$reminderHours  = getRestaurantSetting($restaurantId, 'reminder_hours_before', '24');
$mailersendApiKey = getRestaurantSetting($restaurantId, 'mailersend_api_key', '');
$smsApiKey      = getRestaurantSetting($restaurantId, 'sms_api_key', '');
$smsApiSecret   = getRestaurantSetting($restaurantId, 'sms_api_secret', '');
$smsFromNumber  = getRestaurantSetting($restaurantId, 'sms_from_number', '');
?>

<div id="notifications-main">
    <h4 class="mb-4" id="notifications-title">Notification Settings</h4>

    <?php echo $message; ?>

    <!-- Email Notifications -->
    <div class="card mb-3" id="notifications-email-card">
        <div class="card-body" id="notifications-email-body">
            <h5 class="card-title mb-3" id="notifications-email-title">Email Notifications</h5>
            <form method="POST" hx-post="/partials/settings/notifications.php" hx-target="#notifications-main" hx-swap="outerHTML" id="notifications-email-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_settings">

                <div class="mb-3" id="notifications-from-group">
                    <label for="notif-from-email" class="form-label">From Email Address</label>
                    <input type="email" class="form-control" id="notif-from-email" name="from_email"
                           value="<?php echo htmlspecialchars($fromEmail); ?>"
                           placeholder="noreply@yourrestaurant.com">
                    <div class="form-text">The email address notifications will be sent from.</div>
                </div>

                <div class="form-check form-switch mb-3" id="notifications-confirm-group">
                    <input class="form-check-input" type="checkbox" id="notif-confirmation" name="confirmation_email" value="1"
                           <?php echo $confirmEmail === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notif-confirmation">
                        <strong>Booking Confirmation Email</strong> — Send email when a reservation is created
                    </label>
                </div>

                <div class="form-check form-switch mb-3" id="notifications-confirm-sms-group">
                    <input class="form-check-input" type="checkbox" id="notif-confirmation-sms" name="confirmation_sms" value="1"
                           <?php echo $confirmSms === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notif-confirmation-sms">
                        <strong>Booking Confirmation SMS</strong> — Send text message when a reservation is created
                    </label>
                </div>

                <div class="form-check form-switch mb-3" id="notifications-reminder-group">
                    <input class="form-check-input" type="checkbox" id="notif-reminder" name="reminder_email" value="1"
                           <?php echo $reminderEmail === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notif-reminder">
                        <strong>Reservation Reminder</strong> — Send reminder before reservation
                    </label>
                </div>

                <div class="mb-3 ms-4" id="notifications-reminder-hours-group">
                    <label for="notif-reminder-hours" class="form-label">Reminder Hours Before</label>
                    <input type="number" class="form-control" id="notif-reminder-hours" name="reminder_hours"
                           style="max-width: 150px;" min="1" max="72"
                           value="<?php echo (int)$reminderHours; ?>">
                </div>

                <div class="form-check form-switch mb-3" id="notifications-cancel-group">
                    <input class="form-check-input" type="checkbox" id="notif-cancellation" name="cancellation_email" value="1"
                           <?php echo $cancelEmail === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notif-cancellation">
                        <strong>Cancellation Notice</strong> — Send email when a reservation is cancelled
                    </label>
                </div>

                <div class="form-check form-switch mb-3" id="notifications-waitlist-group">
                    <input class="form-check-input" type="checkbox" id="notif-waitlist" name="waitlist_sms" value="1"
                           <?php echo $waitlistSms === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notif-waitlist">
                        <strong>Waitlist SMS</strong> — Send SMS when table is ready
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="notifications-email-save-btn">Save Notification Settings</button>
            </form>
        </div>
    </div>

    <!-- Email Provider (MailerSend) -->
    <div class="card mb-3" id="notifications-mailersend-card">
        <div class="card-body" id="notifications-mailersend-body">
            <h5 class="card-title mb-3" id="notifications-mailersend-title">Email Provider (MailerSend)</h5>
            <p class="text-muted" id="notifications-mailersend-desc">Configure MailerSend for improved email deliverability. Without an API key, emails fall back to the server's built-in mail.</p>
            <form method="POST" hx-post="/partials/settings/notifications.php" hx-target="#notifications-main" hx-swap="outerHTML" id="notifications-mailersend-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_mailersend">

                <div class="mb-3" id="notifications-mailersend-key-group">
                    <label for="notif-mailersend-key" class="form-label">API Key</label>
                    <input type="password" class="form-control" id="notif-mailersend-key" name="mailersend_api_key"
                           value="<?php echo htmlspecialchars($mailersendApiKey); ?>"
                           placeholder="mlsn.xxxxxxxxxxxxxxxx">
                    <div class="form-text">Get your API key from <a href="https://app.mailersend.com/api-tokens" target="_blank">MailerSend dashboard</a>. The "From Email" above must use a verified domain in MailerSend.</div>
                </div>

                <button type="submit" class="btn btn-primary" id="notifications-mailersend-save-btn">Save MailerSend Settings</button>
            </form>
        </div>
    </div>

    <!-- SMS Provider (Twilio) -->
    <div class="card mb-3" id="notifications-sms-card">
        <div class="card-body" id="notifications-sms-body">
            <h5 class="card-title mb-3" id="notifications-sms-title">SMS Provider (Twilio)</h5>
            <form method="POST" hx-post="/partials/settings/notifications.php" hx-target="#notifications-main" hx-swap="outerHTML" id="notifications-sms-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_sms">

                <div class="mb-3" id="notifications-sid-group">
                    <label for="notif-sms-key" class="form-label">Account SID</label>
                    <input type="text" class="form-control" id="notif-sms-key" name="sms_api_key"
                           value="<?php echo htmlspecialchars($smsApiKey); ?>"
                           placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                </div>

                <div class="mb-3" id="notifications-token-group">
                    <label for="notif-sms-secret" class="form-label">Auth Token</label>
                    <input type="password" class="form-control" id="notif-sms-secret" name="sms_api_secret"
                           value="<?php echo htmlspecialchars($smsApiSecret); ?>">
                </div>

                <div class="mb-3" id="notifications-from-num-group">
                    <label for="notif-sms-from" class="form-label">From Number</label>
                    <input type="text" class="form-control" id="notif-sms-from" name="sms_from_number"
                           value="<?php echo htmlspecialchars($smsFromNumber); ?>"
                           placeholder="+1234567890">
                </div>

                <button type="submit" class="btn btn-primary" id="notifications-sms-save-btn">Save SMS Settings</button>
            </form>
        </div>
    </div>

    <!-- Test SMS -->
    <div class="card mb-3" id="notifications-test-card">
        <div class="card-body" id="notifications-test-body">
            <h5 class="card-title mb-3" id="notifications-test-title">Test SMS</h5>
            <form method="POST" hx-post="/partials/settings/notifications.php" hx-target="#notifications-main" hx-swap="outerHTML" id="notifications-test-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="test_sms">

                <div class="input-group" id="notifications-test-input-group">
                    <input type="text" class="form-control" id="notif-test-phone" name="test_phone"
                           placeholder="+1234567890">
                    <button type="submit" class="btn btn-outline-primary" id="notifications-test-btn">Send Test SMS</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Templates Link -->
    <div class="card mb-3" id="notifications-templates-card">
        <div class="card-body" id="notifications-templates-body">
            <h5 class="card-title" id="notifications-templates-title">Email Templates</h5>
            <p class="text-muted" id="notifications-templates-desc">Customize the content of notification emails.</p>
            <button class="btn btn-outline-primary" id="notifications-templates-btn"
                    hx-get="/partials/settings/email-templates.php"
                    hx-target="#page-content">
                Manage Email Templates
            </button>
        </div>
    </div>
</div>
