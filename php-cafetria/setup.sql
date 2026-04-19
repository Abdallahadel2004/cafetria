CREATE DATABASE IF NOT EXISTS cafetria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cafetria;

-- Drop tables in order to avoid foreign key constraints
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    room        VARCHAR(20),
    extension   VARCHAR(20),
    profile_picture VARCHAR(255) DEFAULT NULL,
    role        ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    price        DECIMAL(10, 2) NOT NULL,
    category_id  INT,
    image        VARCHAR(255) DEFAULT NULL,
    status       ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    total_orders INT DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    notes         TEXT DEFAULT NULL,
    room          VARCHAR(20) NOT NULL,
    total         DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status        ENUM('Processing', 'Out for delivery', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Processing',
    items_summary TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    product_id  INT,
    quantity    INT NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Seed data
INSERT INTO categories (name) VALUES ('Hot Drinks'), ('Cold Drinks'), ('Snacks');

-- Admin & Users
-- 'admin123' hash: $2y$10$GnVN3GCf3sHy9oCyP9MPW.heyFtFDxD3DDBC0xtd4ecKJdZRUjzAe
INSERT INTO users (name, email, password, room, extension, role) VALUES
('Admin', 'admin@cafetria.com', '$2y$10$GnVN3GCf3sHy9oCyP9MPW.heyFtFDxD3DDBC0xtd4ecKJdZRUjzAe', NULL, NULL, 'admin'),
('Islam Askar', 'islam@cafetria.com', '$2y$10$.DLTddl95x4DlCaugIXHXeVDQ9ABu998HFGUKYv0RNZGVWQ7T.p3q', '2010', '5605', 'user'),
('Abdulrahman Hamdy', 'abdulrahman@cafetria.com', '$2y$10$.DLTddl95x4DlCaugIXHXeVDQ9ABu998HFGUKYv0RNZGVWQ7T.p3q', '2006', '6506', 'user');

-- Exactly from products.sql
INSERT INTO `products` (`id`, `name`, `description`, `price`, `category_id`, `image`, `status`, `created_at`, `total_orders`) VALUES
(2, 'Coffee', 'Classic brewed coffee', 25.00, 1, 'https://images.pexels.com/photos/32013899/pexels-photo-32013899/free-photo-of-elegant-black-coffee-in-a-white-cup.jpeg?auto=compress&amp;cs=tinysrgb&amp;dpr=1&amp;w=500', 'Available', '2026-04-19 10:43:05', 0),
(3, 'Cappuccino', 'Espresso with steamed milk', 35.00, 1, 'https://cdn.britannica.com/17/234017-004-FD2DDBFE/cappuccino-Rome-Italy.jpg', 'Available', '2026-04-19 10:43:05', 0),
(5, 'Tea', 'Classic black brew', 15.00, 1, 'https://static.vecteezy.com/system/resources/thumbnails/023/285/334/small/a-cup-off-tea-with-teapot-ai-generate-photo.jpg', 'Available', '2026-04-19 10:43:05', 0),
(6, 'Hot Chocolate', 'Rich and creamy', 30.00, 1, 'https://us.hotelchocolat.com/cdn/shop/files/milky-50-hot-chocolate-hotel-chocolat-hotel-chocolat-632974.jpg?v=1750623054&amp;width=1946', 'Available', '2026-04-19 10:43:05', 0),
(7, 'Iced Coffee', 'Chilled coffee over ice', 30.00, 2, 'https://t4.ftcdn.net/jpg/04/87/09/55/360_F_487095590_bYPcJ3AmVxPHQJ7DhtNiPsBhxhCTh974.jpg', 'Available', '2026-04-19 10:43:05', 0),
(8, 'Iced Latte', 'Espresso, cold milk, ice', 40.00, 2, 'https://static.vecteezy.com/system/resources/thumbnails/034/221/917/small/pumpkin-spice-latte-iced-coffee-background-generative-ai-photo.jpg', 'Available', '2026-04-19 10:43:05', 0),
(9, 'Lemonade', 'Fresh squeezed lemonade', 20.00, 2, 'https://static.vecteezy.com/system/resources/thumbnails/053/212/900/small/refreshing-lemonade-in-mason-jar-with-lemon-slices-on-wooden-table-in-outdoor-cafe-photo.jpg', 'Available', '2026-04-19 10:43:05', 0),
(10, 'Orange Juice', 'Freshly pressed', 25.00, 2, 'https://cdn.pixabay.com/photo/2023/04/13/21/14/ai-generated-7923488_640.jpg', 'Available', '2026-04-19 10:43:05', 0),
(11, 'Mango Smoothie', 'Real mango blend', 45.00, 2, 'https://static.vecteezy.com/system/resources/thumbnails/056/503/475/small/fresh-mango-smoothie-served-in-a-tall-glass-with-mango-slices-on-top-in-a-sunlit-kitchen-photo.jpeg', 'Available', '2026-04-19 10:43:05', 0);
