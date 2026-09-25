# Retell AI Voice Agent Integration — Plan

## Overview

Build a REST API and MCP server so Retell AI voice agents can book, look up, modify, confirm, and cancel reservations during a live phone call. Both interfaces share the same core service layer.

## Architecture

```
Caller (Phone)
    |
    v
Retell AI Voice Agent (cloud)
    |
    |--- Option A: Custom Functions (POST to /api/retell/*.php)
    |--- Option B: MCP Server (HTTP at /api/mcp/server.php)
    |
    v
Shared Service Layer (helpers/voice-api.php)
    |
    v
Existing Helpers (availability.php, restaurant.php, notifications.php)
    |
    v
Database (restaurant_reservations)
```

## API Functions (5 tools)

### 1. `check_availability`
- **Input:** restaurant_slug, date (YYYY-MM-DD), party_size
- **Output:** List of available time slots with service period names
- **Uses:** getAvailableSlots() from helpers/availability.php

### 2. `make_reservation`
- **Input:** restaurant_slug, date, time (HH:MM), party_size, guest_name, guest_phone, guest_email (optional), special_requests (optional)
- **Output:** confirmation_code, reservation summary
- **Uses:** findAvailableTable(), guest upsert, reservation insert, sendConfirmationEmail()

### 3. `lookup_reservation`
- **Input:** restaurant_slug, confirmation_code OR guest_phone
- **Output:** Reservation details (date, time, party size, status, guest name)
- **Uses:** Direct DB query joining reservations + guests

### 4. `cancel_reservation`
- **Input:** restaurant_slug, confirmation_code
- **Output:** Cancellation confirmation message
- **Uses:** Status update to 'cancelled', sendCancellationEmail()
- **Constraint:** Only pending/confirmed reservations can be cancelled

### 5. `confirm_reservation`
- **Input:** restaurant_slug, confirmation_code
- **Output:** Confirmation message with details
- **Uses:** Status update from 'pending' to 'confirmed', sendConfirmationEmail()

---

## Build Prompts

### Prompt 22 — Shared Voice API Service Layer
```
Create helpers/voice-api.php — A shared service layer with pure PHP functions
that both the Retell Custom Functions API and MCP server will call.

Functions (all return associative arrays with 'success' bool + data/error):
1. voiceCheckAvailability($slug, $date, $partySize)
   - Validate restaurant exists by slug
   - Validate date is not in past, within max booking window
   - Validate party size within max_online_party_size
   - Call getAvailableSlots(), format results
   - Return slots grouped by service period

2. voiceMakeReservation($slug, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests)
   - Validate restaurant, date, time, party size
   - Find or create guest (match by phone within restaurant)
   - Auto-assign table via findAvailableTable()
   - Generate confirmation code
   - Insert reservation (source='phone', status='confirmed')
   - Log activity
   - Send confirmation email if configured
   - Return confirmation code + summary

3. voiceLookupReservation($slug, $codeOrPhone)
   - Validate restaurant
   - Search by confirmation_code first, then by guest phone
   - Return reservation details or not-found error

4. voiceCancelReservation($slug, $confirmationCode)
   - Validate restaurant, find reservation
   - Check status is cancellable (pending/confirmed)
   - Update status to cancelled, set cancelled_at
   - Log activity, send cancellation email
   - Return success message

5. voiceConfirmReservation($slug, $confirmationCode)
   - Validate restaurant, find reservation
   - Check status is pending
   - Update status to confirmed
   - Log activity, send confirmation email
   - Return confirmation message

All functions: no session dependency, no CSRF (API auth handled separately).
Include helpers/availability.php, helpers/restaurant.php, helpers/notifications.php.
Push when done.
```

