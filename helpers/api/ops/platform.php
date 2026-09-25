<?php
/**
 * API operations: platform administration (super-admin only).
 * Not scoped to the key's restaurant — restaurant IDs are passed explicitly.
 */

require_once __DIR__ . '/../../services/platform.php';
require_once __DIR__ . '/../../services/billing.php';

/** Current row merged with the fields sent, for PATCH semantics. */
$merge = function (array $current, array $in): array {
    unset($in['id']);
    return array_merge($current, $in);
};
$activeInput = ['active' => ['type' => 'boolean', 'description' => 'true to activate, false to deactivate']];

// ── Restaurants ─────────────────────────────────────────────

$restaurantFields = [
    'name'          => ['type' => 'string'],
    'slug'          => ['type' => 'string', 'description' => 'Lowercase letters, numbers and hyphens; unique; used in booking URLs and guest-agent tools'],
    'timezone'      => ['type' => 'string', 'description' => 'IANA timezone, e.g. America/Chicago'],
    'phone'         => ['type' => 'string'],
    'email'         => ['type' => 'string'],
    'address_line1' => ['type' => 'string'],
    'city'          => ['type' => 'string'],
    'state'         => ['type' => 'string'],
    'postal_code'   => ['type' => 'string'],
    'website'       => ['type' => 'string', 'description' => 'Full URL'],
];

api_op('platform_restaurants_list', [
    'description' => 'List every restaurant on the platform with staff count and today\'s reservation count.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/restaurants',
    'handler' => fn($in, $ctx) => ['restaurants' => svc_platform_restaurant_list()],
]);

api_op('platform_restaurants_get', [
    'description' => 'Get one restaurant record.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/restaurants/{id}',
    'handler' => fn($in, $ctx) => ['restaurant' => svc_platform_restaurant_get($in['id'])],
]);

api_op('platform_restaurants_dashboard', [
    'description' => 'Restaurant overview: staff and roles, reservation counts, subscriptions and account options.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/restaurants/{id}/dashboard',
    'handler' => fn($in, $ctx) => svc_platform_restaurant_dashboard($in['id']),
]);

api_op('platform_restaurants_create', [
    'description' => 'Create a restaurant with default settings, a Main Dining section, turn times and opening hours. The key\'s user becomes its admin.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/restaurants',
    'input' => $restaurantFields, 'required' => ['name', 'slug', 'timezone'],
    'handler' => fn($in, $ctx) => ['restaurant' => svc_platform_restaurant_save(0, $in, $ctx['user_id'])],
]);

api_op('platform_restaurants_update', [
    'description' => 'Change a restaurant\'s profile. Only the fields sent are changed.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/restaurants/{id}',
    'input' => $restaurantFields,
    'handler' => fn($in, $ctx) => ['restaurant' => svc_platform_restaurant_save($in['id'],
        $merge(svc_platform_restaurant_get($in['id']), $in), $ctx['user_id'])],
]);

api_op('platform_restaurants_set_active', [
    'description' => 'Activate or deactivate a restaurant. A deactivated restaurant stops taking bookings and its guest-agent key stops working.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/restaurants/{id}/active',
    'input' => $activeInput, 'required' => ['active'],
    'handler' => fn($in, $ctx) => ['restaurant' => svc_platform_restaurant_set_active($in['id'], $in['active'], $ctx['user_id'])],
]);

// ── Platform users ──────────────────────────────────────────

$userFields = [
    'first_name' => ['type' => 'string'],
    'last_name'  => ['type' => 'string'],
    'email'      => ['type' => 'string'],
    'phone'      => ['type' => 'string'],
    'password'   => ['type' => 'string', 'description' => 'At least 8 characters. Required on create; on update only if changing it.'],
    'role'       => ['type' => 'string', 'enum' => PLATFORM_USER_ROLES, 'description' => 'Platform role'],
];

api_op('platform_users_list', [
    'description' => 'List every user with platform role, status and restaurant memberships.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/users',
    'handler' => fn($in, $ctx) => ['users' => svc_platform_user_list()],
]);

api_op('platform_users_get', [
    'description' => 'Get one user with their restaurant memberships.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/users/{id}',
    'handler' => fn($in, $ctx) => ['user' => svc_platform_user_get($in['id'])],
]);

