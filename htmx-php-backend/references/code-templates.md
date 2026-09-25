# Code Templates and Patterns

Common code templates for HTMX PHP applications.

## Partial Templates

### Basic Dual-Purpose Partial

Template for partials that handle both GET (display) and POST (process):

```php
<?php
session_start();
require_once '../../config/config.php';
require_once '../../models/ModelName.php';
require_once '../../helpers/auth.php';

// Authentication check
check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST: Process form submission
    $model = new ModelName();
    
    // Validate input
    $errors = validate_input($_POST);
    if (!empty($errors)) {
        http_response_code(400);
        echo '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        exit;
    }
    
    // Process data
    $result = $model->create($_POST);
    
    if ($result) {
        header('HX-Trigger: dataCreated');
        echo '<div class="alert alert-success">Created successfully!</div>';
    } else {
        http_response_code(500);
        echo '<div class="alert alert-danger">Failed to create</div>';
    }
} else {
    // GET: Display form
    ?>
    <form hx-post="/partials/module/action.php" 
          hx-target="#result-container">
        <div class="mb-3">
            <label for="field" class="form-label">Label</label>
            <input type="text" name="field" id="field" 
                   class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
    <?php
}
?>
```

### List with Pagination

```php
<?php
session_start();
require_once '../../helpers/auth.php';
require_once '../../models/Task.php';

check_auth();

$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$task = new Task();
$tasks = $task->findByUser($_SESSION['user_id'], $per_page, $offset);
$total = $task->countByUser($_SESSION['user_id']);
$total_pages = ceil($total / $per_page);
?>

<div id="task-list">
    <?php if (empty($tasks)): ?>
        <div class="alert alert-info">No tasks found</div>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <div class="card mb-2" id="task-<?= $task['id'] ?>">
                <div class="card-body">
                    <h5><?= htmlspecialchars($task['title']) ?></h5>
                    <p><?= htmlspecialchars($task['description']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === (int)$page ? 'active' : '' ?>">
                        <a class="page-link" href="#" 
                           hx-get="/partials/tasks/list.php?page=<?= $i ?>"
                           hx-target="#task-list">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
```

### Infinite Scroll List

```php
<?php
session_start();
require_once '../../helpers/auth.php';
require_once '../../models/Task.php';

check_auth();

$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$task = new Task();
$tasks = $task->findByUser($_SESSION['user_id'], $per_page, $offset);
$has_more = count($tasks) === $per_page;
?>

<?php foreach ($tasks as $task): ?>
    <div class="card mb-2">
        <div class="card-body">
            <h5><?= htmlspecialchars($task['title']) ?></h5>
            <p><?= htmlspecialchars($task['description']) ?></p>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($has_more): ?>
    <div hx-get="/partials/tasks/list.php?page=<?= $page + 1 ?>"
         hx-trigger="revealed"
         hx-swap="outerHTML">
        Loading more...
    </div>
<?php endif; ?>
```

### Modal Form Pattern

```php
<?php
session_start();
require_once '../../helpers/auth.php';
require_once '../../models/Task.php';

check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $task = new Task();
    $result = $task->create($_POST + ['user_id' => $_SESSION['user_id']]);
    
    if ($result) {
        // Close modal and refresh list
        header('HX-Trigger: {"closeModal": true, "refreshList": true}');
        echo '';
    } else {
        echo '<div class="alert alert-danger">Failed to create task</div>';
    }
} else {
    // Display modal form
    ?>
    <div class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Task</h5>
                    <button type="button" class="btn-close" 
                            onclick="this.closest('.modal').remove()"></button>
                </div>
                <div class="modal-body">
                    <form id="task-form" 
                          hx-post="/partials/tasks/create.php"
                          hx-target="#modal-result">
                        <div id="modal-result"></div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" id="title" 
                                   class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" 
                                      class="form-control" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" 
                            onclick="this.closest('.modal').remove()">
                        Cancel
                    </button>
                    <button type="submit" form="task-form" class="btn btn-primary">
                        Create
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php
}
?>
```

### File Upload Handler

