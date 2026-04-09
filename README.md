# ☕ Cafetria — Workplace Cafeteria Management System

A modern, premium UI for managing a workplace cafeteria — built with **HTML, CSS & JavaScript**, designed to be wired up with **PHP** backend endpoints.

---

## 📁 Project Structure

```
php-cafetria/
├── login.html              # Entry point — all users start here
├── style.css               # Shared design system (CSS variables, layouts, components)
├── script.js               # Shared interactivity (animations, toggles, scroll effects)
├── README.md               # This file
│
├── admin/                  # Administrator screens (sidebar layout)
│   ├── admin-dashboard.html    # Home — stats cards + live incoming orders table
│   ├── admin-orders.html       # Orders — card-based view with product thumbnails
│   ├── admin-products.html     # Products — catalog table with edit/delete actions
│   ├── admin-add-product.html  # Add Product — form (name, price, category, image)
│   ├── admin-users.html        # Users — accounts table with edit/delete actions
│   ├── admin-register.html     # Register User — form (name, email, password, room, ext, photo)
│   ├── admin-manual-order.html # Manual Order — POS interface with user assignment
│   └── admin-checks.html       # Checks — financial reports with date/user filters
│
└── user/                   # Normal User screens (top navbar layout)
    ├── user-home.html          # Home — product grid + cart + quick reorder
    └── user-orders.html        # My Orders — order history with expandable details
```

---

## 👥 System Roles

| Role | Access | Navigation |
|------|--------|------------|
| **Normal User** | Place orders, view history, cancel pending orders | Top navbar: Home · My Orders |
| **Administrator** | Manage products, users, orders, manual ordering, billing | Left sidebar: Home · Orders · Products · Users · Manual Order · Checks |

---

## 🔀 Application Flow

### 0. Entry Point
All users land on `login.html`. After authentication, the system checks the user's role and redirects:
- **Admin** → `admin/admin-dashboard.html`
- **User** → `user/user-home.html`

### 1. Normal User Flow
```
user-home.html          →  Browse products, add to cart, confirm order
user-orders.html        →  View order history, expand details, cancel if "Processing"
```

### 2. Administrator Flow
```
admin-dashboard.html    →  Monitor live incoming orders, view stats
admin-orders.html       →  Review all orders with product thumbnails, deliver
admin-products.html     →  View/edit/delete products
admin-add-product.html  →  Add a new product to the menu
admin-users.html        →  View/edit/delete user accounts
admin-register.html     →  Register a new user account
admin-manual-order.html →  Place an order on behalf of a user (POS)
admin-checks.html       →  View financial reports filtered by date/user
```

---

## 🎨 Design System

### Colors
| Token | Value | Usage |
|-------|-------|-------|
| `--primary` | `#a33700` | Buttons, accents, active states |
| `--primary-container` | `#ff7943` | Gradient endpoints, badges |
| `--surface` | `#fff4f0` | Page backgrounds |
| `--surface-container-low` | `#ffede5` | Sidebar, cards |
| `--surface-container-lowest` | `#ffffff` | Card interiors, form containers |
| `--on-surface` | `#4b240a` | Primary text |
| `--on-surface-variant` | `#805032` | Secondary text, labels |
| `--secondary` | `#176a21` | Success/available states |
| `--tertiary-container` | `#feb300` | Price badges |
| `--error` | `#b31b25` | Error/delete states |

### Typography
- **Headings**: `Plus Jakarta Sans` (700–800 weight)
- **Body/Labels**: `Be Vietnam Pro` (300–600 weight)

### Icons
- **Library**: [Google Material Symbols Outlined](https://fonts.google.com/icons)
- **Loaded via CDN**: `https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined`

### Layout Patterns
- **Admin**: Fixed 280px left sidebar + fluid main content
- **User**: Sticky glassmorphism top navbar
- **Forms**: Centered `560px` max-width cards with `border-radius: 1.5rem`
- **Tables**: Full-width with hover states and tonal row separators

---

## 🛠 PHP Integration Guide

### Forms
All forms use `method="POST"` with proper `name` attributes on every input. File upload forms include `enctype="multipart/form-data"`.

```html
<!-- Example: admin/admin-add-product.html -->
<form method="POST" action="" enctype="multipart/form-data">
    <input type="text" name="name" ...>
    <input type="number" name="price" ...>
    <select name="category" ...>
    <input type="file" name="image" ...>
</form>
```

### Dynamic Data Zones
All data areas are **intentionally empty** with HTML comments showing the expected structure. Use PHP loops to populate:

#### Tables (`<tbody>`)
```php
<?php foreach ($orders as $order): ?>
<tr>
    <td><?= $order['date'] ?></td>
    <td><?= $order['user_name'] ?></td>
    <td><?= $order['items'] ?></td>
    <td><?= $order['room'] ?></td>
    <td><?= $order['ext'] ?></td>
    <td>EGP <?= $order['total'] ?></td>
    <td><button class="btn btn-primary btn-sm">Deliver</button></td>
</tr>
<?php endforeach; ?>
```

#### Product Grid (`.product-grid`)
```php
<?php foreach ($products as $product): ?>
<div class="product-card">
    <div class="img-wrapper">
        <img src="<?= $product['image'] ?>" class="product-img" alt="<?= $product['name'] ?>">
        <span class="price-badge"><?= $product['price'] ?> LE</span>
    </div>
    <div class="product-body">
        <div>
            <h4 class="product-name"><?= $product['name'] ?></h4>
            <p class="product-desc"><?= $product['description'] ?></p>
        </div>
        <button class="add-btn">
            <span class="material-symbols-outlined">add</span>
        </button>
    </div>
</div>
<?php endforeach; ?>
```

#### Dropdowns (`<select>`)
```php
<select name="user_id" class="form-control">
    <option value="">Select a user...</option>
    <?php foreach ($users as $user): ?>
    <option value="<?= $user['id'] ?>"><?= $user['name'] ?></option>
    <?php endforeach; ?>
</select>
```

### Session Data
Replace static user info in navbars/sidebars with PHP session data:
```php
<p class="user-name"><?= $_SESSION['user_name'] ?></p>
<p class="user-role"><?= $_SESSION['role'] ?></p>
```

### CSS Classes Reference

| Class | Usage |
|-------|-------|
| `.badge-processing` | Yellow — order in progress |
| `.badge-available` | Green — product available |
| `.badge-unavailable` | Red — product unavailable |
| `.badge-delivered` | Gray — order completed |
| `.btn-primary` | Gradient orange CTA |
| `.btn-secondary` | Muted background button |
| `.btn-danger` | Red outline, fills on hover |
| `.btn-sm` | Compact button for tables |
| `.expand-btn` | Clickable +/− for expandable rows |
| `.expand-row` | Hidden row, add `.active` to show |

---

## 📦 Dependencies

| Dependency | Source | Purpose |
|-----------|--------|---------|
| Plus Jakarta Sans | Google Fonts CDN | Heading typography |
| Be Vietnam Pro | Google Fonts CDN | Body typography |
| Material Symbols Outlined | Google Fonts CDN | UI icons |
| DiceBear Avatars | `api.dicebear.com` | Placeholder avatars (replace with real uploads) |

> **No build tools required.** All dependencies are loaded via CDN. Just serve the files with a PHP-capable server (Apache/Nginx + PHP).
