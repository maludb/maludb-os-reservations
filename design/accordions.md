# Accordions

Bootstrap component documentation and examples.

## Accordion

### Description
Accordions component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.accordion`
- `.accordion-body`
- `.accordion-button`
- `.accordion-collapse`
- `.accordion-header`
- `.accordion-item`
- `.bg-primary`
- `.btn`
- `.btn-outline-dark`
- `.btn-outline-primary`
- `.collapse`
- `.d-flex`

### HTML Pattern

```html
<div class="accordion" id="accordionSpecialTitle">
<div class="accordion-item">
<h2 class="accordion-header" id="headingSpecialTitleOne">
<button class="accordion-button" type="button" data-bs-toggle="collapse"
data-bs-target="#collapseSpecialTitleOne" aria-expanded="true"
aria-controls="collapseSpecialTitleOne">
<div class="d-flex flex-column">
<h5 class="m-0">Accordion #1</h5>
</div>
</button>
</h2>
<div id="collapseSpecialTitleOne" class="accordion-collapse collapse show"
aria-labelledby="headingSpecialTitleOne" data-bs-parent="#accordionSpecialTitle">
<div class="accordion-body">
<div class="display-3">Hello,</div>
<p class="mb-3">
This is the first item's accordion body. It is shown by default, until the collapse plugin
adds the appropriate classes that we use to style each element. These classes control the
overall appearance, as well as the showing and hiding via CSS transitions. You can modify
any of this with custom CSS or overriding our default variables. It's also worth noting
that just about any HTML can go within the <code>.accordion-body</code>, though the
transition does limit overflow.
</p>
<div class="stacked-images mb-3">
<img src="assets/images/user.png" alt="Vibe Templates Template" />
<img src="assets/images/user2.png" alt="Vibe Templates Template" />
<img src="assets/images/user3.png" alt="Vibe Templates Template" />
<img src="assets/images/user4.png" alt="Vibe Templates Template" />
<span class="plus bg-primary">+5</span>
```

---

## Accordion

### Description
Accordions component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.accordion`
- `.accordion-body`
- `.accordion-button`
- `.accordion-collapse`
- `.accordion-header`
- `.accordion-item`
- `.card`
- `.card-cover`
- `.col`
- `.collapse`
- `.fw-bold`
- `.gx-4`

### HTML Pattern

```html
<div class="accordion" id="accordionExample2">
<div class="accordion-item">
<h2 class="accordion-header" id="headingOneLight">
<button class="accordion-button" type="button" data-bs-toggle="collapse"
data-bs-target="#collapseOneLight" aria-expanded="true" aria-controls="collapseOneLight">
Accordion Item #1
</button>
</h2>
<div id="collapseOneLight" class="accordion-collapse collapse show"
aria-labelledby="headingOneLight" data-bs-parent="#accordionExample2">
<div class="accordion-body">
<!-- Row starts -->
<div class="row gx-4">
<div class="col">
<div class="card card-cover rounded-2"
style="background-image: url('assets/images/products/banner.jpg');">
<div <div class="p-5 text-white">
<h4 class="fw-bold">
Another longer title belongs here
</h4>
```

---

## Accordion Always Open

### Description
Accordions component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.accordion`
- `.accordion-body`
- `.accordion-button`
- `.accordion-collapse`
- `.accordion-header`
- `.accordion-item`
- `.col`
- `.collapse`
- `.gx-4`
- `.img-fluid`
- `.mb-2`
- `.row`

### HTML Pattern

```html
<div class="accordion" id="accordionPanelsStayOpenExample">
<div class="accordion-item">
<h2 class="accordion-header" id="panelsStayOpen-headingOne">
<button class="accordion-button" type="button" data-bs-toggle="collapse"
data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true"
aria-controls="panelsStayOpen-collapseOne">
Accordion Item #1
</button>
</h2>
<div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show"
aria-labelledby="panelsStayOpen-headingOne">
<div class="accordion-body">
<div class="row gx-4">
<div class="col">
<img src="assets/images/products/product9.jpg" class="img-fluid mb-2"
alt="Vibe Templates" />
</div>
<div class="col">
<img src="assets/images/products/product8.jpg" class="img-fluid mb-2"
alt="Vibe Templates" />
</div>
<div class="col">
<img src="assets/images/products/product7.jpg" class="img-fluid mb-2"
alt="Vibe Templates" />
</div>
<div class="col">
<img src="assets/images/products/product6.jpg" class="img-fluid mb-2"
alt="Vibe Templates" />
</div>
<div class="col">
<!-- ... more content ... -->
```

## Common Use Cases

- Implementing accordions components in your application
- Styling accordions with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
