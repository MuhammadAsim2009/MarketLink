<?php
/**
 * MarketLink - Customer Pre-Order Basket / Cart
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Cart Actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request session.');
        redirect(BASE_URL . 'customer/cart.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 1);

    if ($action === 'add_to_cart' && $pid > 0) {
        // Verify product in database
        $stmt = $pdo->prepare("SELECT product_id, name, quantity_available, is_sold_out FROM products WHERE product_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $pid]);
        $prod = $stmt->fetch();

        if ($prod && !$prod['is_sold_out'] && $prod['quantity_available'] > 0) {
            $existing_qty = $_SESSION['cart'][$pid] ?? 0;
            $new_qty = $existing_qty + $qty;

            // Cap at available stock
            if ($new_qty > $prod['quantity_available']) {
                $new_qty = $prod['quantity_available'];
                set_flash('warning', "Quantity adjusted to the maximum available stock ({$prod['quantity_available']}) for {$prod['name']}.");
            } else {
                set_flash('success', "Added {$qty} item(s) of {$prod['name']} to your pre-order basket.");
            }
            $_SESSION['cart'][$pid] = $new_qty;
        } else {
            set_flash('danger', 'This item is sold out or unavailable.');
        }
        redirect(BASE_URL . 'customer/cart.php');
    }

    if ($action === 'update_qty' && $pid > 0) {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$pid]);
            set_flash('info', 'Item removed from basket.');
        } else {
            // Verify against live stock
            $stmt = $pdo->prepare("SELECT name, quantity_available FROM products WHERE product_id = :pid");
            $stmt->execute([':pid' => $pid]);
            $prod = $stmt->fetch();

            if ($prod) {
                if ($qty > $prod['quantity_available']) {
                    $_SESSION['cart'][$pid] = $prod['quantity_available'];
                    set_flash('warning', "Max available quantity for {$prod['name']} is {$prod['quantity_available']}.");
                } else {
                    $_SESSION['cart'][$pid] = $qty;
                    set_flash('success', 'Basket updated.');
                }
            }
        }
        redirect(BASE_URL . 'customer/cart.php');
    }

    if ($action === 'remove_item' && $pid > 0) {
        unset($_SESSION['cart'][$pid]);
        set_flash('info', 'Item removed from basket.');
        redirect(BASE_URL . 'customer/cart.php');
    }

    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        set_flash('info', 'Your pre-order basket is cleared.');
        redirect(BASE_URL . 'customer/cart.php');
    }
}

// Fetch details for all items currently in cart
$cart_items = [];
$total_amount = 0.00;
$has_stock_issue = false;

if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $product_ids = array_keys($_SESSION['cart']);

    try {
        $stmt = $pdo->prepare("SELECT p.*, u.name as farmer_name, fp.stall_name, fp.operating_days, fp.pickup_window_start, fp.pickup_window_end 
                              FROM products p 
                              JOIN users u ON p.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE p.product_id IN ($placeholders)");
        $stmt->execute($product_ids);
        $fetched_prods = $stmt->fetchAll();

        foreach ($fetched_prods as $p) {
            $pid = $p['product_id'];
            $requested_qty = $_SESSION['cart'][$pid];
            $is_available = (!$p['is_sold_out'] && $p['quantity_available'] > 0);
            $actual_qty = min($requested_qty, $p['quantity_available']);

            if ($actual_qty != $requested_qty || !$is_available) {
                $has_stock_issue = true;
            }

            $line_total = $p['price'] * $actual_qty;
            $total_amount += $line_total;

            $cart_items[] = [
                'product' => $p,
                'requested_qty' => $requested_qty,
                'actual_qty' => $actual_qty,
                'line_total' => $line_total,
                'is_available' => $is_available
            ];
        }
    } catch (PDOException $e) {
        error_log("Cart data load error: " . $e->getMessage());
    }
}

$page_title = 'Pre-Order Basket';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cart3 text-primary me-2"></i>My Pre-Order Basket</h1>
            <p class="text-muted small mb-0">Review your farm-fresh items before choosing pickup schedules</p>
        </div>
        <?php if (!empty($cart_items)): ?>
            <form action="<?= BASE_URL ?>customer/cart.php" method="POST" onsubmit="return confirm('Clear all items from your basket?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_cart">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3 me-1"></i> Clear Basket
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($has_stock_issue): ?>
        <div class="alert alert-warning py-3 mb-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Some quantities in your basket exceed current available farmer inventory and have been capped to available amounts.
        </div>
    <?php endif; ?>

    <?php if (!empty($cart_items)): ?>
        <div class="row g-4">
            <!-- Basket Table -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6">Selected Harvest Items (<?= count($cart_items) ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Produce</th>
                                        <th>Farmer / Stall</th>
                                        <th>Price</th>
                                        <th style="width: 150px;">Quantity</th>
                                        <th>Total</th>
                                        <th class="text-end">Remove</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): 
                                        $p = $item['product'];
                                    ?>
                                        <tr class="<?= !$item['is_available'] ? 'table-danger' : '' ?>">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded bg-light d-flex align-items-center justify-content-center border" style="width: 44px; height: 44px; flex-shrink: 0;">
                                                        <?php if (!empty($p['image_url'])): ?>
                                                            <img src="<?= BASE_URL . e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
                                                        <?php else: ?>
                                                            <i class="bi bi-egg-fried fs-5 text-primary"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $p['product_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                            <?= e($p['name']) ?>
                                                        </a>
                                                        <?php if (!$item['is_available']): ?>
                                                            <span class="badge bg-danger ms-1">Sold Out</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small text-muted">
                                                <?= e($p['stall_name'] ?: $p['farmer_name']) ?>
                                            </td>
                                            <td class="fw-semibold text-primary">
                                                <?= format_currency($p['price']) ?> <small class="text-muted fw-normal">/ <?= e($p['unit']) ?></small>
                                            </td>
                                            <td>
                                                <form action="<?= BASE_URL ?>customer/cart.php" method="POST" class="d-flex align-items-center gap-1">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_qty">
                                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                                    <input type="number" name="quantity" value="<?= $item['actual_qty'] ?>" min="1" max="<?= $p['quantity_available'] ?>" class="form-control form-control-sm text-center" style="width: 70px;" onchange="this.form.submit()">
                                                    <small class="text-muted"><?= e($p['unit']) ?></small>
                                                </form>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($item['line_total']) ?>
                                            </td>
                                            <td class="text-end">
                                                <form action="<?= BASE_URL ?>customer/cart.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="remove_item">
                                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Item">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Add More Items
                    </a>
                </div>
            </div>

            <!-- Order Summary & Checkout Card -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6">Pre-Order Summary</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Estimated Subtotal:</span>
                            <span class="fw-bold"><?= format_currency($total_amount) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 text-success small">
                            <span>Pre-Order Service Fee:</span>
                            <span>$0.00 (Free)</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fs-6 fw-bold">Total (Pay at Pickup):</span>
                            <span class="fs-4 fw-bold text-primary"><?= format_currency($total_amount) ?></span>
                        </div>

                        <div class="p-3 bg-light rounded border mb-4 small text-muted">
                            <i class="bi bi-info-circle-fill text-primary me-1"></i>
                            <strong>No advance payment required.</strong> You reserve fresh inventory online and pay the farmer in person when collecting your packed basket.
                        </div>

                        <?php if (is_logged_in()): ?>
                            <a href="<?= BASE_URL ?>customer/checkout.php" class="btn btn-primary btn-lg w-100 py-2 shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Choose Pickup Schedule & Order
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-lg w-100 py-2 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Log In to Complete Pre-Order
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 p-5 text-center text-muted">
            <i class="bi bi-cart-x fs-1 text-secondary-subtle mb-3"></i>
            <h4>Your basket is empty</h4>
            <p class="small text-muted mb-4">Explore local farmers markets and reserve fresh fruit, veggies, and dairy for your weekend basket.</p>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm align-self-center px-4 py-2">
                <i class="bi bi-basket me-1"></i> Explore Fresh Produce
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
