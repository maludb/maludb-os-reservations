# Settings

Bootstrap component documentation and examples.

## Personal Details

### Description
Settings component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.col-12`
- `.col-sm`
- `.form-control`
- `.form-label`
- `.gx-4`
- `.mb-4`
- `.row`

### HTML Pattern

```html
<!-- Row starts -->
<div class="row gx-4">
<div class="col-sm col-12">
<!-- Form field start -->
<div class="mb-4">
<label for="fullName" class="form-label">Full Name</label>
<input type="text" class="form-control" id="fullName" placeholder="Full Name" />
</div>
<!-- Form field end -->
<!-- Form field start -->
<div class="mb-4">
<label for="contactNumber" class="form-label">Contact</label>
<input type="text" class="form-control" id="contactNumber"
placeholder="Contact" />
</div>
<!-- Form field end -->
</div>
<div class="col-sm col-12">
<!-- Form field start -->
<div class="mb-4">
<label for="emailId" class="form-label">Email</label>
<input type="email" class="form-control" id="emailId" placeholder="Email ID"
value="info@email.com" />
</div>
<!-- Form field end -->
```

---

## Reset Password

### Description
Settings component demonstrating various styling and layout patterns.

### Key Bootstrap Classes

- `.col-12`
- `.form-control`
- `.form-label`
- `.gx-4`
- `.mb-4`
- `.row`

### HTML Pattern

```html
<div class="row gx-4">
<div class="col-12">
<!-- Form field start -->
<div class="mb-4">
<label for="currentPassword" class="form-label">Current Password</label>
<input type="text" class="form-control" id="currentPassword"
placeholder="Enter Current Password" />
</div>
<!-- Form field end -->
<!-- Form field start -->
<div class="mb-4">
<label for="newPassword" class="form-label">New Password</label>
<input type="text" class="form-control" id="newPassword"
placeholder="Enter New Password" />
</div>
<!-- Form field end -->
<!-- Form field start -->
<div>
<label for="confirmNewPassword" class="form-label">Confirm New
Password</label>
<input type="text" class="form-control" id="confirmNewPassword"
placeholder="Confirm New Password" />
</div>
<!-- Form field end -->
```

## Common Use Cases

- Implementing settings components in your application
- Styling settings with Bootstrap utility classes
- Creating consistent UI patterns and designs

## Bootstrap Documentation

For more detailed information, visit:
https://getbootstrap.com/docs/5.3/components/
