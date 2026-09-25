<?php
/**
 * UI ↔ API parity map, checked by scripts/api-smoke.php.
 *
 * Every POST handler under html/partials/ must appear here, mapped to the API
 * operation(s) that do the same thing — or to a string saying why agents
 * don't get it. Add a line whenever you add a handler.
 */

return [
    // Human sign-in flows: agents use API keys instead
    'auth/login.php'           => 'Human sign-in; agents authenticate with API keys',
    'auth/register.php'        => 'Human sign-up; platform_restaurants_create covers restaurant creation',
    'auth/forgot-password.php' => 'Human password reset',
    'auth/reset-password.php'  => 'Human password reset',
    'auth/google-complete.php' => 'Human Google sign-in',
    'auth/accept-invite.php'   => 'Human accepts an invitation',
    'auth/switch-restaurant.php' => 'Session restaurant switch; super-admin keys send X-Restaurant-Id',

    // Key management stays with humans so a leaked key cannot mint more keys
    'settings/save-api-key.php' => 'API key management is human-only',

    // Todos
    'todos/save.php'          => ['todos_create', 'todos_update'],
    'todos/update-status.php' => ['todos_set_status'],
    'todos/delete.php'        => ['todos_delete'],

    // Reservations
    'reservations/save.php'          => ['reservations_create'],
    'reservations/update.php'        => ['reservations_update'],
    'reservations/status-update.php' => ['reservations_set_status'],

    // Guests and events
    'guests/save.php'         => ['guests_create', 'guests_update', 'guests_add_tag', 'guests_remove_tag'],
    'events/save.php'         => ['events_create', 'events_update'],
    'events/save-booking.php' => ['events_book'],

    // Floor: sections, tables, waitlist
    'sections/save.php'                => ['sections_create', 'sections_update'],
    'sections/toggle.php'              => ['sections_set_active'],
    'tables/save.php'                  => ['tables_create', 'tables_update'],
    'tables/toggle.php'                => ['tables_set_active'],
    'tables/assign-walkin.php'         => ['tables_seat_walkin'],
    'tables/update-meal-status.php'    => ['tables_set_meal_status', 'tables_complete'],
    'waitlist/save.php'                => ['waitlist_add'],
    'waitlist/update-status.php'       => ['waitlist_set_status'],
    'waitlist/notify.php'              => ['waitlist_notify'],
    'waitlist/clear.php'               => ['waitlist_clear'],
    'waitlist/checkin-reservation.php' => ['waitlist_checkin_reservation'],
    'waitlist/seat-reservation.php'    => ['waitlist_seat_reservation'],
    'waitlist/notify-reservation.php'  => ['waitlist_notify_reservation'],

    // Billing: read-only for agents (decision 2026-09-25)
    'billing/process-payment.php' => 'Payments need a person (agents can read invoices and prepay history)',
    'billing/save-prepay.php'     => 'Payments need a person (agents can read invoices and prepay history)',

    // Platform administration (super-admin)
    'platform/save-restaurant.php'        => ['platform_restaurants_create', 'platform_restaurants_update'],
    'platform/toggle-restaurant.php'      => ['platform_restaurants_set_active'],
    'platform/save-platform-user.php'     => ['platform_users_create', 'platform_users_update'],
    'platform/toggle-platform-user.php'   => ['platform_users_set_active'],
    'platform/save-product.php'           => ['platform_products_create', 'platform_products_update'],
    'platform/toggle-product.php'         => ['platform_products_set_active'],
    'platform/save-product-feature.php'   => ['platform_product_features_add'],
    'platform/delete-product-feature.php' => ['platform_product_features_delete'],
    'platform/save-subscription.php'      => ['platform_subscriptions_create', 'platform_subscriptions_update'],
    'platform/save-account-option.php'    => ['platform_account_options_create', 'platform_account_options_update'],
    'platform/toggle-account-option.php'  => ['platform_account_options_set_enabled'],
    'platform/delete-account-option.php'  => ['platform_account_options_delete'],
    'platform/save-nav-permission.php'    => ['platform_nav_permissions_create', 'platform_nav_permissions_update'],
    'platform/delete-nav-permission.php'  => ['platform_nav_permissions_delete'],
    'platform/save-billing-settings.php'  => ['platform_billing_settings_update'],

    // Restaurant configuration
    'settings/save-profile.php'          => ['settings_profile_update'],
    'settings/save-hours.php'            => ['hours_create', 'hours_update', 'hours_delete'],
    'settings/save-special-date.php'     => ['special_dates_create', 'special_dates_update', 'special_dates_delete'],
    'settings/save-turn-time.php'        => ['turn_times_create', 'turn_times_update', 'turn_times_delete'],
    'settings/save-meal-percentages.php' => ['meal_percentages_update'],
    'settings/notifications.php'         => ['notifications_update', 'notifications_send_test_sms'],
    'settings/email-templates.php'       => ['email_templates_create', 'email_templates_update', 'email_templates_delete'],
    'settings/save-integrations.php'     => ['integrations_update'],

    // Team and phone numbers
    'settings/save-user.php'           => ['users_create', 'users_update'],
    'settings/toggle-user.php'         => ['users_set_active'],
    'settings/save-phone-number.php'   => ['phone_numbers_create', 'phone_numbers_update'],
    'settings/delete-phone-number.php' => ['phone_numbers_delete'],

    // AI agents (voice / Retell, text, email)
    'settings/save-voice-prompt.php'    => ['voice_agents_update'],
    'settings/delete-voice-prompt.php'  => ['voice_agents_delete'],
    'settings/sync-voice-prompt.php'    => ['voice_agents_sync'],
    'settings/retell-create-agent.php'  => ['voice_agents_create'],
    'settings/retell-update-prompt.php' => ['voice_agents_update_prompt'],
    'settings/retell-assign-phone.php'  => ['voice_agents_assign_phone'],
    'settings/save-text-agent.php'      => ['text_agents_create', 'text_agents_update'],
    'settings/delete-text-agent.php'    => ['text_agents_delete'],
    'settings/save-email-agent.php'     => ['email_agents_create', 'email_agents_update'],
    'settings/delete-email-agent.php'   => ['email_agents_delete'],
];
