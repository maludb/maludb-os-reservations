# Bootstrap 5.3 Components with HTMX

Bootstrap component examples integrated with HTMX for dynamic behavior.

## Forms

### Basic Form

```html
<form hx-post="/partials/submit.php" hx-target="#result">
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email">
        <div class="form-text">We'll never share your email.</div>
    </div>
    
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password">
    </div>
    
    <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label" for="remember">Remember me</label>
    </div>
    
    <button type="submit" class="btn btn-primary">
        Submit
        <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
    </button>
</form>

<div id="result" class="mt-3"></div>
```

### Input Groups

```html
<div class="input-group mb-3">
    <span class="input-group-text">@</span>
    <input type="text" 
           class="form-control" 
           placeholder="Username"
           hx-get="/partials/check-username.php"
           hx-trigger="keyup changed delay:500ms"
           hx-target="#username-feedback">
</div>
<div id="username-feedback"></div>

<div class="input-group mb-3">
    <input type="text" 
           class="form-control" 
           placeholder="Search"
           name="q"
           hx-get="/partials/search.php"
           hx-trigger="keyup changed delay:500ms"
           hx-target="#search-results">
    <button class="btn btn-outline-secondary" type="button">
        <i class="bi bi-search"></i>
    </button>
</div>
```

### Floating Labels

```html
<div class="form-floating mb-3">
    <input type="email" 
           class="form-control" 
           id="floatingInput" 
           placeholder="name@example.com"
           hx-post="/partials/validate-email.php"
           hx-trigger="blur"
           hx-target="#email-validation">
    <label for="floatingInput">Email address</label>
</div>
<div id="email-validation"></div>
```

### Select Menus

```html
<select class="form-select mb-3"
        hx-get="/partials/get-options.php"
        hx-trigger="change"
        hx-target="#dependent-select">
    <option selected>Choose...</option>
    <option value="1">One</option>
    <option value="2">Two</option>
    <option value="3">Three</option>
</select>

<div id="dependent-select"></div>
```

### Range Slider

```html
<div x-data="{ value: 50 }">
    <label for="customRange" class="form-label">
        Volume: <span x-text="value"></span>
    </label>
    <input type="range" 
           class="form-range" 
           id="customRange"
           x-model="value"
           min="0" 
           max="100"
           hx-post="/partials/update-volume.php"
           hx-trigger="change"
           hx-vals="js:{value: event.target.value}">
</div>
```

## Buttons

### Button Groups

```html
<div class="btn-group" role="group">
    <button type="button" 
            class="btn btn-outline-primary"
            hx-get="/partials/view-list.php"
            hx-target="#content">
        <i class="bi bi-list"></i>
    </button>
    <button type="button" 
            class="btn btn-outline-primary"
            hx-get="/partials/view-grid.php"
            hx-target="#content">
        <i class="bi bi-grid"></i>
    </button>
</div>
```

### Dropdown Buttons

```html
<div class="dropdown">
    <button class="btn btn-secondary dropdown-toggle" 
            type="button" 
            data-bs-toggle="dropdown">
        Actions
    </button>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item" 
               href="#"
               hx-get="/partials/edit.php"
               hx-target="#modal-content"
               data-bs-toggle="modal"
               data-bs-target="#actionModal">
                Edit
            </a>
        </li>
        <li>
            <a class="dropdown-item" 
               href="#"
               hx-delete="/partials/delete.php"
               hx-target="#item"
               hx-confirm="Delete this item?">
                Delete
            </a>
        </li>
    </ul>
</div>
```

### Split Buttons

```html
<div class="btn-group">
    <button type="button" 
            class="btn btn-primary"
            hx-post="/partials/primary-action.php"
            hx-target="#result">
        Primary Action
    </button>
    <button type="button" 
            class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
            data-bs-toggle="dropdown">
        <span class="visually-hidden">Toggle Dropdown</span>
    </button>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item" 
               href="#"
               hx-post="/partials/action2.php"
               hx-target="#result">
                Action 2
            </a>
        </li>
    </ul>
</div>
```

## Cards

### Basic Card with Actions