api_op('platform_users_create', [
    'description' => 'Create a user account. Use platform_users_update with assign_restaurant_id to add them to a restaurant.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/users',
    'input' => $userFields, 'required' => ['first_name', 'last_name', 'email', 'password'], 'secret' => ['password'],
    'handler' => fn($in, $ctx) => ['user' => svc_platform_user_save(0, $in, $ctx['user_id'])],
]);

api_op('platform_users_update', [
    'description' => 'Change a user. Only the fields sent are changed. assign_restaurant_id + assign_role adds or changes a restaurant membership.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/users/{id}',
    'input' => $userFields + [
        'assign_restaurant_id' => ['type' => 'integer'],
        'assign_role' => ['type' => 'string', 'enum' => PLATFORM_RESTAURANT_ROLES, 'description' => 'Restaurant role for assign_restaurant_id'],
    ],
    'secret' => ['password'],
    'handler' => function ($in, $ctx) use ($merge) {
        $data = $merge(svc_platform_user_get($in['id']), $in);
        if (!isset($in['password'])) $data['password'] = '';
        return ['user' => svc_platform_user_save($in['id'], $data, $ctx['user_id'])];
    },
]);

api_op('platform_users_set_active', [
    'description' => 'Activate or deactivate a user account (cannot deactivate yourself).',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/users/{id}/active',
    'input' => $activeInput, 'required' => ['active'],
    'handler' => fn($in, $ctx) => ['user' => svc_platform_user_set_active($in['id'], $in['active'], $ctx['user_id'])],
]);

// ── Products & features ─────────────────────────────────────

$productFields = [
    'name'             => ['type' => 'string'],
    'slug'             => ['type' => 'string', 'description' => 'Lowercase letters, numbers and hyphens; unique'],
    'description'      => ['type' => 'string'],
    'price'            => ['type' => 'number'],
    'billing_interval' => ['type' => 'string', 'enum' => PLATFORM_BILLING_INTERVALS],
    'sort_order'       => ['type' => 'integer'],
    'is_active'        => ['type' => 'boolean', 'description' => 'Default true on create'],
];

api_op('platform_products_list', [
    'description' => 'List subscription products with feature count and active subscription count.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/products',
    'handler' => fn($in, $ctx) => ['products' => svc_platform_product_list()],
]);

api_op('platform_products_get', [
    'description' => 'Get one product with its features.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/products/{id}',
    'handler' => fn($in, $ctx) => ['product' => svc_platform_product_get($in['id'])],
]);

api_op('platform_products_create', [
    'description' => 'Create a subscription product.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/products',
    'input' => $productFields, 'required' => ['name', 'slug'],
    'handler' => fn($in, $ctx) => ['product' => svc_platform_product_save(0, $in + ['is_active' => true], $ctx['user_id'])],
]);

api_op('platform_products_update', [
    'description' => 'Change a product. Only the fields sent are changed.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/products/{id}',
    'input' => $productFields,
    'handler' => fn($in, $ctx) => ['product' => svc_platform_product_save($in['id'],
        $merge(svc_platform_product_get($in['id']), $in), $ctx['user_id'])],
]);

api_op('platform_products_set_active', [
    'description' => 'Activate or deactivate a product (inactive products cannot be chosen for new subscriptions).',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/products/{id}/active',
    'input' => $activeInput, 'required' => ['active'],
    'handler' => fn($in, $ctx) => ['product' => svc_platform_product_set_active($in['id'], $in['active'], $ctx['user_id'])],
]);

api_op('platform_product_features_add', [
    'description' => 'Add a feature line to a product.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/products/{id}/features',
    'input' => [
        'feature_key'   => ['type' => 'string', 'description' => 'Machine key, e.g. max_tables'],
        'feature_label' => ['type' => 'string', 'description' => 'Display label'],
        'feature_value' => ['type' => 'string'],
        'sort_order'    => ['type' => 'integer'],
    ],
    'required' => ['feature_key', 'feature_label'],
    'handler' => fn($in, $ctx) => ['feature' => svc_platform_feature_add($in['id'], $in, $ctx['user_id'])],
]);

api_op('platform_product_features_delete', [
    'description' => 'Remove a feature from a product.',
    'role' => 'super-admin', 'method' => 'DELETE', 'path' => 'platform/products/{id}/features/{feature_id}',
    'input' => ['feature_id' => ['type' => 'integer', 'description' => 'ID of the feature']],
    'handler' => function ($in, $ctx) {
        svc_platform_feature_delete($in['id'], $in['feature_id'], $ctx['user_id']);
        return ['deleted' => true, 'feature_id' => $in['feature_id']];
    },
]);

