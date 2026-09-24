<?php
/**
 * MarketLink - Farmer Product Management (CRUD)
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$farmer_id = get_logged_in_user_id();
$errors = [];
$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

// Product Categories predefined list
$categories = ['Vegetables', 'Fruits', 'Dairy & Eggs', 'Bakery & Honey', 'Herbs & Microgreens', 'Pantry & Preserves'];
$units = ['kg', 'bunch', 'box', 'dozen', 'piece', 'gram (500g)', 'litre', 'jar'];

// Handle POST actions (Create, Update, Delete, Toggle Sold Out, Quick Restock)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request session. Please try again.');
        redirect(BASE_URL . 'farmer/products.php');
    }

    $post_action = $_POST['form_action'] ?? '';

    // 1. ADD or EDIT PRODUCT
    if ($post_action === 'save_product') {
        $p_id = (int)($_POST['product_id'] ?? 0);
        $name = sanitize_input($_POST['name'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = sanitize_input($_POST['unit'] ?? 'kg');
        $quantity = (int)($_POST['quantity_available'] ?? 0);
        $description = sanitize_input($_POST['description'] ?? '');
        $image_url = sanitize_input($_POST['image_url'] ?? '');

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Product name is required.';
        }
        if (empty($category)) {
            $errors['category'] = 'Please select a category.';
        }
        if ($price <= 0) {
            $errors['price'] = 'Price must be greater than 0.';
        }
        if ($quantity < 0) {
            $errors['quantity'] = 'Quantity cannot be negative.';
        }

        // Optional File Upload handling
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

            if (in_array($file_ext, $allowed_exts)) {
                $upload_dir = __DIR__ . '/../assets/images/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = 'prod_' . $farmer_id . '_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $image_url = 'assets/images/products/' . $new_filename;
                }
            }
        }

        if (empty($errors)) {
            try {
                if ($p_id > 0) {
                    // Update - Strict Row-Level check (farmer_id must match)
                    $upd_stmt = $pdo->prepare("UPDATE products SET 
                        name = :name, category = :category, price = :price, unit = :unit, 
                        quantity_available = :quantity, description = :description, 
                        image_url = IF(:image_url != '', :image_url, image_url) 
                        WHERE product_id = :pid AND farmer_id = :fid");
                    $upd_stmt->execute([
                        ':name' => $name,
                        ':category' => $category,
                        ':price' => $price,
                        ':unit' => $unit,
                        ':quantity' => $quantity,
                        ':description' => $description,
                        ':image_url' => $image_url,
                        ':pid' => $p_id,
                        ':fid' => $farmer_id
                    ]);

                    set_flash('success', 'Product "' . $name . '" updated successfully.');
                } else {
                    // Create
                    $ins_stmt = $pdo->prepare("INSERT INTO products (farmer_id, name, category, price, unit, quantity_available, description, image_url, is_sold_out, created_at) 
                        VALUES (:fid, :name, :category, :price, :unit, :quantity, :description, :image_url, 0, NOW())");
                    $ins_stmt->execute([
                        ':fid' => $farmer_id,
                        ':name' => $name,
                        ':category' => $category,
                        ':price' => $price,
                        ':unit' => $unit,
                        ':quantity' => $quantity,
                        ':description' => $description,
                        ':image_url' => $image_url
                    ]);

                    set_flash('success', 'Product "' . $name . '" added to your inventory.');
                }
                redirect(BASE_URL . 'farmer/products.php');
            } catch (PDOException $e) {
                error_log("Product save error: " . $e->getMessage());
                $errors['general'] = 'Failed to save product to database.';
            }
        }
    }

    // 2. TOGGLE SOLD OUT
    if ($post_action === 'toggle_sold_out') {
        $p_id = (int)($_POST['product_id'] ?? 0);
        $current_sold_out = (int)($_POST['current_status'] ?? 0);
        $new_sold_out = $current_sold_out ? 0 : 1;

        try {
            $stmt = $pdo->prepare("UPDATE products SET is_sold_out = :status WHERE product_id = :pid AND farmer_id = :fid");
            $stmt->execute([
                ':status' => $new_sold_out,
                ':pid' => $p_id,
                ':fid' => $farmer_id
            ]);
            $msg = $new_sold_out ? 'Item marked as Sold Out.' : 'Item marked as In Stock.';
            set_flash('info', $msg);
        } catch (PDOException $e) {
            error_log("Toggle sold out error: " . $e->getMessage());
            set_flash('danger', 'Could not update status.');
        }
        redirect(BASE_URL . 'farmer/products.php');
    }

    // 3. DELETE PRODUCT
    if ($post_action === 'delete_product') {
        $p_id = (int)($_POST['product_id'] ?? 0);
        try {
            // Check if product is in active orders
            $chk = $pdo->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.product_id = :pid AND o.status IN ('placed', 'accepted', 'ready')");
            $chk->execute([':pid' => $p_id]);
            if ($chk->fetchColumn() > 0) {
                set_flash('danger', 'Cannot delete this product because it is part of pending active orders. Mark it sold out instead.');
            } else {
                $del = $pdo->prepare("DELETE FROM products WHERE product_id = :pid AND farmer_id = :fid");
                $del->execute([':pid' => $p_id, ':fid' => $farmer_id]);
                set_flash('success', 'Product deleted from inventory.');
            }
        } catch (PDOException $e) {
            error_log("Product delete error: " . $e->getMessage());
            set_flash('danger', 'Could not delete product.');
        }
        redirect(BASE_URL . 'farmer/products.php');
    }

    // 4. QUICK BULK RESTOCK / TEMPLATE
    if ($post_action === 'bulk_restock') {
        $add_qty = (int)($_POST['restock_amount'] ?? 10);
        if ($add_qty > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE products SET quantity_available = quantity_available + :qty, is_sold_out = 0 WHERE farmer_id = :fid");
                $stmt->execute([':qty' => $add_qty, ':fid' => $farmer_id]);
                set_flash('success', "Added +{$add_qty} stock to all your items and cleared sold-out flags for the weekend market!");
            } catch (PDOException $e) {
                set_flash('danger', 'Bulk restock failed.');
            }
        }
        redirect(BASE_URL . 'farmer/products.php');
    }
}

// Fetch single product for edit form if requested
$edit_product = null;
if ($action === 'edit' && $edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :pid AND farmer_id = :fid LIMIT 1");
        $stmt->execute([':pid' => $edit_id, ':fid' => $farmer_id]);
        $edit_product = $stmt->fetch();
        if (!$edit_product) {
            set_flash('danger', 'Product not found or unauthorized.');
            redirect(BASE_URL . 'farmer/products.php');
        }
    } catch (PDOException $e) {
        set_flash('danger', 'Database error.');
        redirect(BASE_URL . 'farmer/products.php');
    }
}

// Fetch list of farmer's products with filters
$filter_cat = sanitize_input($_GET['category'] ?? '');
$search_q = sanitize_input($_GET['q'] ?? '');

$sql = "SELECT * FROM products WHERE farmer_id = :fid";
$params = [':fid' => $farmer_id];

if (!empty($filter_cat)) {
    $sql .= " AND category = :category";
    $params[':category'] = $filter_cat;
}
if (!empty($search_q)) {
    $sql .= " AND (name LIKE :q1 OR description LIKE :q2)";
    $params[':q1'] = '%' . $search_q . '%';
    $params[':q2'] = '%' . $search_q . '%';
}
$sql .= " ORDER BY created_at DESC";

$products = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch farmer products error: " . $e->getMessage());
}

$page_title = ($action === 'add' ? 'Add Product' : ($action === 'edit' ? 'Edit Product' : 'Product Inventory'));
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-box-seam text-primary me-2"></i>My Stall Inventory</h1>
            <p class="text-muted small mb-0">Publish weekly harvest, set pricing, and manage available quantities</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($action === 'list'): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkRestockModal">
                    <i class="bi bi-arrow-repeat me-1"></i> Weekly Restock Tool
                </button>
                <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add New Harvest Item
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Inventory List
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add / Edit Product Form -->
        <div class="card shadow-sm border-0 max-w-700 mx-auto">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fs-6">
                    <i class="bi <?= $action === 'add' ? 'bi-plus-circle-fill text-primary' : 'bi-pencil-square text-accent' ?> me-2"></i>
                    <?= $action === 'add' ? 'Add Harvest Product to Stall' : 'Edit Product: ' . e($edit_product['name']) ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>farmer/products.php" method="POST" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="save_product">
                    <input type="hidden" name="product_id" value="<?= $edit_product['product_id'] ?? 0 ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($edit_product['name'] ?? ($_POST['name'] ?? '')) ?>" placeholder="e.g. Organic Heirloom Tomatoes" required>
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php 
                                $selected_cat = $edit_product['category'] ?? ($_POST['category'] ?? '');
                                foreach ($categories as $cat): 
                                ?>
                                    <option value="<?= e($cat) ?>" <?= $selected_cat === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= e($errors['category']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="price">Price (PKR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">PKR</span>
                                <input type="number" step="0.01" min="0.10" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" id="price" name="price" value="<?= e($edit_product['price'] ?? ($_POST['price'] ?? '')) ?>" placeholder="0.00" required>
                            </div>
                            <?php if (isset($errors['price'])): ?><div class="text-danger small mt-1"><?= e($errors['price']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="unit">Unit Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="unit" name="unit">
                                <?php 
                                $selected_unit = $edit_product['unit'] ?? ($_POST['unit'] ?? 'kg');
                                foreach ($units as $u): 
                                ?>
                                    <option value="<?= e($u) ?>" <?= $selected_unit === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="quantity_available">Stock Available</label>
                            <input type="number" min="0" class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" id="quantity_available" name="quantity_available" value="<?= e($edit_product['quantity_available'] ?? ($_POST['quantity_available'] ?? 20)) ?>">
                            <?php if (isset($errors['quantity'])): ?><div class="invalid-feedback"><?= e($errors['quantity']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Harvest Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Tell customers about freshness, farming practices (e.g. pesticide-free, picked this morning)"><?= e($edit_product['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="product_image">Product Image (Optional)</label>
                        <input type="file" class="form-control mb-2" id="product_image" name="product_image" accept="image/*">
                        <input type="text" class="form-control small" id="image_url" name="image_url" value="<?= e($edit_product['image_url'] ?? ($_POST['image_url'] ?? '')) ?>" placeholder="Or enter an image web URL / path">
                        <?php if (!empty($edit_product['image_url'])): ?>
                            <div class="mt-2 small text-muted">
                                Current image: <code><?= e($edit_product['image_url']) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> <?= $action === 'add' ? 'Publish Item' : 'Save Changes' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>

        <!-- Filters & Search Bar -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form action="<?= BASE_URL ?>farmer/products.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control" placeholder="Search your stock by name..." value="<?= e($search_q) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= $filter_cat === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <?php if (!empty($search_q) || !empty($filter_cat)): ?>
                            <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fs-6">
                    <i class="bi bi-list-ul me-1 text-primary"></i> Harvest Listings (<?= count($products) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($products)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Price / Unit</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr class="<?= $p['is_sold_out'] ? 'opacity-75 bg-light' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded bg-light d-flex align-items-center justify-content-center text-secondary border" style="width: 48px; height: 48px; flex-shrink: 0;">
                                                    <?php if (!empty($p['image_url'])): ?>
                                                        <img src="<?= BASE_URL . e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
                                                    <?php else: ?>
                                                        <i class="bi bi-egg-fried fs-4"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= e($p['name']) ?></div>
                                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?= e($p['description'] ?: 'No description') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?= e($p['category']) ?></span></td>
                                        <td class="fw-semibold text-primary">
                                            <?= format_currency($p['price']) ?> <span class="text-muted small fw-normal">/ <?= e($p['unit']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($p['quantity_available'] <= 5 && !$p['is_sold_out']): ?>
                                                <span class="badge bg-danger-subtle text-danger"><?= $p['quantity_available'] ?> left</span>
                                            <?php else: ?>
                                                <span class="fw-semibold"><?= $p['quantity_available'] ?></span> <?= e($p['unit']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['is_sold_out']): ?>
                                                <span class="badge bg-danger">Sold Out</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Toggle Sold Out Form -->
                                                <form method="POST" action="<?= BASE_URL ?>farmer/products.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="toggle_sold_out">
                                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $p['is_sold_out'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= $p['is_sold_out'] ? 'btn-outline-success' : 'btn-outline-warning' ?>" title="<?= $p['is_sold_out'] ? 'Mark In Stock' : 'Mark Sold Out' ?>">
                                                        <i class="bi <?= $p['is_sold_out'] ? 'bi-check-circle' : 'bi-slash-circle' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Edit Button -->
                                                <a href="<?= BASE_URL ?>farmer/products.php?action=edit&id=<?= $p['product_id'] ?>" class="btn btn-light btn-sm border" title="Edit Item">
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <!-- Delete Button -->
                                                <form method="POST" action="<?= BASE_URL ?>farmer/products.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= e($p['name']) ?> from inventory?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Item">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-basket3 fs-1 text-secondary-subtle d-block mb-2"></i>
                        <h6>No harvest products found</h6>
                        <p class="small text-muted mb-3">Add items that you will be selling at the farmers market this week.</p>
                        <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Add First Item
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Bulk Weekly Restock Modal -->
<div class="modal fade" id="bulkRestockModal" tabindex="-1" aria-labelledby="bulkRestockLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>farmer/products.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="bulk_restock">
                <div class="modal-header">
                    <h5 class="modal-title fs-6" id="bulkRestockLabel"><i class="bi bi-arrow-repeat text-primary me-2"></i>Weekend Market Quick Restock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Preparing for this weekend's market? This utility adds stock to all of your listed items and resets any "Sold Out" tags back to "Available".
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="restock_amount">Add Quantity to Every Item</label>
                        <input type="number" min="1" max="500" class="form-control" id="restock_amount" name="restock_amount" value="15" required>
                        <div class="form-text small">E.g., enter 15 to increase every product's stock by +15.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Apply Weekend Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
