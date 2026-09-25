# Technology Stack - HTMX PHP Application Template

## Overview

This template uses a traditional LAMP stack enhanced with modern HTMX interactions and a custom Bootstrap-based design system. The stack prioritizes simplicity, security, and developer productivity.

## Core Technologies

### Backend Stack

#### PHP 8.2+
**Purpose:** Server-side application logic and HTML generation

**Key Features Used:**
- Type declarations
- Null coalescing operator (`??`)
- Arrow functions
- Named arguments
- Constructor property promotion

**Configuration:**
```ini
; Recommended php.ini settings
memory_limit = 128M
max_execution_time = 30
upload_max_filesize = 5M
post_max_size = 6M
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
display_errors = 0
log_errors = 1
```

**Extensions Required:**
- PDO
- pdo_pgsql
- mbstring
- session
- json

#### PostgreSQL 17
**Purpose:** Relational database for application data (schema in `docs/sql/pg_schema.sql`)

**Key Features:**
- Foreign key constraints
- ACID transactions
- `updated_at` maintained by the `update_updated_at()` trigger function
- UTF-8 encoding

**Connection:**
```php
PDO (pgsql driver) with prepared statements — see config/database.php
```

#### Apache 2.4+
**Purpose:** Web server

**Required Modules:**
- mod_rewrite (URL rewriting)
- mod_headers (HTTP headers)
- mod_expires (cache control)
- mod_deflate (compression)

**Configuration:**
```apache
# .htaccess essentials
RewriteEngine On
AllowOverride All
```

### Frontend Stack

#### HTMX 2.0.8
**Purpose:** Dynamic HTML interactions without JavaScript

**CDN:**
```html
<script src="https://unpkg.com/htmx.org@2.0.8"></script>
```

**Core Features Used:**
- `hx-get`, `hx-post`, `hx-put`, `hx-delete` - HTTP requests
- `hx-target` - Response destination
- `hx-swap` - Swap strategy
- `hx-trigger` - Event triggers
- `hx-indicator` - Loading states
- `hx-push-url` - Browser history
- `hx-confirm` - User confirmation
- `hx-sync` - Request coordination

**Response Headers Used:**
- `HX-Redirect` - Client-side redirect
- `HX-Trigger` - Trigger client events
- `HX-Refresh` - Refresh page
- `HX-Retarget` - Change target
- `HX-Reswap` - Change swap method

#### Bootstrap 5.3.3
**Purpose:** UI component framework and responsive grid

**CDN:**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
```

**Components Used:**
- Grid system (responsive layout)
- Cards (content containers)
- Forms (input components)
- Buttons (actions)
- Navbar (navigation)
- Modals (dialogs)
- Alerts (notifications)
- Badges (status indicators)
- Tables (data display)
- Spinners (loading indicators)

**JavaScript Components:**
- Modal
- Collapse
- Dropdown
- Tooltip (optional)
- Popover (optional)
- Toast (optional)

#### Bootstrap Icons 1.11+
**Purpose:** Icon library

**CDN:**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css" rel="stylesheet">
```

**Common Icons Used:**
- Navigation: `bi-house`, `bi-list-check`, `bi-gear`
- Actions: `bi-plus`, `bi-pencil`, `bi-trash`, `bi-check`
- UI: `bi-chevron-down`, `bi-x`, `bi-search`
- Status: `bi-check-circle`, `bi-exclamation-triangle`

#### Alpine.js 3.x (Optional)
**Purpose:** Lightweight client-side reactivity

**CDN:**
```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

**Use Cases:**
- Toggle visibility
- Form field management
- Character counters
- Dropdown menus
- Tabs and accordions
- Local state management

**Integration with HTMX:**
```html
<div x-data="{ editing: false }">
    <div x-show="!editing" @click="editing = true">View mode</div>
    <form x-show="editing" 
          hx-post="/save.php"
          @htmx:after-request="editing = false">
        Edit mode
    </form>
