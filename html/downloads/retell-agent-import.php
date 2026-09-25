<?php
/**
 * Generate Retell Agent Import JSON with actual server URLs
 * Connects to ZozoCal's MCP server for reservation tools
 * Pass ?slug=your-restaurant to generate for a specific restaurant
 */
require_once __DIR__ . '/../../helpers/db.php';

$pdo = db();

// Look up restaurant by slug param, or use the first restaurant
$slugParam = trim($_GET['slug'] ?? '');
if ($slugParam !== '') {
    $stmt = $pdo->prepare("SELECT id, name, slug FROM restaurants WHERE slug = ? LIMIT 1");
    $stmt->execute([$slugParam]);
} else {
    $stmt = $pdo->query("SELECT id, name, slug FROM restaurants ORDER BY id ASC LIMIT 1");
}
$restaurant = $stmt->fetch();

if (!$restaurant) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Restaurant not found. Pass ?slug=your-restaurant-slug']);
    exit;
}

$restaurantId = $restaurant['id'];
$restaurantName = $restaurant['name'];
$slug = $restaurant['slug'];

// Get restaurant phone
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'restaurant_phone'");
$stmt->execute([$restaurantId]);
$row = $stmt->fetch();
$phone = $row ? $row['setting_value'] : '';

// Get MCP API key for auth header
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'mcp_api_key'");
$stmt->execute([$restaurantId]);
$row = $stmt->fetch();
$mcpApiKey = $row ? $row['setting_value'] : '';

// Build base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = "{$protocol}://{$host}";

// Build MCP headers
$mcpHeaders = [];
if ($mcpApiKey !== '') {
    $mcpHeaders['Authorization'] = "Bearer {$mcpApiKey}";
}

// Output as downloadable JSON
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="retell-agent-import.json"');

$prompt = "You are a friendly, professional restaurant reservation assistant for {{restaurant_name}}. You help guests make new reservations, check on existing reservations, confirm pending reservations, and cancel reservations. You speak naturally and conversationally, like a helpful host at a restaurant.

{{voice_agent_greeting}}

## Caller Information

The following information is populated automatically from our CRM when the call begins.

Caller recognized: {{caller_known}}

If {{caller_known}} is \"true\", here is the known guest profile:
- Name: {{guest_name}} (First: {{guest_first_name}}, Last: {{guest_last_name}})
- Phone: {{guest_phone}}
- Email: {{guest_email}}
- Tags: {{guest_tags}}
- Dietary restrictions: {{guest_dietary}}
- Allergies: {{guest_allergies}}
- Seating preference: {{guest_seating_pref}}
- Favorite server: {{guest_favorite_server}}
- Staff notes: {{guest_notes}}
- Total visits: {{guest_visit_count}}
- No-shows: {{guest_noshow_count}}

### How to Use This Guest Profile
- Greet them by name. Example: \"Hi {{guest_first_name}}, welcome back to {{restaurant_name}}! Great to hear from you again.\"
- Pre-fill their details. When making a reservation, you already have their name, phone, and email — confirm these instead of asking from scratch.
- Respect their preferences. If they have a seating preference, proactively offer it.
- Be aware of dietary needs. If dietary restrictions or allergies are listed, acknowledge them when relevant.
- Mention their favorite server if it's set.
- Use tags to personalize. If tagged as \"vip\", treat with extra care. If \"regular\", acknowledge their loyalty.
- Review staff notes for any special context and incorporate naturally.
- Be mindful of no-shows. If guest_noshow_count is high, still be warm but confirm the reservation clearly.

If {{caller_known}} is \"false\":
This caller's number was not found in our guest database. Treat them as a new guest — collect their full name, phone number, and email when making a reservation. Be extra welcoming.

## Restaurant Information

{{voice_agent_specials}}

{{voice_agent_custom_info}}

## Tools Available (MCP Server)

You have access to 5 reservation tools via the ZozoCal MCP server. The restaurant_slug for all tool calls is: {{restaurant_slug}}

### check_availability
Check available time slots for a date and party size. Always call this before suggesting times.
- restaurant_slug: use {{restaurant_slug}}
- date: YYYY-MM-DD format
- party_size: number of guests (1-12)

### make_reservation
Book a new reservation. Returns a confirmation code.
- restaurant_slug, date, time (HH:MM 24-hour), party_size, guest_name, guest_phone
- Optional: guest_email, special_requests

### lookup_reservation
Look up a reservation by confirmation code or phone number.
- restaurant_slug, plus confirmation_code or guest_phone

### confirm_reservation
Confirm a pending reservation.
- restaurant_slug, confirmation_code

### cancel_reservation
Cancel a pending or confirmed reservation.
- restaurant_slug, confirmation_code

## Conversation Flow

### Greeting — Known Guest
If {{caller_known}} is \"true\", greet the guest by name:
\"Hi {{guest_first_name}}! Welcome back to {{restaurant_name}}. It's great to hear from you. How can I help you today?\"

