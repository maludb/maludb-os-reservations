# Role-Based Security Update — Plan

## Role Mapping (old → new)

| Old Role | New Role | Access |
|----------|----------|--------|
| `is_platform_admin` | `super-admin` | Platform management, view/assist any restaurant |
| `owner` | `admin` | All restaurant setup, invite users in admin/manager/user roles |
| `manager` | `manager` | All restaurant setup |
| `host` | `user` | View/make/change/seat reservations, clear tables |

## Sidebar Access Control

| Section | Roles |
|---------|-------|
| RESERVATIONS (Dashboard, Reservations, Floor Plan, Waitlist) | all roles |
| GUESTS (Guest Directory) | all roles |
| MANAGEMENT (Table Setup, Sections, Hours, Turn Times, Special Dates, Reports) | admin, manager |
| SETTINGS (Profile, Rules, Notifications, Staff, Integrations) | admin only |
| PLATFORM (Manage Restaurants) | super-admin only |

## Changes

- [ ] 1. **helpers/auth.php** — Rename role functions:
  - `isOwner()` → `isAdmin()` (checks role === 'admin')
  - `isManager()` → `isManager()` (checks admin OR manager)
  - `isHost()` → `isStaff()` (checks admin/manager/user — any role)
  - `isPlatformAdmin()` → `isSuperAdmin()` (checks is_platform_admin)
  - `requireOwner()` → `requireAdmin()`
  - `requireManager()` → `requireManager()` (same name, updated logic)
  - `requirePlatformAdmin()` → `requireSuperAdmin()`
  - Keep old function names as aliases for backward compatibility during transition

- [ ] 2. **html/app.php** — Update sidebar role checks

- [ ] 3. **html/partials/settings/user-form.php** — Update role dropdown (admin, manager, user)

- [ ] 4. **html/partials/settings/save-user.php** — Update role validation

- [ ] 5. **html/partials/settings/users.php** — Update role badge colors/labels

- [ ] 6. **html/partials/platform/save-restaurant.php** — Change default role from 'owner' to 'admin'

- [ ] 7. **All partials using requireOwner()** — Change to requireAdmin()

- [ ] 8. **All partials using requireManager()** — Keep (logic updated in auth.php)

- [ ] 9. **All partials using requirePlatformAdmin()** — Change to requireSuperAdmin()
