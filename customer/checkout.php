<?php
/**
 * MarketLink - Pre-Order Checkout & Pickup Slot Selection
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$customer_id = get_logged_in_user_id();

if (empty($_SESSION['cart'])) {
    set_flash('warning', 'Your basket is empty. Add fresh produce before checking out.');
    redirect(BASE_URL . 'customer/browse-products.php');
}

// Fetch all products in cart and group by Farmer
$cart_by_farmer = [];
$placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
$product_ids = array_keys($_SESSION['cart']);

try {
    $stmt = $pdo->prepare("SELECT p.*, u.name as farmer_name, fp.stall_name, fp.address as stall_address, 
                          fp.operating_days, fp.pickup_window_start, fp.pickup_window_end, fp.order_cutoff_hours 
                          FROM products p 
                          JOIN users u ON p.farmer_id = u.user_id 
                          LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                          WHERE p.product_id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $fid = $p['farmer_id'];
        if (!isset($cart_by_farmer[$fid])) {
            $cart_by_farmer[$fid] = [
                'farmer_id' => $fid,
                'farmer_name' => $p['farmer_name'],
                'stall_name' => $p['stall_name'] ?: $p['farmer_name'],
                'stall_address' => $p['stall_address'] ?: 'Market Stall',
                'operating_days' => $p['operating_days'] ?: 'Sat,Sun',
                'pickup_start' => $p['pickup_window_start'] ?: '08:00:00',
                'pickup_end' => $p['pickup_window_end'] ?: '14:00:00',
                'cutoff_hours' => (int)($p['order_cutoff_hours'] ?: 2),
                'items' => [],
                'farmer_total' => 0.00
            ];
        }
        $qty = min($_SESSION['cart'][$p['product_id']], $p['quantity_available']);
        $line_total = $p['price'] * $qty;
        $cart_by_farmer[$fid]['farmer_total'] += $line_total;
        $cart_by_farmer[$fid]['items'][] = [
            'product' => $p,
            'quantity' => $qty,
            'line_total' => $line_total
        ];
    }
} catch (PDOException $e) {
    error_log("Checkout items fetch error: " . $e->getMessage());
}

$errors = [];

// Handle Pre-Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid form session. Please try again.';
    } else {
        $pickup_dates = $_POST['pickup_date'] ?? [];
        $pickup_slots = $_POST['pickup_slot'] ?? [];

        // Validate that every farmer order has a date and slot
        foreach ($cart_by_farmer as $fid => $f_data) {
            $p_date = sanitize_input($pickup_dates[$fid] ?? '');
            $p_slot = sanitize_input($pickup_slots[$fid] ?? '');

            if (empty($p_date)) {
                $errors["date_{$fid}"] = "Please choose a pickup date for {$f_data['stall_name']}.";
            }
            if (empty($p_slot)) {
                $errors["slot_{$fid}"] = "Please choose a pickup time slot for {$f_data['stall_name']}.";
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $placed_order_ids = [];

                foreach ($cart_by_farmer as $fid => $f_data) {
                    $p_date = sanitize_input($pickup_dates[$fid]);
                    $p_slot = sanitize_input($pickup_slots[$fid]);
                    $total = $f_data['farmer_total'];

                    // 1. Insert Order
                    $ord_stmt = $pdo->prepare("INSERT INTO orders (customer_id, farmer_id, pickup_date, pickup_slot, status, total_amount, created_at) 
                                              VALUES (:cid, :fid, :p_date, :p_slot, 'placed', :total, NOW())");
                    $ord_stmt->execute([
                        ':cid' => $customer_id,
                        ':fid' => $fid,
                        ':p_date' => $p_date,
                        ':p_slot' => $p_slot,
                        ':total' => $total
                    ]);
                    $order_id = (int)$pdo->lastInsertId();
                    $placed_order_ids[] = $order_id;

                    // 2. Insert Order Items & Decrement Inventory
                    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (:oid, :pid, :qty, :price)");
                    $stock_stmt = $pdo->prepare("UPDATE products 
                                                 SET quantity_available = GREATEST(0, quantity_available - :qty1), 
                                                     is_sold_out = CASE WHEN (quantity_available - :qty2) <= 0 THEN 1 ELSE is_sold_out END 
                                                 WHERE product_id = :pid");

                    foreach ($f_data['items'] as $item) {
                        $p = $item['product'];
                        $qty = $item['quantity'];

                        $item_stmt->execute([
                            ':oid' => $order_id,
                            ':pid' => $p['product_id'],
                            ':qty' => $qty,
                            ':price' => $p['price']
                        ]);

                        $stock_stmt->execute([
                            ':qty1' => $qty,
                            ':qty2' => $qty,
                            ':pid'  => $p['product_id']
                        ]);
                    }

                    // 3. Send in-app notification to Farmer
                    $customer_name = get_logged_in_user_name();
                    create_notification($pdo, $fid, "New Pre-Order #{$order_id} received from {$customer_name} for pickup on " . format_date($p_date) . " ({$p_slot}).");

                    // 4. Send in-app notification to Customer
                    create_notification($pdo, $customer_id, "Your pre-order #{$order_id} with {$f_data['stall_name']} has been placed successfully!");
                }

                $pdo->commit();

                // Clear Cart
                $_SESSION['cart'] = [];

                set_flash('success', 'Your pre-orders have been successfully reserved! The farmer will prepare your harvest pack.');
                redirect(BASE_URL . 'customer/orders.php');

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Order placement transaction failed: " . $e->getMessage());
                $errors['general'] = 'Failed to place pre-order due to a database error. Please try again.';
            }
        }
    }
}

$page_title = 'Pre-Order Checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-calendar-check text-primary me-2"></i>Schedule Pre-Order Pickup</h1>
            <p class="text-muted small mb-0">Select your weekend collection date and preferred time slot for each stall</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/cart.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Basket
            </a>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>customer/checkout.php" method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Pickup Schedule per Farmer -->
            <div class="col-lg-8">
                <?php foreach ($cart_by_farmer as $fid => $f_data): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 fs-6 text-primary fw-bold">
                                        <i class="bi bi-shop me-1"></i> <?= e($f_data['stall_name']) ?>
                                    </h5>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($f_data['stall_address']) ?> &bull; Market Days: <strong><?= e($f_data['operating_days']) ?></strong></small>
                                </div>
                                <span class="badge bg-light text-dark border"><?= count($f_data['items']) ?> item(s)</span>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <!-- Items List -->
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Produce</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th class="text-end">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($f_data['items'] as $item): 
                                            $p = $item['product'];
                                        ?>
                                            <tr>
                                                <td class="fw-semibold"><?= e($p['name']) ?></td>
                                                <td><?= $item['quantity'] ?> <?= e($p['unit']) ?></td>
                                                <td class="text-muted"><?= format_currency($p['price']) ?></td>
                                                <td class="text-end fw-bold text-primary"><?= format_currency($item['line_total']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <hr class="my-3 text-secondary-subtle">

                            <!-- Pickup Slot Selection -->
                            <h6 class="fw-bold mb-3 fs-6"><i class="bi bi-clock me-1 text-primary"></i> Select Pickup Schedule for this Stall</h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="date_<?= $fid ?>">Pickup Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control <?= isset($errors["date_{$fid}"]) ? 'is-invalid' : '' ?>" id="date_<?= $fid ?>" name="pickup_date[<?= $fid ?>]" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                                    <?php if (isset($errors["date_{$fid}"])): ?><div class="invalid-feedback"><?= e($errors["date_{$fid}"]) ?></div><?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" for="slot_<?= $fid ?>">Time Slot <span class="text-danger">*</span></label>
                                    <select class="form-select <?= isset($errors["slot_{$fid}"]) ? 'is-invalid' : '' ?>" id="slot_<?= $fid ?>" name="pickup_slot[<?= $fid ?>]" required>
                                        <option value="08:00 AM - 09:00 AM">08:00 AM - 09:00 AM</option>
                                        <option value="09:00 AM - 10:00 AM">09:00 AM - 10:00 AM</option>
                                        <option value="10:00 AM - 11:00 AM" selected>10:00 AM - 11:00 AM</option>
                                        <option value="11:00 AM - 12:00 PM">11:00 AM - 12:00 PM</option>
                                        <option value="12:00 PM - 01:00 PM">12:00 PM - 01:00 PM</option>
                                        <option value="01:00 PM - 02:00 PM">01:00 PM - 02:00 PM</option>
                                    </select>
                                    <div class="form-text small">Stall pickup window: <?= date('h:i A', strtotime($f_data['pickup_start'])) ?> - <?= date('h:i A', strtotime($f_data['pickup_end'])) ?></div>
                                    <?php if (isset($errors["slot_{$fid}"])): ?><div class="invalid-feedback"><?= e($errors["slot_{$fid}"]) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pre-Order Confirmation Box -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6">Pre-Order Summary</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="small text-muted d-block">Ordering Customer:</label>
                            <span class="fw-bold"><?= e(get_logged_in_user_name()) ?></span> (<?= e($_SESSION['email'] ?? '') ?>)
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Produce Stalls:</span>
                            <span class="fw-bold"><?= count($cart_by_farmer) ?> stall(s)</span>
                        </div>

                        <hr class="my-2">

                        <?php 
                        $grand_total = 0;
                        foreach ($cart_by_farmer as $f) {
                            $grand_total += $f['farmer_total'];
                        }
                        ?>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bold">Total Amount:</span>
                            <span class="fs-4 fw-bold text-primary"><?= format_currency($grand_total) ?></span>
                        </div>

                        <div class="p-3 bg-light rounded border mb-4 small text-muted">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-wallet2 text-primary me-1"></i> Payment at Pickup:</div>
                            Orders are reserved online and settled in person when you collect your harvest at the farmer's market stall.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 shadow">
                            <i class="bi bi-check2-circle me-1"></i> Confirm & Place Pre-Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
