# Complete Directory Structure

This document provides the complete directory structure for HTMX PHP applications with detailed explanations.

## Full Structure

```
/var/www/html/                 # Web root (publicly accessible)
├── index.php                  # Landing/redirect page
├── login.php                  # Login page
├── register.php               # Registration page
├── forgot-password.php        # Password reset request
├── reset-password.php         # Password reset with token
├── verify-email.php           # Email verification with token
├── logout.php                 # Logout handler
├── app.php                    # Main SPA entry point (protected)
├── terms.php                  # Terms of Service
├── privacy.php                # Privacy Policy
├── error.php                  # Generic error page
├── 404.php                    # Not found page
├── .htaccess                  # Apache URL rewriting
│
├── partials/                  # HTMX endpoint targets
│   ├── auth/                  # Authentication handlers
│   │   ├── login-form.php           # Login POST handler
│   │   ├── register-form.php        # Registration POST handler
│   │   ├── forgot-password-form.php # Password reset request handler
│   │   └── reset-password-form.php  # Password reset completion handler
│   │
│   ├── tasks/                 # Task management endpoints
│   │   ├── list.php                 # List all tasks
│   │   ├── create.php               # Create task (GET form, POST handler)
│   │   ├── edit.php                 # Edit task (GET form, POST handler)
│   │   ├── delete.php               # Delete task handler
│   │   ├── view.php                 # Single task view
│   │   └── search.php               # Task search/filter
│   │
│   ├── dashboard/             # Dashboard components
│   │   ├── index.php                # Main dashboard content
│   │   ├── stats.php                # Statistics widget
│   │   └── recent-activity.php      # Recent activity feed
│   │
│   ├── users/                 # User management (if applicable)
│   │   ├── profile.php              # User profile view/edit
│   │   ├── settings.php             # User settings
│   │   └── avatar-upload.php        # Avatar upload handler
│   │
│   └── components/            # Reusable components
│       ├── header.php               # Header component
│       ├── nav.php                  # Navigation component
│       └── notifications.php        # Notifications component
│
├── assets/                    # Static assets
│   ├── css/
│   │   ├── styles.css               # Main stylesheet
│   │   └── auth.css                 # Login/register styles
│   ├── js/
│   │   └── custom.js                # Custom JavaScript
│   └── images/
│       ├── logo.png
│       └── icons/
│
└── uploads/                   # User-uploaded files
    ├── avatars/               # User avatar images
    ├── attachments/           # Task attachments
    └── .htaccess              # Protect directory listings

/var/www/                      # Above web root (secure, not web-accessible)
├── config/
│   ├── database.php           # Database connection singleton
│   ├── config.php             # Application configuration
│   └── session.php            # Session configuration (optional)
│
├── models/
│   ├── Database.php           # Database connection class
│   ├── Model.php              # Base model class (optional)
│   ├── User.php               # User model
│   ├── Task.php               # Task model
│   └── PasswordReset.php      # Password reset token model
│
├── helpers/
│   ├── functions.php          # General helper functions
│   ├── validation.php         # Form validation functions
│   ├── sanitize.php           # Input sanitization
│   ├── email.php              # Email sending functions
│   ├── auth.php               # Authentication helpers
│   └── csrf.php               # CSRF protection functions
│
└── views/
    └── layouts/
        ├── main.php           # Main SPA layout wrapper
        ├── auth.php           # Auth pages layout
        └── public.php         # Public pages layout
```

## Directory Purposes

### /var/www/html/ (Web Root)

**Standalone Pages** - Full page loads, typically for initial page load or non-authenticated pages
- `index.php` - Entry point, redirects based on authentication
- `app.php` - Protected SPA shell that loads partials dynamically
- `login.php`, `register.php` - Public authentication pages
- `forgot-password.php`, `reset-password.php` - Password recovery flow
- `verify-email.php` - Email verification landing page

**Partials Directory** - AJAX endpoints that return HTML fragments
- Each partial can handle both GET (render form/content) and POST (process data)
- Organized by feature/module (tasks, dashboard, users, etc.)
- All partials should include authentication checks
- Return HTML fragments, not full page structures

**Assets Directory** - Static files served directly
- CSS, JavaScript, images
- Organized by type
- Can be cached aggressively

**Uploads Directory** - User-generated content
- Should be protected with .htaccess to prevent directory listing
- May need special handling for file types
- Consider separate subdirectories by feature

### /var/www/ (Above Web Root)

**Config Directory** - Application configuration
- Database credentials and connection settings
- Application-wide constants and settings
- Environment-specific configuration

