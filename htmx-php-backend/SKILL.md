---
name: htmx-php-backend
description: PHP backend architecture and development patterns for HTMX applications using LAMP stack (PHP, MariaDB/MySQL, Apache). Use when working on an HTMX-based PHP project for creating new features, structuring endpoints and partials, implementing CRUD operations, adding authentication or security, organizing code, debugging server-side issues, or any development task involving the PHP backend with HTMX responses.
---

# HTMX PHP Backend Architecture

PHP backend architecture guide for HTMX applications following LAMP stack patterns with clean directory structure, partial-based endpoints, and proper separation of concerns.

## Project Structure

The standard directory structure for HTMX PHP applications:

```
/var/www/html/                 # Web root (public access)
├── index.php                  # Landing page
├── login.php                  # Login page
├── register.php               # Registration page
├── forgot-password.php        # Password reset request
├── reset-password.php         # Password reset form
├── verify-email.php           # Email verification
├── logout.php                 # Logout handler
├── app.php                    # Main SPA entry point (protected)
├── partials/                  # HTMX endpoint targets
├── assets/                    # CSS, JS, images
└── uploads/                   # User uploads

/var/www/                      # Above web root (secure)
├── config/                    # Configuration files
├── models/                    # Database models
├── helpers/                   # Utility functions
└── views/                     # Layout templates
```

See [references/directory-structure.md](references/directory-structure.md) for complete structure.

## Core Concepts

### Partials: Dual-Purpose Endpoints

Partials handle both GET requests (render HTML) and POST requests (process data):

```php
// partials/tasks/create.php
<?php
session_start();
require_once '../../helpers/auth.php';
check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $task = new Task();
    $result = $task->create($_POST);
    
    if ($result) {
        echo '<div class="alert alert-success">Task created!</div>';
    } else {
        echo '<div class="alert alert-danger">Failed to create task</div>';
    }
} else {
    // Return form HTML
    ?>
    <form hx-post="/partials/tasks/create.php" hx-target="#result">
        <input type="text" name="title" class="form-control" required>
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
    <?php
}
?>
```

### Authentication Pattern

Protect partials with session checks:

```php
// helpers/auth.php
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('HX-Redirect: /login.php');
        exit;
    }
}
```

### HTMX Response Headers

Control client behavior with headers:

```php
// Redirect after action
header('HX-Redirect: /app');

// Trigger client event
header('HX-Trigger: taskCreated');

// Trigger multiple events
header('HX-Trigger: {"taskCreated": {"id": 123}, "showNotification": true}');
```

### Model Pattern

Models handle database operations only:

```php
// models/Task.php
class Task {
    private $db;
    
    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO tasks (user_id, title, created_at) VALUES (?, ?, NOW())"
        );
        return $stmt->execute([$data['user_id'], $data['title']]);
    }
    
    public function findByUser($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
}
```

## Common Patterns

### List Partial

```php
// partials/tasks/list.php
<?php
session_start();
require_once '../../helpers/auth.php';
check_auth();

$task = new Task();
$tasks = $task->findByUser($_SESSION['user_id']);
?>

<?php foreach ($tasks as $task): ?>
    <div class="card mb-2" id="task-<?= $task['id'] ?>">
        <div class="card-body">
            <h5><?= htmlspecialchars($task['title']) ?></h5>
            <button hx-delete="/partials/tasks/delete.php?id=<?= $task['id'] ?>" 
                    hx-target="#task-<?= $task['id'] ?>"
                    hx-swap="outerHTML"
                    hx-confirm="Delete this task?">
                Delete
            </button>
        </div>
    </div>
<?php endforeach; ?>
```

### Delete Handler

```php
// partials/tasks/delete.php
<?php
session_start();
require_once '../../helpers/auth.php';
check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $task = new Task();
    $result = $task->delete($_GET['id'], $_SESSION['user_id']);
    
    if ($result) {
        header('HX-Trigger: taskDeleted');
        echo ''; // Empty response removes element
    }
}
?>
```

### Search Pattern

```php
// partials/tasks/search.php
<?php
session_start();
require_once '../../helpers/auth.php';
check_auth();

$query = $_GET['q'] ?? '';
$task = new Task();
$tasks = $task->search($_SESSION['user_id'], $query);

foreach ($tasks as $task) {
    echo "<div class='card mb-2'>";
    echo "<div class='card-body'>" . htmlspecialchars($task['title']) . "</div>";
    echo "</div>";
}
?>
```

Triggered by:

```html
<input type="search" 
       hx-get="/partials/tasks/search.php" 
       hx-trigger="keyup changed delay:500ms" 
       hx-target="#results">
```

## Security

### Input Validation

```php
// helpers/validation.php
function validate_task($data) {
    $errors = [];
    if (empty($data['title'])) {
        $errors[] = 'Title is required';
    }
    if (strlen($data['title']) > 255) {
        $errors[] = 'Title too long';
    }
    return $errors;
}
```

### CSRF Protection

```php
// helpers/csrf.php
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}
```

### SQL Injection Prevention

Always use prepared statements:

```php
// GOOD
$stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = ?");
$stmt->execute([$user_id]);

// BAD - Never do this
$query = "SELECT * FROM tasks WHERE user_id = $user_id";
```

## Configuration

### Database Connection

```php
// config/database.php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = new PDO(
            "mysql:host=localhost;dbname=tasktracker;charset=utf8mb4",
            "username",
            "password",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
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

### URL Rewriting

```apache
# /var/www/html/.htaccess
RewriteEngine On

# Clean URLs for main pages
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

# Allow direct access to assets, partials, uploads
RewriteCond %{REQUEST_URI} !^/(assets|partials|uploads)/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
```

## Debugging

Common issues and solutions:

**Session not persisting:** Call `session_start()` at the beginning of every partial

**HTMX request not working:** Check browser network tab for errors, verify endpoint URLs

**Partial returns full page:** Ensure partial only outputs HTML fragment, not full structure

See [references/code-templates.md](references/code-templates.md) for more templates.
