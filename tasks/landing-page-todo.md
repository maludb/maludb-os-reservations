# Landing Page — Todo

Create a marketing landing page for zozocal.com inspired by OpenTable's reservation management page.

## Plan

- [x] 1. Update `html/index.php` — replace the login redirect with a full landing page
- [x] 2. Landing page sections (inspired by OpenTable structure):
  - **Navbar** — ZozoCal brand logo, nav links (Features, How It Works), Login button, "Get Started" CTA
  - **Hero** — Bold headline, subtitle, two CTAs, mock reservation dashboard card with floating badges
  - **Stats/Social Proof Bar** — 24/7 Online Bookings, -40% No-Shows, +25% Table Utilization, 5-Star Experience
  - **Features Section** — 6 icon cards: Online Reservations, Floor Plan, Guest CRM, Waitlist, Notifications, Reports
  - **How It Works** — 3 steps: Set Up, Accept Reservations, Manage & Grow
  - **Benefits** — Two blocks with check lists and visual mock-ups (guest booking + team dashboard)
  - **CTA Section** — Final gradient call to action
  - **Footer** — Links, copyright
- [x] 3. Use Bootstrap 5 + custom CSS (same gradient theme as login page), fully responsive
- [x] 4. Link "Get Started" and "Login" to `/login.php`
- [x] 5. All divs get unique IDs per CLAUDE.md
- [x] 6. Log changes to `docs/activity.md`
- [ ] 7. Commit and push

## Review
- Landing page replaces the old login redirect in `html/index.php`
- Logged-in users still auto-redirect to `/app.php`
- Self-contained page with own CSS/JS, not inside app.php
- Responsive design with mobile breakpoints at 991px and 767px
- Uses the purple/blue gradient brand (#667eea to #764ba2) consistently
- All divs have unique IDs prefixed with `landing-`
