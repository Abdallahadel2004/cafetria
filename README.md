# ☕ Cafetria — Workplace Cafeteria Management System

A modern, premium management system for a workplace cafeteria — built with **PHP, MySQL, HTML, CSS & JavaScript**.

---

## 📁 Project Structure

```
php-cafetria/
├── login.php                   # Entry point — Secure Auth system
├── db.php                      # Centralized PDO Database connection
├── style.css                   # Global design system (Glassmorphism, Dark mode ready)
├── script.js                   # Global interactivity & UX helpers
├── setup.sql                   # Database schema and seed data
│
├── admin/                      # Consolidated Admin Panel
│   ├── admin-dashboard.php     # Real-time Stats & Live Orders
│   ├── admin-orders.php        # Searchable Order Management
│   ├── admin-products.php      # Catalog Management (Full CRUD)
│   ├── admin-users.php         # User Account Control
│   ├── admin-manual-order.php  # POS Interface for Admins
│   ├── admin-checks.php        # Financial & Sales Reporting
│   ├── _sidebar.php            # Global Admin Navigation & Toast/Confirm Container
│   ├── admin.js                # Admin-specific logic & Toast system
│   └── api/                    # AJAX endpoints for admin operations
│
└── api_login_and_UserPages/    # Customer Facing Portal
    ├── user-home.php           # Order Menu — browsing & cart system
    ├── user-orders.php         # My Orders — status tracking
    ├── user.js                 # User-specific logic & Toast system
    └── api/                    # User-side Order API
```

---

## 🚀 Quick Start (Setup)

1.  **Database Setup:**
    *   Import/Run the `php-cafetria/setup.sql` file in your MySQL client.
    *   Ensure your database matches the `fixing_database.md` master reference.
2.  **Configuration:**
    *   Check `db.php` to ensure your DB credentials match your environment.
3.  **Run:**
    *   Use a local PHP server: `php -S localhost:8000` from the root directory.
    *   Access via `http://localhost:8000/php-cafetria/login.php`.

4.  **Login Credentials:**
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
