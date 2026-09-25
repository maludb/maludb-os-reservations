# Dropdowns

Bootstrap component documentation and examples.

## Dropdown Button

### Description
Dropdowns component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.btn`
- `.btn-danger`
- `.btn-group`
- `.btn-info`
- `.btn-primary`
- `.dropdown`
- `.dropdown-divider`
- `.dropdown-item`
- `.dropdown-menu`
- `.dropdown-toggle`
- `.dropdown-toggle-split`
- `.visually-hidden`

### HTML Pattern

```html
<!-- Example Dropdown Button -->
<div class="btn-group">
<div class="dropdown">
<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"
aria-expanded="false">
Dropdown Button
</button>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="#">Action</a></li>
<li><a class="dropdown-item" href="#">Another action</a></li>
<li><a class="dropdown-item" href="#">Something else here</a></li>
</ul>
</div>
</div>
<!-- Example Dropdown Link -->
<div class="btn-group">
<div class="dropdown">
<a class="btn btn-danger dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
aria-expanded="false">
Dropdown Link
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="#">Action</a></li>
<li><a class="dropdown-item" href="#">Another action</a></li>
<li><a class="dropdown-item" href="#">Something else here</a></li>
</ul>
</div>
</div>
```

---

## Dropdown Buttons

### Description
Dropdowns component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.btn`
- `.btn-danger`
- `.btn-dark`
- `.btn-group`
- `.btn-info`
- `.btn-light`
- `.btn-primary`
- `.btn-secondary`
- `.btn-success`
- `.btn-warning`
- `.card`
- `.card-body`

### HTML Pattern

```html
<div class="btn-group">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"
aria-expanded="false">Primary</button>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="#">Action</a></li>
<li><a class="dropdown-item" href="#">Another action</a></li>
<li><a class="dropdown-item" href="#">Something else here</a></li>
<li>
<hr class="dropdown-divider">
</li>
<li><a class="dropdown-item" href="#">Separated link</a></li>
</ul>
</div><!-- /btn-group -->
<div class="btn-group">
<button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown"
aria-expanded="false">Secondary</button>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="#">Action</a></li>
<li><a class="dropdown-item" href="#">Another action</a></li>
<li><a class="dropdown-item" href="#">Something else here</a></li>
<li>
<hr class="dropdown-divider">
</li>
<li><a class="dropdown-item" href="#">Separated link</a></li>
</ul>
</div><!-- /btn-group -->
<div class="btn-group">
<button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown"
aria-expanded="false">Success</button>
<ul class="dropdown-menu">
<!-- ... more content ... -->
```

## Common Use Cases

- Implementing dropdowns components in your application
- Styling dropdowns with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
