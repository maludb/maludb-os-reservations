# Form Accordion

Bootstrap component documentation and examples.

## Form Accordion

### Description
Form Accordion component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.accordion`
- `.accordion-body`
- `.accordion-button`
- `.accordion-collapse`
- `.accordion-header`
- `.accordion-item`
- `.btn`
- `.btn-outline-secondary`
- `.btn-primary`
- `.col-12`
- `.col-sm-12`
- `.col-sm-4`

### HTML Pattern

```html
<div class="accordion" id="accordionForm">
<div class="accordion-item">
<h2 class="accordion-header">
<button class="accordion-button" type="button" data-bs-toggle="collapse"
data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
Section #1
</button>
</h2>
<div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionForm">
<div class="accordion-body">
<!-- Row starts -->
<div class="row gx-4">
<div class="col-xl-3 col-sm-4 col-12">
<div class="mb-3">
<label class="form-label" for="name">Name</label>
<input type="text" class="form-control" id="name" placeholder="Enter fullname">
</div>
</div>
<div class="col-xl-3 col-sm-4 col-12">
<div class="mb-3">
<label class="form-label" for="email">Email</label>
<input type="email" class="form-control" id="email" placeholder="Enter email address">
</div>
</div>
<div class="col-xl-3 col-sm-4 col-12">
<div class="mb-3">
<label class="form-label" for="phn">Phone</label>
<input type="number" class="form-control" id="phn" placeholder="Enter phone number">
</div>
```

## Common Use Cases

- Implementing form accordion components in your application
- Styling form accordion with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
