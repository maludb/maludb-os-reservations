# Toasts

Bootstrap component documentation and examples.

## Positions

### Description
Toasts component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.bi`
- `.bi-hand-thumbs-up-fill`
- `.btn`
- `.btn-close`
- `.btn-primary`
- `.end-0`
- `.fs-2`
- `.me-2`
- `.me-auto`
- `.p-3`
- `.position-fixed`
- `.text-center`

### HTML Pattern

```html
<div class="text-center">
<!-- Toast Top Right Button -->
<button type="button" class="btn btn-primary" id="topRightBtn">Top Right</button>
<!-- Toast Message - Added JS in Custom Toast.JS file -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<div id="topRightToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
<div class="toast-header">
<i class="bi bi-hand-thumbs-up-fill text-primary me-2 fs-2"></i>
<strong class="me-auto">Vibe Templates</strong>
<small>3 mins ago</small>
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
</div>
<div class="toast-body">
Hello, world! This is a Vibe Templates.
```

---

## Colors

### Description
Toasts component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.bi`
- `.bi-hand-thumbs-up-fill`
- `.btn`
- `.btn-close`
- `.btn-danger`
- `.end-0`
- `.fs-3`
- `.me-2`
- `.me-auto`
- `.p-3`
- `.position-fixed`
- `.text-bg-danger`

### HTML Pattern

```html
<div class="text-center">
<!-- Toast Danger Button -->
<button type="button" class="btn btn-danger" id="toastDangerBtn">Danger</button>
<!-- Toast Message - Added JS in Custom Toast.JS file -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<div id="toastDanger" class="toast text-bg-danger" role="alert" aria-live="assertive"
aria-atomic="true">
<div class="toast-header text-bg-danger">
<i class="bi bi-hand-thumbs-up-fill me-2 fs-3"></i>
<strong class="me-auto">Vibe Templates</strong>
<small>3 mins ago</small>
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
</div>
<div class="toast-body">
Hello, world! This is a Vibe Templates.
```

---

## Stacking

### Description
Toasts component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.bi`
- `.bi-hand-thumbs-up-fill`
- `.btn-close`
- `.fs-2`
- `.me-2`
- `.me-auto`
- `.position-static`
- `.show`
- `.text-body-secondary`
- `.text-primary`
- `.toast`
- `.toast-body`

### HTML Pattern

```html
<!-- Toast Container for Stacking Start -->
<div class="toast-container position-static">
<!-- Toast Shows -->
<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
<div class="toast-header">
<i class="bi bi-hand-thumbs-up-fill text-primary me-2 fs-2"></i>
<strong class="me-auto">Bootstrap</strong>
<small class="text-body-secondary">just now</small>
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
</div>
<div class="toast-body">
See? Just like this.
</div>
</div>
<!-- Toast Shows -->
<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
<div class="toast-header">
<i class="bi bi-hand-thumbs-up-fill text-primary me-2 fs-2"></i>
<strong class="me-auto">Bootstrap</strong>
<small class="text-body-secondary">2 seconds ago</small>
<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
</div>
<div class="toast-body">
Heads up, toasts will stack automatically
```

---

## Custom Content

### Description
Toasts component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.align-items-center`
- `.btn-close`
- `.d-flex`
- `.m-auto`
- `.me-2`
- `.show`
- `.toast`
- `.toast-body`

### HTML Pattern

```html
<!-- Toast start -->
<div class="toast show align-items-center" role="alert" aria-live="assertive" aria-atomic="true">
<div class="d-flex">
<div class="toast-body">
Hello, world! This is a toast message.
</div>
<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"
aria-label="Close"></button>
</div>
</div>
<!-- Toast end -->
```

---

## Custom Content

### Description
Toasts component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.border-top`
- `.btn`
- `.btn-outline-dark`
- `.btn-primary`
- `.btn-sm`
- `.mt-2`
- `.pt-2`
- `.show`
- `.toast`
- `.toast-body`

### HTML Pattern

```html
<!-- Toast start -->
<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
<div class="toast-body">
Hello, world! This is a toast message.
<div class="mt-2 pt-2 border-top">
<button type="button" class="btn btn-primary btn-sm">Take action</button>
<button type="button" class="btn btn-outline-dark btn-sm"
data-bs-dismiss="toast">Close</button>
```

## Common Use Cases

- Implementing toasts components in your application
- Styling toasts with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
