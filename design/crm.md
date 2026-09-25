# Crm

Bootstrap component documentation and examples.

## Customers

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.badge`
- `.bg-danger`
- `.bg-danger-subtle`
- `.bg-secondary-subtle`
- `.bi`
- `.bi-exclamation-circle-fill`
- `.col-12`
- `.col-sm-12`
- `.d-flex`
- `.gap-3`
- `.gx-4`
- `.m-0`

### HTML Pattern

```html
<!-- Row starts -->
<div class="row gx-4">
<div class="col-sm-12 col-12">
<div class="d-flex gap-3">
<div class="position-relative">
<h2 class="m-0">200</h2>
<span class="badge bg-secondary-subtle text-dark small mb-2">
<i class="bi bi-exclamation-circle-fill me-1 text-danger"></i>3 new customers
</span>
<div class=""><span class="badge bg-danger-subtle text-danger me-1">+33%</span>Compared to
last week</div>
</div>
<div class="position-relative">
<h2 class="m-0">300</h2>
<span class="badge bg-secondary-subtle text-dark small mb-2">
<i class="bi bi-exclamation-circle-fill me-1 text-danger"></i>6 customers online
</span>
<div class=""><span class="badge bg-danger-subtle text-danger me-1">+26%</span>Compared to
last week</div>
</div>
<div class="position-relative">
<h2 class="m-0">600</h2>
<span class="badge bg-secondary-subtle text-dark small mb-2">
<i class="bi bi-exclamation-circle-fill me-1 text-danger"></i>8 active customers
</span>
<div class=""><span class="badge bg-danger-subtle text-danger me-1">+22%</span>Compared to
last week</div>
</div>
<div class="position-relative">
```

---

## Contracts

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.badge`
- `.bg-danger-subtle`
- `.bg-primary-subtle`
- `.customer`
- `.delivery-details`
- `.scroll350`
- `.text-danger`
- `.text-primary`
- `.user-messages`

### HTML Pattern

```html
<div class="scroll350">
<ul class="user-messages">
<li>
<div class="customer bg-danger-subtle text-danger">MK</div>
<div class="delivery-details">
<span class="badge bg-danger-subtle text-danger">Expired</span>
<h5>Marie Kieffer</h5>
<p>Thanks for choosing Apple product, further if you have any questions please contact sales
team.</p>
</div>
</li>
<li>
<div class="customer bg-danger-subtle text-danger">ES</div>
<div class="delivery-details">
<span class="badge bg-danger-subtle text-danger">Live</span>
<h5>Ewelina Sikora</h5>
<p>Boost your sales by 50% with the easiest and proven marketing tool for customer enggement
&amp; motivation.</p>
</div>
</li>
<li>
<div class="customer bg-danger-subtle text-danger">TN</div>
<div class="delivery-details">
<span class="badge bg-danger-subtle text-danger">Expiring Soon</span>
<h5>Teboho Ncube</h5>
<p>Use an exclusive promo code HKYMM50 and get 50% off on your first order in the new year.
</p>
</div>
</li>
<li>
<!-- ... more content ... -->
```

---

## Payments

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.align-items-start`
- `.badge`
- `.bg-danger-subtle`
- `.bg-primary-subtle`
- `.d-flex`
- `.img-4x`
- `.m-0`
- `.mb-1`
- `.mb-4`
- `.me-3`
- `.my-4`
- `.rounded-circle`

### HTML Pattern

```html
<div class="scroll350">
<div class="my-4">
<div class="d-flex align-items-start">
<img src="assets/images/user3.png" class="img-4x me-3 rounded-circle"
alt="Vibe Templates Template" />
<div class="mb-4">
<h5 class="mb-1">Joan Paul</h5>
<p class="mb-1">3 day ago</p>
<p class="mb-1 small text-dark">Unpaid invoice ref. #26788</p>
<span class="badge bg-danger-subtle text-danger">Unpaid</span>
</div>
</div>
<div class="d-flex align-items-start">
<img src="assets/images/user4.png" class="img-4x me-3 rounded-circle"
alt="Vibe Templates Template" />
<div class="mb-4">
<h5 class="mb-1">Vincenzo Lyons</h5>
<p class="mb-1">3 hours ago</p>
<p class="mb-1 small text-dark">Paid invoice ref. #23457</p>
<span class="badge bg-danger-subtle text-danger">Paid</span>
</div>
</div>
<div class="d-flex align-items-start">
<img src="assets/images/user5.png" class="img-4x me-3 rounded-circle"
alt="Vibe Templates Template" />
<div class="mb-4">
<h5 class="mb-1">Clarence Wyatt</h5>
<p class="mb-1">7 hours ago</p>
<p class="mb-1 small text-dark">Paid invoice ref. #23459</p>
<span class="badge bg-danger-subtle text-danger">Partially Paid</span>
<!-- ... more content ... -->
```

---

## Project Activity

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.activity-feed`
- `.bi`
- `.bi-arrow-up-right`
- `.feed-date`
- `.feed-item`
- `.mb-1`
- `.pb-1`
- `.scroll350`
- `.text-danger`
- `.text-dark`
- `.text-primary`