```html
<div class="card" id="card-123">
    <div class="card-body">
        <h5 class="card-title">Card Title</h5>
        <p class="card-text">Card content goes here.</p>
        <button class="btn btn-primary"
                hx-get="/partials/view-details.php?id=123"
                hx-target="#modal-content"
                data-bs-toggle="modal"
                data-bs-target="#detailsModal">
            View Details
        </button>
        <button class="btn btn-danger"
                hx-delete="/partials/delete.php?id=123"
                hx-target="#card-123"
                hx-swap="outerHTML"
                hx-confirm="Delete this card?">
            Delete
        </button>
    </div>
</div>
```

### Card with Image

```html
<div class="card">
    <img src="image.jpg" class="card-img-top" alt="...">
    <div class="card-body">
        <h5 class="card-title">Title</h5>
        <p class="card-text">Description</p>
        <button class="btn btn-sm btn-outline-primary"
                hx-post="/partials/like.php?id=123"
                hx-target="closest .card-body"
                hx-swap="afterbegin">
            <i class="bi bi-heart"></i> Like
        </button>
    </div>
</div>
```

### Card Grid

```html
<div class="row row-cols-1 row-cols-md-3 g-4" id="card-grid">
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Card 1</h5>
                <p class="card-text">Content</p>
            </div>
        </div>
    </div>
    <!-- More cards -->
</div>

<!-- Load more -->
<button hx-get="/partials/load-more.php?page=2"
        hx-target="#card-grid"
        hx-swap="beforeend"
        class="btn btn-primary mt-3">
    Load More
</button>
```

## Modals

### Basic Modal

```html
<!-- Trigger -->
<button class="btn btn-primary"
        hx-get="/partials/modal-content.php"
        hx-target="#modalContent"
        data-bs-toggle="modal"
        data-bs-target="#exampleModal">
    Open Modal
</button>

<!-- Modal Structure -->
<div class="modal fade" id="exampleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" id="modalContent">
            <!-- Content loaded by HTMX -->
        </div>
    </div>
</div>
```

### Full-Screen Modal

```html
<div class="modal fade" id="fullscreenModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content" id="fullscreenContent">
            <!-- Content here -->
        </div>
    </div>
</div>
```

### Scrollable Modal

```html
<div class="modal fade" id="scrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Long Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="scrollableContent">
                <!-- Long content loaded here -->
            </div>
        </div>
    </div>
</div>
```

## Alerts

### Dismissible Alerts

```html
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Warning!</strong> This action cannot be undone.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### Alert with Action

```html
<div class="alert alert-primary d-flex justify-content-between align-items-center">
    <span>New version available!</span>
    <button class="btn btn-sm btn-outline-primary"
            hx-post="/partials/update.php"
            hx-target="closest .alert"
            hx-swap="outerHTML">
        Update Now
    </button>
</div>
```

### Auto-Dismissing Alert

```html
<div class="alert alert-success" 
     role="alert"
     x-data="{ show: true }"
     x-show="show"
     x-init="setTimeout(() => show = false, 5000)"
     x-transition>
    Operation completed successfully!
</div>
```

## Badges

### Badge with Counter

```html
<button class="btn btn-primary position-relative">
    Notifications
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
          id="notification-count"
          hx-get="/partials/notification-count.php"
          hx-trigger="every 30s"
          hx-swap="innerHTML">
        5
    </span>
</button>
```

### Status Badges

```html
<span class="badge bg-success" 
      hx-get="/partials/status.php"
      hx-trigger="every 10s"
      hx-swap="outerHTML">
    Online
</span>
```

## Breadcrumbs

### Dynamic Breadcrumbs

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
        <li class="breadcrumb-item active">Current</li>
    </ol>
</nav>
```

## Pagination

### Standard Pagination

```html
<nav>
    <ul class="pagination">
        <li class="page-item disabled">
            <span class="page-link">Previous</span>
        </li>
        <li class="page-item active">
            <span class="page-link">1</span>
        </li>
        <li class="page-item">
            <a class="page-link" 
               href="#"
               hx-get="/partials/page.php?p=2"
               hx-target="#content">
                2
            </a>
        </li>
        <li class="page-item">
            <a class="page-link" 
               href="#"
               hx-get="/partials/page.php?p=3"
               hx-target="#content">
                3
            </a>
        </li>
        <li class="page-item">
            <a class="page-link" 
               href="#"
               hx-get="/partials/page.php?p=2"
               hx-target="#content">
                Next
            </a>
        </li>
    </ul>
</nav>
```

