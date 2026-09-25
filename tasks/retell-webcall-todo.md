# Retell Voice Agent Web Calling — Todo

## Tasks

- [x] 1. Fix `create-web-call.php` to use settings table instead of missing `config/retell.php`, and support passing metadata (guest info, reservation context) to the agent
- [x] 2. Add Retell Web SDK (`retell-client-js-sdk`) script tag to `app.php`
- [x] 3. Rewrite `retell-integration.js` for the restaurant system — call UI overlay, pass contextual data, handle audio through browser
- [x] 4. Add CSS styles for the call overlay UI
- [x] 5. Add "Call with AI Agent" buttons to key pages: reservation detail, guest detail, waitlist entries
- [x] 6. Add Retell Agent ID configuration to the integrations settings page

## Review

All 6 tasks completed. Changes made:

- **create-web-call.php**: Now reads `retell_api_key` and `retell_agent_id` from settings table. Passes metadata as `retell_llm_dynamic_variables` so the Retell agent has context about the guest/reservation.
- **app.php**: Added Retell Web SDK (CDN v3.5.1) and retell-integration.js script tags.
- **retell-integration.js**: Complete rewrite with full-screen call overlay UI, pulse animation, call timer, contextual info display. Uses `data-retell-call` attribute on buttons with data attributes for metadata.
- **kobie-custom.css**: Added overlay styles with backdrop blur, animated pulse ring for call states, status colors.
- **Reservation detail**: "Call AI Agent" button passes all reservation context (guest, date, time, party size, confirmation code, status, special requests).
- **Guest detail**: "Call AI Agent" button passes guest contact info.
- **Waitlist**: Each entry has a call button passing guest name, phone, and party size.
- **Integrations settings**: New "Retell Agent ID" field for configuring the default agent.
