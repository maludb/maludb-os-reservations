# Tables

Bootstrap component documentation and examples.

## Default

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.align-middle`
- `.bi`
- `.bi-pencil`
- `.bi-trash`
- `.btn`
- `.btn-icon`
- `.btn-primary`
- `.btn-sm`
- `.img-3x`
- `.m-0`
- `.mb-1`
- `.me-2`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table align-middle table-hover m-0 truncate">
<thead>
<tr>
<th scope="col">Employee</th>
<th scope="col">Position</th>
<th scope="col">Address</th>
<th scope="col">Age</th>
<th scope="col">Start date</th>
<th scope="col">Progress</th>
<th scope="col">Salary</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<tr>
<th scope="row">
<img class="rounded-circle img-3x me-2" src="assets/images/user.png"
alt="Vibe Templates" />
</th>
<td>Developer</td>
<td>3994 Grant View Drive, Muskego, 53150</td>
<td>28</td>
<td>28/10/2023</td>
<td>
<div class="progress small">
<div class="progress-bar" role="progressbar" style="width: 75%" aria-valuenow="75"
aria-valuemin="0" aria-valuemax="100"></div>
</div>
<!-- ... more content ... -->
```

---

## Responsive

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.m-0`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.text-primary`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table truncate m-0">
<thead>
<tr>
<th>Customer ID</th>
<th>First Name</th>
<th>Last Name</th>
<th>Phone</th>
<th>Email</th>
<th>Items Bought</th>
<th>Money Spent</th>
<th>Last Login</th>
</tr>
</thead>
<tbody>
<tr>
<td>#00001</td>
<td><a href="#" class="text-primary">Alia</a></td>
<td>Willams</td>
<td>+143-148-60985</td>
<td>info@example.com</td>
<td>250</td>
<td>$4500</td>
<td>10/10/2023 4:30pm</td>
</tr>
<tr>
<td>#00002</td>
<td><a href="#" class="text-primary">Nathan</a></td>
<td>James</td>
<!-- ... more content ... -->
```

---

## Table SM

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.m-0`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.table-sm`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table table-sm truncate m-0">
<thead>
<tr>
<th>#</th>
<th>Heading 1</th>
<th>Heading 2</th>
<th>Heading 3</th>
<th>Heading 4</th>
<th>Heading 5</th>
<th>Heading 6</th>
<th>Heading 7</th>
<th>Heading 8</th>
<th>Heading 9</th>
</tr>
</thead>
<tbody>
<tr>
<th>001</th>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
<td>Long text</td>
</tr>
<!-- ... more content ... -->
```

---

## Table Striped

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.m-0`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.table-striped`
- `.text-primary`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table table-striped truncate m-0">
<thead>
<tr>
<th>Customer ID</th>
<th>First Name</th>
<th>Last Name</th>
<th>Phone</th>
<th>Email</th>
<th>Items Bought</th>
<th>Money Spent</th>
<th>Last Login</th>
</tr>
</thead>
<tbody>
<tr>
<td>#00001</td>
<td><a href="#" class="text-primary">Alia</a></td>
<td>Willams</td>
<td>+143-148-60985</td>
<td>info@example.com</td>
<td>250</td>
<td>$4500</td>
<td>10/10/2023 4:30pm</td>
</tr>
<tr>
<td>#00002</td>
<td><a href="#" class="text-primary">Nathan</a></td>
<td>James</td>
<!-- ... more content ... -->
```

---

## Table Bordered

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.m-0`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table truncate m-0">
<thead>
<tr>
<th>Customer ID</th>
<th>First Name</th>
<th>Last Name</th>
<th>Phone</th>
<th>Email</th>
<th>Items Bought</th>
<th>Money Spent</th>
<th>Last Login</th>
</tr>
</thead>
<tbody>
<tr>
<td>#00001</td>
<td>Alia</td>
<td>Willams</td>
<td>+143-148-60985</td>
<td>info@example.com</td>
<td>250</td>
<td>$4500</td>
<td>10/10/2023 4:30pm</td>
</tr>
<tr>
<td>#00002</td>
<td>Nathan</td>
<td>James</td>
<!-- ... more content ... -->
```

---

## Table Danger

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.align-middle`
- `.img-1xx`
- `.m-0`
- `.me-2`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.text-center`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table align-middle truncate m-0">
<thead>
<tr>
<th>Country</th>
<th>Languages</th>
<th>Population</th>
<th>Median Age</th>
<th>Area (Km²)</th>
</tr>
</thead>
<tbody>
<tr>
<td>
<img src="assets/images/flags/1x1/hk.svg" class="img-1xx me-2" alt="Hong Kong" />Hong
Kong
</td>
<td>Chinese (official), English</td>
<td>7,39,000</td>
<td>31.3</td>
<td>1106</td>
</tr>
<tr>
<td>
<img src="assets/images/flags/1x1/au.svg" class="img-1xx me-2"
alt="Australia" />Australia
</td>
<td>English 79%, native and other languages</td>
<td>23,630,169</td>
<!-- ... more content ... -->
```

---

## Table

### Description
Tables component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.badge`
- `.border`
- `.border-primary`
- `.m-0`
- `.table`
- `.table-outer`
- `.table-responsive`
- `.text-primary`
- `.truncate`

### HTML Pattern

```html
<div class="table-outer">
<div class="table-responsive">
<table class="table truncate m-0">
<thead>
<tr>
<th>#</th>
<th>Title</th>
<th>Module</th>
<th>Reporter</th>
<th>Status</th>
<th>Owner</th>
<th>Severity</th>
<th>Created</th>
<th>Updated</th>
<th>Due</th>
</tr>
</thead>
<tbody>
<tr>
<td>1</td>
<td>App crashes</td>
<td>Main App</td>
<td>Lewis</td>
<td>
<span class="badge border border-primary text-primary">Open</span>
</td>
<td>Micheal</td>
<td>
<span class="badge border border-primary text-primary">High</span>
</td>
<!-- ... more content ... -->
```

## Common Use Cases

- Implementing tables components in your application
- Styling tables with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