## Progress Bars

### Dynamic Progress

```html
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
```

### Multiple Progress Bars

```html
<div class="progress" hx-get="/partials/multi-progress.php"
     hx-trigger="every 2s"
     hx-swap="innerHTML">
    <div class="progress-bar" style="width: 15%">15%</div>
    <div class="progress-bar bg-success" style="width: 30%">30%</div>
    <div class="progress-bar bg-info" style="width: 20%">20%</div>
</div>
```

## Spinners

### Loading Spinner

```html
<div class="text-center">
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
```

### Button with Spinner

```html
<button class="btn btn-primary" 
        hx-post="/partials/process.php"
        hx-target="#result"
        hx-indicator=".htmx-indicator">
    Process
    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
</button>
```

## Toasts

### Toast Notification

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast" id="liveToast" role="alert">
        <div class="toast-header">
            <strong class="me-auto">Notification</strong>
            <small>Just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toast-message">
            Hello, world! This is a toast message.
        </div>
    </div>
</div>

<script>
// Show toast on server trigger
document.body.addEventListener('show-toast', function(evt) {
    document.getElementById('toast-message').textContent = evt.detail.message;
    new bootstrap.Toast(document.getElementById('liveToast')).show();
});
</script>
```

## Tooltips & Popovers

### Tooltip

```html
<button type="button" 
        class="btn btn-secondary" 
        data-bs-toggle="tooltip" 
        data-bs-placement="top" 
        title="Tooltip text">
    Hover me
</button>

<script>
// Initialize tooltips after HTMX swap
document.body.addEventListener('htmx:afterSwap', function() {
    var tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
```

### Popover

```html
<button type="button" 
        class="btn btn-danger" 
        data-bs-toggle="popover" 
        data-bs-title="Popover title" 
        data-bs-content="And here's some amazing content.">
    Click me
</button>

<script>
// Initialize popovers
document.body.addEventListener('htmx:afterSwap', function() {
    var popoverTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="popover"]')
    );
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>
```

## Offcanvas

### Side Menu

```html
<button class="btn btn-primary" 
        type="button" 
        data-bs-toggle="offcanvas" 
        data-bs-target="#offcanvasMenu">
    Open Menu
</button>

<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" 
                   href="#"
                   hx-get="/partials/home.php"
                   hx-target="#content"
                   data-bs-dismiss="offcanvas">
                    Home
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" 
                   href="#"
                   hx-get="/partials/profile.php"
                   hx-target="#content"
                   data-bs-dismiss="offcanvas">
                    Profile
                </a>
            </li>
        </ul>
    </div>
</div>
```

## Collapse

### Accordion

```html
<div class="accordion" id="accordionExample">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseOne"
                    hx-get="/partials/section1.php"
                    hx-target="#collapseOne .accordion-body"
                    hx-trigger="click once">
                Section 1
            </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse show">
            <div class="accordion-body">
                <!-- Content loaded on first click -->
            </div>
        </div>
    </div>
</div>
```

## List Groups

### Interactive List

```html
<div class="list-group" id="task-list">
    <button type="button" 
            class="list-group-item list-group-item-action"
            hx-get="/partials/task-details.php?id=1"
            hx-target="#details">
        Task 1
    </button>
    <button type="button" 
            class="list-group-item list-group-item-action"
            hx-get="/partials/task-details.php?id=2"
            hx-target="#details">
        Task 2
    </button>
</div>

<div id="details" class="mt-3"></div>
```

### List with Badges

```html
<ul class="list-group">
    <li class="list-group-item d-flex justify-content-between align-items-center">
        Inbox
        <span class="badge bg-primary rounded-pill" 
              hx-get="/partials/inbox-count.php"
              hx-trigger="every 30s"
              hx-swap="innerHTML">
            14
        </span>
    </li>
</ul>
```
