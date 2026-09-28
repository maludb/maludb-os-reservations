# Plan — Adopt ZozoCal into the MaluDB Business OS

Goal: one install of ZozoCal serves every restaurant of a business, signed in by the Business OS kernel
(`app.<domain>` launcher → `reservations.<domain>`), with its people and restaurants managed in the kernel —
and, with `OS_ENABLED` off, exactly the application it is today. The survey, the gaps and the decisions are in
`docs/os-adoption.md`. (The previous plan, portable install, is complete and archived in
`tasks/portable-install-plan.md`.)

Branch `os-adoption`. Each step is its own commit. Nothing on a production server; zozocal.com is untouched.

## Step 0 — Owner decisions (docs/os-adoption.md §2) — taken 2026-09-28, each as recommended
- [x] Roles and their capabilities
- [x] How the roles reach the kernel (`app_roles` on the existing admin MCP)
- [x] Platform super-admin mapping
- [x] SaaS billing screens left as they are

## Step 1 — Security fixes (before sign-on; one commit each, all small) — done 2026-09-28, each proven on a scratch copy
- [x] S1 Invitation needs a one-time code: adding a user with no password shows the admin a code once (hashed in `users`); accept-invite requires it
- [x] S2 Adding an existing email to a restaurant never changes that person's password
- [x] S3 Retell signature check back on, with the restaurant's own key, on every Retell endpoint
- [x] S4 Twilio signature check on the SMS webhook (`sms_agents.webhook_auth_token` / the account secret)
- [x] S5 Inbound email webhook requires a shared secret
- [x] S6 No Retell key from another restaurant: drop the "any restaurant" fallback; callers pass their restaurant
- [x] S7 Remove `info.php`, `test-db.php`, `test-email.php`
- [x] S8 `downloads/retell-agent-import.php` behind `requireAdmin()` and the current restaurant
- [x] S9 Demo pages off unless `DEMO_ENABLED` is set
- [x] S10 Cron scripts refuse anything but the CLI
- [x] S11 CSRF on the restaurant switch
- [x] S12 Stop logging reset tokens, failed-login emails and raw webhook bodies

## Step 2 — The adapter (plugin `os-adopt/references/adapter.md`, in its order)
- [ ] Migration `docs/sql/os_adoption.sql`: `users.os_member_id`, `restaurants.os_scope_id`, `user_restaurants.source`, `sso_nonces`, `member_sessions`, `directory_sync_state`, the roles catalogue (`app_rights`, `app_roles`)
- [ ] `helpers/os_tokens.php` — the hand-off token and claims verifiers (the plugin's sign-on kit)
- [ ] `html/sso/index.php` and `html/sso/logout.php` — link or create the user, write the kernel's memberships, call `login_user()`, open the chosen restaurant
- [ ] `requireAuth()` — under `OS_ENABLED`: session listed, user linked and active, restaurant still held; otherwise the launcher
- [ ] Local sign-in, registration, Google, invitation, reset → the launcher; staff, platform-user and restaurant screens read-only ("Managed in the operating system"); logout → the launcher; API keys only for linked, active users
- [ ] `bin/os_directory_sync.php` + systemd timer — sites become restaurants (seeded like registration), access becomes memberships, deactivated people lose their sessions
- [ ] `app_roles` on `html/api/mcp/admin.php` (kernel token only)
- [ ] `maludb-os.json`, `/api/v1/health`, `deploy/` (vhost for `reservations.<domain>` + `127.0.0.1:8182`, timer, cron lines from the install path)

## Step 3 — Proofs (without a kernel, then on this server)
- [ ] Standalone: `OS_ENABLED` off → `scripts/check-install.php` and `scripts/api-smoke.php` pass, password login works
- [ ] On: every other way in lands on the launcher
- [ ] First sign-on links an existing user by email once; a second holder of that email is not linked
- [ ] A held restaurant opens; another restaurant's page is refused; the switcher lists only held ones
- [ ] Revocation via the fixture feed → next request goes to the launcher; that user's API key answers 401
- [ ] A new site becomes a seeded restaurant; a removed one goes inactive with its data
- [ ] Then the kernel's `bin/app_install.php plan` against this branch (the `apply` is the owner's)

## Review
(written when the work is done)