**Models Directory** - Data layer
- Each model represents a database table
- Contains only database operations (CRUD)
- No HTML or presentation logic
- Returns arrays or objects, not formatted output

**Helpers Directory** - Utility functions
- Reusable functions used across the application
- Input validation and sanitization
- Email sending, file handling, etc.
- Should be stateless pure functions when possible

**Views Directory** - Layout templates
- Full page HTML wrappers (not fragments)
- Used by standalone pages, not partials
- Contains common HTML structure (doctype, head, body wrapper)

## Naming Conventions

### Files
- Use lowercase with hyphens for multi-word files: `reset-password.php`
- Use descriptive names that indicate purpose: `delete.php` not `del.php`
- Partials that handle both GET and POST: `create.php`, `edit.php`
- Partials that only handle one method can be more specific: `search.php`, `delete.php`

### Directories
- Lowercase, plural for collections: `tasks/`, `users/`
- Singular for singletons: `dashboard/`, `config/`
- Use feature-based organization in partials

## Security Considerations

### Public vs Private
- **Everything under /var/www/html/** is potentially web-accessible
- **Everything under /var/www/** (above html) is NOT web-accessible
- Store sensitive files (config, models, helpers) above web root
- Use .htaccess to further restrict access where needed

### File Permissions
```bash
# Directories
chmod 755 /var/www/html/
chmod 755 /var/www/html/partials/
chmod 755 /var/www/html/assets/
chmod 755 /var/www/html/uploads/

# PHP files (not directly executable)
chmod 644 /var/www/html/*.php
chmod 644 /var/www/html/partials/**/*.php

# Config files (more restrictive)
chmod 640 /var/www/config/*.php

# Upload directory (writable by web server)
chmod 775 /var/www/html/uploads/
```

### .htaccess Protection

```apache
# /var/www/html/uploads/.htaccess
Options -Indexes
<FilesMatch "\.(php|php3|php4|php5|phtml)$">
    Deny from all
</FilesMatch>
```

## URL Rewriting

### Clean URLs with .htaccess

All top-level pages should have clean URL rewrites:

```apache
# /var/www/html/.htaccess
RewriteEngine On

# Clean URLs for main pages (removes .php extension)
RewriteRule ^app$ app.php [L]
RewriteRule ^login$ login.php [L]
RewriteRule ^register$ register.php [L]
RewriteRule ^logout$ logout.php [L]
RewriteRule ^forgot-password$ forgot-password.php [L]
RewriteRule ^reset-password$ reset-password.php [L]
RewriteRule ^verify-email$ verify-email.php [L]
RewriteRule ^terms$ terms.php [L]
RewriteRule ^privacy$ privacy.php [L]

# Custom error pages
ErrorDocument 404 /404.php
ErrorDocument 500 /error.php

# Prevent direct access to .php files in partials (optional security layer)
# RewriteRule ^partials/.*\.php$ - [F,L]

# Allow direct access to assets, partials, uploads
RewriteCond %{REQUEST_URI} !^/(assets|partials|uploads)/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
```

### Usage Examples

With these rewrites, users can access:
- `/app` instead of `/app.php`
- `/login` instead of `/login.php`
- `/register` instead of `/register.php`
- `/terms` instead of `/terms.php`

The SPA URL stays clean as `/app` while partials are directly accessed:
- Partials: `/partials/tasks/list.php` (direct access)
- Main pages: `/app`, `/login` (rewritten)

This provides clean URLs for user-facing pages while keeping partials as straightforward endpoints.

## Expansion Guidelines

### Adding a New Feature Module

When adding a new feature (e.g., "projects"):

1. Create directory: `/var/www/html/partials/projects/`
2. Add standard CRUD partials:
   - `list.php` - Display all projects
   - `create.php` - Create form + handler
   - `edit.php` - Edit form + handler
   - `view.php` - Single project view
   - `delete.php` - Delete handler
3. Create model: `/var/www/models/Project.php`
4. Add navigation link in `app.php` or nav component
5. Add any feature-specific helpers if needed

### Adding a New Standalone Page

For new public pages (e.g., "about.php"):

1. Create file in web root: `/var/www/html/about.php`
2. Use appropriate layout from `/var/www/views/layouts/`
3. Add navigation link where appropriate
4. Update .htaccess if custom routing needed

### Adding New Components

For reusable UI components:

1. Create in `/var/www/html/partials/components/`
2. Make them include-able or HTMX-loadable
3. Keep them stateless when possible
4. Document required parameters