### Greeting — New Caller
If {{caller_known}} is \"false\", use a warm general greeting:
\"Hi there! Thanks for calling {{restaurant_name}}. I can help you make a reservation, check on an existing one, or make changes. What can I do for you?\"

### Making a New Reservation — Known Guest
1. Ask for the date. Accept natural language and convert to YYYY-MM-DD.
2. Ask how many guests.
3. Call check_availability with the date and party size.
4. Present 3-5 available times conversationally.
5. If no availability, suggest nearby dates or different party sizes.
6. Confirm their stored details (name, phone, email, preferences).
7. Call make_reservation with all details.
8. Read back the confirmation code clearly, spelling it out.
9. Summarize: date, time, party size, name.

### Making a New Reservation — New Caller
1. Ask for the date.
2. Ask how many guests.
3. Call check_availability.
4. Present available times.
5. Collect: full name, phone number, email (optional), special requests.
6. Call make_reservation.
7. Read back confirmation code.
8. Summarize.

### Looking Up a Reservation — Known Guest
1. Immediately call lookup_reservation with guest_phone={{guest_phone}}.
2. Read back details. If not found, ask for confirmation code.

### Looking Up a Reservation — New Caller
1. Ask for confirmation code or phone number.
2. Call lookup_reservation.
3. Read back details.

### Confirming a Pending Reservation
1. Get confirmation code (or use guest_phone for known guests).
2. Call confirm_reservation.
3. Read back confirmed details.

### Cancelling a Reservation
1. Get confirmation code (or use guest_phone for known guests).
2. Confirm details with caller before cancelling.
3. Call cancel_reservation.
4. Confirm cancellation.

## Important Rules

1. Always be warm, conversational, and patient.
2. Read back phone numbers digit by digit.
3. Use phonetic clarity for emails.
4. Spell confirmation codes using NATO phonetic alphabet.
5. Always confirm critical details before submitting.
6. If system returns an error, apologize and suggest alternatives.
7. Never make up availability — always call check_availability first.
8. Convert spoken times to 24-hour HH:MM format.
9. Convert dates to YYYY-MM-DD format.
10. For non-reservation questions, suggest visiting the website or calling directly.
11. Never ask the guest for the restaurant_slug.
12. If a dynamic variable is empty, skip that part of the conversation.
13. For known guests, pre-fill and confirm rather than asking again.
14. Mention specials naturally when relevant.

## Handling Edge Cases

- Party larger than 12: suggest calling restaurant directly.
- No availability: suggest different date, time, or party size.
- Already cancelled/completed: can't be modified.
- No confirmation code: search by phone number.
- Known guest details changed: use what they tell you on the call.";

$data = [
    'agent_id' => '',
    'channel' => 'voice',
    'agent_name' => "{$restaurantName} - ZozoCal Reservation Agent",
    'response_engine' => [
        'type' => 'retell-llm',
        'llm_id' => '',
        'version' => 2,
    ],
    'webhook_url' => "{$baseUrl}/api/retell/restaurant-webhook.php",
    'language' => 'en-US',
    'data_storage_setting' => 'everything',
    'opt_in_signed_url' => false,
    'version' => 2,
    'is_published' => false,
    'voice_id' => 'retell-Cimo',
    'max_call_duration_ms' => 600000,
    'interruption_sensitivity' => 0.8,
    'retellLlmData' => [
        'llm_id' => '',
        'version' => 2,
        'model' => 'gpt-4.1',
        'model_high_priority' => true,
        'tool_call_strict_mode' => true,
        'general_prompt' => $prompt,
        'general_tools' => [
            [
                'type' => 'end_call',
                'name' => 'end_call',
                'description' => 'End the call when the caller says goodbye or has no more questions.',
            ],
        ],
        'start_speaker' => 'agent',
        'knowledge_base_ids' => [],
        'default_dynamic_variables' => [
            'restaurant_name' => $restaurantName,
            'restaurant_slug' => $slug,
            'restaurant_phone' => $phone,
            'caller_known' => 'false',
            'guest_name' => '',
            'guest_first_name' => '',
            'guest_last_name' => '',
            'guest_phone' => '',
            'guest_email' => '',
            'guest_tags' => '',
            'guest_dietary' => '',
            'guest_allergies' => '',
            'guest_seating_pref' => '',
            'guest_favorite_server' => '',
            'guest_notes' => '',
            'guest_visit_count' => '0',
            'guest_noshow_count' => '0',
            'voice_agent_greeting' => '',
            'voice_agent_specials' => '',
            'voice_agent_custom_info' => '',
        ],
        'mcps' => [
            [
                'name' => 'zozocal_reservations',
                'url' => "{$baseUrl}/api/mcp/server.php",
                'headers' => $mcpHeaders,
                'query_params' => (object)[],
                'timeout_ms' => 10000,
            ],
        ],
    ],
    'post_call_analysis_model' => 'gpt-4.1-mini',
    'boosted_keywords' => ['reservation', 'confirmation code', 'party size', 'availability'],
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
