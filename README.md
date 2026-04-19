# ☕ Cafetria — Workplace Cafeteria Management System

A modern, premium management system for a workplace cafeteria — built with **PHP, MySQL, HTML, CSS & JavaScript**.

---

## 📁 Project Structure

```
php-cafetria/
├── login.html                  # Entry point — all users start here
├── style.css                   # Shared design system (CSS variables, layouts, components)
├── script.js                   # Shared interactivity (animations, toggles, scroll effects)
├── setup.sql                   # Database schema and seed data (Run this first!)
│
├── admin/admin/                # PHP Administrator backend
│   ├── admin-dashboard.php     # Live Dashboard — real-time stats & incoming orders
│   ├── admin-orders.php        # Orders Management — searchable table & card views
│   ├── admin-products.php      # Product Catalog — full CRUD with AJAX toggles
│   ├── admin-users.php         # User Management — account controls
│   ├── admin-manual-order.php  # POS Interface — place orders for users
│   ├── admin-checks.php        # Financial Reports — date/user filtered billing
│   ├── _sidebar.php            # Shared sidebar component with Logout
│   ├── db.php                  # Database connection (PDO)
│   └── api/                    # AJAX endpoints for admin actions
│
├── api_login_and_UserPages/    # PHP User backend & Auth
│   ├── login.php               # Secure login handler
│   ├── logout.php              # Session destruction
│   ├── user-home.php           # User Portal — browse and order
│   └── user-orders.php         # Order History — status tracking
│
└── admin/                      # Original HTML Mockups (Static)
```

---

## 🚀 Quick Start (Setup)

1.  **Database Setup:**
    *   Open phpMyAdmin or your MySQL client.
    *   Import/Run the `php-cafetria/setup.sql` file.
    *   This creates the `cafetria` database and seeds it with demo data.

2.  **Configuration:**
    *   Verify database credentials in `admin/admin/db.php` and `api_login_and_UserPages/db.php`.
    *   Default is `root` with no password.

3.  **Login Credentials:**
    *   **Admin:** `admin@cafetria.com` / `admin123`
    *   **User:** `user@cafetria.com` / `user123`

---

## 👥 System Roles

| Role | Access | Navigation |
|------|--------|------------|
| **Normal User** | Browse menu, place orders, view personal history, cancel pending orders. | Home · My Orders · Logout |
| **Administrator** | Live monitoring, menu management, user accounts, manual POS, billing reports. | Dashboard · Orders · Products · Users · Manual Order · Checks · Logout |

---

## 🔀 Application Flow

### 1. Authentication
All users land on `login.html`. The `login.php` handler verifies credentials and role:
- **Admin** → `admin/admin/admin-dashboard.php`
- **User** → `api_login_and_UserPages/user-home.php`

### 2. Admin Features
- **Live Dashboard:** Real-time stats on today's revenue and active orders.
- **AJAX Delivery:** Administrators can deliver or cancel orders without page reloads.
- **Product Controls:** Toggle product availability (visibility) instantly.
- **Financial Checks:** Detailed breakdown of spending per user over custom date ranges.

### 3. User Features
- **Smart Menu:** Products are grouped by category (Hot/Cold Drinks).
- **Cart System:** Add multiple items, specify quantities, and add custom notes.
- **Live History:** Track order status from "Processing" to "Delivered".

---

## 🎨 Design System

### Colors
- **Primary:** `#a33700` (Deep Orange) — Active states and primary actions.
- **Secondary:** `#176a21` (Forest Green) — Success states and "Available" badges.
- **Error:** `#b31b25` (Red) — "Unavailable" states and Logout.
- **Surface:** `#fff4f0` (Warm Cream) — Soft backgrounds for readability.

### Typography
- **Headings:** `Plus Jakarta Sans`
- **Body:** `Be Vietnam Pro`
- **Icons:** `Material Symbols Outlined` (Google Fonts)

---

## 📦 Dependencies

| Dependency | Source | Purpose |
|-----------|--------|---------|
| Google Fonts | CDN | Typography & Icons |
| PHP PDO | Built-in | Secure database interactions |
| MySQL | Local | Data persistence |

> **Note:** This system is designed for local development or internal workplace servers. No build tools (like Webpack or Vite) are required.
