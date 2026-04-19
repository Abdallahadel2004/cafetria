<?php
/**
 * admin-products.php
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

require_once __DIR__ . '/../db.php';

$filterCat = $_GET['category'] ?? '';
$whereClause = $filterCat ? "WHERE category = " . $pdo->quote($filterCat) : "";

$products = $pdo->query("
    SELECT id, name, category, price, status, emoji, description, total_orders
    FROM   products
    $whereClause
    ORDER  BY category, name
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Products</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include '_sidebar.php'; ?>

    <main class="main">
        <div class="topbar">
            <h2>Products</h2>
            <div class="topbar-actions">
                <div class="search-wrap">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input class="search-input" type="text" id="products-search" placeholder="Search products..." oninput="filterProductsTable()">
                </div>
                <select class="filter-select" onchange="location='admin-products.php?category='+encodeURIComponent(this.value)">
                    <option value="" <?= !$filterCat ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCat === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" onclick="openAddProduct()">
                    <span class="material-symbols-outlined">add</span>Add Product
                </button>
            </div>
        </div>

        <div class="page-content">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title"><span class="material-symbols-outlined">restaurant_menu</span>Product Catalog</div>
                    <span class="badge badge-available" id="products-count"><?= count($products) ?> products</span>
                </div>
                <div class="table-wrap">
                    <table id="products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Orders</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-body">
                            <?php if (empty($products)): ?>
                            <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--on-surface-variant)">No products found</td></tr>
                            <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr id="product-row-<?= (int)$p['id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" data-category="<?= strtolower(htmlspecialchars($p['category'])) ?>">
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <span style="font-size:1.5rem"><?= htmlspecialchars($p['emoji'] ?: '🍽️') ?></span>
                                        <div>
                                            <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
                                            <div style="font-size:0.75rem;color:var(--on-surface-variant)"><?= htmlspecialchars($p['description']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-category"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td><span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.95rem">EGP <?= number_format($p['price']) ?></span></td>
                                <td><span class="badge badge-<?= $p['status'] === 'Available' ? 'available' : 'unavailable' ?> status-badge" id="status-<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                                <td><?= (int)$p['total_orders'] ?> orders</td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <button class="btn btn-secondary btn-sm" onclick='openEditProduct(<?= json_encode(["id" => (int)$p['id'], "name" => $p['name'], "category" => $p['category'], "price" => (int)$p['price'], "status" => $p['status'], "emoji" => $p['emoji'], "desc" => $p['description']]) ?>)'><span class="material-symbols-outlined">edit</span></button>
                                        <button class="btn btn-secondary btn-sm" onclick="toggleProduct(<?= (int)$p['id'] ?>)"><span class="material-symbols-outlined"><?= $p['status'] === 'Available' ? 'visibility_off' : 'visibility' ?></span></button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?= (int)$p['id'] ?>)"><span class="material-symbols-outlined">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals (simplified for brevity) -->
    <div class="modal-overlay" id="add-product-modal"><div class="modal"><h3>Add New Product</h3><div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal('add-product-modal')">Cancel</button><button class="btn btn-primary" onclick="submitAddProduct()">Add</button></div></div></div>
    <div class="modal-overlay" id="edit-product-modal"><div class="modal"><h3>Edit Product</h3><div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal('edit-product-modal')">Cancel</button><button class="btn btn-primary" onclick="submitEditProduct()">Save</button></div></div></div>

    <script src="admin.js"></script>
</body>
</html>
