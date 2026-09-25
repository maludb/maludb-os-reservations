<?php
/**
 * API operations: read-only insight — dashboard, reports, message logs, activity log
 */

require_once __DIR__ . '/../../services/insight.php';

$logFilters = [
    'direction' => ['type' => 'string', 'enum' => INSIGHT_DIRECTIONS],
    'search'    => ['type' => 'string', 'description' => 'Text in the addresses/numbers or message content'],
    'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    'date_to'   => ['type' => 'string', 'description' => 'YYYY-MM-DD (inclusive)'],
    'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
];

api_op('dashboard_summary', [
    'description' => "Today's numbers: reservations, covers, no-shows, table occupancy, waitlist size, arrivals in the next 2 hours, unconfirmed reservations for today/tomorrow, and upcoming events.",
    'method' => 'GET', 'path' => 'dashboard',
    'handler' => fn($in, $ctx) => svc_insight_dashboard($ctx['restaurant_id']),
]);

api_op('reports_data', [
    'description' => 'Reservation report for a date range (default: last 30 days): totals, no-show and cancellation rates, new vs returning guests, and breakdowns by day, hour, section, source and weekday×hour.',
    'method' => 'GET', 'path' => 'reports',
    'role' => 'manager',
    'input' => [
        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'end_date'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    ],
    'handler' => fn($in, $ctx) => svc_insight_report_data($ctx['restaurant_id'], $in),
]);

api_op('messages_email_log', [
    'description' => 'Emails sent and received by the email agent and notifications, newest first.',
    'method' => 'GET', 'path' => 'messages/email',
    'input' => $logFilters,
    'handler' => fn($in, $ctx) => svc_insight_messages($ctx['restaurant_id'], 'email', $in),
]);

api_op('messages_sms_log', [
    'description' => 'SMS messages sent and received, newest first.',
    'method' => 'GET', 'path' => 'messages/sms',
    'input' => $logFilters,
    'handler' => fn($in, $ctx) => svc_insight_messages($ctx['restaurant_id'], 'sms', $in),
]);

api_op('messages_voice_log', [
    'description' => 'Voice agent calls with summaries and transcripts, newest first.',
    'method' => 'GET', 'path' => 'messages/voice',
    'input' => $logFilters,
    'handler' => fn($in, $ctx) => svc_insight_messages($ctx['restaurant_id'], 'voice', $in),
]);

api_op('activity_log_list', [
    'description' => 'Audit trail of changes in this restaurant, newest first. Changes made through the API or MCP appear as action "api_call" naming the key. Filter e.g. entity_type=reservation&entity_id=12 for one record\'s history.',
    'method' => 'GET', 'path' => 'activity',
    'role' => 'manager',
    'input' => [
        'entity_type' => ['type' => 'string', 'description' => 'e.g. reservation, guest, event, api'],
        'entity_id'   => ['type' => 'integer'],
        'action'      => ['type' => 'string', 'description' => 'e.g. create, update, status_change, api_call'],
        'user_id'     => ['type' => 'integer'],
        'date_from'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'date_to'     => ['type' => 'string', 'description' => 'YYYY-MM-DD (inclusive)'],
        'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
    ],
    'handler' => fn($in, $ctx) => svc_insight_activity($ctx['restaurant_id'], $in),
]);
