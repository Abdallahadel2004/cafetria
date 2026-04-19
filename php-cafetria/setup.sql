-- ═══════════════════════════════════════════════════════════════════════
--  Cafetria — Database setup
-- ═══════════════════════════════════════════════════════════════════════
--
--  Run this once in phpMyAdmin (or `mysql -u root -p < setup.sql`)
--  to create the schema AND seed it with a working admin + a working
--  customer + a handful of menu items.
--
--  Default test logins (change passwords ASAP):
--    admin@cafetria.com / admin123
--    user@cafetria.com  / user123
--
--  All column names match what the PHP files in this project actually
--  query — verified against:
--    - admin/admin/admin-dashboard.php
--    - admin/admin/admin-orders.php
--    - admin/admin/admin-products.php
--    - admin/admin/api/products.php
--    - admin/admin/api/orders.php
--    - api_login_and_UserPages/login.php
--    - api_login_and_UserPages/user-home.php
--    - api_login_and_UserPages/user-orders.php
--    - api_login_and_UserPages/api/order.php
-- ═══════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS cafetria
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafetria;

-- ── Wipe existing tables (safe re-run) ─────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════
--  USERS
-- ═══════════════════════════════════════════════════════════════════════
CREATE TABLE users (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)      NOT NULL,
    email       VARCHAR(150)      NOT NULL,
    password    VARCHAR(255)      NOT NULL,           -- bcrypt hash from password_hash()
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    room        VARCHAR(20)           NULL,
    extension   VARCHAR(20)           NULL,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════
--  PRODUCTS
-- ═══════════════════════════════════════════════════════════════════════
CREATE TABLE products (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)      NOT NULL,
    category      VARCHAR(60)       NOT NULL,
    price         INT UNSIGNED      NOT NULL DEFAULT 0,           -- price in EGP, no decimals
    status        ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
    emoji         VARCHAR(8)            NULL DEFAULT '🍽️',
    description   VARCHAR(255)          NULL DEFAULT '',
    total_orders  INT UNSIGNED      NOT NULL DEFAULT 0,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME              NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_status   (status),
    KEY idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════
--  ORDERS
-- ═══════════════════════════════════════════════════════════════════════
CREATE TABLE orders (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED      NOT NULL,
    items_summary  VARCHAR(500)      NOT NULL,                    -- e.g. "2x Coffee, 1x Tea"
    total          INT UNSIGNED      NOT NULL DEFAULT 0,
    status         ENUM('Processing','Delivered','Cancelled') NOT NULL DEFAULT 'Processing',
    notes          VARCHAR(500)          NULL DEFAULT '',
    created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME              NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user    (user_id),
    KEY idx_orders_status  (status),
    KEY idx_orders_created (created_at),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══════════════════════════════════════════════════════════════════════
--  SEED DATA
-- ═══════════════════════════════════════════════════════════════════════

-- ── Users ──────────────────────────────────────────────────────────────
-- Both hashes were generated with PHP:
--   echo password_hash('admin123', PASSWORD_DEFAULT);
--   echo password_hash('user123',  PASSWORD_DEFAULT);
-- They verify correctly with password_verify(). Change passwords later
-- via the admin "Add User" page or by re-hashing.
INSERT INTO users (name, email, password, role, room, extension) VALUES
('Admin User',  'admin@cafetria.com',
 '$2y$10$GnVN3GCf3sHy9oCyP9MPW.heyFtFDxD3DDBC0xtd4ecKJdZRUjzAe',
 'admin', '101', '1000'),

('Demo User',   'user@cafetria.com',
 '$2y$10$.DLTddl95x4DlCaugIXHXeVDQ9ABu998HFGUKYv0RNZGVWQ7T.p3q',
 'user',  '201', '1024'),

('Sara Ahmed',  'sara@cafetria.com',
 '$2y$10$.DLTddl95x4DlCaugIXHXeVDQ9ABu998HFGUKYv0RNZGVWQ7T.p3q',
 'user',  '202', '1025'),

('Omar Hassan', 'omar@cafetria.com',
 '$2y$10$.DLTddl95x4DlCaugIXHXeVDQ9ABu998HFGUKYv0RNZGVWQ7T.p3q',
 'user',  '305', '1031');


-- ── Products ────────────────────────────────────────────────────────────
INSERT INTO products (name, category, price, status, emoji, description, total_orders) VALUES
('Espresso',         'Hot',  20, 'Available',   '☕', 'Strong, single shot',           0),
('Coffee',           'Hot',  25, 'Available',   '☕', 'Classic brewed coffee',         0),
('Cappuccino',       'Hot',  35, 'Available',   '☕', 'Espresso with steamed milk',    0),
('Latte',            'Hot',  35, 'Available',   '🥛', 'Smooth milky espresso',         0),
('Tea',              'Hot',  15, 'Available',   '🍵', 'Classic black brew',            0),
('Hot Chocolate',    'Hot',  30, 'Available',   '🍫', 'Rich and creamy',               0),

('Iced Coffee',      'Cold', 30, 'Available',   '🧊', 'Chilled coffee over ice',       0),
('Iced Latte',       'Cold', 40, 'Available',   '🥤', 'Espresso, cold milk, ice',      0),
('Lemonade',         'Cold', 20, 'Available',   '🍋', 'Fresh squeezed lemonade',       0),
('Orange Juice',     'Cold', 25, 'Available',   '🍊', 'Freshly pressed',               0),
('Mango Smoothie',   'Cold', 45, 'Available',   '🥭', 'Real mango blend',              0),
('Sparkling Water',  'Cold', 15, 'Unavailable', '💧', 'Out of stock',                  0);


-- ── A couple of demo orders so user-orders.php isn't empty ─────────────
INSERT INTO orders (user_id, items_summary, total, status, notes, created_at) VALUES
(2, '2x Coffee, 1x Tea',       65,  'Delivered',  'Extra sugar in the tea', NOW() - INTERVAL 2 DAY),
(2, '1x Cappuccino, 1x Latte', 70,  'Delivered',  '',                       NOW() - INTERVAL 1 DAY),
(2, '1x Iced Coffee',          30,  'Processing', 'Less ice please',        NOW() - INTERVAL 1 HOUR);

-- Bump the products.total_orders counters so the dashboard reflects reality
UPDATE products SET total_orders = total_orders + 2 WHERE name = 'Coffee';
UPDATE products SET total_orders = total_orders + 1 WHERE name = 'Tea';
UPDATE products SET total_orders = total_orders + 1 WHERE name = 'Cappuccino';
UPDATE products SET total_orders = total_orders + 1 WHERE name = 'Latte';
UPDATE products SET total_orders = total_orders + 1 WHERE name = 'Iced Coffee';
