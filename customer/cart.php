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
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                   || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
        $msg = 'Basket updated.';
        $msg_type = 'success';

        if ($qty <= 0) {
            unset($_SESSION['cart'][$pid]);
            $msg = 'Item removed from basket.';
            $msg_type = 'info';
        } else {
            // Verify against live stock
            $stmt = $pdo->prepare("SELECT name, quantity_available FROM products WHERE product_id = :pid");
            $stmt->execute([':pid' => $pid]);
            $prod = $stmt->fetch();

            if ($prod) {
                if ($qty > $prod['quantity_available']) {
                    $_SESSION['cart'][$pid] = $prod['quantity_available'];
                    $msg = "Max available quantity for {$prod['name']} is {$prod['quantity_available']}.";
                    $msg_type = 'warning';
                } else {
                    $_SESSION['cart'][$pid] = $qty;
                    $msg = 'Basket updated.';
                    $msg_type = 'success';
                }
            }
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            $line_total = 0;
            $overall_total = 0;
            $total_items = array_sum($_SESSION['cart']);

            if (!empty($_SESSION['cart'])) {
                $ph = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
                $st = $pdo->prepare("SELECT product_id, price FROM products WHERE product_id IN ($ph)");
                $st->execute(array_keys($_SESSION['cart']));
                $prods = $st->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($_SESSION['cart'] as $c_pid => $c_qty) {
                    if (isset($prods[$c_pid])) {
                        $item_sub = $prods[$c_pid] * $c_qty;
                        $overall_total += $item_sub;
                        if ($c_pid == $pid) {
                            $line_total = $item_sub;
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'msg_type' => $msg_type,
                'qty' => $_SESSION['cart'][$pid] ?? 0,
                'cart_count' => $total_items,
                'line_total' => format_currency($line_total),
                'subtotal' => format_currency($overall_total),
                'total_amount' => format_currency($overall_total)
            ]);
            exit;
        }

        set_flash($msg_type, $msg);
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
                                        <th style="width: 165px;">Quantity</th>
                                        <th>Total</th>
                                        <th class="text-end">Remove</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): 
                                        $p = $item['product'];
                                    ?>
                                        <tr id="cart-row-<?= $p['product_id'] ?>" class="<?= !$item['is_available'] ? 'table-danger' : '' ?>">
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
                                                <form action="<?= BASE_URL ?>customer/cart.php" method="POST" class="d-flex align-items-center gap-1 cart-qty-form" data-product-id="<?= $p['product_id'] ?>" data-price="<?= (float)$p['price'] ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_qty">
                                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                                    <div class="input-group input-group-sm" style="width: 110px;">
                                                        <button class="btn btn-outline-secondary btn-qty-change px-2" type="button" data-delta="-1" style="border-color: var(--border);"><i class="bi bi-dash"></i></button>
                                                        <input type="number" name="quantity" value="<?= $item['actual_qty'] ?>" min="1" max="<?= $p['quantity_available'] ?>" class="form-control text-center px-1 cart-qty-input" data-product-id="<?= $p['product_id'] ?>" style="min-width: 38px;">
                                                        <button class="btn btn-outline-secondary btn-qty-change px-2" type="button" data-delta="1" style="border-color: var(--border);"><i class="bi bi-plus"></i></button>
                                                    </div>
                                                    <small class="text-muted ms-1"><?= e($p['unit']) ?></small>
                                                </form>
                                            </td>
                                            <td class="fw-bold text-primary" id="line-total-<?= $p['product_id'] ?>">
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
                            <span class="fw-bold" id="cart-subtotal"><?= format_currency($total_amount) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 text-success small">
                            <span>Pre-Order Service Fee:</span>
                            <span>PKR 0.00 (Free)</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fs-6 fw-bold">Total (Pay at Pickup):</span>
                            <span class="fs-4 fw-bold text-primary" id="cart-grandtotal"><?= format_currency($total_amount) ?></span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const debounceTimers = {};
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Quantity stepper buttons
    document.querySelectorAll('.btn-qty-change').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const delta = parseInt(this.dataset.delta, 10) || 0;
            const form = this.closest('.cart-qty-form');
            if (!form) return;
            const input = form.querySelector('.cart-qty-input');
            if (!input) return;

            const min = parseInt(input.min, 10) || 1;
            const max = parseInt(input.max, 10) || 999;
            let currentVal = parseInt(input.value, 10) || 1;
            let newVal = currentVal + delta;

            if (newVal < min) newVal = min;
            if (newVal > max) {
                newVal = max;
                if (window.showToast) {
                    window.showToast(`Maximum available stock reached (${max})`, 'warning');
                }
            }

            input.value = newVal;
            triggerDebouncedUpdate(form, input);
        });
    });

    // Direct input change / keystroke
    document.querySelectorAll('.cart-qty-input').forEach(input => {
        input.addEventListener('input', function() {
            const form = this.closest('.cart-qty-form');
            if (!form) return;
            triggerDebouncedUpdate(form, this);
        });
    });

    function triggerDebouncedUpdate(form, input) {
        const pid = form.dataset.productId;
        const price = parseFloat(form.dataset.price) || 0;
        let qty = parseInt(input.value, 10);
        const max = parseInt(input.max, 10) || 999;

        if (isNaN(qty) || qty < 1) {
            qty = 1;
        } else if (qty > max) {
            qty = max;
            input.value = max;
        }

        // Snap client-side preview for line total
        const lineTotalEl = document.getElementById(`line-total-${pid}`);
        if (lineTotalEl) {
            lineTotalEl.textContent = 'PKR ' + (price * qty).toFixed(2);
        }

        // Clear existing debounce timer for this product
        if (debounceTimers[pid]) {
            clearTimeout(debounceTimers[pid]);
        }

        // Set 500ms debounce
        debounceTimers[pid] = setTimeout(() => {
            sendQtyUpdate(pid, qty, form);
        }, 500);
    }

    function sendQtyUpdate(pid, qty, form) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'update_qty');
        formData.append('product_id', pid);
        formData.append('quantity', qty);
        formData.append('is_ajax', '1');

        fetch('<?= BASE_URL ?>customer/cart.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                // Update line total with formatted value
                const lineTotalEl = document.getElementById(`line-total-${pid}`);
                if (lineTotalEl && data.line_total) {
                    lineTotalEl.textContent = data.line_total;
                }
                // Update subtotal & grand total
                const subtotalEl = document.getElementById('cart-subtotal');
                if (subtotalEl && data.subtotal) {
                    subtotalEl.textContent = data.subtotal;
                }
                const grandTotalEl = document.getElementById('cart-grandtotal');
                if (grandTotalEl && data.total_amount) {
                    grandTotalEl.textContent = data.total_amount;
                }
                // Update navbar cart badge
                if (window.updateCartBadge && typeof data.cart_count !== 'undefined') {
                    window.updateCartBadge(data.cart_count);
                }
                // Show debounced single toast notification
                if (window.showToast) {
                    window.showToast(data.message || 'Basket updated.', data.msg_type || 'success');
                }
            }
        })
        .catch(err => {
            console.error('Cart update error:', err);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
