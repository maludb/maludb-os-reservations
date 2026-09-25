# ZozoCal Agent API (REST + MCP)

Everything a restaurant user can do in the web app is available to AI agents and scripts
through two surfaces built from **one operation registry**, so they always match:

| Surface | URL | For |
|---|---|---|
| Admin MCP server | `/api/mcp/admin.php` | Claude and other MCP clients (Streamable HTTP, JSON-RPC 2.0) |
| REST API | `/api/v1/index.php/<path>` | Scripts and HTTP-tool agents |
| OpenAPI 3 | `/api/v1/index.php/openapi.json` | Discovery / client generation (no auth) |

The guest-facing booking tools used by the voice, SMS and email agents are a separate MCP
server, `/api/mcp/server.php`, authenticated with the restaurant's MCP key
(Settings → Integrations). It is not covered here.

## Authentication

1. An admin opens **Settings → API Keys**, names a key and picks an expiry. The key
   (`zc_…`) is shown once.
2. Send it on every request: `Authorization: Bearer zc_…`
3. The key acts as the user who created it, in that restaurant, with that user's role
   (`user`/staff < `manager` < `admin`). Removing the user from the restaurant, deactivating
   the user, or revoking the key stops it immediately.
4. Platform super-admin keys may act on any restaurant by adding `X-Restaurant-Id: <id>`,
   and can call the `platform_*` operations.

`POST /api/v1/auth.php?action=login` (email, password, optional `restaurant_id`) still issues a
90-day token for apps that sign in with a password.

API keys cannot create other API keys; key management stays in the web app.

## Operations

Each operation has one name used everywhere (MCP tool name = OpenAPI `operationId`), a
minimum role, a JSON schema for its input, and a REST method + path.

- `GET /api/v1/index.php/operations`: what this key may call.
- MCP `tools/list`: the same list, as tools.
- `openapi.json`: full schemas for every operation, with `x-required-role`.

Areas: todos, reservations & availability, tables, sections, waitlist, guests, events,
settings (profile, hours, special dates, turn times, meal percentages, notifications, email
templates, integrations), team users, phone numbers, voice/text/email agents and Retell,
dashboard, reports, message logs, activity log, billing (read-only), platform administration.

Billing is read-only for agents: paying invoices and adding prepayments need a person.

## Conventions

- Dates are `YYYY-MM-DD`, times `HH:MM` (24-hour), in the restaurant's time zone.
- `PATCH` changes only the fields sent.
- List operations take `limit` (default 50, max 200) and `offset`.
- Secrets (Retell/OpenAI/Twilio keys and the like) can be set but are only ever returned masked.

### REST responses

```json
{ "success": true,  "data": { ... } }
{ "success": false, "error": "Human-readable message", "code": "VALIDATION_ERROR" }
```

| HTTP | code | Meaning |
|---|---|---|
| 401 | `AUTH_REQUIRED` | Missing, invalid, expired or revoked key |
| 403 | `FORBIDDEN` | Role too low for this operation |
| 404 | `NOT_FOUND` | No such record in this restaurant / no such route |
| 409 | `INVALID_TRANSITION` | e.g. seating a cancelled reservation |
| 422 | `VALIDATION_ERROR` | Bad or missing input |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `SERVER_ERROR` | Unexpected failure (logged) |

### MCP results

`tools/call` returns the same `data` object as JSON text. On failure `isError` is `true` and the
text is `{"error": "...", "code": "..."}`.

## Examples

```bash
KEY=zc_...
curl -H "Authorization: Bearer $KEY" "https://zozocal.com/api/v1/index.php/availability?date=2026-10-02&party_size=4"

curl -X POST -H "Authorization: Bearer $KEY" https://zozocal.com/api/v1/index.php/reservations \
  -d '{"reservation_date":"2026-10-02","reservation_time":"19:00","party_size":4,"first_name":"Ada","last_name":"Lovelace","phone":"5551234567"}'

curl -X POST -H "Authorization: Bearer $KEY" https://zozocal.com/api/v1/index.php/reservations/42/status -d '{"status":"seated"}'

curl -X POST -H "Authorization: Bearer $KEY" https://zozocal.com/api/mcp/admin.php \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"reservations_list","arguments":{"date_from":"2026-10-02"}}}'
```

### Connecting Claude

Claude Code:

```bash
claude mcp add --transport http zozocal https://zozocal.com/api/mcp/admin.php \
  --header "Authorization: Bearer zc_..."
```

Claude Messages API (MCP connector): pass `{"type": "url", "url": "https://zozocal.com/api/mcp/admin.php", "name": "zozocal", "authorization_token": "zc_..."}` in `mcp_servers`.

## Audit

Every write through REST or MCP adds an `activity_log` row (`action = api_call`) naming the
channel, the operation and the key, with the input (secrets removed). The underlying change is
logged as usual too. Agents can review this with `activity_log_list`.

## Adding a feature

1. Put the logic in `helpers/services/<area>.php`, and have the HTMX partial call it.
2. Declare the operation with `api_op()` in `helpers/api/ops/<area>.php`.
3. Map the partial to the operation in `scripts/api-parity.php`.
4. Run `php scripts/api-smoke.php`: it fails if a POST handler has no operation.
