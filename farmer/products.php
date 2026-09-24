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
        $image_url = trim(sanitize_input($_POST['image_url'] ?? ''), " \t\n\r\0\x0B\"'");

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
                        ':image_url' => !empty($image_url) ? $image_url : null
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

// Compute product summary metrics
$in_stock_count = 0;
$sold_out_count = 0;
$low_stock_count = 0;
foreach ($products as $p) {
    if ($p['is_sold_out'] || (int)$p['quantity_available'] <= 0) {
        $sold_out_count++;
    } elseif ((int)$p['quantity_available'] <= 5) {
        $low_stock_count++;
        $in_stock_count++;
    } else {
        $in_stock_count++;
    }
}

$active_nav = 'products';
$page_title = ($action === 'add' ? 'Add Produce Item' : ($action === 'edit' ? 'Edit Produce Item' : 'Harvest & Products'));
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Harvest & Products</h1>
        <p class="text-muted small mb-0">Manage produce catalog, set PKR pricing per unit, and adjust stock quantities</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($action === 'list'): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#bulkRestockModal">
                <i class="bi bi-arrow-repeat me-1 text-success"></i> Bulk Restock Tool
            </button>
            <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm rounded-3">
                <i class="bi bi-plus-circle me-1"></i> Add New Harvest Item
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-secondary btn-sm rounded-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Products
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger py-2 small mb-3 border-0 shadow-xs"><?= e($errors['general']) ?></div>
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
                        <label class="form-label fw-semibold" for="product_image">Product Image (Optional)</label>
                        <div class="row g-3 align-items-start">
                            <div class="col-md-8">
                                <label class="form-label small text-muted mb-1" for="product_image">Upload from Computer</label>
                                <input type="file" class="form-control mb-2" id="product_image" name="product_image" accept="image/*">
                                
                                <label class="form-label small text-muted mb-1" for="image_url">Or Web Image URL / Path</label>
                                <div class="input-group mb-1">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control" id="image_url" name="image_url" value="<?= e($edit_product['image_url'] ?? ($_POST['image_url'] ?? '')) ?>" placeholder="https://images.unsplash.com/... or assets/images/...">
                                </div>
                                <div class="form-text small mb-2">Paste any direct image link or web address. Preview updates automatically.</div>
                                
                                <!-- Quick Sample Produce Presets -->
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                                    <small class="text-muted me-1" style="font-size: 0.75rem;">Quick Samples:</small>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=600&auto=format&fit=crop&q=80">Tomatoes</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=600&auto=format&fit=crop&q=80">Apples</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=600&auto=format&fit=crop&q=80">Carrots</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1582722872446-47dc5c78a088?w=600&auto=format&fit=crop&q=80">Eggs</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=600&auto=format&fit=crop&q=80">Honey</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill sample-img-btn" style="font-size: 0.75rem;" data-url="https://images.unsplash.com/photo-1550583724-b2692b85b150?w=600&auto=format&fit=crop&q=80">Milk</button>
                                </div>

                                <?php if (!empty($edit_product['image_url'])): ?>
                                    <div class="mt-2 small text-muted">
                                        Saved Path: <code class="text-break"><?= e($edit_product['image_url']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="border rounded-3 p-1 bg-light d-flex align-items-center justify-content-center mx-auto shadow-sm position-relative" style="width: 100%; height: 140px; overflow: hidden; max-width: 220px;">
                                        <img id="image_preview" 
                                             src="<?= !empty($edit_product['image_url']) ? e(get_image_url($edit_product['image_url'])) : 'https://placehold.co/300x200?text=No+Image' ?>" 
                                             alt="Preview" 
                                             class="w-100 h-100 rounded object-fit-cover"
                                             onerror="this.onerror=null;this.src='https://placehold.co/300x200?text=Invalid+Image';">
                                        <div id="preview_status" class="position-absolute bottom-0 start-0 end-0 py-1 px-2 small text-white bg-dark bg-opacity-75 d-none" style="font-size: 0.7rem;"></div>
                                    </div>
                                    <span class="small text-muted d-block mt-1"><i class="bi bi-eye me-1"></i>Image Live Preview</span>
                                </div>
                            </div>
                        </div>
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
                                                <div class="rounded bg-light d-flex align-items-center justify-content-center text-secondary border" style="width: 48px; height: 48px; flex-shrink: 0; overflow: hidden;">
                                                    <?php if (!empty($p['image_url'])): ?>
                                                        <img src="<?= e(get_image_url($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
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
                                                <!-- View Button -->
                                                <button type="button" class="btn btn-light btn-sm border view-product-btn" title="View Product Details"
                                                        data-id="<?= $p['product_id'] ?>"
                                                        data-name="<?= e($p['name']) ?>"
                                                        data-category="<?= e($p['category']) ?>"
                                                        data-price="<?= e(format_currency($p['price'])) ?>"
                                                        data-unit="<?= e($p['unit']) ?>"
                                                        data-quantity="<?= (int)$p['quantity_available'] ?>"
                                                        data-status="<?= $p['is_sold_out'] ? 'sold_out' : 'available' ?>"
                                                        data-image="<?= e(get_image_url($p['image_url'])) ?>"
                                                        data-description="<?= e($p['description']) ?>"
                                                        data-created="<?= e(format_date($p['created_at'])) ?>"
                                                        data-edit-url="<?= BASE_URL ?>farmer/products.php?action=edit&id=<?= $p['product_id'] ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>

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

<!-- View Product Modal Dialog -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-labelledby="viewProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span id="vpCategoryBadge" class="badge bg-secondary-subtle text-secondary">Category</span>
                    <span id="vpStatusBadge" class="badge bg-success">Available</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-start">
                    <!-- Image Showcase -->
                    <div class="col-md-5">
                        <div class="border rounded-4 overflow-hidden bg-light d-flex align-items-center justify-content-center shadow-xs position-relative" style="height: 240px;">
                            <img id="vpImage" src="" alt="Product" class="w-100 h-100 object-fit-cover" onerror="this.onerror=null;this.src='https://placehold.co/400x300?text=Produce';">
                        </div>
                    </div>
                    <!-- Details Column -->
                    <div class="col-md-7">
                        <h4 id="vpName" class="h5 fw-bold mb-2 text-dark">Product Name</h4>
                        
                        <div class="d-flex align-items-baseline gap-2 mb-3">
                            <span id="vpPrice" class="fs-4 fw-bold text-primary">PKR 0.00</span>
                            <span class="text-muted fs-6">/ <span id="vpUnit">kg</span></span>
                        </div>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small d-block">Available Stock:</span>
                                    <strong id="vpStock" class="text-dark fs-6">0</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Listed Date:</span>
                                    <strong id="vpCreated" class="text-dark small">—</strong>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h6 class="small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Harvest Description</h6>
                            <p id="vpDescription" class="small text-secondary mb-0" style="line-height: 1.6; white-space: pre-line;">No description provided.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-between">
                <div class="small text-muted">
                    Product ID: <code id="vpId" class="fw-semibold">#0</code>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
                    <a id="vpEditBtn" href="#" class="btn btn-primary btn-sm rounded-3">
                        <i class="bi bi-pencil me-1"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Weekly Restock Modal -->
<div class="modal fade" id="bulkRestockModal" tabindex="-1" aria-labelledby="bulkRestockLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="<?= BASE_URL ?>farmer/products.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="bulk_restock">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fs-6 fw-bold" id="bulkRestockLabel"><i class="bi bi-arrow-repeat text-primary me-2"></i>Weekend Market Quick Restock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Preparing for this weekend's market? This utility adds stock to all of your listed items and resets any "Sold Out" tags back to "Available".
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="restock_amount">Add Quantity to Every Item</label>
                        <input type="number" min="1" max="500" class="form-control" id="restock_amount" name="restock_amount" value="15" required>
                        <div class="form-text small">E.g., enter 15 to increase every product's stock by +15.</div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-3">Apply Weekend Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('image_url');
    const fileInput = document.getElementById('product_image');
    const previewImg = document.getElementById('image_preview');
    const previewStatus = document.getElementById('preview_status');
    const sampleBtns = document.querySelectorAll('.sample-img-btn');
    const baseUrl = '<?= BASE_URL ?>';

    if (previewImg) {
        function showStatus(text, isError) {
            if (previewStatus) {
                if (text) {
                    previewStatus.textContent = text;
                    previewStatus.className = 'position-absolute bottom-0 start-0 end-0 py-1 px-2 small text-white ' + (isError ? 'bg-danger' : 'bg-success');
                    previewStatus.classList.remove('d-none');
                } else {
                    previewStatus.classList.add('d-none');
                }
            }
        }

        function updatePreviewFromUrl(url) {
            url = (url || '').trim().replace(/^["']|["']$/g, '');
            if (!url) {
                previewImg.onerror = null;
                previewImg.onload = null;
                previewImg.src = 'https://placehold.co/300x200?text=No+Image';
                showStatus('', false);
                return;
            }

            showStatus('Loading preview...', false);

            previewImg.onload = function() {
                if (previewImg.src.includes('placehold.co')) {
                    showStatus('', false);
                } else {
                    showStatus('Image Ready ✓', false);
                }
            };

            previewImg.onerror = function() {
                this.onerror = null;
                this.src = 'https://placehold.co/300x200?text=Invalid+Image+Link';
                showStatus('Cannot load image link', true);
            };

            if (/^(https?:\/\/|\/\/|data:image\/)/i.test(url)) {
                previewImg.src = url;
            } else {
                const clean = url.replace(/^[/\\]+/, '');
                previewImg.src = baseUrl + clean;
            }
        }

        if (urlInput) {
            ['input', 'change', 'paste', 'keyup', 'blur'].forEach(function(evt) {
                urlInput.addEventListener(evt, function() {
                    setTimeout(function() {
                        updatePreviewFromUrl(urlInput.value);
                    }, 50);
                });
            });

            if (urlInput.value.trim()) {
                updatePreviewFromUrl(urlInput.value);
            }
        }

        // Quick sample produce presets
        sampleBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const sampleUrl = this.getAttribute('data-url');
                if (urlInput && sampleUrl) {
                    urlInput.value = sampleUrl;
                    updatePreviewFromUrl(sampleUrl);
                }
            });
        });

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.onerror = null;
                            previewImg.onload = null;
                            previewImg.src = e.target.result;
                            showStatus('Local File Selected ✓', false);
                        };
                        reader.readAsDataURL(file);
                    }
                } else if (urlInput && urlInput.value) {
                    updatePreviewFromUrl(urlInput.value);
                }
            });
        }
    }

    // View Product Modal Trigger Handler
    const viewModalEl = document.getElementById('viewProductModal');
    if (viewModalEl) {
        const viewModal = new bootstrap.Modal(viewModalEl);
        document.querySelectorAll('.view-product-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const category = this.getAttribute('data-category');
                const price = this.getAttribute('data-price');
                const unit = this.getAttribute('data-unit');
                const quantity = parseInt(this.getAttribute('data-quantity'), 10) || 0;
                const status = this.getAttribute('data-status');
                const image = this.getAttribute('data-image');
                const description = this.getAttribute('data-description');
                const created = this.getAttribute('data-created');
                const editUrl = this.getAttribute('data-edit-url');

                document.getElementById('vpId').textContent = '#' + id;
                document.getElementById('vpName').textContent = name;
                document.getElementById('vpCategoryBadge').textContent = category;
                document.getElementById('vpPrice').textContent = price;
                document.getElementById('vpUnit').textContent = unit;
                document.getElementById('vpStock').textContent = quantity + ' ' + unit;
                document.getElementById('vpCreated').textContent = created || '—';
                document.getElementById('vpDescription').textContent = description ? description : 'No specific harvest description provided.';
                
                const vpImg = document.getElementById('vpImage');
                vpImg.src = image || 'https://placehold.co/400x300?text=Produce';
                
                document.getElementById('vpEditBtn').href = editUrl;

                const statusBadge = document.getElementById('vpStatusBadge');
                if (status === 'sold_out') {
                    statusBadge.className = 'badge bg-danger';
                    statusBadge.textContent = 'Sold Out';
                } else {
                    statusBadge.className = 'badge bg-success';
                    statusBadge.textContent = 'Available';
                }

                viewModal.show();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
