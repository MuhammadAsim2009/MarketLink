<?php
/**
 * MarketLink - Farmer Stall Profile & Location Settings
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$farmer_id = get_logged_in_user_id();
$errors = [];

// Handle Profile Updates via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid form session. Please try again.';
    } else {
        $contact_name = sanitize_input($_POST['name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $stall_name = sanitize_input($_POST['stall_name'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $operating_days = isset($_POST['operating_days']) && is_array($_POST['operating_days']) ? $_POST['operating_days'] : [];
        $pickup_window_start = sanitize_input($_POST['pickup_window_start'] ?? '08:00');
        $pickup_window_end = sanitize_input($_POST['pickup_window_end'] ?? '14:00');
        $order_cutoff_hours = (int)($_POST['order_cutoff_hours'] ?? 2);
        $selected_markets = isset($_POST['markets']) && is_array($_POST['markets']) ? $_POST['markets'] : [];

        if (empty($stall_name)) {
            $errors['stall_name'] = 'Stall / Farm name is required.';
        }
        if (empty($contact_name)) {
            $errors['name'] = 'Contact person name is required.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Update users table
                $u_stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE user_id = :uid");
                $u_stmt->execute([
                    ':name' => $contact_name,
                    ':phone' => $phone,
                    ':uid' => $farmer_id
                ]);
                $_SESSION['user_name'] = $contact_name;

                // 2. Update farmer_profiles table (using INSERT ON DUPLICATE KEY UPDATE)
                $days_str = implode(',', $operating_days);
                $fp_stmt = $pdo->prepare("INSERT INTO farmer_profiles 
                    (farmer_id, stall_name, address, latitude, longitude, operating_days, pickup_window_start, pickup_window_end, order_cutoff_hours) 
                    VALUES (:fid, :stall_name, :address, :lat, :lng, :days, :p_start, :p_end, :cutoff)
                    ON DUPLICATE KEY UPDATE 
                    stall_name = VALUES(stall_name),
                    address = VALUES(address),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    operating_days = VALUES(operating_days),
                    pickup_window_start = VALUES(pickup_window_start),
                    pickup_window_end = VALUES(pickup_window_end),
                    order_cutoff_hours = VALUES(order_cutoff_hours)");
                $fp_stmt->execute([
                    ':fid' => $farmer_id,
                    ':stall_name' => $stall_name,
                    ':address' => $address,
                    ':lat' => $latitude,
                    ':lng' => $longitude,
                    ':days' => $days_str,
                    ':p_start' => $pickup_window_start,
                    ':p_end' => $pickup_window_end,
                    ':cutoff' => $order_cutoff_hours
                ]);

                // 3. Update market associations in market_farmers
                $del_mf = $pdo->prepare("DELETE FROM market_farmers WHERE farmer_id = :fid");
                $del_mf->execute([':fid' => $farmer_id]);

                if (!empty($selected_markets)) {
                    $ins_mf = $pdo->prepare("INSERT INTO market_farmers (market_id, farmer_id) VALUES (:mid, :fid)");
                    foreach ($selected_markets as $mid) {
                        $mid_int = (int)$mid;
                        if ($mid_int > 0) {
                            $ins_mf->execute([':mid' => $mid_int, ':fid' => $farmer_id]);
                        }
                    }
                }

                $pdo->commit();
                set_flash('success', 'Stall profile and market settings saved successfully.');
                redirect(BASE_URL . 'farmer/profile.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Farmer profile save error: " . $e->getMessage());
                $errors['general'] = 'Failed to update stall profile.';
            }
        }
    }
}

// Fetch current profile data
$user_info = null;
$farmer_profile = null;
$all_markets = [];
$attending_markets = [];

try {
    $u_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = :uid LIMIT 1");
    $u_stmt->execute([':uid' => $farmer_id]);
    $user_info = $u_stmt->fetch();

    $p_stmt = $pdo->prepare("SELECT * FROM farmer_profiles WHERE farmer_id = :fid LIMIT 1");
    $p_stmt->execute([':fid' => $farmer_id]);
    $farmer_profile = $p_stmt->fetch();

    $m_stmt = $pdo->query("SELECT market_id, market_name, address, operating_days FROM markets ORDER BY market_name ASC");
    $all_markets = $m_stmt->fetchAll();

    $mf_stmt = $pdo->prepare("SELECT market_id FROM market_farmers WHERE farmer_id = :fid");
    $mf_stmt->execute([':fid' => $farmer_id]);
    $attending_markets = $mf_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Fetch profile data error: " . $e->getMessage());
}

$active_nav = 'profile';
$page_title = 'Stall Profile & Schedule Settings';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Stall Settings & Schedule</h1>
        <p class="text-muted small mb-0">Configure your farm branding, operating days, pickup timings, and interactive map pin</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-eye me-1"></i> Preview Public Stall
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger py-2 small mb-3 border-0 shadow-xs"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form action="<?= BASE_URL ?>farmer/profile.php" method="POST" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
            <!-- Left Column: Stall Details & Timings -->
            <div class="col-lg-6">
                <!-- Branding & Contact Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-person-badge text-primary me-2"></i>Stall & Contact Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label" for="stall_name">Stall / Farm Brand Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['stall_name']) ? 'is-invalid' : '' ?>" id="stall_name" name="stall_name" value="<?= e($farmer_profile['stall_name'] ?? '') ?>" required placeholder="e.g. Green Valley Organic Farm (Stall #12)">
                            <?php if (isset($errors['stall_name'])): ?><div class="invalid-feedback"><?= e($errors['stall_name']) ?></div><?php endif; ?>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($user_info['name'] ?? '') ?>" required>
                                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone / WhatsApp</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($user_info['phone'] ?? '') ?>" placeholder="03001234567">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="address">Stall Location / Farm Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="e.g. Row B, Stall 12, Central Farmers Market"><?= e($farmer_profile['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Operating Days & Pickup Window Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-clock-history text-primary me-2"></i>Operating Schedule & Pickup Windows</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label d-block fw-semibold">Operating Days at Market</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php 
                                $all_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                                $profile_days = !empty($farmer_profile['operating_days']) ? explode(',', $farmer_profile['operating_days']) : ['Sat', 'Sun'];
                                foreach ($all_days as $d): 
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="<?= $d ?>" id="day_<?= $d ?>" <?= in_array($d, $profile_days) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="day_<?= $d ?>"><?= $d ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="pickup_window_start">Pickup Start Time</label>
                                <input type="time" class="form-control" id="pickup_window_start" name="pickup_window_start" value="<?= e($farmer_profile['pickup_window_start'] ?? '08:00') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pickup_window_end">Pickup End Time</label>
                                <input type="time" class="form-control" id="pickup_window_end" name="pickup_window_end" value="<?= e($farmer_profile['pickup_window_end'] ?? '14:00') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="order_cutoff_hours" title="Minimum hours required before pickup slot to pack produce">Cutoff Hours</label>
                                <input type="number" min="1" max="72" class="form-control" id="order_cutoff_hours" name="order_cutoff_hours" value="<?= e($farmer_profile['order_cutoff_hours'] ?? 2) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attending Farmers Markets Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-geo-alt text-primary me-2"></i>Select Markets Where You Sell</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">Check the markets where your stall will be present so shoppers can discover you on that market's directory page:</p>
                        <?php if (!empty($all_markets)): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($all_markets as $m): ?>
                                    <div class="form-check p-2 bg-light rounded border">
                                        <input class="form-check-input ms-1" type="checkbox" name="markets[]" value="<?= $m['market_id'] ?>" id="m_<?= $m['market_id'] ?>" <?= in_array($m['market_id'], $attending_markets) ? 'checked' : '' ?>>
                                        <label class="form-check-label ms-2 small" for="m_<?= $m['market_id'] ?>">
                                            <strong><?= e($m['market_name']) ?></strong> &bull; <span class="text-muted"><?= e($m['address']) ?> (<?= e($m['operating_days']) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">No markets configured yet by administrator.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive OpenStreetMap Location Pin Picker -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-pin-map-fill text-accent me-2"></i>Stall Map Pin (Leaflet & OpenStreetMap)</h5>
                        <span class="badge bg-secondary-subtle text-secondary small">Click or drag pin</span>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">
                            Click anywhere on the map or drag the orange pin to set your exact stall / farm location for customer navigation:
                        </p>

                        <div id="stallMap" style="height: 320px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 1rem;"></div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small" for="latitude">Latitude</label>
                                <input type="number" step="0.00000001" class="form-control form-control-sm" id="latitude" name="latitude" value="<?= e($farmer_profile['latitude'] ?? '31.52040000') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="longitude">Longitude</label>
                                <input type="number" step="0.00000001" class="form-control form-control-sm" id="longitude" name="longitude" value="<?= e($farmer_profile['longitude'] ?? '74.35870000') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-xs border-0 p-3 bg-white rounded-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 py-2 rounded-3 shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Save Stall Configuration
                    </button>
                </div>
            </div>
        </div>
    </form>

<!-- Leaflet Map Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultLat = parseFloat(document.getElementById('latitude').value) || 31.5204;
    const defaultLng = parseFloat(document.getElementById('longitude').value) || 74.3587;

    const map = L.map('stallMap').setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    let marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    marker.on('dragend', function (e) {
        const position = marker.getLatLng();
        document.getElementById('latitude').value = position.lat.toFixed(8);
        document.getElementById('longitude').value = position.lng.toFixed(8);
    });

    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        document.getElementById('latitude').value = e.latlng.lat.toFixed(8);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(8);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