### Prompt 23 — Retell Custom Functions API Endpoints
```
Create the Retell AI Custom Functions API endpoints under html/api/retell/.

1. Create helpers/retell-auth.php — Request verification:
   - verifyRetellSignature($body, $signature, $apiKey)
   - Uses HMAC SHA-256 to verify X-Retell-Signature header
   - API key stored in settings table (setting_key = 'retell_api_key')
   - Returns true/false

2. Create html/api/retell/check-availability.php — POST endpoint:
   - Verify X-Retell-Signature
   - Parse JSON body: { "args": { "date": "...", "party_size": N, "restaurant_slug": "..." } }
   - Call voiceCheckAvailability()
   - Return JSON response (Retell reads this and speaks it)

3. Create html/api/retell/make-reservation.php — POST endpoint:
   - Same auth pattern
   - Parse args: date, time, party_size, guest_name, guest_phone, guest_email, special_requests
   - Call voiceMakeReservation()
   - Return JSON with confirmation code

4. Create html/api/retell/lookup-reservation.php — POST endpoint:
   - Parse args: confirmation_code or guest_phone
   - Call voiceLookupReservation()
   - Return reservation details as JSON

5. Create html/api/retell/cancel-reservation.php — POST endpoint:
   - Parse args: confirmation_code
   - Call voiceCancelReservation()
   - Return cancellation confirmation

6. Create html/api/retell/confirm-reservation.php — POST endpoint:
   - Parse args: confirmation_code
   - Call voiceConfirmReservation()
   - Return confirmation details

All endpoints: JSON Content-Type, no session/CSRF, Retell signature auth.
Response format: JSON object converted to string by Retell for LLM consumption.
Keep responses concise and natural-language friendly (the LLM reads them to speak).
Push when done.
```

### Prompt 24 — MCP Server
```
Create an MCP (Model Context Protocol) HTTP server at html/api/mcp/server.php.

1. Create html/api/mcp/server.php — Streamable HTTP MCP server:
   - Implements MCP protocol over HTTP (POST with JSON-RPC 2.0)
   - Handles: initialize, tools/list, tools/call
   - Auth: Bearer token in Authorization header (stored as setting 'mcp_api_key')
   - No session dependency

2. Tool definitions (tools/list response):
   - check_availability: { date: string, party_size: integer, restaurant_slug: string }
   - make_reservation: { date, time, party_size, guest_name, guest_phone, restaurant_slug, guest_email?, special_requests? }
   - lookup_reservation: { confirmation_code?, guest_phone?, restaurant_slug }
   - cancel_reservation: { confirmation_code, restaurant_slug }
   - confirm_reservation: { confirmation_code, restaurant_slug }

3. Tool execution (tools/call):
   - Route to appropriate voiceXxx() function from helpers/voice-api.php
   - Return MCP-formatted tool result (content array with text type)

4. Error handling:
   - Invalid method → JSON-RPC error -32601
   - Invalid params → JSON-RPC error -32602
   - Internal error → JSON-RPC error -32603

Push when done.
```

### Prompt 25 — Settings UI and Documentation
```
1. Add Retell AI settings to the notification/integration settings page:
   - Add a new card section in html/partials/settings/notifications.php OR create
     html/partials/settings/integrations.php
   - Fields: Retell API Key (for signature verification), MCP API Key (bearer token)
   - Display the endpoint URLs for easy copy-paste into Retell dashboard
   - Test connection button

2. Add a sidebar link for "Integrations" under SETTINGS (owner only) in app.php

3. Create docs/retell-setup-guide.md:
   - How to configure each custom function in Retell dashboard
   - Function names, descriptions, parameter schemas (copy-paste ready)
   - MCP server connection instructions
   - Example agent prompt for the voice agent

Push when done.
```

---

## Retell Agent Prompt (for reference)

Example system prompt for the Retell voice agent:

```
You are a friendly restaurant reservation assistant for {{restaurant_name}}.

When a caller wants to make a reservation:
1. Ask for the date and party size
2. Call check_availability to find open times
3. Read back 3-5 available times and ask which they prefer
4. Ask for their name and phone number
5. Call make_reservation to book it
6. Read back the confirmation code and details

When a caller wants to check on a reservation:
1. Ask for their confirmation code or phone number
2. Call lookup_reservation to find it
3. Read back the details

When a caller wants to cancel:
1. Look up the reservation first
2. Confirm they want to cancel
3. Call cancel_reservation
4. Confirm the cancellation

When a caller wants to confirm a pending reservation:
1. Look up the reservation first
2. Call confirm_reservation
3. Read back the confirmed details

Always be polite and concise. Spell out confirmation codes letter by letter.
```

---

## Review Checklist

- [ ] Shared service layer with no session dependency
- [ ] Retell signature verification on all API endpoints
- [ ] MCP server with JSON-RPC 2.0 protocol
- [ ] All 5 tools working: check, book, lookup, cancel, confirm
- [ ] Natural-language-friendly responses for voice reading
- [ ] Settings UI for API keys
- [ ] Setup documentation with copy-paste schemas
- [ ] Activity logging on all reservation actions
- [ ] Email notifications triggered where appropriate
