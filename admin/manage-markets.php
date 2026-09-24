<?php
/**
 * MarketLink - Admin Farmers Market Management (CRUD & Map Geocoding)
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Manage Farmers Markets';
$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);
$errors = [];

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
        redirect(BASE_URL . 'admin/manage-markets.php');
    }

    $form_action = sanitize_input($_POST['form_action'] ?? '');

    // 1. SAVE MARKET (Add / Edit)
    if ($form_action === 'save_market') {
        $mid = (int)($_POST['market_id'] ?? 0);
        $market_name = sanitize_input($_POST['market_name'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $operating_days = sanitize_input($_POST['operating_days'] ?? '');
        $timings = sanitize_input($_POST['timings'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 31.5204;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 74.3587;

        if (empty($market_name)) {
            $errors['market_name'] = 'Market name is required.';
        }
        if (empty($address)) {
            $errors['address'] = 'Market address is required.';
        }

        if (empty($errors)) {
            try {
                if ($mid > 0) {
                    $stmt = $pdo->prepare("UPDATE markets SET market_name = :name, address = :address, operating_days = :days, timings = :timings, latitude = :lat, longitude = :lng WHERE market_id = :mid");
                    $stmt->execute([
                        ':name' => $market_name,
                        ':address' => $address,
                        ':days' => $operating_days,
                        ':timings' => $timings,
                        ':lat' => $latitude,
                        ':lng' => $longitude,
                        ':mid' => $mid
                    ]);
                    set_flash('success', 'Market "' . $market_name . '" updated successfully.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO markets (market_name, address, operating_days, timings, latitude, longitude) VALUES (:name, :address, :days, :timings, :lat, :lng)");
                    $stmt->execute([
                        ':name' => $market_name,
                        ':address' => $address,
                        ':days' => $operating_days,
                        ':timings' => $timings,
                        ':lat' => $latitude,
                        ':lng' => $longitude
                    ]);
                    set_flash('success', 'New market "' . $market_name . '" added to the platform directory.');
                }
                redirect(BASE_URL . 'admin/manage-markets.php');
            } catch (PDOException $e) {
                error_log("Market save error: " . $e->getMessage());
                $errors['general'] = 'Database error while saving market.';
            }
        }
    }

    // 2. DELETE MARKET
    if ($form_action === 'delete_market') {
        $mid = (int)($_POST['market_id'] ?? 0);
        if ($mid > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM markets WHERE market_id = :mid");
                $del->execute([':mid' => $mid]);
                set_flash('success', 'Market removed from system.');
            } catch (PDOException $e) {
                error_log("Delete market error: " . $e->getMessage());
                set_flash('danger', 'Failed to delete market.');
            }
        }
        redirect(BASE_URL . 'admin/manage-markets.php');
    }
}

// Fetch edit market if requested
$edit_market = null;
if ($action === 'edit' && $edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM markets WHERE market_id = :mid LIMIT 1");
        $stmt->execute([':mid' => $edit_id]);
        $edit_market = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch all markets
$markets = [];
try {
    $stmt = $pdo->query("SELECT m.*, 
                        (SELECT COUNT(*) FROM market_farmers WHERE market_id = m.market_id) as stall_count 
                        FROM markets m ORDER BY m.market_name ASC");
    $markets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch markets error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-geo text-primary me-2"></i>Manage Farmers Markets</h1>
            <p class="text-muted small mb-0">Add physical market plazas, operating days, and pin their coordinates on OpenStreetMap</p>
        </div>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add New Market
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Market List
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add / Edit Market Form -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fs-6">
                    <i class="bi <?= $action === 'add' ? 'bi-plus-circle text-primary' : 'bi-pencil-square text-accent' ?> me-2"></i>
                    <?= $action === 'add' ? 'Create New Farmers Market' : 'Edit Market: ' . e($edit_market['market_name']) ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>admin/manage-markets.php" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="save_market">
                    <input type="hidden" name="market_id" value="<?= $edit_market['market_id'] ?? 0 ?>">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="market_name">Market Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['market_name']) ? 'is-invalid' : '' ?>" id="market_name" name="market_name" value="<?= e($edit_market['market_name'] ?? '') ?>" required placeholder="e.g. Central Square Farmers Market">
                                <?php if (isset($errors['market_name'])): ?><div class="invalid-feedback"><?= e($errors['market_name']) ?></div><?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="address">Full Address / Location <span class="text-danger">*</span></label>
                                <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address" name="address" rows="3" required placeholder="e.g. Heritage Park Plaza, Main Blvd, Downtown"><?= e($edit_market['address'] ?? '') ?></textarea>
                                <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="operating_days">Operating Days</label>
                                    <input type="text" class="form-control" id="operating_days" name="operating_days" value="<?= e($edit_market['operating_days'] ?? 'Sat,Sun') ?>" placeholder="e.g. Sat,Sun or Every Sunday">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="timings">Market Timings</label>
                                    <input type="text" class="form-control" id="timings" name="timings" value="<?= e($edit_market['timings'] ?? '07:00 AM - 02:00 PM') ?>" placeholder="e.g. 08:00 AM - 02:00 PM">
                                </div>
                            </div>
                        </div>

                        <!-- OpenStreetMap Location Picker -->
                        <div class="col-lg-6">
                            <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-pin-map text-primary me-1"></i> Market Map Coordinates</span>
                                <small class="text-muted fw-normal">Click or drag pin</small>
                            </label>

                            <div id="adminMarketMap" style="height: 230px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 0.8rem;"></div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small" for="latitude">Latitude</label>
                                    <input type="number" step="0.00000001" class="form-control form-control-sm" id="latitude" name="latitude" value="<?= e($edit_market['latitude'] ?? '31.52040000') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="longitude">Longitude</label>
                                    <input type="number" step="0.00000001" class="form-control form-control-sm" id="longitude" name="longitude" value="<?= e($edit_market['longitude'] ?? '74.35870000') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Save Market
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lat = parseFloat(document.getElementById('latitude').value) || 31.5204;
            const lng = parseFloat(document.getElementById('longitude').value) || 74.3587;

            const map = L.map('adminMarketMap').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            let marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on('dragend', function (e) {
                const pos = marker.getLatLng();
                document.getElementById('latitude').value = pos.lat.toFixed(8);
                document.getElementById('longitude').value = pos.lng.toFixed(8);
            });

            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                document.getElementById('latitude').value = e.latlng.lat.toFixed(8);
                document.getElementById('longitude').value = e.latlng.lng.toFixed(8);
            });
        });
        </script>

    <?php else: ?>

        <!-- Markets Grid & Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fs-6">Registered Markets (<?= count($markets) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($markets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Market Name</th>
                                    <th>Address</th>
                                    <th>Operating Days</th>
                                    <th>Timings</th>
                                    <th>Attending Stalls</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($markets as $m): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= e($m['market_name']) ?></td>
                                        <td class="small text-muted"><?= e($m['address']) ?></td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= e($m['operating_days']) ?></span></td>
                                        <td class="small"><?= e($m['timings']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $m['stall_count'] ?> stall(s)</span></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="<?= BASE_URL ?>customer/browse-markets.php?market_id=<?= $m['market_id'] ?>" target="_blank" class="btn btn-light btn-sm border" title="Preview Market">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>admin/manage-markets.php?action=edit&id=<?= $m['market_id'] ?>" class="btn btn-light btn-sm border" title="Edit Market">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" action="<?= BASE_URL ?>admin/manage-markets.php" class="d-inline" onsubmit="return confirm('Delete market <?= e($m['market_name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="form_action" value="delete_market">
                                                    <input type="hidden" name="market_id" value="<?= $m['market_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Market">
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
                        <i class="bi bi-geo fs-1 text-secondary-subtle d-block mb-2"></i>
                        <h6>No markets registered yet</h6>
                        <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm mt-2">Add First Market</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
