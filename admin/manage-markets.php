<?php
/**
 * MarketLink - Admin Farmers Market Management (CRUD & Map Geocoding)
 * SaaS Redesign with Sidebar Layout & Interactive Geospatial Overview
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Manage Farmers Markets';
$active_nav = 'markets';
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
                    set_flash('success', 'New market plaza "' . $market_name . '" added to the directory.');
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
$total_stalls_participating = 0;
try {
    $stmt = $pdo->query("SELECT m.*, 
                        (SELECT COUNT(*) FROM market_farmers WHERE market_id = m.market_id) as stall_count 
                        FROM markets m ORDER BY m.market_name ASC");
    $markets = $stmt->fetchAll();
    foreach ($markets as $m) {
        $total_stalls_participating += (int)$m['stall_count'];
    }
} catch (PDOException $e) {
    error_log("Fetch markets error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header & Action Controls -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-primary px-2 py-1"><i class="bi bi-geo-alt-fill me-1"></i> Locations & Plazas</span>
            <span class="badge badge-neutral px-2 py-1"><?= count($markets) ?> Active Markets</span>
        </div>
        <h1 class="h3 fw-bold mb-1">Farmers Markets Management</h1>
        <p class="text-muted small mb-0">Configure physical farmers market venues, operational timings, and geospatial coordinates</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($action === 'list'): ?>
            <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Market
            </a>
            <a href="<?= BASE_URL ?>customer/browse-markets.php" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Public Directory
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
    <div class="card border shadow-xs mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi <?= $action === 'add' ? 'bi-plus-circle text-primary' : 'bi-pencil-square text-accent' ?> me-2"></i>
                <?= $action === 'add' ? 'Create New Farmers Market Venue' : 'Edit Market: ' . e($edit_market['market_name']) ?>
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
                            <label class="form-label fw-semibold" for="market_name">Market Plaza Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['market_name']) ? 'is-invalid' : '' ?>" id="market_name" name="market_name" value="<?= e($edit_market['market_name'] ?? '') ?>" required placeholder="e.g. Model Town Community Farmers Market">
                            <?php if (isset($errors['market_name'])): ?><div class="invalid-feedback"><?= e($errors['market_name']) ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="address">Full Venue Address <span class="text-danger">*</span></label>
                            <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address" name="address" rows="3" required placeholder="e.g. Central Park Grounds, Block C, Model Town, Lahore"><?= e($edit_market['address'] ?? '') ?></textarea>
                            <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="operating_days">Operating Days</label>
                                <input type="text" class="form-control" id="operating_days" name="operating_days" value="<?= e($edit_market['operating_days'] ?? 'Sat,Sun') ?>" placeholder="e.g. Sat,Sun or Every Friday">
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
                            <span><i class="bi bi-pin-map-fill text-primary me-1"></i> Interactive Map Pin Location</span>
                            <small class="text-muted fw-normal">Click or drag pin to position</small>
                        </label>

                        <div id="adminMarketMap" style="height: 240px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 0.8rem; z-index: 1;"></div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted" for="latitude">Latitude</label>
                                <input type="number" step="0.00000001" class="form-control form-control-sm" id="latitude" name="latitude" value="<?= e($edit_market['latitude'] ?? '31.52040000') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted" for="longitude">Longitude</label>
                                <input type="number" step="0.00000001" class="form-control form-control-sm" id="longitude" name="longitude" value="<?= e($edit_market['longitude'] ?? '74.35870000') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i> <?= $action === 'add' ? 'Publish Market' : 'Save Changes' ?>
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

    <!-- Overview Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-sm-6">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-primary bg-primary-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-geo-alt fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Market Venues</div>
                        <div class="fs-4 fw-bold text-dark"><?= count($markets) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-success bg-success-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-shop fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Participating Stalls</div>
                        <div class="fs-4 fw-bold text-dark"><?= $total_stalls_participating ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-12">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-warning bg-warning-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-calendar-week fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Weekend Coverage</div>
                        <div class="fs-4 fw-bold text-dark">Active Weekly</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Platform Overview Map Card -->
    <div class="card border shadow-xs mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-map-fill text-primary me-2"></i>Platform Geographic Network Map
            </h5>
            <span class="badge badge-primary"><?= count($markets) ?> Venues Pinned</span>
        </div>
        <div class="card-body p-0">
            <div id="allMarketsMap" style="height: 280px; width: 100%;"></div>
        </div>
    </div>

    <!-- Markets Directory Table -->
    <div class="card border shadow-xs">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-buildings text-primary me-2"></i>Registered Markets Directory
            </h5>
            <div class="d-flex align-items-center gap-2">
                <input type="text" id="marketSearch" class="form-control form-control-sm" placeholder="Search markets..." style="max-width: 220px;">
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($markets)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="marketsTable">
                        <thead class="table-light small">
                            <tr>
                                <th>Market Name & Details</th>
                                <th>Address & Location</th>
                                <th>Operating Days</th>
                                <th>Timings</th>
                                <th>Stalls</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($markets as $m): ?>
                                <tr class="market-row">
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($m['market_name']) ?></div>
                                        <small class="text-muted">Lat: <?= number_format((float)$m['latitude'], 4) ?>, Lng: <?= number_format((float)$m['longitude'], 4) ?></small>
                                    </td>
                                    <td>
                                        <div class="small text-secondary" style="max-width: 260px;">
                                            <i class="bi bi-pin-map text-primary me-1"></i><?= e($m['address']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= e($m['operating_days']) ?></span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-dark"><i class="bi bi-clock me-1 text-muted"></i><?= e($m['timings']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $m['stall_count'] ?> stall<?= $m['stall_count'] == 1 ? '' : 's' ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= BASE_URL ?>customer/browse-markets.php?market_id=<?= $m['market_id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-2" title="Preview Public Page">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>admin/manage-markets.php?action=edit&id=<?= $m['market_id'] ?>" class="btn btn-outline-primary btn-sm py-1 px-2" title="Edit Market">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-markets.php" class="d-inline" onsubmit="return confirm('Delete market <?= e($m['market_name']) ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="delete_market">
                                                <input type="hidden" name="market_id" value="<?= $m['market_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete Market">
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
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-2); color: var(--text-3);">
                        <i class="bi bi-geo fs-2"></i>
                    </div>
                    <h6 class="fw-bold mb-1">No Farmers Markets Registered</h6>
                    <p class="small text-muted mb-3">Add your first physical farmers market venue to start hosting farmer stalls.</p>
                    <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Add First Market
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Map Leaflet Script for Overview -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const marketsData = <?= json_encode(array_map(function($m) {
            return [
                'name' => $m['market_name'],
                'address' => $m['address'],
                'lat' => (float)$m['latitude'],
                'lng' => (float)$m['longitude'],
                'days' => $m['operating_days'],
                'timings' => $m['timings'],
                'stalls' => (int)$m['stall_count']
            ];
        }, $markets)) ?>;

        if (marketsData.length > 0 && document.getElementById('allMarketsMap')) {
            const first = marketsData[0];
            const map = L.map('allMarketsMap').setView([first.lat, first.lng], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            const bounds = [];
            marketsData.forEach(function(m) {
                if (m.lat && m.lng) {
                    const marker = L.marker([m.lat, m.lng]).addTo(map);
                    marker.bindPopup(`
                        <div style="font-family: inherit; font-size: 13px;">
                            <strong style="color: #2E7D4F; font-size: 14px;">${m.name}</strong><br>
                            <span style="color: #64748b;">${m.address}</span><br>
                            <div style="margin-top: 5px; font-size: 12px;">
                                <strong>Days:</strong> ${m.days} | <strong>Time:</strong> ${m.timings}<br>
                                <strong>Active Stalls:</strong> ${m.stalls}
                            </div>
                        </div>
                    `);
                    bounds.push([m.lat, m.lng]);
                }
            });

            if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [30, 30] });
            }
        }

        // Live Market Search Filter
        const searchInput = document.getElementById('marketSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('#marketsTable .market-row').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
