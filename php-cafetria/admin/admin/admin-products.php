<?php
/**
 * admin-products.php
 *
 * PHP responsibilities:
 *   - Auth guard
 *   - Query full product catalog from DB
 *   - Render table rows + visual grid cards
 *   - Pass catalog summary as JS variable for Claude AI suggestions
 *
 * JS (admin.js) responsibilities:
 *   - Client-side search/filter
 *   - Add / Edit / Delete / Toggle via AJAX to api/products.php
 *   - Claude AI product suggestion call
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.html'); exit;
}

require_once '../db.php';

// Optional category filter from URL
$filterCat = $_GET['category'] ?? '';

$whereClause = $filterCat
    ? "WHERE category = " . $pdo->quote($filterCat)
    : "";

$products = $pdo->query("
    SELECT id, name, category, price, status, emoji, description, total_orders
    FROM   products
    $whereClause
    ORDER  BY category, name
")->fetchAll(PDO::FETCH_ASSOC);

// Distinct categories for the filter dropdown
$categories = $pdo->query("
    SELECT DISTINCT category FROM products ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

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
<link rel="stylesheet" href="../style.css">
</head>
<body>

<?php include '_sidebar.php'; ?>

<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <h2>Products</h2>
        <div class="topbar-actions">
            <div class="search-wrap">
                <span class="material-symbols-outlined search-icon">search</span>
                <input class="search-input" type="text" id="products-search"
                       placeholder="Search products..." oninput="filterProductsTable()">
            </div>

            <!-- Category filter — reloads with ?category=... for server-side filtering -->
            <select class="filter-select"
                    onchange="location='admin-products.php?category='+encodeURIComponent(this.value)">
                <option value="" <?= !$filterCat ? 'selected' : '' ?>>All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"
                    <?= $filterCat === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-secondary btn-sm" onclick="runProductsAI()">
                <span class="material-symbols-outlined">auto_awesome</span>AI Suggest
            </button>
            <button class="btn btn-primary btn-sm" onclick="openAddProduct()">
                <span class="material-symbols-outlined">add</span>Add Product
            </button>
        </div>
    </div>

    <div class="page-content">

        <!-- AI Suggestions Box (hidden until requested) -->
        <div class="section-card" id="products-ai-box" style="display:none">
            <div class="section-header">
                <div class="section-title">
                    <span class="material-symbols-outlined">auto_awesome</span>AI Product Suggestions
                </div>
                <button class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('products-ai-box').style.display='none'">
                    Close
                </button>
            </div>
            <div id="products-ai-content"
                 style="padding:1.25rem;font-size:0.875rem;line-height:1.7;color:var(--on-surface)">
            </div>
        </div>

        <!-- ── Table View ──────────────────────────── -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <span class="material-symbols-outlined">restaurant_menu</span>
                    Product Catalog
                </div>
                <span class="badge badge-available" id="products-count">
                    <?= count($products) ?> products
                </span>
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
                        <tr>
                            <td colspan="6" style="text-align:center;padding:2rem;color:var(--on-surface-variant)">
                                No products found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                        <tr id="product-row-<?= (int)$p['id'] ?>"
                            data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                            data-category="<?= strtolower(htmlspecialchars($p['category'])) ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <span style="font-size:1.5rem">
                                        <?= htmlspecialchars($p['emoji'] ?: '🍽️') ?>
                                    </span>
                                    <div>
                                        <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
                                        <div style="font-size:0.75rem;color:var(--on-surface-variant)">
                                            <?= htmlspecialchars($p['description']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-category">
                                    <?= htmlspecialchars($p['category']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:0.95rem">
                                    EGP <?= number_format($p['price']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'Available' ? 'available' : 'unavailable' ?>
                                             status-badge" id="status-<?= (int)$p['id'] ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                            <td><?= (int)$p['total_orders'] ?> orders</td>
                            <td>
                                <div style="display:flex;gap:6px">
                                    <button class="btn btn-secondary btn-sm"
                                            onclick='openEditProduct(<?= json_encode([
                                                "id"       => (int)$p['id'],
                                                "name"     => $p['name'],
                                                "category" => $p['category'],
                                                "price"    => (int)$p['price'],
                                                "status"   => $p['status'],
                                                "emoji"    => $p['emoji'],
                                                "desc"     => $p['description'],
                                            ]) ?>)'>
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                    <button class="btn btn-secondary btn-sm"
                                            onclick="toggleProduct(<?= (int)$p['id'] ?>)"
                                            title="Toggle availability">
                                        <span class="material-symbols-outlined">
                                            <?= $p['status'] === 'Available' ? 'visibility_off' : 'visibility' ?>
                                        </span>
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                            onclick="deleteProduct(<?= (int)$p['id'] ?>)">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Visual Grid ──────────────────────────── -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <span class="material-symbols-outlined">grid_view</span>
                    Visual Catalog
                </div>
            </div>
            <div class="product-grid" id="products-grid">
                <?php foreach ($products as $p): ?>
                <div class="product-card" id="product-card-<?= (int)$p['id'] ?>">
                    <div class="product-img-wrap">
                        <?php if ($p['image'] ?? ''): ?>
                            <img src="<?= htmlspecialchars($p['image']) ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>
                            <span class="product-img-placeholder">
                                <?= htmlspecialchars($p['emoji'] ?: '🍽️') ?>
                            </span>
                        <?php endif; ?>
                        <span class="price-badge">EGP <?= number_format($p['price']) ?></span>
                    </div>
                    <div class="product-body">
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
                        <div class="product-footer">
                            <span class="badge badge-<?= $p['status'] === 'Available' ? 'available' : 'unavailable' ?>"
                                  style="font-size:0.7rem">
                                <?= htmlspecialchars($p['status']) ?>
                            </span>
                            <div class="product-actions">
                                <button class="btn btn-secondary btn-sm"
                                        onclick='openEditProduct(<?= json_encode([
                                            "id"       => (int)$p['id'],
                                            "name"     => $p['name'],
                                            "category" => $p['category'],
                                            "price"    => (int)$p['price'],
                                            "status"   => $p['status'],
                                            "emoji"    => $p['emoji'],
                                            "desc"     => $p['description'],
                                        ]) ?>)'>
                                    <span class="material-symbols-outlined" style="font-size:15px">edit</span>
                                </button>
                                <button class="btn btn-danger btn-sm"
                                        onclick="deleteProduct(<?= (int)$p['id'] ?>)">
                                    <span class="material-symbols-outlined" style="font-size:15px">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div><!-- /page-content -->
</main>

<!-- ── Add Product Modal ──────────────────────────── -->
<div class="modal-overlay" id="add-product-modal">
    <div class="modal">
        <h3>Add New Product</h3>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Product Name</label>
                <input class="form-control" id="new-name" type="text" placeholder="e.g. Espresso">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-control" id="new-category">
                    <?php foreach ($categories as $cat): ?>
                    <option><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                    <option>Other</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Price (EGP)</label>
                <input class="form-control" id="new-price" type="number" placeholder="e.g. 35">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="new-status">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Description</label>
                <input class="form-control" id="new-desc" type="text" placeholder="Brief description">
            </div>
            <div class="form-group">
                <label class="form-label">Emoji Icon</label>
                <input class="form-control" id="new-emoji" type="text" placeholder="e.g. ☕" maxlength="2">
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('add-product-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAddProduct()">
                <span class="material-symbols-outlined">add</span>Add Product
            </button>
        </div>
    </div>
</div>

<!-- ── Edit Product Modal ─────────────────────────── -->
<div class="modal-overlay" id="edit-product-modal">
    <div class="modal">
        <h3>Edit Product</h3>
        <input type="hidden" id="edit-id">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Product Name</label>
                <input class="form-control" id="edit-name" type="text">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-control" id="edit-category">
                    <?php foreach ($categories as $cat): ?>
                    <option><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Price (EGP)</label>
                <input class="form-control" id="edit-price" type="number">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="edit-status">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Description</label>
                <input class="form-control" id="edit-desc" type="text">
            </div>
            <div class="form-group">
                <label class="form-label">Emoji Icon</label>
                <input class="form-control" id="edit-emoji" type="text" maxlength="2">
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('edit-product-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitEditProduct()">
                <span class="material-symbols-outlined">save</span>Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Pass product catalog to JS for AI context -->
<script>
const PRODUCTS_CATALOG = <?= json_encode(array_map(fn($p) => [
    'name'     => $p['name'],
    'category' => $p['category'],
    'price'    => (int)$p['price'],
    'status'   => $p['status'],
    'orders'   => (int)$p['total_orders'],
], $products)) ?>;
</script>
<script src="admin.js"></script>
</body>
</html>