```php
<?php
session_start();
require_once '../../config/config.php';
require_once '../../helpers/auth.php';
require_once '../../models/Attachment.php';

check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo '<div class="alert alert-danger">Invalid file type</div>';
        exit;
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        http_response_code(400);
        echo '<div class="alert alert-danger">File too large</div>';
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save to database
        $attachment = new Attachment();
        $attachment->create([
            'user_id' => $_SESSION['user_id'],
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        ]);
        
        header('HX-Trigger: fileUploaded');
        echo '<div class="alert alert-success">File uploaded successfully</div>';
    } else {
        http_response_code(500);
        echo '<div class="alert alert-danger">Upload failed</div>';
    }
}
?>
```

## Model Templates

### Basic Model Class

```php
<?php
require_once 'Database.php';

class ModelName {
    private $db;
    private $table = 'table_name';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (field1, field2, created_at) 
             VALUES (?, ?, NOW())"
        );
        return $stmt->execute([
            $data['field1'],
            $data['field2']
        ]);
    }
    
    public function findById($id, $user_id = null) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $params = [$id];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function findAll($user_id = null, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if ($user_id !== null) {
            $sql .= " WHERE user_id = ?";
            $params[] = $user_id;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function update($id, $data, $user_id = null) {
        $sql = "UPDATE {$this->table} SET field1 = ?, field2 = ?, 
                updated_at = NOW() WHERE id = ?";
        $params = [
            $data['field1'],
            $data['field2'],
            $id
        ];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id, $user_id = null) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $params = [$id];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function search($user_id, $query) {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = ? AND (field1 LIKE ? OR field2 LIKE ?)
             ORDER BY created_at DESC"
        );
        $search = "%{$query}%";
        $stmt->execute([$user_id, $search, $search]);
        return $stmt->fetchAll();
    }
    
    public function count($user_id = null) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if ($user_id !== null) {
            $sql .= " WHERE user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
?>
```

### User Model with Authentication

```php
<?php
require_once 'Database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, password_hash, name, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        return $stmt->execute([
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['name']
        ]);
    }
    
    public function authenticate($email, $password) {
        $stmt = $this->db->prepare(
            "SELECT id, email, password_hash, name FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ];
        }
        
        return false;
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare(
            "SELECT id, email, name FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function updatePassword($user_id, $new_password) {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([
            password_hash($new_password, PASSWORD_DEFAULT),
            $user_id
        ]);
    }
}
?>
```

## Helper Function Templates

### Validation Helper

```php
<?php
// helpers/validation.php

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_required($value, $field_name = 'Field') {
    if (empty(trim($value))) {
        return "$field_name is required";
    }
    return null;
}

function validate_length($value, $min, $max, $field_name = 'Field') {
    $length = strlen($value);
    if ($length < $min) {
        return "$field_name must be at least $min characters";
    }
    if ($length > $max) {
        return "$field_name must be less than $max characters";
    }
    return null;
}

function validate_password_strength($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number";
    }
    return null;
}

function validate_form($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $field_rules) {
        $value = $data[$field] ?? '';
        
        foreach ($field_rules as $rule => $params) {
            switch ($rule) {
                case 'required':
                    if (empty(trim($value))) {
                        $errors[$field][] = "$field is required";
                    }
                    break;
                case 'email':
                    if (!empty($value) && !validate_email($value)) {
                        $errors[$field][] = "$field must be a valid email";
                    }
                    break;
                case 'min':
                    if (strlen($value) < $params) {
                        $errors[$field][] = "$field must be at least $params characters";
                    }
                    break;
                case 'max':
                    if (strlen($value) > $params) {
                        $errors[$field][] = "$field must be less than $params characters";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

// Usage example:
// $errors = validate_form($_POST, [
//     'email' => ['required' => true, 'email' => true],
//     'password' => ['required' => true, 'min' => 8],
//     'name' => ['required' => true, 'max' => 100]
// ]);
?>
```

### Response Helper

```php
<?php
// helpers/response.php

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function html_success($message, $trigger = null) {
    if ($trigger) {
        header("HX-Trigger: $trigger");
    }
    echo "<div class='alert alert-success'>$message</div>";
    exit;
}

function html_error($message, $status = 400) {
    http_response_code($status);
    echo "<div class='alert alert-danger'>$message</div>";
    exit;
}

function redirect_htmx($url) {
    header("HX-Redirect: $url");
    exit;
}

function trigger_events($events) {
    // $events can be string or array
    if (is_array($events)) {
        header('HX-Trigger: ' . json_encode($events));
    } else {
        header("HX-Trigger: $events");
    }
}
?>
```

