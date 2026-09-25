---
name: htmx-bootstrap-frontend
description: Frontend development patterns combining HTMX, Bootstrap 5.3, and Alpine.js for interactive web applications. Use when building user interfaces, creating interactive components, styling with Bootstrap, implementing HTMX interactions, adding Alpine.js reactivity, handling forms, managing UI state, or any frontend development with HTMX and Bootstrap.
---

# HTMX Bootstrap Frontend

Frontend patterns for building interactive web applications using HTMX for dynamic updates, Bootstrap 5.3 for styling, and Alpine.js for client-side reactivity.

## Core Technologies

### HTMX 2.0
- AJAX requests directly from HTML
- No JavaScript framework required
- Progressive enhancement friendly
- Server-driven UI updates

### Bootstrap 5.3
- Responsive grid system
- Pre-built components
- Utility classes
- Modern design system

### Alpine.js (Optional)
- Lightweight reactivity
- Complements HTMX
- Client-side state management
- Minimal JavaScript syntax

## HTMX Fundamentals

### Basic Request Attributes

```html
<!-- GET request -->
<button hx-get="/partials/data.php" 
        hx-target="#result">
    Load Data
</button>

<!-- POST request -->
<form hx-post="/partials/tasks/create.php" 
      hx-target="#task-list">
    <input type="text" name="title" required>
    <button type="submit">Create</button>
</form>

<!-- PUT/PATCH/DELETE -->
<button hx-put="/partials/tasks/update.php" 
        hx-target="#task-123">
    Update
</button>

<button hx-delete="/partials/tasks/delete.php?id=123" 
        hx-target="#task-123" 
        hx-swap="outerHTML"
        hx-confirm="Are you sure?">
    Delete
</button>
```

### Target and Swap

```html
<!-- Default: innerHTML of target -->
<button hx-get="/data.php" hx-target="#result">Load</button>
<div id="result"></div>

<!-- Swap strategies -->
<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="innerHTML">Default</button>

<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="outerHTML">Replace Element</button>

<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="beforebegin">Insert Before</button>

<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="afterend">Insert After</button>

<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="delete">Delete Target</button>

<button hx-get="/data.php" 
        hx-target="#result" 
        hx-swap="none">No Swap</button>
```

### Triggers

```html
<!-- Default triggers: click for buttons, submit for forms -->
<button hx-get="/data.php">Click Me</button>

<!-- Custom triggers -->
<input hx-get="/search.php" 
       hx-trigger="keyup changed delay:500ms" 
       hx-target="#results">

<!-- Multiple triggers -->
<div hx-get="/refresh.php" 
     hx-trigger="load, every 30s">
    Auto-refreshing content
</div>

<!-- Trigger filters -->
<button hx-get="/data.php" 
        hx-trigger="click[ctrlKey]">
    Ctrl+Click
</button>

<!-- Special events -->
<div hx-get="/data.php" 
     hx-trigger="revealed">
    Loads when scrolled into view
</div>
```

### Loading States

```html
<!-- Show indicator during request -->
<button hx-get="/data.php" hx-target="#result">
    Load
    <span class="htmx-indicator spinner-border spinner-border-sm"></span>
</button>

<!-- Custom indicator -->
<button hx-get="/data.php" 
        hx-target="#result"
        hx-indicator="#custom-loader">
    Load
</button>
<div id="custom-loader" class="htmx-indicator">
    <div class="spinner-border"></div>
</div>

<!-- CSS for indicators -->
<style>
.htmx-indicator {
    display: none;
}
.htmx-request .htmx-indicator {
    display: inline-block;
}
.htmx-request.htmx-indicator {
    display: inline-block;
}
</style>
```

## Bootstrap Integration

### Form Components with HTMX

```html
<!-- Bootstrap form with HTMX -->
<form hx-post="/partials/tasks/create.php" 
      hx-target="#result"
      class="needs-validation">
    <div class="mb-3">
        <label for="title" class="form-label">Title</label>
        <input type="text" 
               class="form-control" 
               id="title" 
               name="title" 
               required>
        <div class="invalid-feedback">
            Please provide a title.
        </div>
    </div>
    
    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control" 
                  id="description" 
                  name="description" 
                  rows="3"></textarea>
    </div>
    
    <button type="submit" class="btn btn-primary">
        Submit
        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
    </button>
</form>

<div id="result" class="mt-3"></div>
```

### Bootstrap Cards with HTMX

```html
<!-- Dynamic card list -->
<div class="row" id="task-cards">
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Task Title</h5>
                <p class="card-text">Task description here</p>
                <button class="btn btn-sm btn-primary" 
                        hx-get="/partials/tasks/edit.php?id=1" 
                        hx-target="#modal-content">
                    Edit
                </button>
                <button class="btn btn-sm btn-danger" 
                        hx-delete="/partials/tasks/delete.php?id=1" 
                        hx-target="closest .col-md-4"
                        hx-swap="outerHTML"
                        hx-confirm="Delete this task?">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
```

