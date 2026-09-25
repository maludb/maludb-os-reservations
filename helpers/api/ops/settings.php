<?php
/**
 * API operations: restaurant settings — business profile, meal progress
 * percentages, notification settings, email templates, integrations.
 * (Hours, special dates and turn times are in ops/hours.php.)
 */

require_once __DIR__ . '/../../services/settings.php';

// ── Business profile (admin) ───────────────────────────────

api_op('settings_profile_get', [
    'description' => 'Get the restaurant business profile: name, slug, contact details, address, website and time zone.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'settings/profile',
    'handler' => fn($in, $ctx) => ['profile' => svc_settings_profile_get($ctx['restaurant_id'])],
]);

api_op('settings_profile_update', [
    'description' => 'Update the business profile. Only the fields sent are changed; send an empty string to clear an optional field.',
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'settings/profile',
    'input' => [
        'name'          => ['type' => 'string'],
        'phone'         => ['type' => 'string'],
        'email'         => ['type' => 'string'],
        'address_line1' => ['type' => 'string'],
        'city'          => ['type' => 'string'],
        'state'         => ['type' => 'string'],
        'postal_code'   => ['type' => 'string'],
        'website'       => ['type' => 'string', 'description' => 'Full URL, e.g. https://example.com'],
        'timezone'      => ['type' => 'string', 'description' => 'IANA time zone, e.g. America/Chicago'],
    ],
    'handler' => fn($in, $ctx) => ['profile' => svc_settings_profile_update($ctx['restaurant_id'], $ctx['user_id'], $in)],
]);

// ── Meal progress percentages (manager) ────────────────────

$mealInput = [];
foreach (getMealStatusMeta() as $status => $m) {
    $mealInput[$status] = ['type' => 'integer', 'description' => "Percent of the meal completed at \"{$m['label']}\" (0-100)"];
}

api_op('meal_percentages_get', [
    'description' => 'Get the estimated percent of a meal completed at each table meal status (seated, ordering, eating, check_dropped, paid). Used to predict when tables free up.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'settings/meal-percentages',
    'handler' => fn($in, $ctx) => ['meal_percentages' => svc_meal_percentages_get($ctx['restaurant_id'])],
]);

api_op('meal_percentages_update', [
    'description' => 'Set meal progress percentages. Only the statuses sent are changed.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'settings/meal-percentages',
    'input' => $mealInput,
    'handler' => fn($in, $ctx) => ['meal_percentages' => svc_meal_percentages_update($ctx['restaurant_id'], $in)],
]);

// ── Notification settings (admin) ──────────────────────────

api_op('notifications_get', [
    'description' => 'Get guest notification settings (which emails/SMS are sent, reminder timing, sender, SMS provider). API keys are returned masked.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'settings/notifications',
    'handler' => fn($in, $ctx) => ['notifications' => svc_notifications_get($ctx['restaurant_id'])],
]);

api_op('notifications_update', [
    'description' => 'Update notification settings. Only the fields sent are changed. Keys are write-only.',
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'settings/notifications',
    'input' => [
        'confirmation_email'    => ['type' => 'boolean', 'description' => 'Email guests when a reservation is confirmed'],
        'confirmation_sms'      => ['type' => 'boolean', 'description' => 'Text guests when a reservation is confirmed'],
        'reminder_email'        => ['type' => 'boolean', 'description' => 'Send reminder emails'],
        'cancellation_email'    => ['type' => 'boolean', 'description' => 'Email guests when a reservation is cancelled'],
        'waitlist_sms'          => ['type' => 'boolean', 'description' => 'Text waitlist guests when their table is ready'],
        'from_email'            => ['type' => 'string', 'description' => 'Sender address for guest emails'],
        'reminder_hours_before' => ['type' => 'integer', 'description' => 'Hours before the reservation to send the reminder (min 1)'],
        'mailersend_api_key'    => ['type' => 'string', 'description' => 'MailerSend API key (write-only)'],
        'sms_api_key'           => ['type' => 'string', 'description' => 'SMS provider API key / account SID (write-only)'],
        'sms_api_secret'        => ['type' => 'string', 'description' => 'SMS provider secret / auth token (write-only)'],
        'sms_from_number'       => ['type' => 'string', 'description' => 'SMS sender phone number'],
    ],
    'secret' => ['mailersend_api_key', 'sms_api_key', 'sms_api_secret'],
    'handler' => fn($in, $ctx) => ['notifications' => svc_notifications_update($ctx['restaurant_id'], $in)],
]);

