# HTMX Patterns Reference

Advanced HTMX patterns and techniques for common use cases.

## Table of Contents
- [Navigation Patterns](#navigation-patterns)
- [Form Patterns](#form-patterns)
- [List Management](#list-management)
- [Modal Patterns](#modal-patterns)
- [Notification Patterns](#notification-patterns)
- [Advanced Techniques](#advanced-techniques)

## Navigation Patterns

### SPA-Style Navigation

```html
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">My App</a>
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link active" 
                   href="#" 
                   hx-get="/partials/dashboard.php" 
                   hx-target="#main-content"
                   hx-push-url="/app"
                   @click="setActive($event)">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" 
                   href="#" 
                   hx-get="/partials/tasks.php" 
                   hx-target="#main-content"
                   hx-push-url="/app"
                   @click="setActive($event)">
                    Tasks
                </a>
            </li>
        </ul>
    </div>
</nav>

<main id="main-content" class="container mt-4">
    <!-- Content loaded here -->
</main>

<script>
function setActive(event) {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');
}
</script>
```

### Breadcrumb Navigation

```html
<nav aria-label="breadcrumb">
    <ol class="breadcrumb" id="breadcrumb">
        <li class="breadcrumb-item">
            <a href="#" 
               hx-get="/partials/home.php" 
               hx-target="#content"
               hx-push-url="/app">
                Home
            </a>
        </li>
        <li class="breadcrumb-item active">Current Page</li>
    </ol>
</nav>
```

### Tab Navigation

```html
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" 
           href="#" 
           hx-get="/partials/overview.php" 
           hx-target="#tab-content">
            Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" 
           href="#" 
           hx-get="/partials/details.php" 
           hx-target="#tab-content">
            Details
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" 
           href="#" 
           hx-get="/partials/settings.php" 
           hx-target="#tab-content">
            Settings
        </a>
    </li>
</ul>

<div class="tab-content p-3 border border-top-0" id="tab-content">
    <!-- Content loaded here -->
</div>
```

## Form Patterns

### Multi-Step Form

```html
<div x-data="{ step: 1 }">
    <!-- Progress indicator -->
    <div class="progress mb-3">
        <div class="progress-bar" 
             :style="'width: ' + (step * 33.33) + '%'">
        </div>
    </div>
    
    <!-- Step 1 -->
    <div x-show="step === 1">
        <h4>Step 1: Basic Info</h4>
        <div class="mb-3">
            <input type="text" class="form-control" placeholder="Name">
        </div>
        <button @click="step = 2" class="btn btn-primary">Next</button>
    </div>
    
    <!-- Step 2 -->
    <div x-show="step === 2">
        <h4>Step 2: Details</h4>
        <div class="mb-3">
            <textarea class="form-control" placeholder="Description"></textarea>
        </div>
        <button @click="step = 1" class="btn btn-secondary">Back</button>
        <button @click="step = 3" class="btn btn-primary">Next</button>
    </div>
    
    <!-- Step 3 -->
    <div x-show="step === 3">
        <h4>Step 3: Review</h4>
        <form hx-post="/partials/submit.php" hx-target="#result">
            <!-- Form fields here -->
            <button @click="step = 2" type="button" class="btn btn-secondary">Back</button>
            <button type="submit" class="btn btn-success">Submit</button>
        </form>
    </div>
    
    <div id="result"></div>
</div>
```

### Dynamic Form Fields

```html
<form hx-post="/partials/submit.php" hx-target="#result">
    <div x-data="{ fields: [''] }">
        <template x-for="(field, index) in fields" :key="index">
            <div class="mb-2 d-flex gap-2">
                <input type="text" 
                       :name="'items[' + index + ']'" 
                       class="form-control">
                <button type="button" 
                        @click="fields.splice(index, 1)" 
                        class="btn btn-danger"
                        x-show="fields.length > 1">
                    Remove
                </button>
            </div>
        </template>
        <button type="button" 
                @click="fields.push('')" 
                class="btn btn-secondary">
            Add Field
        </button>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Submit</button>
</form>
```

### Dependent Selects

```html
<div class="mb-3">
    <label class="form-label">Category</label>
    <select name="category" 
            class="form-select"
            hx-get="/partials/get-subcategories.php" 
            hx-target="#subcategory-container"
            hx-trigger="change">
        <option value="">Select Category</option>
        <option value="electronics">Electronics</option>
        <option value="clothing">Clothing</option>
    </select>
</div>

<div id="subcategory-container">
    <!-- Subcategory select loaded here -->
</div>
```

### File Upload with Preview

```html
<div x-data="{ preview: null }">
    <input type="file" 
           @change="preview = URL.createObjectURL($event.target.files[0])"
           class="form-control mb-2">
    
    <img :src="preview" 
         x-show="preview" 
         class="img-thumbnail" 
         style="max-width: 200px;">
    
    <button hx-post="/partials/upload.php" 
            hx-encoding="multipart/form-data"
            hx-include="[type='file']"
            hx-target="#upload-result"
            class="btn btn-primary">
        Upload
    </button>
    
    <div id="upload-result"></div>
</div>
```

## List Management

### Sortable List

```html
<!-- Requires SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<form hx-post="/partials/update-order.php" 
      hx-trigger="end"
      class="sortable">
    <div class="list-group" id="sortable-list">
        <div class="list-group-item" data-id="1">
            <input type="hidden" name="order[]" value="1">
            Item 1
        </div>
        <div class="list-group-item" data-id="2">
            <input type="hidden" name="order[]" value="2">
            Item 2
        </div>
    </div>
</form>

<script>
new Sortable(document.getElementById('sortable-list'), {
    animation: 150,
    onEnd: function() {
        htmx.trigger('.sortable', 'end');
    }
});
</script>
```

### Filterable List

```html
<div x-data="{ 
    filter: '',
    items: ['Apple', 'Banana', 'Cherry', 'Date'] 
}">
    <input x-model="filter" 
           type="search" 
           class="form-control mb-3" 
           placeholder="Filter items...">
    
    <div class="list-group">
        <template x-for="item in items.filter(i => 
            i.toLowerCase().includes(filter.toLowerCase())
        )">
            <div class="list-group-item" x-text="item"></div>
        </template>
    </div>
</div>
```

### Pagination

```html
<div id="content">
    <!-- Content items -->
</div>

<nav>
    <ul class="pagination">
        <li class="page-item">
            <a class="page-link" 
               href="#" 
               hx-get="/partials/list.php?page=1" 
               hx-target="#content">
                1
            </a>
        </li>
        <li class="page-item active">
            <a class="page-link" href="#">2</a>
        </li>
        <li class="page-item">
            <a class="page-link" 
               href="#" 
               hx-get="/partials/list.php?page=3" 
               hx-target="#content">
                3
            </a>
        </li>
    </ul>
</nav>
```

### Load More Button

```html
<div id="items-container">
    <!-- Initial items -->
</div>

<button hx-get="/partials/items.php?page=2" 
        hx-target="#items-container" 
        hx-swap="beforeend"
        hx-select=".item"
        class="btn btn-primary">
    Load More
</button>
```

## Modal Patterns

### Confirmation Dialog

```html
<!-- Trigger -->
<button class="btn btn-danger" 
        hx-get="/partials/confirm-delete.php?id=123" 
        hx-target="#confirm-modal"
        data-bs-toggle="modal" 
        data-bs-target="#confirmModal">
    Delete
</button>

<!-- Modal -->
<div class="modal fade" id="confirmModal">
    <div class="modal-dialog">
        <div class="modal-content" id="confirm-modal">
            <!-- Server returns modal content -->
        </div>
    </div>
</div>

<!-- Server response -->
<div class="modal-header">
    <h5 class="modal-title">Confirm Delete</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    Are you sure you want to delete this item?
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        Cancel
    </button>
    <button hx-delete="/partials/delete.php?id=123" 
            hx-target="#item-123"
            hx-swap="outerHTML"
            data-bs-dismiss="modal"
            class="btn btn-danger">
        Delete
    </button>
</div>
```

### Form in Modal

```html
<div class="modal-header">
    <h5 class="modal-title">Edit Task</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form hx-post="/partials/tasks/update.php?id=123"
          hx-target="#modal-result">
        <div id="modal-result"></div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="Existing Title">
        </div>
        <button type="submit" class="btn btn-primary">
            Save Changes
        </button>
    </form>
</div>

<!-- On success, server can close modal with: -->
<!-- HX-Trigger: {"closeModal": true} -->
<script>
document.body.addEventListener('close-modal', function() {
    bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
});
</script>
```

## Notification Patterns

### Toast Notifications

```html
<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="toast" class="toast" role="alert">
        <div class="toast-header">
            <strong class="me-auto">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toast-message">
            <!-- Message here -->
        </div>
    </div>
</div>

<!-- Trigger from server -->
<!-- HX-Trigger: {"showToast": {"message": "Success!"}} -->
<script>
document.body.addEventListener('show-toast', function(evt) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-message').textContent = evt.detail.message;
    new bootstrap.Toast(toast).show();
});
</script>
```

### Alert Stack

```html
<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
    <!-- Alerts appear here -->
</div>

<!-- Server returns alerts that auto-dismiss -->
<div class="alert alert-success alert-dismissible fade show" 
     role="alert"
     x-data="{ show: true }"
     x-show="show"
     x-init="setTimeout(() => { show = false; setTimeout(() => $el.remove(), 300) }, 3000)">
    Operation successful!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

## Advanced Techniques

### Polling with Error Handling

```html
<div hx-get="/partials/status.php" 
     hx-trigger="every 5s"
     hx-target="this"
     hx-swap="innerHTML"
     @htmx:response-error="this.removeAttribute('hx-trigger')">
    Checking status...
</div>
```

### Request Queuing

```html
<!-- Ensure requests happen in order -->
<div hx-get="/partials/step1.php"
     hx-trigger="load"
     hx-sync="this:queue">
</div>

<div hx-get="/partials/step2.php"
     hx-trigger="load delay:1s"
     hx-sync="this:queue">
</div>
```

### Conditional Swap

```html
<button hx-post="/partials/action.php"
        hx-target="#result"
        hx-on::before-swap="if(event.detail.xhr.status === 422) {
            event.detail.shouldSwap = true;
            event.detail.isError = false;
        }">
    Submit
</button>
```

### Out of Band Updates

```html
<!-- Main target -->
<div id="main-content">
    <!-- Primary content here -->
</div>

<!-- Secondary update area -->
<div id="notifications">
    <!-- Notification count -->
</div>

<!-- Server response includes both updates -->
<!-- Main content -->
<div>Updated main content</div>

<!-- Out of band update -->
<div id="notifications" hx-swap-oob="true">
    <span class="badge bg-danger">5 new</span>
</div>
```

### WebSocket Integration

```html
<div hx-ext="ws" ws-connect="/ws">
    <form ws-send>
        <input name="message" class="form-control">
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
    <div id="messages">
        <!-- Messages appear here -->
    </div>
</div>
```

### History Management

```html
<!-- Save scroll position -->
<script>
document.body.addEventListener('htmx:beforeHistorySave', function() {
    localStorage.setItem('scrollPos', window.scrollY);
});

document.body.addEventListener('htmx:historyRestore', function() {
    const scrollPos = localStorage.getItem('scrollPos');
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos));
    }
});
</script>
```

### Custom Headers

```html
<button hx-post="/partials/action.php"
        hx-headers='{"X-Custom-Header": "value"}'>
    Submit
</button>

<!-- Or using JavaScript -->
<script>
document.body.addEventListener('htmx:configRequest', function(evt) {
    evt.detail.headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;
});
</script>
```

### Request Caching

```html
<!-- Cache GET requests -->
<button hx-get="/partials/data.php"
        hx-target="#result"
        hx-sync="this:replace">
    Load Data (Cached)
</button>
```

### Optimistic Updates

```html
<div id="task-count">5 tasks</div>

<button hx-post="/partials/tasks/create.php"
        hx-target="#result"
        onclick="document.getElementById('task-count').textContent = '6 tasks'">
    Create Task
</button>

<!-- If request fails, revert with htmx:responseError -->
```