### Bootstrap Modal with HTMX

```html
<!-- Modal trigger -->
<button class="btn btn-primary" 
        hx-get="/partials/tasks/create.php" 
        hx-target="#modal-content"
        data-bs-toggle="modal" 
        data-bs-target="#taskModal">
    Create Task
</button>

<!-- Modal structure -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" id="modal-content">
            <!-- Content loaded by HTMX -->
        </div>
    </div>
</div>

<!-- Modal content from server -->
<!-- /partials/tasks/create.php returns: -->
<div class="modal-header">
    <h5 class="modal-title">Create Task</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form hx-post="/partials/tasks/create.php" 
          hx-target="#modal-result">
        <div id="modal-result"></div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
</div>
```

### Bootstrap Alerts

```html
<!-- Server returns alert HTML -->
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Task created successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="alert alert-danger alert-dismissible fade show" role="alert">
    Error: Failed to create task
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- Auto-dismiss with Alpine.js -->
<div x-data="{ show: true }" 
     x-show="show" 
     x-init="setTimeout(() => show = false, 3000)"
     class="alert alert-success">
    Auto-dismissing alert
</div>
```

### Bootstrap Navigation

```html
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">App Name</a>
        <button class="navbar-toggler" type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" 
                       href="#" 
                       hx-get="/partials/dashboard/index.php" 
                       hx-target="#page-content"
                       hx-push-url="/app">
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" 
                       href="#" 
                       hx-get="/partials/tasks/list.php" 
                       hx-target="#page-content"
                       hx-push-url="/app">
                        Tasks
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div id="page-content">
        <!-- Content loaded here -->
    </div>
</div>
```

## Common UI Patterns

### Infinite Scroll

```html
<div id="content-container">
    <!-- Initial content -->
    <div class="card mb-2">Item 1</div>
    <div class="card mb-2">Item 2</div>
    
    <!-- Load more trigger -->
    <div hx-get="/partials/tasks/list.php?page=2" 
         hx-trigger="revealed" 
         hx-swap="outerHTML"
         class="text-center p-3">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>
```

### Active Search

```html
<div class="mb-3">
    <input type="search" 
           class="form-control" 
           name="q" 
           placeholder="Search tasks..." 
           hx-get="/partials/tasks/search.php" 
           hx-trigger="keyup changed delay:500ms" 
           hx-target="#search-results">
</div>

<div id="search-results">
    <!-- Results appear here -->
</div>
```

### Click to Edit

```html
<div id="task-123">
    <div hx-get="/partials/tasks/edit.php?id=123" 
         hx-target="#task-123"
         style="cursor: pointer;">
        <h5>Task Title</h5>
        <p>Click to edit</p>
    </div>
</div>

<!-- Server returns form on click, then updates on submit -->
```

### Inline Delete

```html
<div class="list-group" id="task-list">
    <div class="list-group-item d-flex justify-content-between" 
         id="task-1">
        <span>Task Title</span>
        <button class="btn btn-sm btn-danger" 
                hx-delete="/partials/tasks/delete.php?id=1" 
                hx-target="#task-1"
                hx-swap="outerHTML swap:1s"
                hx-confirm="Delete?">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
```

### Bulk Actions

```html
<form hx-post="/partials/tasks/bulk-delete.php" 
      hx-target="#task-list">
    <button type="submit" class="btn btn-danger mb-3">
        Delete Selected
    </button>
    
    <div class="list-group" id="task-list">
        <div class="list-group-item">
            <input type="checkbox" name="ids[]" value="1" class="form-check-input me-2">
            Task 1
        </div>
        <div class="list-group-item">
            <input type="checkbox" name="ids[]" value="2" class="form-check-input me-2">
            Task 2
        </div>
    </div>
</form>
```

### Progress Bar

```html
<div id="progress-container">
    <div class="progress">
        <div class="progress-bar" 
             role="progressbar" 
             style="width: 0%" 
             hx-get="/partials/progress.php" 
             hx-trigger="every 1s"
             hx-target="this"
             hx-swap="outerHTML">
            0%
        </div>
    </div>
</div>

<!-- Server returns updated progress bar -->
<div class="progress-bar" 
     role="progressbar" 
     style="width: 45%">
    45%
</div>
```

## Alpine.js Integration

### Basic Reactivity

```html
<!-- Toggle visibility -->
<div x-data="{ open: false }">
    <button @click="open = !open" class="btn btn-primary">
        Toggle
    </button>
    <div x-show="open" class="alert alert-info mt-2">
        Content shown when open
    </div>
</div>

<!-- Dynamic classes -->
<div x-data="{ active: false }">
    <button @click="active = !active" 
            :class="active ? 'btn-success' : 'btn-primary'" 
            class="btn">
        Click Me
    </button>
</div>
```