api_op('notifications_send_test_sms', [
    'description' => 'Send a test SMS to a phone number using the saved SMS settings. This sends a real text message.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'settings/notifications/test-sms',
    'input' => ['phone' => ['type' => 'string', 'description' => 'Destination phone number']],
    'required' => ['phone'],
    'handler' => fn($in, $ctx) => svc_notifications_test_sms($ctx['restaurant_id'], $in['phone']),
]);

// ── Email templates (admin) ────────────────────────────────

$templateInput = [
    'subject'   => ['type' => 'string', 'description' => 'Email subject; may use {placeholders}'],
    'body_html' => ['type' => 'string', 'description' => 'HTML body; placeholders: {guest_name}, {guest_first_name}, {guest_last_name}, {restaurant_name}, {restaurant_phone}, {date}, {time}, {party_size}, {confirmation_code}, {status}'],
    'body_text' => ['type' => 'string', 'description' => 'Plain-text body (optional)'],
    'is_active' => ['type' => 'boolean'],
];

api_op('email_templates_list', [
    'description' => 'List the guest email templates (confirmation, reminder, cancellation, waitlist_ready, modification, thank_you).',
    'role' => 'admin', 'method' => 'GET', 'path' => 'email-templates',
    'handler' => fn($in, $ctx) => ['email_templates' => svc_email_templates_list($ctx['restaurant_id'])],
]);

api_op('email_templates_create', [
    'description' => 'Create the template for one type. Each type can exist once per restaurant.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'email-templates',
    'input' => ['template_key' => ['type' => 'string', 'enum' => SETTINGS_EMAIL_TEMPLATE_KEYS]] + $templateInput,
    'required' => ['template_key', 'subject', 'body_html'],
    'handler' => fn($in, $ctx) => ['email_template' => svc_email_templates_save($ctx['restaurant_id'], 0, $in + ['is_active' => true])],
]);

api_op('email_templates_update', [
    'description' => 'Edit an email template. Only the fields sent are changed.',
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'email-templates/{id}',
    'input' => $templateInput,
    'handler' => fn($in, $ctx) => ['email_template' => svc_email_templates_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_row('email_templates', $in['id'], $ctx['restaurant_id'], 'Template'), $in))],
]);

api_op('email_templates_delete', [
    'description' => 'Delete an email template (the built-in default text is used again).',
    'role' => 'admin', 'method' => 'DELETE', 'path' => 'email-templates/{id}',
    'handler' => function ($in, $ctx) {
        svc_email_templates_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

// ── Integrations (admin) ───────────────────────────────────

api_op('integrations_get', [
    'description' => 'Get voice-agent integration settings: Retell agent ID, voice prompt sections (greeting, specials, custom info). Retell and MCP keys are returned masked.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'settings/integrations',
    'handler' => fn($in, $ctx) => ['integrations' => svc_integrations_get($ctx['restaurant_id'])],
]);

api_op('integrations_update', [
    'description' => 'Update integration settings. Only the fields sent are changed; an empty string clears a value. Keys are write-only. Changing mcp_api_key disconnects voice/SMS agents that use the old key.',
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'settings/integrations',
    'input' => [
        'retell_api_key'          => ['type' => 'string', 'description' => 'Retell AI API key (write-only)'],
        'retell_agent_id'         => ['type' => 'string', 'description' => 'Default Retell agent ID for web calls'],
        'mcp_api_key'             => ['type' => 'string', 'description' => 'Key the guest MCP server (/api/mcp/server.php) requires (write-only)'],
        'openai_api_key'          => ['type' => 'string', 'description' => 'OpenAI API key used by the SMS and email agents (write-only)'],
        'voice_agent_greeting'    => ['type' => 'string', 'description' => 'Voice agent greeting'],
        'voice_agent_specials'    => ['type' => 'string', 'description' => 'Specials the voice agent mentions'],
        'voice_agent_custom_info' => ['type' => 'string', 'description' => 'Extra restaurant info for the voice agent'],
    ],
    'secret' => ['retell_api_key', 'mcp_api_key', 'openai_api_key'],
    'handler' => fn($in, $ctx) => ['integrations' => svc_integrations_update($ctx['restaurant_id'], $in)],
]);