## Standalone Page Templates

### Login Page

```php
<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /app');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - App Name</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h2 class="text-center mb-4">Login</h2>
                <div id="login-result"></div>
                <form hx-post="/partials/auth/login-form.php" 
                      hx-target="#login-result">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" 
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" 
                               class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="/forgot-password.php">Forgot Password?</a> |
                    <a href="/register.php">Register</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

### Main SPA Shell (app.php)

```php
<?php
session_start();
require_once '../config/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/styles.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?= APP_NAME ?></a>
            <div class="navbar-nav">
                <a class="nav-link" href="#" 
                   hx-get="/partials/dashboard/index.php" 
                   hx-target="#page-content" 
                   hx-push-url="/app">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="#" 
                   hx-get="/partials/tasks/list.php" 
                   hx-target="#page-content" 
                   hx-push-url="/app">
                    <i class="bi bi-list-check"></i> Tasks
                </a>
                <a class="nav-link" href="/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
            <span class="navbar-text">
                <?= htmlspecialchars($user_name) ?>
            </span>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div id="page-content">
            <?php require '../html/partials/dashboard/index.php'; ?>
        </div>
    </div>
    
    <script src="/assets/js/custom.js"></script>
</body>
</html>
```

## Configuration Files

### Complete .htaccess Template

```apache
# /var/www/html/.htaccess

# Enable rewrite engine
RewriteEngine On

# ============================================
# Clean URLs for top-level pages
# ============================================
RewriteRule ^app$ app.php [L]
RewriteRule ^login$ login.php [L]
RewriteRule ^register$ register.php [L]
RewriteRule ^logout$ logout.php [L]
RewriteRule ^forgot-password$ forgot-password.php [L]
RewriteRule ^reset-password$ reset-password.php [L]
RewriteRule ^verify-email$ verify-email.php [L]
RewriteRule ^terms$ terms.php [L]
RewriteRule ^privacy$ privacy.php [L]

# ============================================
# Security
# ============================================

# Prevent access to sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent directory browsing
Options -Indexes

# Prevent access to config files (if any accidentally in web root)
<FilesMatch "\.(ini|conf|config)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# ============================================
# Error Pages
# ============================================
ErrorDocument 404 /404.php
ErrorDocument 500 /error.php
ErrorDocument 403 /error.php

# ============================================
# Optional: Prevent direct access to partials
# Uncomment if you want to force routing through app.php
# ============================================
# RewriteCond %{REQUEST_URI} ^/partials/
# RewriteCond %{HTTP_REFERER} !^https?://(www\.)?yourdomain\.com [NC]
# RewriteRule .* - [F,L]

# ============================================
# Allow direct access to specific directories
# ============================================
RewriteCond %{REQUEST_URI} !^/(assets|partials|uploads)/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# ============================================
# Performance
# ============================================

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
</IfModule>

# Security headers
<IfModule mod_headers.c>
    # Prevent clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # XSS protection
    Header always set X-XSS-Protection "1; mode=block"
    
    # Prevent MIME type sniffing
    Header always set X-Content-Type-Options "nosniff"
    
    # Referrer policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

### Uploads Directory .htaccess

```apache
# /var/www/html/uploads/.htaccess

# Prevent directory browsing
Options -Indexes

# Prevent PHP execution in uploads directory
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Only allow specific file types to be accessed
<FilesMatch "\.(jpg|jpeg|png|gif|pdf|doc|docx|txt)$">
    Order deny,allow
    Allow from all
</FilesMatch>
```

### PHP Configuration (.user.ini or php.ini)

```ini
; /var/www/html/.user.ini (if supported by host)

; Security settings
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/www/logs/php_errors.log

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
session.cookie_samesite = Strict

; File upload settings
upload_max_filesize = 5M
post_max_size = 6M
max_file_uploads = 10

; Memory and execution limits
memory_limit = 128M
max_execution_time = 30
max_input_time = 60
```