### HTML Pattern

```html
<div class="scroll350">
<div class="activity-feed">
<div class="feed-item">
<span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
Hour Ago</span>
<div class="mb-1">
<a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
</div>
<div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
<div class="text-dark">Admin Dashboards <i class="bi bi-arrow-up-right"></i> </div>
</div>
<div class="feed-item">
<span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
Hour Ago</span>
<div class="mb-1">
<a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
</div>
<div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
<div class="text-dark">Admin Dashboards <i class="bi bi-arrow-up-right"></i> </div>
</div>
<div class="feed-item">
<span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
Hour Ago</span>
<div class="mb-1">
<a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
</div>
<div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
<div class="text-dark">Admin Dashboards <i class="bi bi-arrow-up-right"></i> </div>
</div>
<div class="feed-item">
<!-- ... more content ... -->
```

---

## Deals

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.graph-body-lg`
- `.m-0`
- `.mb-2`
- `.my-3`
- `.text-center`

### HTML Pattern

```html
<div class="graph-body-lg">
<div id="deals"></div>
</div>
<div class="my-3 text-center">
<h1>3850</h1>
<h5 class="mb-2">
Monthly Deals Growth
</h5>
<p class="m-0">
Measure how fast you’re growing monthly recurring deals.
</p>
```

---

## Leads

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.auto-align-graph`
- `.graph-body-lg`
- `.m-0`
- `.mb-2`
- `.my-3`
- `.text-center`

### HTML Pattern

```html
<div class="graph-body-lg auto-align-graph">
<div id="leads"></div>
</div>
<div class="my-3 text-center">
<h1>2500</h1>
<h5 class="mb-2">
Monthly Leads Growth
</h5>
<p class="m-0">
Measure how fast you’re growing monthly recurring deals.
</p>
```

---

## Tickets

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.auto-align-graph`
- `.graph-body-lg`
- `.m-0`
- `.mb-2`
- `.my-3`
- `.text-center`

### HTML Pattern

```html
<div class="graph-body-lg auto-align-graph">
<div id="tickets"></div>
</div>
<div class="my-3 text-center">
<h1>800</h1>
<h5 class="mb-2">
Monthly Tickets Growth
</h5>
<p class="m-0">
Measure how fast you’re growing monthly recurring deals.
</p>
```

---

## Invoices

### Description
Crm component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.bg-primary`
- `.col-sm-3`
- `.col-xl-3`
- `.d-flex`
- `.fw-bold`
- `.gx-4`
- `.justify-content-between`
- `.mb-2`
- `.mb-4`
- `.progress`
- `.progress-bar`
- `.row`

### HTML Pattern

```html
<!-- Row starts -->
<div class="row gx-4">
<div class="col-xl-3 col-sm-3">
<h5 class="mb-4 fw-bold">Overview</h5>
<div class="mb-4">
<div class="d-flex justify-content-between mb-2">
<span>2 Drafts</span>
<span class="text-primary fw-bold">2%</span>
</div>
<div class="progress small">
<div class="progress-bar bg-primary" role="progressbar" style="width: 2%" aria-valuenow="2"
aria-valuemin="2" aria-valuemax="100">
```

## Common Use Cases

- Implementing crm components in your application
- Styling crm with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
