# Retell AI Voice Agent — Setup Guide

## Overview

This guide covers connecting Retell AI voice agents to the restaurant reservation system. Two integration methods are available:

1. **Custom Functions** — Individual HTTP endpoints for each action
2. **MCP Server** — Single endpoint exposing all tools via Model Context Protocol

Both use the same backend logic and produce the same results.

---

## Prerequisites

1. A Retell AI account (https://www.retellai.com)
2. Your restaurant's **slug** (found in Settings > Restaurant Profile or Settings > Integrations)
3. Your server's public URL (e.g., `https://yourdomain.com`)

---

## Option A: Retell Custom Functions

### Step 1: Set API Key

1. Go to **Settings > Integrations** in the restaurant admin
2. Enter your Retell API Key (from Retell dashboard > Settings > API Keys)
3. Save

### Step 2: Configure Functions in Retell

In your Retell AI agent, click **"+ Add"** > **"Custom Function"** and create each function below:

---

#### Function 1: `check_availability`

- **Name:** `check_availability`
- **Description:** Check available reservation time slots for a specific date and party size
- **URL:** `https://yourdomain.com/api/retell/check-availability.php`
- **Method:** POST
- **Speech while function runs:** "Let me check what's available for you."
- **Parameters:**

```json
{
  "type": "object",
  "required": ["restaurant_slug", "date", "party_size"],
  "properties": {
    "restaurant_slug": {
      "type": "string",
      "description": "The restaurant identifier. Always use: your-slug-here"
    },
    "date": {
      "type": "string",
      "description": "The reservation date in YYYY-MM-DD format"
    },
    "party_size": {
      "type": "integer",
      "description": "Number of guests dining"
    }
  }
}
```

---

#### Function 2: `make_reservation`

- **Name:** `make_reservation`
- **Description:** Book a new restaurant reservation and get a confirmation code
- **URL:** `https://yourdomain.com/api/retell/make-reservation.php`
- **Method:** POST
- **Speech while function runs:** "I'm booking that for you now."
- **Parameters:**

```json
{
  "type": "object",
  "required": ["restaurant_slug", "date", "time", "party_size", "guest_name", "guest_phone"],
  "properties": {
    "restaurant_slug": {
      "type": "string",
      "description": "The restaurant identifier. Always use: your-slug-here"
    },
    "date": {
      "type": "string",
      "description": "Reservation date in YYYY-MM-DD format"
    },
    "time": {
      "type": "string",
      "description": "Reservation time in HH:MM format (24-hour, e.g. 19:00)"
    },
    "party_size": {
      "type": "integer",
      "description": "Number of guests"
    },
    "guest_name": {
      "type": "string",
      "description": "Guest's full name (first and last)"
    },
    "guest_phone": {
      "type": "string",
      "description": "Guest's phone number"
    },
    "guest_email": {
      "type": "string",
      "description": "Guest's email address for confirmation (optional)"
    },
    "special_requests": {
      "type": "string",
      "description": "Any special requests like allergies, celebrations, seating preferences (optional)"
    }
  }
}
```

---

#### Function 3: `lookup_reservation`

- **Name:** `lookup_reservation`
- **Description:** Look up an existing reservation by confirmation code or phone number
- **URL:** `https://yourdomain.com/api/retell/lookup-reservation.php`
- **Method:** POST
- **Speech while function runs:** "Let me look that up for you."
- **Parameters:**

```json
{
  "type": "object",
  "required": ["restaurant_slug"],
  "properties": {
    "restaurant_slug": {
      "type": "string",
      "description": "The restaurant identifier. Always use: your-slug-here"
    },
    "confirmation_code": {
      "type": "string",
      "description": "The 10-character reservation confirmation code"
    },
    "guest_phone": {
      "type": "string",
      "description": "Guest phone number to search by (use if no confirmation code)"
    }
  }
}
```

---

#### Function 4: `cancel_reservation`

- **Name:** `cancel_reservation`
- **Description:** Cancel an existing reservation
- **URL:** `https://yourdomain.com/api/retell/cancel-reservation.php`
- **Method:** POST
- **Speech while function runs:** "I'm processing your cancellation."
- **Parameters:**

```json
{
  "type": "object",
  "required": ["restaurant_slug", "confirmation_code"],
  "properties": {
    "restaurant_slug": {
      "type": "string",
      "description": "The restaurant identifier. Always use: your-slug-here"
    },
    "confirmation_code": {
      "type": "string",
      "description": "The confirmation code of the reservation to cancel"
    }
  }
}
```

---

#### Function 5: `confirm_reservation`

- **Name:** `confirm_reservation`
- **Description:** Confirm a pending reservation
- **URL:** `https://yourdomain.com/api/retell/confirm-reservation.php`
- **Method:** POST
- **Speech while function runs:** "I'm confirming your reservation now."
- **Parameters:**

```json
{
  "type": "object",
  "required": ["restaurant_slug", "confirmation_code"],
  "properties": {
    "restaurant_slug": {
      "type": "string",
      "description": "The restaurant identifier. Always use: your-slug-here"
    },
    "confirmation_code": {
      "type": "string",
      "description": "The confirmation code of the reservation to confirm"
    }
  }
}
```

---

## Option B: MCP Server

### Step 1: Set MCP API Key

1. Go to **Settings > Integrations** in the restaurant admin
2. Click "Generate" next to the MCP API Key field (or enter your own)
3. Save

### Step 2: Connect in Retell

1. In your Retell AI agent, click **"+ Add"** > **"MCP"**
2. Enter the server URL: `https://yourdomain.com/api/mcp/server.php`
3. Add custom header: `Authorization: Bearer <your-mcp-api-key>`
4. Click Connect — you should see 5 tools listed
5. Select which tools to enable for the agent

---

## Agent Prompt

Use this as your Retell agent's system prompt (replace placeholders):

```
You are a friendly reservation assistant for [Restaurant Name]. You help callers book, check on, confirm, and cancel reservations.

IMPORTANT: Always use restaurant_slug "[your-slug]" for all function calls.

## Making a Reservation
1. Ask for the date they'd like to dine and how many guests
2. Call check_availability to find open times
3. Offer 3-5 available time options and ask which they prefer
4. Ask for their full name
5. For phone, use the caller's number from the call
6. Optionally ask for email for a confirmation email
7. Ask about special requests (allergies, celebrations, seating)
8. Call make_reservation to book it
9. Read the confirmation code letter by letter (e.g., "A as in Alpha, B as in Bravo...")

## Checking a Reservation
1. Ask for their confirmation code or phone number
2. Call lookup_reservation
3. Read back the date, time, party size, and status

## Cancelling
1. First look up the reservation
2. Confirm they want to cancel
3. Call cancel_reservation
4. Confirm cancellation

## Confirming a Pending Reservation
1. Look up the reservation
2. If status is "pending", call confirm_reservation
3. Read back the confirmed details

## Guidelines
- Be warm, professional, and concise
- Spell out confirmation codes using the NATO phonetic alphabet
- If no availability, suggest nearby dates
- For parties larger than the max, suggest calling the restaurant directly
- Always confirm details before making a reservation
```

---

## Testing

1. Use the **Test Connection** button in Settings > Integrations to verify the API works
2. In Retell dashboard, use the test call feature to simulate a conversation
3. Check the restaurant's Activity Log for API-created reservations (source: "phone")