### Form Handling

```html
<!-- Live character count -->
<div x-data="{ text: '', limit: 280 }">
    <textarea x-model="text" 
              class="form-control" 
              :maxlength="limit"></textarea>
    <small class="text-muted">
        <span x-text="text.length"></span> / <span x-text="limit"></span>
    </small>
</div>

<!-- Conditional form fields -->
<div x-data="{ type: 'email' }">
    <select x-model="type" class="form-select mb-3">
        <option value="email">Email</option>
        <option value="phone">Phone</option>
    </select>
    
    <input x-show="type === 'email'" 
           type="email" 
           class="form-control" 
           placeholder="Email">
    
    <input x-show="type === 'phone'" 
           type="tel" 
           class="form-control" 
           placeholder="Phone">
</div>
```

### Combined with HTMX

```html
<!-- Alpine manages local state, HTMX handles server -->
<div x-data="{ 
    editing: false,
    title: 'Original Title' 
}">
    <!-- View mode -->
    <div x-show="!editing">
        <h5 x-text="title"></h5>
        <button @click="editing = true" class="btn btn-sm btn-primary">
            Edit
        </button>
    </div>
    
    <!-- Edit mode -->
    <form x-show="editing" 
          hx-post="/partials/tasks/update.php?id=123"
          hx-target="#result"
          @htmx:after-request="editing = false">
        <input x-model="title" class="form-control mb-2">
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button @click="editing = false" 
                type="button" 
                class="btn btn-secondary btn-sm">
            Cancel
        </button>
    </form>
    
    <div id="result"></div>
</div>
```

### Dropdown Menu

```html
<div x-data="{ open: false }" @click.away="open = false">
    <button @click="open = !open" class="btn btn-secondary">
        Menu <i class="bi bi-chevron-down"></i>
    </button>
    
    <div x-show="open" 
         x-transition
         class="dropdown-menu d-block position-absolute">
        <a href="#" 
           class="dropdown-item"
           hx-get="/partials/action1.php"
           hx-target="#content">
            Action 1
        </a>
        <a href="#" 
           class="dropdown-item"
           hx-get="/partials/action2.php"
           hx-target="#content">
            Action 2
        </a>
    </div>
</div>
```

## Events and Lifecycle

### HTMX Events

```html
<!-- Listen to HTMX events -->
<div hx-post="/partials/task/create.php"
     hx-target="#result"
     @htmx:before-request="console.log('Starting request')"
     @htmx:after-request="console.log('Request complete')"
     @htmx:response-error="console.log('Error occurred')">
</div>

<!-- Custom event handling -->
<script>
document.body.addEventListener('htmx:afterSwap', function(evt) {
    // Initialize tooltips after content swap
    var tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
```

### Server-Triggered Events

```html
<!-- Server sends: HX-Trigger: taskCreated -->
<div id="task-list" 
     @task-created="alert('New task created!')">
</div>

<!-- Multiple events with data -->
<!-- Server: HX-Trigger: {"showToast": {"message": "Success!"}} -->
<div @show-toast="
    new bootstrap.Toast(
        document.getElementById('toast')
    ).show()
">
</div>
```

## Validation

### Client-Side Validation

```html
<form hx-post="/partials/tasks/create.php" 
      hx-target="#result"
      class="needs-validation"
      novalidate>
    <div class="mb-3">
        <input type="text" 
               class="form-control" 
               name="title" 
               required 
               minlength="3">
        <div class="invalid-feedback">
            Title must be at least 3 characters
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>

<script>
// Bootstrap validation
document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>
```

### Server-Side Validation Display

```html
<!-- Server returns validation errors as Bootstrap invalid-feedback -->
<div class="mb-3">
    <input type="text" class="form-control is-invalid" name="email" value="">
    <div class="invalid-feedback">
        Please enter a valid email address
    </div>
</div>
```

## Performance and Best Practices

### Debouncing Search

```html
<input type="search" 
       hx-get="/partials/search.php" 
       hx-trigger="keyup changed delay:500ms" 
       hx-target="#results"
       placeholder="Search...">
```

### Request Deduplication

```html
<!-- Prevent multiple simultaneous requests -->
<button hx-get="/partials/data.php" 
        hx-target="#result"
        hx-sync="this:drop">
    Load Data
</button>
```

### Optimistic UI

```html
<!-- Show immediate feedback -->
<button hx-delete="/partials/tasks/delete.php?id=123" 
        hx-target="#task-123"
        hx-swap="outerHTML swap:0s"
        onclick="this.closest('#task-123').style.opacity='0.5'">
    Delete
</button>
```

See [references/htmx-patterns.md](references/htmx-patterns.md) for more patterns and [references/bootstrap-components.md](references/bootstrap-components.md) for component examples.