</div>
```

### Custom Design System

#### CSS Variables
**File:** `assets/css/custom.css`

**Color System:**
```css
:root {
  /* Primary Colors */
  --primary-color: #4267cd;
  --primary-light: #e3eaff;
  --primary-dark: #3054b7;
  
  /* Status Colors */
  --success-color: #26ba4f;
  --warning-color: #ffae1f;
  --danger-color: #f87957;
  --info-color: #3688fa;
  
  /* Shadows */
  --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
  --box-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.18);
  
  /* Borders */
  --border-radius: 10px;
  --border-radius-sm: 6px;
}
```

**Component Styles:**
- Cards with hover effects
- Gradient buttons
- Enhanced forms
- Fixed sidebar navigation
- Gradient navbar
- Custom badges and alerts

## Development Stack

### Version Control

#### Git
**Purpose:** Source code management

**Workflow:**
1. Initialize repository
2. Commit after each feature
3. Push to remote regularly
4. Meaningful commit messages

**Essential Files:**
```gitignore
# .gitignore
/var/www/config/database.php
/var/www/html/uploads/*
*.log
.DS_Store
```

### Database Management

#### PDO (PHP Data Objects)
**Purpose:** Database abstraction layer

**Features:**
- Prepared statements (SQL injection prevention)
- Multiple database support
- Error handling with exceptions
- Transaction support

**Connection Pattern:**
```php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = new PDO(
            "pgsql:host=127.0.0.1;port=5432;dbname=zozocal",
            "username",
            "password",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Security Stack

#### Session Management
**Mechanism:** PHP native sessions

**Configuration:**
```php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
```

#### Password Hashing
**Method:** `password_hash()` with PASSWORD_DEFAULT

```php
// Hashing
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verification
$valid = password_verify($password, $hash);
```

#### CSRF Protection
**Implementation:** Token-based

```php
// Generate
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Verify
hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
```

#### Input Validation
**Methods:**
- `filter_var()` for email, URL validation
- `htmlspecialchars()` for XSS prevention
- Type checking and length validation
- Whitelist validation for restricted fields

### File Structure Standards

#### Naming Conventions
- **Files:** lowercase-with-hyphens.php
- **Classes:** PascalCase
- **Functions:** camelCase or snake_case
- **Variables:** camelCase or snake_case
- **Constants:** UPPER_SNAKE_CASE

#### Code Organization
```
Partials:    /var/www/html/partials/[module]/[action].php
Models:      /var/www/models/[ModelName].php
Helpers:     /var/www/helpers/[purpose].php
Config:      /var/www/config/[name].php
Assets:      /var/www/html/assets/[type]/[file]
```

## Architecture Patterns

### MVC-Like Pattern
**Not strict MVC, but separation of concerns:**

- **Models** (M): Database operations
- **Partials** (V+C): View generation + business logic
- **Helpers**: Shared utilities

### Singleton Pattern
**Used for:** Database connections

**Benefits:**
- Single connection per request
- Resource efficiency
- Consistent configuration

### Dual-Purpose Partials
**Pattern:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    // Return HTML response
} else {
    // Render form
    // Return HTML form
}
```

### Service Layer and Agent API
- Business logic for writes lives in `helpers/services/<area>.php` (plain functions, explicit
  restaurant/user IDs, `ServiceError` for failures). HTMX partials render HTML around them.
- `helpers/api/registry.php` declares each operation once (`api_op()`); the REST router
  (`html/api/v1/index.php`, PATH_INFO routing, no mod_rewrite needed), the admin MCP server
  (`html/api/mcp/admin.php`) and the OpenAPI document are generated from it.
- Bearer API keys (`api_tokens`), role checks per operation, every write audited in `activity_log`.
- `php scripts/api-smoke.php` checks UI/API parity and both surfaces. See `docs/api.md`.

### Progressive Enhancement
**Strategy:**
- Base functionality works without JavaScript
- HTMX enhances experience
- Graceful degradation where possible

## Performance Considerations

### Caching Strategy
**Apache:**
```apache
# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

**PHP:**
- OpCache enabled in production
- Session storage optimized
- Database query optimization

### Asset Loading
- CDN for Bootstrap and HTMX
- Local fallbacks optional
- Minified CSS/JS in production
- Gzip compression enabled

### Database Optimization
- Proper indexing
- Use of JOINs to avoid N+1 queries
- Prepared statement caching
- Connection pooling via singleton

## Development Tools (Optional)

### Code Quality
- PHPStan/Psalm for static analysis
- PHP_CodeSniffer for style checking
- Manual code review

### Debugging
- Xdebug for development
- Error logging for production
- Browser DevTools for frontend
- HTMX debugging extension

### Testing
- Manual testing primary method
- Browser compatibility testing
- Mobile responsiveness testing
- Security testing (manual)

## Browser Support

### Target Browsers
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile Safari (latest)
- Chrome Mobile (latest)

### Progressive Enhancement
- Core functionality works in all browsers
- Enhanced features for modern browsers
- Fallbacks for older browsers where needed

## Deployment Stack

### Production Requirements
- PHP 8.2+ with required extensions
- PostgreSQL 17
- Apache 2.4+ with mod_rewrite
- HTTPS/SSL certificate
- Sufficient disk space for uploads
- Regular backups

### Environment Configuration
```php
// config/config.php
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('DEBUG_MODE', APP_ENV === 'development');
```

## Version Information

### Minimum Versions
- PHP: 8.2
- PostgreSQL: 17
- Apache: 2.4
- Bootstrap: 5.3
- HTMX: 2.0

### Recommended Versions
- PHP: 8.3 (latest stable)
- PostgreSQL: 17 (latest stable)
- Apache: 2.4 (latest stable)
- Bootstrap: 5.3.3
- HTMX: 2.0.8

## Technology Decisions

### Why LAMP Stack?
- Proven, stable technology
- Wide hosting support
- Extensive documentation
- Large developer community
- Cost-effective

### Why HTMX?
- Server-driven architecture
- Minimal JavaScript
- Progressive enhancement
- Simple to learn and use
- Reduces frontend complexity

### Why Bootstrap?
- Rapid UI development
- Responsive by default
- Extensive components
- Well-documented
- Large ecosystem

### Why Not Modern Frameworks?
- Requirement: Traditional LAMP stack
- Simplicity over complexity
- Easier maintenance
- Lower learning curve
- Direct control over code

## References

- PHP Documentation: https://www.php.net/docs.php
- HTMX Documentation: https://htmx.org/docs/
- Bootstrap Documentation: https://getbootstrap.com/docs/5.3/
- PostgreSQL Documentation: https://www.postgresql.org/docs/17/
- Apache Documentation: https://httpd.apache.org/docs/
