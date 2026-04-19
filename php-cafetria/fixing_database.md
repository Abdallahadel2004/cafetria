# ☕ Cafetria Database Alignment Guide

This document serves as the **Master Schema Reference**. 

**IMPORTANT:** The application code has been optimized to use specific column names and capitalized statuses. **DO NOT** refactor the code to match old/legacy SQL files. Instead, ensure the database matches these rules:

---

## 1. User Table (`users`)
- **Room Column**: Must be named `room` (NOT `room_no`).
- **Extension Column**: Must be named `extension` (NOT `ext`).

## 2. Orders Table (`orders`)
- **Status Enum**: Must support: `'Processing'`, `'Out for delivery'`, `'Delivered'`, `'Cancelled'`.
- **Items Summary**: Must have a column named `items_summary` (TEXT). This stores a snapshot like "2x Coffee, 1x Tea".
- **Room Column**: Must be named `room`.
- **Extension Column**: Must be named `extension` (VARCHAR 20). Stores the point-in-time extension of the user.

## 3. Products Table (`products`)
- **Status Enum**: Must support: `'Available'`, `'Unavailable'`.
- **Total Orders**: Must have a column named `total_orders` (INT) to support dashboard ranking.
- **Category**: Should link via `category_id` to the `categories` table.

---

## 🛠️ SQL Migration Commands
If the database is reset or needs updating, run these commands:

```sql
-- Align User Columns
ALTER TABLE users CHANGE room_no room VARCHAR(20);
ALTER TABLE users CHANGE ext extension VARCHAR(20);

-- Align Orders Table
ALTER TABLE orders MODIFY COLUMN status ENUM('Processing', 'Out for delivery', 'Delivered', 'Cancelled') DEFAULT 'Processing';
ALTER TABLE orders ADD COLUMN items_summary TEXT;
ALTER TABLE orders ADD COLUMN extension VARCHAR(20) AFTER room;

-- Align Product Columns
ALTER TABLE products MODIFY COLUMN status ENUM('Available', 'Unavailable') DEFAULT 'Available';
ALTER TABLE products ADD COLUMN total_orders INT DEFAULT 0;
```

---

## 🚀 System Requirements & UI
- **Toast Notifications**: All pages must include `<div id="toast-container"></div>` at the end of the body.
- **Confirmation Modals**: All pages must include the `#confirm-modal-overlay` HTML structure for custom confirmation dialogs.
- **Scripts**: 
    - User side uses `user.js` and `style.css`.
    - Admin side uses `admin.js` and `admin/style.css`.
- **Backfill**: If `items_summary` is empty for old orders, run `php backfill_summaries.php`.

---
*Last updated: 2026-04-19 — Ensuring stability and premium UX across all modules.*