// ── Subscriptions ───────────────────────────────────────────

$subscriptionFields = [
    'product_id' => ['type' => 'integer'],
    'status'     => ['type' => 'string', 'enum' => PLATFORM_SUBSCRIPTION_STATUSES],
    'started_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    'expires_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD (optional)'],
    'notes'      => ['type' => 'string'],
];

api_op('platform_subscriptions_list', [
    'description' => 'List subscriptions, optionally for one restaurant.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/subscriptions',
    'input' => ['restaurant_id' => ['type' => 'integer']],
    'handler' => fn($in, $ctx) => ['subscriptions' => svc_platform_subscription_list($in['restaurant_id'] ?? null)],
]);

api_op('platform_subscriptions_create', [
    'description' => 'Subscribe a restaurant to a product.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/subscriptions',
    'input' => ['restaurant_id' => ['type' => 'integer']] + $subscriptionFields,
    'required' => ['restaurant_id', 'product_id', 'started_at'],
    'handler' => fn($in, $ctx) => ['subscription' => svc_platform_subscription_save(0, $in, $ctx['user_id'])],
]);

api_op('platform_subscriptions_update', [
    'description' => 'Change a subscription (product, status, dates, notes). Only the fields sent are changed. Setting status cancelled records the cancellation time.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/subscriptions/{id}',
    'input' => $subscriptionFields,
    'handler' => fn($in, $ctx) => ['subscription' => svc_platform_subscription_save($in['id'],
        $merge(svc_platform_row('subscriptions', $in['id'], 'Subscription'), $in), $ctx['user_id'])],
]);

// ── Account options ─────────────────────────────────────────

api_op('platform_account_options_list', [
    'description' => 'List per-restaurant account options (feature flags), optionally for one restaurant.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/account-options',
    'input' => ['restaurant_id' => ['type' => 'integer']],
    'handler' => fn($in, $ctx) => ['account_options' => svc_platform_account_option_list($in['restaurant_id'] ?? null)],
]);

api_op('platform_account_options_create', [
    'description' => 'Set an account option for a restaurant (creates it, or overwrites the existing option with the same key).',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/account-options',
    'input' => [
        'restaurant_id' => ['type' => 'integer'],
        'option_key'    => ['type' => 'string'],
        'option_value'  => ['type' => 'string', 'description' => "Default '1'"],
        'enabled'       => ['type' => 'boolean', 'description' => 'Default true'],
        'notes'         => ['type' => 'string'],
    ],
    'required' => ['restaurant_id', 'option_key'],
    'handler' => fn($in, $ctx) => ['account_option' => svc_platform_account_option_save(0, $in + ['enabled' => true], $ctx['user_id'])],
]);

api_op('platform_account_options_update', [
    'description' => 'Change an account option\'s value, enabled flag or notes. Only the fields sent are changed.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/account-options/{id}',
    'input' => [
        'option_value' => ['type' => 'string'],
        'enabled'      => ['type' => 'boolean'],
        'notes'        => ['type' => 'string'],
    ],
    'handler' => fn($in, $ctx) => ['account_option' => svc_platform_account_option_save($in['id'],
        $merge(svc_platform_row('account_options', $in['id'], 'Account option'), $in), $ctx['user_id'])],
]);

api_op('platform_account_options_set_enabled', [
    'description' => 'Enable or disable an account option.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/account-options/{id}/enabled',
    'input' => ['enabled' => ['type' => 'boolean']], 'required' => ['enabled'],
    'handler' => fn($in, $ctx) => ['account_option' => svc_platform_account_option_set_enabled($in['id'], $in['enabled'], $ctx['user_id'])],
]);

api_op('platform_account_options_delete', [
    'description' => 'Delete an account option.',
    'role' => 'super-admin', 'method' => 'DELETE', 'path' => 'platform/account-options/{id}',
    'handler' => function ($in, $ctx) {
        svc_platform_account_option_delete($in['id'], $ctx['user_id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

// ── Nav permissions ─────────────────────────────────────────

$navFields = [
    'nav_item_id'     => ['type' => 'string', 'description' => 'Sidebar element id, e.g. nav-reports'],
    'label'           => ['type' => 'string'],
    'user_role'       => ['type' => 'string', 'description' => 'Platform role (user, super-admin); empty = any'],
    'restaurant_role' => ['type' => 'string', 'description' => 'Restaurant role (user, manager, admin); empty = any'],
    'is_active'       => ['type' => 'boolean'],
];

api_op('platform_nav_permissions_list', [
    'description' => 'List sidebar visibility rules (a nav item is shown when any active rule matches the user\'s platform and restaurant role).',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/nav-permissions',
    'input' => ['nav_item_id' => ['type' => 'string']],
    'handler' => fn($in, $ctx) => ['nav_permissions' => svc_platform_nav_permission_list($in['nav_item_id'] ?? '')],
]);

api_op('platform_nav_permissions_create', [
    'description' => 'Add a sidebar visibility rule.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/nav-permissions',
    'input' => $navFields, 'required' => ['nav_item_id'],
    'handler' => fn($in, $ctx) => ['nav_permission' => svc_platform_nav_permission_save(0, $in + ['is_active' => true], $ctx['user_id'])],
]);

api_op('platform_nav_permissions_update', [
    'description' => 'Change a sidebar visibility rule. Only the fields sent are changed.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/nav-permissions/{id}',
    'input' => $navFields,
    'handler' => fn($in, $ctx) => ['nav_permission' => svc_platform_nav_permission_save($in['id'],
        $merge(svc_platform_row('nav_permissions', $in['id'], 'Nav permission'), $in), $ctx['user_id'])],
]);

api_op('platform_nav_permissions_delete', [
    'description' => 'Delete a sidebar visibility rule.',
    'role' => 'super-admin', 'method' => 'DELETE', 'path' => 'platform/nav-permissions/{id}',
    'handler' => function ($in, $ctx) {
        svc_platform_nav_permission_delete($in['id'], $ctx['user_id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

// ── Billing settings & jobs ─────────────────────────────────

api_op('platform_billing_settings_list', [
    'description' => 'Invoice settings of every restaurant: invoice day, description, monthly price, prepay balance, billing user.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/billing-settings',
    'handler' => fn($in, $ctx) => ['restaurants' => svc_platform_billing_settings_list()],
]);

api_op('platform_billing_settings_get', [
    'description' => 'Invoice settings of one restaurant.',
    'role' => 'super-admin', 'method' => 'GET', 'path' => 'platform/billing-settings/{id}',
    'input' => ['id' => ['type' => 'integer', 'description' => 'Restaurant ID']],
    'handler' => fn($in, $ctx) => ['billing_settings' => svc_platform_billing_settings_get($in['id'])],
]);

api_op('platform_billing_settings_update', [
    'description' => 'Change a restaurant\'s invoice settings. Only the fields sent are changed.',
    'role' => 'super-admin', 'method' => 'PATCH', 'path' => 'platform/billing-settings/{id}',
    'input' => [
        'id'                   => ['type' => 'integer', 'description' => 'Restaurant ID'],
        'invoice_day_of_month' => ['type' => 'integer', 'description' => '1-28'],
        'invoice_description'  => ['type' => 'string'],
        'monthly_price'        => ['type' => 'number', 'description' => '0 = not invoiced'],
    ],
    'handler' => fn($in, $ctx) => ['billing_settings' => svc_platform_billing_settings_save($in['id'],
        $merge(svc_platform_billing_settings_get($in['id']), $in), $ctx['user_id'])],
]);

api_op('platform_run_invoice_job', [
    'description' => 'Run an invoice job now. generate_invoices bills active restaurants whose invoice day is today (or day_of_month) and applies prepay balances; mark_overdue marks unpaid invoices past due. Normally run daily by cron — running generate_invoices twice on the same day creates duplicate invoices.',
    'role' => 'super-admin', 'method' => 'POST', 'path' => 'platform/invoice-jobs',
    'input' => [
        'job' => ['type' => 'string', 'enum' => ['generate_invoices', 'mark_overdue']],
        'day_of_month' => ['type' => 'integer', 'description' => 'generate_invoices only; default today'],
    ],
    'required' => ['job'],
    'handler' => fn($in, $ctx) => $in['job'] === 'mark_overdue'
        ? ['job' => 'mark_overdue', 'marked_overdue' => svc_billing_mark_overdue()]
        : ['job' => 'generate_invoices'] + svc_billing_generate_invoices($in['day_of_month'] ?? null),
]);
